<?php

namespace Platform;

use Dotenv\Dotenv;
use Platform\Http\Request;

class App
{
    private static ?array $config = null;

    public static function boot(): void
    {
        // Load .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        // Load app config
        self::$config = require __DIR__ . '/../config/app.php';

        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Session
        self::startSession();
    }

    public static function config(string $key = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }
        // Support dot notation: 'session.lifetime'
        $parts = explode('.', $key);
        $value = self::$config;
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = ($_ENV['APP_ENV'] ?? 'production') === 'production';

        session_set_cookie_params([
            'lifetime' => (self::$config['session']['lifetime'] ?? 120) * 60,
            'path'     => '/',
            'httponly'  => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Regenerate session ID periodically
        $regenerate = self::$config['session']['regenerate'] ?? 1800;
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > $regenerate) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }
}
