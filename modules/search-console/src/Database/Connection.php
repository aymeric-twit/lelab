<?php

namespace App\Database;

use PDO;

/**
 * Singleton PDO — fournit une connexion unique à MySQL.
 */
class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../../config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['charset']
            );

            self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
        }

        return self::$instance;
    }

    /** Réinitialise la connexion (utile pour les tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
