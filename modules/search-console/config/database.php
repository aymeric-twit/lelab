<?php

/**
 * Configuration de la base de données.
 * Les valeurs sont lues depuis les variables d'environnement (.env).
 */
return [
    'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
    'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_NAME']     ?? 'search_console',
    'username' => $_ENV['DB_USER']     ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
