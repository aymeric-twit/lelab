#!/usr/bin/env php
<?php

/**
 * Script CLI de synchronisation des données Search Console.
 *
 * Usage :
 *   php bin/sync.php                 # Sync tous les sites, tous les types
 *   php bin/sync.php --site-id=3     # Sync uniquement le site ID 3
 *   php bin/sync.php --no-import     # Ne pas ré-importer la liste des sites
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

// Parse des arguments CLI
$options = getopt('', ['site-id::', 'no-import']);
$siteId     = isset($options['site-id']) ? (int) $options['site-id'] : null;
$importSites = !isset($options['no-import']);

echo "========================================\n";
echo " Search Console — Synchronisation\n";
echo " " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    $controller = new \App\Controller\SyncController();
    $results = $controller->run($importSites, $siteId);

    echo "\n========================================\n";
    echo " Résumé\n";
    echo "========================================\n";

    $hasError = false;
    foreach ($results as $r) {
        $status = $r['status'] === 'success' ? 'OK' : ($r['status'] === 'up_to_date' ? 'A JOUR' : 'ERREUR');
        echo sprintf(
            " [%s] %s (%s) — %s lignes en %ss\n",
            $status,
            $r['site'] ?? '?',
            $r['search_type'] ?? '?',
            $r['rows_fetched'] ?? 0,
            $r['duration'] ?? 0
        );

        if ($r['status'] === 'error') {
            $hasError = true;
            echo "   -> " . ($r['error'] ?? 'Erreur inconnue') . "\n";
        }
    }

    echo "\nTerminé.\n";
    exit($hasError ? 1 : 0);

} catch (\Throwable $e) {
    echo "\nERREUR FATALE : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
