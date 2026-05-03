<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\JwtService;

class AuthController
{
    private JwtService $jwt;

    public function __construct()
    {
        $this->jwt = new JwtService();
    }

    public function register(array $req): void
    {
        $body = $req['body'] ?? [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        if (UserModel::findByEmail($email)) {
            $this->json(['error' => 'email_in_use'], 409);
            return;
        }

        $id   = $this->uuid();
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $user = UserModel::create($id, $email, $hash);
        $token = $this->jwt->sign($user['id'], $user['role']);
        $this->json([
            'token' => $token,
            'user'  => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']],
        ]);
    }

    public function login(array $req): void
    {
        $body     = $req['body'] ?? [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $user = UserModel::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->json(['error' => 'invalid_credentials'], 401);
            return;
        }

        $token = $this->jwt->sign($user['id'], $user['role']);
        $this->json([
            'token' => $token,
            'user'  => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']],
        ]);
    }

    public function me(array $req): void
    {
        $u = $req['sessionUser'];
        $this->json([
            'id'        => $u['id'],
            'email'     => $u['email'],
            'role'      => $u['role'],
            'createdAt' => $u['created_at'],
        ]);
    }

    public function changePassword(array $req): void
    {
        $body = $req['body'] ?? [];
        $currentPassword = $body['currentPassword'] ?? '';
        $newPassword     = $body['newPassword'] ?? '';

        if ($currentPassword === '' || strlen($newPassword) < 8) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $user = UserModel::findById($req['user']['id']);
        if (!$user) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $this->json(['error' => 'invalid_current_password'], 401);
            return;
        }

        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        UserModel::updatePassword($user['id'], $hash);
        $this->json(['ok' => true]);
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
