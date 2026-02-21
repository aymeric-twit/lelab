<?php

/**
 * Point d'entrée unique de l'application (front controller).
 *
 * Routes :
 *   /                  -> Dashboard
 *   /auth              -> Page d'auth
 *   /auth/login        -> Redirige vers Google
 *   /auth/callback     -> Callback OAuth
 *   /auth/logout       -> Déconnexion
 *   /sync-status       -> Historique des syncs
 *   /api/*             -> Endpoints JSON
 */

require __DIR__ . '/../vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

// Routage simple basé sur le path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- Routes Auth ---
    if ($path === '/auth') {
        (new \App\Controller\AuthController())->index();
    } elseif ($path === '/auth/login') {
        (new \App\Controller\AuthController())->login();
    } elseif ($path === '/auth/callback') {
        (new \App\Controller\AuthController())->callback();
    } elseif ($path === '/auth/logout') {
        (new \App\Controller\AuthController())->logout();

    // --- Routes API ---
    } elseif ($path === '/api/sites') {
        (new \App\Controller\ApiController())->sites();
    } elseif ($path === '/api/daily-trend') {
        (new \App\Controller\ApiController())->dailyTrend();
    } elseif ($path === '/api/top-queries') {
        (new \App\Controller\ApiController())->topQueries();
    } elseif ($path === '/api/top-pages') {
        (new \App\Controller\ApiController())->topPages();
    } elseif ($path === '/api/devices') {
        (new \App\Controller\ApiController())->devices();
    } elseif ($path === '/api/countries') {
        (new \App\Controller\ApiController())->countries();
    } elseif ($path === '/api/totals') {
        (new \App\Controller\ApiController())->totals();
    } elseif ($path === '/api/compare') {
        (new \App\Controller\ApiController())->compare();
    } elseif ($path === '/api/sync-logs') {
        (new \App\Controller\ApiController())->syncLogs();
    } elseif ($path === '/api/sync' && $method === 'POST') {
        (new \App\Controller\ApiController())->triggerSync();

    // --- Routes Dashboard ---
    } elseif ($path === '/sync-status') {
        (new \App\Controller\DashboardController())->syncStatus();
    } elseif ($path === '/' || $path === '/dashboard') {
        (new \App\Controller\DashboardController())->index();

    } else {
        http_response_code(404);
        echo '404 - Page non trouvée';
    }
} catch (\Throwable $e) {
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        http_response_code(500);
        echo '<h1>Erreur</h1><pre>' . htmlspecialchars($e->getMessage()) . "\n\n" . $e->getTraceAsString() . '</pre>';
    } else {
        http_response_code(500);
        echo 'Erreur interne du serveur.';
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    }
}
