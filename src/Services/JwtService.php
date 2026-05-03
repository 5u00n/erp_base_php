<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Config;

class JwtService
{
    private string $secret;
    private string $expiresIn;

    public function __construct()
    {
        $cfg = Config::get();
        $this->secret    = $cfg->jwtSecret;
        $this->expiresIn = $cfg->jwtExpiresIn;
    }

    public function sign(string $sub, string $role): string
    {
        $now = time();
        $exp = $now + $this->parseDuration($this->expiresIn);

        $payload = [
            'sub'  => $sub,
            'role' => $role,
            'iat'  => $now,
            'exp'  => $exp,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /** @return array{sub: string, role: string} */
    public function verify(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $arr = (array) $decoded;
        return ['sub' => $arr['sub'], 'role' => $arr['role']];
    }

    private function parseDuration(string $dur): int
    {
        if (is_numeric($dur)) {
            return (int) $dur;
        }
        $unit  = strtolower(substr($dur, -1));
        $value = (int) substr($dur, 0, -1);
        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            'w' => $value * 604800,
            default => 604800, // 7d fallback
        };
    }
}
