<?php

// KG-Entities module boot: load Composer autoloader and set API key
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Pre-set the env variable so load_config() in functions.php finds it
// even if there is no local .env file in the module directory
if (!empty($_ENV['GOOGLE_KG_API_KEY'])) {
    putenv("GOOGLE_KG_API_KEY={$_ENV['GOOGLE_KG_API_KEY']}");
}
