<?php

namespace Platform;

use Dotenv\Dotenv;
use Platform\Log\Logger;

class App
{
    private static ?array $config = null;

    public static function boot(): void
    {
        // Load .env
        // createMutable pour que le .env prévale sur les variables d'environnement
        // pré-définies par l'hébergeur (ex: Gandi Simple Hosting définit DB_USER=hosting-db)
        $dotenv = Dotenv::createMutable(__DIR__ . '/..');
        $dotenv->load();

        // Load app config
        self::$config = require __DIR__ . '/../config/app.php';

        // Init logger
        Logger::init(__DIR__ . '/../storage/logs');

        // Global exception handler
        self::configurerGestionErreurs();

        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");

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

    public static function estEnDeveloppement(): bool
    {
        return (self::$config['env'] ?? 'production') === 'development';
    }

    private static function configurerGestionErreurs(): void
    {
        set_exception_handler(function (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'fichier' => $e->getFile(),
                'ligne'   => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            if (self::estEnDeveloppement()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                echo '<h1>Erreur interne</h1>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            } else {
                Http\Response::abortAvecPage(500);
            }
        });

        set_error_handler(function (int $severity, string $message, string $fichier, int $ligne): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $fichier, $ligne);
        });
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (self::$config['env'] ?? 'production') === 'production';

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
