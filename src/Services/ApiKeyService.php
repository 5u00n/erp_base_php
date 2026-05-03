<?php

namespace App\Services;

use App\Config;

/**
 * API key helpers mirroring server/src/lib/apiKey.ts exactly.
 *
 * Key format: erp.<uuid>.<base64url-secret>
 * Hash:       HMAC-SHA256(fullKey, API_KEY_PEPPER) → hex
 */
class ApiKeyService
{
    private string $pepper;

    public function __construct()
    {
        $this->pepper = Config::get()->apiKeyPepper;
    }

    public function randomSecretPart(): string
    {
        $bytes = random_bytes(24);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function format(string $id, string $secret): string
    {
        return "erp.$id.$secret";
    }

    /** @return array{id: string, secretPart: string}|null */
    public function parse(string $fullKey): ?array
    {
        $parts = explode('.', $fullKey, 3);
        if (count($parts) !== 3 || $parts[0] !== 'erp') {
            return null;
        }
        return ['id' => $parts[1], 'secretPart' => $parts[2]];
    }

    public function hash(string $fullKey): string
    {
        return hash_hmac('sha256', $fullKey, $this->pepper);
    }

    public function verify(string $fullKey, string $storedHash): bool
    {
        $computed = $this->hash($fullKey);
        return hash_equals($computed, $storedHash);
    }

    /** @return array{prefix: string, last4: string} */
    public function displayMeta(string $id, string $secret): array
    {
        return [
            'prefix' => "erp.$id.",
            'last4'  => substr($secret, -4),
        ];
    }
}
