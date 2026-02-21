<?php
/**
 * CLI Module Scaffolder
 *
 * Usage:
 *   php cli/make-module.php mon-slug --name="Mon Module" --description="Description"
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Platform\Database\Connection;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// -----------------------------------------------
// Parse arguments
// -----------------------------------------------

$args = $argv;
array_shift($args); // remove script name

$slug = null;
$name = null;
$description = '';

foreach ($args as $arg) {
    if (str_starts_with($arg, '--name=')) {
        $name = substr($arg, 7);
    } elseif (str_starts_with($arg, '--description=')) {
        $description = substr($arg, 14);
    } elseif (!str_starts_with($arg, '-') && $slug === null) {
        $slug = $arg;
    }
}

if (!$slug) {
    echo "Usage: php cli/make-module.php <slug> --name=\"Module Name\" --description=\"Description\"\n";
    exit(1);
}

// -----------------------------------------------
// Validate slug
// -----------------------------------------------

if (!preg_match('/^[a-z][a-z0-9\-]{1,49}$/', $slug)) {
    echo "Error: Le slug doit etre en minuscules, avec des tirets, entre 2 et 50 caracteres.\n";
    echo "  Exemples valides : mon-module, seo-tool, analytics-v2\n";
    exit(1);
}

if ($slug === '_template') {
    echo "Error: Le slug '_template' est reserve.\n";
    exit(1);
}

// Default name from slug if not provided
if (!$name) {
    $name = ucfirst(str_replace('-', ' ', $slug));
}

$basePath = __DIR__ . '/../modules';
$templatePath = $basePath . '/_template';
$targetPath = $basePath . '/' . $slug;

// -----------------------------------------------
// Check template exists
// -----------------------------------------------

if (!is_dir($templatePath)) {
    echo "Error: Le template modules/_template/ est introuvable.\n";
    exit(1);
}

// -----------------------------------------------
// Check target doesn't exist
// -----------------------------------------------

if (is_dir($targetPath)) {
    echo "Error: Le dossier modules/{$slug}/ existe deja.\n";
    exit(1);
}

// -----------------------------------------------
// Copy template and replace placeholders
// -----------------------------------------------

echo "Creation du module '{$slug}'...\n";

mkdir($targetPath, 0755, true);

$files = scandir($templatePath);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }

    $source = $templatePath . '/' . $file;
    $dest = $targetPath . '/' . $file;

    if (is_file($source)) {
        $content = file_get_contents($source);
        $content = str_replace('{{SLUG}}', $slug, $content);
        $content = str_replace('{{NAME}}', $name, $content);
        $content = str_replace('{{DESCRIPTION}}', $description, $content);
        file_put_contents($dest, $content);
        echo "  Cree: modules/{$slug}/{$file}\n";
    }
}

// Remove _schema_doc from module.json (it's documentation only)
$moduleJsonPath = $targetPath . '/module.json';
if (file_exists($moduleJsonPath)) {
    $data = json_decode(file_get_contents($moduleJsonPath), true);
    unset($data['_schema_doc']);
    file_put_contents($moduleJsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
}

// -----------------------------------------------
// Insert into database
// -----------------------------------------------

echo "Insertion en base de donnees...\n";

try {
    $db = Connection::get();

    $stmt = $db->prepare('
        INSERT INTO modules (slug, name, description, version, icon, sort_order, quota_mode, default_quota)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$slug, $name, $description, '1.0.0', 'bi-tools', 100, 'form_submit', 100]);

    $moduleId = $db->lastInsertId();
    echo "  Module insere (id: {$moduleId})\n";

    // Grant access to all admin users
    $admins = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    $stmtAccess = $db->prepare('INSERT IGNORE INTO user_module_access (user_id, module_id, granted, granted_by) VALUES (?, ?, 1, ?)');

    foreach ($admins as $admin) {
        $stmtAccess->execute([$admin['id'], $moduleId, $admin['id']]);
    }

    echo "  Acces accorde aux administrateurs\n";
} catch (\PDOException $e) {
    echo "Error DB: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nModule '{$name}' cree avec succes !\n";
echo "  Dossier : modules/{$slug}/\n";
echo "  Prochaines etapes :\n";
echo "    1. Editez modules/{$slug}/module.json pour ajuster icon, sort_order, routes\n";
echo "    2. Developpez votre module dans modules/{$slug}/index.php\n";
echo "    3. Lancez 'php database/migrate.php' pour synchroniser\n";
