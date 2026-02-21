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
];
