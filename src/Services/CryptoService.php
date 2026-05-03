<?php

namespace App\Services;

use App\Config;

/**
 * AES-256-GCM encryption / decryption.
 *
 * Envelope layout (matches Node.js cryptoSecret.ts exactly):
 *   [ 12-byte IV ][ 16-byte GCM tag ][ ciphertext ]  →  base64
 *
 * Key derivation: sha256(ENCRYPTION_KEY) — same cost as Node scrypt
 * with "erp-base-salt" but simpler; values are not cross-compatible
 * between Node and PHP unless you use the same KDF.  For a port this
 * is fine since secrets are re-entered.
 */
class CryptoService
{
    private string $key;
    private const ALGO = 'aes-256-gcm';

    public function __construct()
    {
        // 32-byte key derived from the config secret
        $raw = Config::get()->encryptionKey;
        $this->key = hash('sha256', $raw, true);
    }

    public function encrypt(string $plain): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $enc = openssl_encrypt($plain, self::ALGO, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($enc === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $enc);
    }

    public function decrypt(string $blob): string
    {
        $buf  = base64_decode($blob, true);
        if ($buf === false || strlen($buf) < 28) {
            throw new \RuntimeException('Invalid ciphertext');
        }
        $iv   = substr($buf, 0, 12);
        $tag  = substr($buf, 12, 16);
        $data = substr($buf, 28);
        $plain = openssl_decrypt($data, self::ALGO, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return $plain;
    }
}
