<?php
/**
 * Endpoint SSE pour le traitement bulk en temps réel.
 * Appelé par EventSource côté client avec ?domain=...&device=...
 */
require_once __DIR__ . '/functions.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Désactiver tous les buffers de sortie
while (ob_get_level()) {
    ob_end_flush();
}

$domain     = $_GET['domain'] ?? '';
$formFactor = $_GET['device'] ?? 'ALL';
$sitemapUrl = trim($_GET['sitemap_url'] ?? '');
$source     = $_GET['source'] ?? 'sitemap';
set_time_limit(0);

// Nettoyage des anciens fichiers cache (> 30 min)
cleanup_bulk_cache();

$cacheId = generate_cache_id();

// Callback : chaque log → event SSE
$onLog = function (string $message) {
    echo "event: log\ndata: " . json_encode(['message' => $message], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    if (connection_aborted()) {
        exit;
    }
};

$result = process_bulk($domain, $formFactor, $onLog, $sitemapUrl, $source);
save_bulk_cache($cacheId, $result);

echo "event: done\ndata: " . json_encode(['cacheId' => $cacheId]) . "\n\n";
flush();
