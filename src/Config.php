<?php

namespace App;

/**
 * Typed configuration reader.  All values come from $_ENV (loaded by phpdotenv
 * in public/index.php before any class is instantiated).
 */
final class Config
{
    private static ?self $instance = null;

    public readonly string $databaseUrl;
    public readonly string $jwtSecret;
    public readonly string $jwtExpiresIn;
    public readonly string $apiKeyPepper;
    public readonly string $encryptionKey;
    public readonly int    $port;
    public readonly string $corsOrigin;
    public readonly ?string $stripeSecretKey;
    public readonly ?string $vapidPublicKey;
    public readonly ?string $vapidPrivateKey;
    public readonly string  $vapidSubject;
    public readonly ?string $fcmServerKey;
    public readonly string  $seedAdminEmail;
    public readonly string  $seedAdminPassword;

    private function __construct()
    {
        $this->databaseUrl       = $this->require('DATABASE_URL');
        $this->jwtSecret         = $this->require('JWT_SECRET');
        $this->jwtExpiresIn      = $_ENV['JWT_EXPIRES_IN'] ?? '7d';
        $this->apiKeyPepper      = $this->require('API_KEY_PEPPER');
        $this->encryptionKey     = $this->require('ENCRYPTION_KEY');
        $this->port              = (int) ($_ENV['PORT'] ?? 8000);
        $this->corsOrigin        = $_ENV['CORS_ORIGIN'] ?? 'http://localhost:5173';
        $this->stripeSecretKey   = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        $this->vapidPublicKey    = $_ENV['VAPID_PUBLIC_KEY'] ?? null;
        $this->vapidPrivateKey   = $_ENV['VAPID_PRIVATE_KEY'] ?? null;
        $this->vapidSubject      = $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@example.com';
        $this->fcmServerKey      = $_ENV['FCM_SERVER_KEY'] ?? null;
        $this->seedAdminEmail    = $_ENV['SEED_ADMIN_EMAIL'] ?? 'admin@example.com';
        $this->seedAdminPassword = $_ENV['SEED_ADMIN_PASSWORD'] ?? 'admin123456';
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    private function require(string $key): string
    {
        if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
            throw new \RuntimeException("Missing required environment variable: $key");
        }
        return $_ENV[$key];
    }
}
