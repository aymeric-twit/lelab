# Patterns PHP backend — Reference

## Endpoint AJAX (process.php)

```php
<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', 0);
error_reporting(0);

// Validation des entrees
$query = trim($_POST['query'] ?? '');
if ($query === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Requete vide']);
    exit;
}

// Traitement
$resultat = traiter($query);

// Reponse
echo json_encode([
    'query' => $query,
    'data' => $resultat
], JSON_UNESCAPED_UNICODE);
```

## Endpoint de progression (progress.php)

```php
<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$jobId = $_GET['job'] ?? '';

// Validation du job ID (securite)
if (!preg_match('#^[a-f0-9]{13,32}$#', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID invalide']);
    exit;
}

$cheminProgression = __DIR__ . '/data/jobs/' . $jobId . '/progress.json';

if (!file_exists($cheminProgression)) {
    http_response_code(404);
    echo json_encode(['error' => 'Job introuvable']);
    exit;
}

echo file_get_contents($cheminProgression);
```

## SSE streaming (bulk_stream.php)

```php
<?php
require_once __DIR__ . '/functions.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Desactiver tous les buffers de sortie
while (ob_get_level()) {
    ob_end_flush();
}

set_time_limit(0);

$domain = $_GET['domain'] ?? '';

// Callback : chaque log → event SSE
$onLog = function (string $message) {
    echo "event: log\ndata: " . json_encode(['message' => $message], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    if (connection_aborted()) {
        exit;
    }
};

$resultat = traiterEnStream($domain, $onLog);

// Event final
echo "event: done\ndata: " . json_encode(['cacheId' => $resultat['id']]) . "\n\n";
flush();
```

## Ecriture atomique de progression (worker)

Le worker CLI ecrit la progression de maniere atomique (write .tmp + rename) pour eviter
les lectures partielles par le polling frontend :

```php
function ecrireProgression(string $chemin, array $donnees): void
{
    $contenu = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $cheminTemp = $chemin . '.tmp';
    file_put_contents($cheminTemp, $contenu, LOCK_EX);
    rename($cheminTemp, $chemin);
}

// Exemple d'utilisation dans un worker
ecrireProgression($cheminProgression, [
    'status' => 'running',
    'percent' => 45,
    'step' => 'Analyse des regles...',
    'elapsed_sec' => time() - $tempsDebut,
]);
```

## Nettoyage des anciens jobs

```php
function nettoyerAnciensJobs(string $dossierJobs, int $ttlSecondes = 86400): void
{
    if (!is_dir($dossierJobs)) return;
    $limite = time() - $ttlSecondes;

    foreach (new DirectoryIterator($dossierJobs) as $item) {
        if ($item->isDot() || !$item->isDir()) continue;
        if ($item->getMTime() >= $limite) continue;

        $chemin = $item->getPathname();
        $fichiers = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($chemin, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($fichiers as $fichier) {
            $fichier->isDir() ? rmdir($fichier->getPathname()) : unlink($fichier->getPathname());
        }
        rmdir($chemin);
    }
}
```

## Structure du repertoire data/jobs/

```
data/jobs/{jobId}/
├── config.json          # Configuration du job (parametres soumis)
├── progress.json        # Progression atomique (lu par progress.php)
├── fichier_upload.csv   # Fichier(s) telecharge(s) par l'utilisateur
└── resultats.json       # Resultats finaux (optionnel, peut etre dans progress)
```

## Quota manuel (api_call)

```php
<?php
use Platform\Module\Quota;

if (!Quota::trackerSiDisponible($slug)) {
    http_response_code(429);
    echo json_encode(['error' => 'Quota depasse']);
    return;
}
```
