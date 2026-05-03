<?php

namespace App\Middleware;

use App\Models\ApiKeyModel;
use App\Models\ProjectModel;
use App\Models\UserModel;
use App\Services\ApiKeyService;
use App\Services\JwtService;

/**
 * Dual-auth middleware: accepts either X-Api-Key (API key) or Bearer JWT (project owner).
 * Sets $req['projectApi'] for API key auth, $req['user'] for JWT auth.
 *
 * Mirrors server/src/plugins/auth.ts → authenticateProjectAccess()
 */
class AuthenticateProjectAccess
{
    private JwtService    $jwt;
    private ApiKeyService $apiKey;

    public function __construct()
    {
        $this->jwt    = new JwtService();
        $this->apiKey = new ApiKeyService();
    }

    public function __invoke(array &$req): bool
    {
        $projectId   = $req['params']['projectId'] ?? '';
        $apiKeyHeader = $req['headers']['x-api-key'] ?? '';

        if ($apiKeyHeader !== '') {
            return $this->handleApiKey($req, $apiKeyHeader, $projectId);
        }
        return $this->handleJwt($req, $projectId);
    }

    private function handleApiKey(array &$req, string $rawKey, string $projectId): bool
    {
        $parsed = $this->apiKey->parse($rawKey);
        if (!$parsed) {
            $this->abort(401, 'invalid_api_key');
            return false;
        }
        $row = ApiKeyModel::findById($parsed['id']);
        if (!$row || !$this->apiKey->verify($rawKey, $row['key_hash'])) {
            $this->abort(401, 'invalid_api_key');
            return false;
        }
        if ($row['project_id'] !== $projectId) {
            $this->abort(403, 'forbidden');
            return false;
        }
        $scopes = json_decode($row['scopes'], true) ?? [];
        $req['projectApi'] = [
            'projectId' => $row['project_id'],
            'apiKeyId'  => $row['id'],
            'scopes'    => $scopes,
        ];
        return true;
    }

    private function handleJwt(array &$req, string $projectId): bool
    {
        $header = $req['headers']['authorization'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            $this->abort(401, 'unauthorized');
            return false;
        }
        $token = substr($header, 7);
        try {
            $payload = $this->jwt->verify($token);
            $user    = UserModel::findById($payload['sub']);
            if (!$user || $user['role'] !== $payload['role']) {
                $this->abort(401, 'unauthorized');
                return false;
            }
            $project = ProjectModel::findByOwnerAndId($user['id'], $projectId);
            if (!$project) {
                $this->abort(404, 'not_found');
                return false;
            }
            $req['user']        = ['id' => $user['id'], 'role' => $user['role']];
            $req['sessionUser'] = $user;
            return true;
        } catch (\Throwable) {
            $this->abort(401, 'unauthorized');
            return false;
        }
    }

    private function abort(int $code, string $error): void
    {
        http_response_code($code);
        echo json_encode(['error' => $error]);
        exit;
    }
}
