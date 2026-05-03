<?php

namespace App\Controllers;

use App\Models\ApiKeyModel;
use App\Models\ProjectModel;
use App\Services\ApiKeyService;

class ApiKeyController
{
    private ApiKeyService $svc;

    public function __construct()
    {
        $this->svc = new ApiKeyService();
    }

    public function list(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $p = ProjectModel::findByOwnerAndId($req['user']['id'], $projectId);
        if (!$p) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        $keys = ApiKeyModel::listByProject($projectId);
        $this->json(['keys' => array_map([$this, 'publicRow'], $keys)]);
    }

    public function create(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $p = ProjectModel::findByOwnerAndId($req['user']['id'], $projectId);
        if (!$p) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }

        $body = $req['body'] ?? [];
        $name = trim($body['name'] ?? '');
        if ($name === '') {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }
        $scopes     = is_array($body['scopes'] ?? null) ? $body['scopes'] : [];
        $id         = $this->uuid();
        $secret     = $this->svc->randomSecretPart();
        $fullKey    = $this->svc->format($id, $secret);
        $meta       = $this->svc->displayMeta($id, $secret);
        $scopesJson = json_encode($scopes);

        $created = ApiKeyModel::create(
            $id, $projectId, $name,
            $this->svc->hash($fullKey),
            $meta['prefix'], $meta['last4'],
            $scopesJson
        );

        $this->json(['key' => $this->publicRow($created), 'fullKey' => $fullKey], 201);
    }

    public function revoke(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $keyId     = $req['params']['keyId'];
        $p = ProjectModel::findByOwnerAndId($req['user']['id'], $projectId);
        if (!$p) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        $count = ApiKeyModel::revoke($keyId, $projectId);
        if ($count === 0) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        $this->json(['ok' => true]);
    }

    private function publicRow(array $k): array
    {
        return [
            'id'        => $k['id'],
            'name'      => $k['name'],
            'prefix'    => $k['key_prefix'],
            'last4'     => $k['key_last4'],
            'revokedAt' => $k['revoked_at'],
            'createdAt' => $k['created_at'],
            'scopes'    => json_decode($k['scopes'], true) ?? [],
        ];
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
