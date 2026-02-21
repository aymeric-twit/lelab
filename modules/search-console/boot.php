<?php

// Search-Console module boot: load Composer autoloader and set env
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Set module URL prefix for internal redirects
if (!defined('MODULE_URL_PREFIX')) {
    define('MODULE_URL_PREFIX', '/m/search-console');
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');
