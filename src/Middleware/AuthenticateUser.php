<?php

namespace App\Middleware;

use App\Models\UserModel;
use App\Services\JwtService;

/**
 * Authenticates a Bearer JWT from the Authorization header.
 * On success, sets $req['user'] = ['id'=>..., 'role'=>...].
 * On failure, sends 401 and returns false.
 */
class AuthenticateUser
{
    private JwtService $jwt;

    public function __construct()
    {
        $this->jwt = new JwtService();
    }

    /** @param array $req Mutable request context */
    public function __invoke(array &$req): bool
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
            $req['user'] = ['id' => $user['id'], 'role' => $user['role']];
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
