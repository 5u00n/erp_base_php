<?php

namespace App\Controllers;

use App\Models\PushConfigModel;
use App\Models\PushSubscriptionModel;
use App\Services\CryptoService;
use App\Services\PushService;
use App\Config;

class PushController
{
    private CryptoService $crypto;
    private PushService   $push;

    public function __construct()
    {
        $this->crypto = new CryptoService();
        $this->push   = new PushService();
    }

    public function getConfig(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $cfg       = PushConfigModel::findByProject($projectId);
        $subCount  = PushSubscriptionModel::countByProject($projectId);
        $cfg       = $cfg ?: [];

        $this->json([
            'config' => [
                'provider'      => $cfg['provider'] ?? 'webpush',
                'hasSecret'     => !empty($cfg['encrypted_blob']),
                'updatedAt'     => $cfg['updated_at'] ?? null,
                'subscriptions' => $subCount,
            ],
            'vapidPublicKey' => Config::get()->vapidPublicKey,
        ]);
    }

    public function putConfig(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $provider  = $body['provider'] ?? 'fcm';
        $secret    = $body['serverSecret'] ?? '';

        if ($secret === '') {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $encrypted = $this->crypto->encrypt($secret);
        $cfg = PushConfigModel::upsert($this->uuid(), $projectId, $provider, $encrypted);

        $this->json([
            'config' => [
                'provider'  => $cfg['provider'],
                'hasSecret' => true,
                'updatedAt' => $cfg['updated_at'],
            ],
        ]);
    }

    public function listSubscriptions(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $subs      = PushSubscriptionModel::listByProject($projectId);
        $this->json(['subscriptions' => $subs]);
    }

    public function subscribe(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $endpoint  = $body['endpoint'] ?? '';
        $keys      = $body['keys'] ?? [];
        $p256dh    = $keys['p256dh'] ?? '';
        $auth      = $keys['auth'] ?? '';
        $userId    = $req['user']['id'] ?? null;

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        try {
            $sub = PushSubscriptionModel::upsert(
                $this->uuid(), $projectId, $endpoint, $p256dh, $auth, $userId
            );
            $this->json(['subscription' => ['id' => $sub['id'], 'endpoint' => $sub['endpoint']]], 201);
        } catch (\Throwable) {
            $this->json(['error' => 'subscription_exists'], 409);
        }
    }

    public function unsubscribe(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $endpoint  = $body['endpoint'] ?? '';

        if ($endpoint === '') {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        PushSubscriptionModel::deleteByEndpoint($projectId, $endpoint);
        http_response_code(204);
    }

    public function test(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $title     = $body['title'] ?? 'Test Notification';
        $message   = $body['message'] ?? 'Hello from ERP Base!';
        $payload   = ['title' => $title, 'body' => $message];

        $subs = PushSubscriptionModel::listFull($projectId);

        if (!empty($subs)) {
            $sent = 0;
            foreach ($subs as $s) {
                if ($this->push->sendWebPush($s['endpoint'], $s['p256dh'], $s['auth'], $payload)) {
                    $sent++;
                }
            }
            $this->json(['ok' => true, 'mode' => 'web_push', 'sent' => $sent, 'total' => count($subs)]);
            return;
        }

        $deviceToken = $body['deviceToken'] ?? '';
        $fcmKey = Config::get()->fcmServerKey;

        if ($deviceToken && $fcmKey) {
            $result = $this->push->sendFcm($deviceToken, $payload);
            $this->json($result);
            return;
        }

        $this->json([
            'ok'      => false,
            'mode'    => 'stub',
            'message' => 'No web-push subscriptions found. Set up VAPID keys and subscribe a browser, or set FCM_SERVER_KEY + deviceToken.',
        ]);
    }

    private function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}
