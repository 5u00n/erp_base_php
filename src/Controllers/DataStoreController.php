<?php

namespace App\Controllers;

use App\Models\DataStoreConfigModel;
use App\Services\CryptoService;

class DataStoreController
{
    private CryptoService $crypto;

    public function __construct()
    {
        $this->crypto = new CryptoService();
    }

    public function getConfig(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $cfg       = DataStoreConfigModel::findByProject($projectId);

        if (!$cfg) {
            $this->json(['storeType' => 'sql_json', 'connected' => true, 'maskedConfig' => ['type' => 'sql_json']]);
            return;
        }

        $maskedConfig = ['type' => $cfg['store_type']];
        $connected    = $cfg['store_type'] === 'sql_json';

        if ($cfg['encrypted_config'] !== '') {
            try {
                $raw    = $this->crypto->decrypt($cfg['encrypted_config']);
                $parsed = json_decode($raw, true) ?? [];
                $full   = array_merge(['type' => $cfg['store_type']], $parsed);
                $maskedConfig = $this->maskConfig($full);
                $connected    = true;
            } catch (\Throwable) {
                $connected = false;
            }
        }

        $this->json(['storeType' => $cfg['store_type'], 'connected' => $connected, 'maskedConfig' => $maskedConfig]);
    }

    public function putConfig(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $type      = $body['type'] ?? '';

        $validTypes = ['sql_json', 'file', 'lowdb', 'mongo', 'postgres', 'mysql', 'sqlite_file'];
        if (!in_array($type, $validTypes, true)) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $rest = $body;
        unset($rest['type']);
        $encryptedConfig = !empty($rest) ? $this->crypto->encrypt(json_encode($rest)) : '';

        DataStoreConfigModel::upsert($this->uuid(), $projectId, $type, $encryptedConfig);
        $this->json(['ok' => true, 'storeType' => $type]);
    }

    public function testConnection(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];

        // If a valid body config is supplied, test it directly
        $type = $body['type'] ?? null;
        if ($type === 'sql_json') {
            $this->json(['ok' => true, 'type' => 'sql_json']);
            return;
        }

        $cfg = DataStoreConfigModel::findByProject($projectId);
        if (!$cfg) {
            $this->json(['ok' => true, 'type' => 'sql_json']);
            return;
        }

        if ($cfg['store_type'] === 'sql_json') {
            $this->json(['ok' => true, 'type' => 'sql_json']);
            return;
        }

        $this->json(['ok' => false, 'error' => 'Connection test not supported for this store type in PHP port']);
    }

    private function maskConfig(array $cfg): array
    {
        $type = $cfg['type'];
        return match ($type) {
            'sql_json'    => ['type' => $type],
            'file', 'lowdb' => ['type' => $type, 'path' => $cfg['path'] ?? null],
            'mongo'       => ['type' => $type, 'hasUri' => true],
            'postgres', 'mysql' => [
                'type'     => $type,
                'host'     => $cfg['host'] ?? null,
                'port'     => $cfg['port'] ?? null,
                'database' => $cfg['database'] ?? null,
                'user'     => $cfg['user'] ?? null,
            ],
            'sqlite_file' => ['type' => $type, 'path' => $cfg['path'] ?? null],
            default       => ['type' => $type],
        };
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
