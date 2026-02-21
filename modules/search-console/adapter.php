<?php

/**
 * Adapter for the Search Console module.
 * Translates platform routes (/m/search-console/...) to the module's internal routing.
 */

// The boot.php is loaded by ModuleRenderer before this file
// Determine the sub-path within the module
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '/m/search-console';
$path = parse_url($requestUri, PHP_URL_PATH);

// Strip the platform prefix to get the module-internal path
$internalPath = substr($path, strlen($prefix)) ?: '/';
if ($internalPath === '') {
    $internalPath = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- Routes Auth ---
    if ($internalPath === '/auth') {
        (new \App\Controller\AuthController())->index();
    } elseif ($internalPath === '/auth/login') {
        (new \App\Controller\AuthController())->login();
    } elseif ($internalPath === '/auth/callback') {
        (new \App\Controller\AuthController())->callback();
    } elseif ($internalPath === '/auth/logout') {
        (new \App\Controller\AuthController())->logout();

    // --- Routes API ---
    } elseif ($internalPath === '/api/sites') {
        (new \App\Controller\ApiController())->sites();
    } elseif ($internalPath === '/api/daily-trend') {
        (new \App\Controller\ApiController())->dailyTrend();
    } elseif ($internalPath === '/api/top-queries') {
        (new \App\Controller\ApiController())->topQueries();
    } elseif ($internalPath === '/api/top-pages') {
        (new \App\Controller\ApiController())->topPages();
    } elseif ($internalPath === '/api/devices') {
        (new \App\Controller\ApiController())->devices();
    } elseif ($internalPath === '/api/countries') {
        (new \App\Controller\ApiController())->countries();
    } elseif ($internalPath === '/api/totals') {
        (new \App\Controller\ApiController())->totals();
    } elseif ($internalPath === '/api/compare') {
        (new \App\Controller\ApiController())->compare();
    } elseif ($internalPath === '/api/sync-logs') {
        (new \App\Controller\ApiController())->syncLogs();
    } elseif ($internalPath === '/api/sync' && $method === 'POST') {
        (new \App\Controller\ApiController())->triggerSync();

    // --- Routes Dashboard ---
    } elseif ($internalPath === '/sync-status') {
        (new \App\Controller\DashboardController())->syncStatus();
    } elseif ($internalPath === '/' || $internalPath === '/dashboard') {
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
