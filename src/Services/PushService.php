<?php

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use App\Config;

/**
 * Push notification service.
 * Supports VAPID web-push (minishlink/web-push) and FCM legacy HTTP fallback.
 */
class PushService
{
    private Config $cfg;

    public function __construct()
    {
        $this->cfg = Config::get();
    }

    /**
     * Send a web-push notification to a single subscription.
     */
    public function sendWebPush(
        string $endpoint,
        string $p256dh,
        string $auth,
        array $payload
    ): bool {
        if (!$this->cfg->vapidPublicKey || !$this->cfg->vapidPrivateKey) {
            return false;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $this->cfg->vapidSubject,
                'publicKey'  => $this->cfg->vapidPublicKey,
                'privateKey' => $this->cfg->vapidPrivateKey,
            ],
        ]);

        $subscription = Subscription::create([
            'endpoint' => $endpoint,
            'keys'     => ['p256dh' => $p256dh, 'auth' => $auth],
        ]);

        $report = $webPush->sendOneNotification($subscription, json_encode($payload));
        return $report->isSuccess();
    }

    /**
     * Send an FCM push notification via legacy HTTP API.
     */
    public function sendFcm(string $deviceToken, array $payload): array
    {
        $serverKey = $this->cfg->fcmServerKey;
        if (!$serverKey) {
            return ['ok' => false, 'error' => 'FCM_SERVER_KEY not configured'];
        }

        $body = json_encode([
            'to'           => $deviceToken,
            'notification' => $payload,
        ]);

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: key=' . $serverKey,
            ],
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $parsed = json_decode($response, true);
        $success = $status === 200 && ($parsed['success'] ?? 0) > 0;
        return ['ok' => $success, 'mode' => 'fcm', 'response' => $parsed];
    }
}
