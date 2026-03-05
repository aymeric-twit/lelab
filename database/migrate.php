<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Platform\Database\Connection;
use Platform\Database\Migrator;
use Platform\Module\ModuleRegistry;

$dotenv = Dotenv::createMutable(__DIR__ . '/..');
$dotenv->load();

$db = Connection::get();
$migrator = new Migrator($db, __DIR__ . '/migrations');

echo "Running migrations...\n";
$ran = $migrator->run();

if (empty($ran)) {
    echo "Nothing to migrate.\n";
} else {
    foreach ($ran as $migration) {
        echo "  Migrated: {$migration}\n";
    }
}

echo "Seeding...\n";
$migrator->seed(__DIR__ . '/seed.sql');

echo "Syncing modules to database...\n";
ModuleRegistry::discover(__DIR__ . '/../modules');
ModuleRegistry::syncToDatabase($db);
echo "Done.\n";
