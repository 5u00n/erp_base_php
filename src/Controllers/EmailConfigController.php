<?php

namespace App\Controllers;

use App\Models\EmailConfigModel;
use App\Services\CryptoService;
use App\Services\EmailService;

class EmailConfigController
{
    private CryptoService $crypto;
    private EmailService  $email;

    public function __construct()
    {
        $this->crypto = new CryptoService();
        $this->email  = new EmailService();
    }

    public function getConfig(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $rec       = EmailConfigModel::findByProject($projectId);

        if (!$rec) {
            $this->json([
                'host'           => '',
                'port'           => 587,
                'secure'         => false,
                'fromAddress'    => '',
                'fromName'       => '',
                'hasCredentials' => false,
            ]);
            return;
        }

        $this->json([
            'host'           => $rec['host'],
            'port'           => (int) $rec['port'],
            'secure'         => (bool) $rec['secure'],
            'fromAddress'    => $rec['from_address'],
            'fromName'       => $rec['from_name'],
            'hasCredentials' => $rec['encrypted_credentials'] !== '',
        ]);
    }

    public function putConfig(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];

        $host        = trim($body['host'] ?? '');
        $port        = (int) ($body['port'] ?? 587);
        $secure      = (bool) ($body['secure'] ?? false);
        $fromAddress = trim($body['fromAddress'] ?? '');
        $fromName    = trim($body['fromName'] ?? '');

        if ($host === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $user     = $body['user'] ?? '';
        $password = $body['password'] ?? '';
        $encryptedCredentials = ($user !== '' || $password !== '')
            ? $this->crypto->encrypt(json_encode(['user' => $user, 'password' => $password]))
            : '';

        EmailConfigModel::upsert(
            $this->uuid(), $projectId, $host, $port, $secure, $fromAddress, $fromName, $encryptedCredentials
        );
        $this->json(['ok' => true]);
    }

    public function test(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $cfg       = $this->resolveSmtp($projectId);

        if (!$cfg) {
            $this->json(['ok' => false, 'error' => 'Email config not set'], 400);
            return;
        }

        if (!empty($body['to']) && filter_var($body['to'], FILTER_VALIDATE_EMAIL)) {
            $result = $this->email->send($cfg, [
                'to'      => $body['to'],
                'subject' => 'Test email from ERP Base',
                'html'    => '<p>This is a test email sent from your ERP Base project.</p>',
                'text'    => 'This is a test email sent from your ERP Base project.',
            ]);
        } else {
            $result = $this->email->verifyTransport($cfg);
        }

        $this->json($result);
    }

    public function send(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $to        = $body['to'] ?? '';
        $subject   = $body['subject'] ?? '';
        $html      = $body['html'] ?? '';

        if (empty($to) || $subject === '' || $html === '') {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $cfg = $this->resolveSmtp($projectId);
        if (!$cfg) {
            $this->json(['ok' => false, 'error' => 'Email config not set for this project'], 400);
            return;
        }

        $result = $this->email->send($cfg, [
            'to'      => $to,
            'subject' => $subject,
            'html'    => $html,
            'text'    => $body['text'] ?? null,
        ]);
        $this->json($result);
    }

    private function resolveSmtp(string $projectId): ?array
    {
        $rec = EmailConfigModel::findByProject($projectId);
        if (!$rec || $rec['host'] === '') return null;

        $credentials = [];
        if ($rec['encrypted_credentials'] !== '') {
            try {
                $credentials = json_decode($this->crypto->decrypt($rec['encrypted_credentials']), true) ?? [];
            } catch (\Throwable) {}
        }

        return array_merge([
            'host'        => $rec['host'],
            'port'        => (int) $rec['port'],
            'secure'      => (bool) $rec['secure'],
            'fromAddress' => $rec['from_address'],
            'fromName'    => $rec['from_name'],
        ], $credentials);
    }

    private function json(array $data, int $status = 200): void
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
