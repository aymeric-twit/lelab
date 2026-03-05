<?php

return [
    'name'    => $_ENV['APP_NAME'] ?? 'SEO Platform',
    'url'     => $_ENV['APP_URL'] ?? 'http://localhost:8080',
    'env'     => $_ENV['APP_ENV'] ?? 'production',
    'session' => [
        'lifetime'    => 120,       // minutes
        'regenerate'  => 1800,      // seconds (30 min)
    ],
    'rate_limit' => [
        'login_max'     => 5,
        'login_window'  => 900,     // 15 minutes
    ],
    'trusted_proxies' => array_filter(
        array_map('trim', explode(',', $_ENV['TRUSTED_PROXIES'] ?? ''))
    ),

    // Inscription publique
    'inscription' => [
        'active'        => (bool) ($_ENV['INSCRIPTION_ACTIVE'] ?? false),
        'rate_limit'    => 5,       // max inscriptions par IP par heure
        'rate_window'   => 3600,    // secondes
    ],

    // Remember Me
    'remember' => [
        'lifetime' => 30 * 24 * 60 * 60,  // 30 jours en secondes
        'cookie'   => 'remember_me',
    ],

    // Email (Symfony Mailer)
    'email' => [
        'host'       => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port'       => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'username'   => $_ENV['MAIL_USERNAME'] ?? '',
        'password'   => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from'       => $_ENV['MAIL_FROM'] ?? 'noreply@example.com',
        'from_name'  => $_ENV['MAIL_FROM_NAME'] ?? 'Le lab',
    ],
];
