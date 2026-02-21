<?php
/**
 * Endpoint AJAX : détecte l'URL du sitemap via robots.txt d'un domaine.
 */
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$domain = trim($_GET['domain'] ?? '');
$sitemapUrl = $domain !== '' ? fetch_sitemap_from_robots($domain) : null;

echo json_encode(['sitemap_url' => $sitemapUrl]);
