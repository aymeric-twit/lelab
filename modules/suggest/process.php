<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// Listes autorisées
$allowed_hl = ["fr", "en", "de", "es", "it", "nl"];
$allowed_gl = ["FR", "US", "CA", "BE", "CH", "DE", "ES", "IT", "NL"];

function normalize($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    return preg_replace('/[^a-z0-9 ]/', '', $str);
}

function getSuggestions($query, $hl, $gl) {
    $url = "https://suggestqueries.google.com/complete/search";
    $params = http_build_query([
        'client' => 'firefox',
        'hl' => $hl,
        'gl' => $gl,
        'q' => $query
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data[1] ?? [];
}

// Récupération et validation des entrées
$query = trim($_POST['query'] ?? '');
$hl = strtolower($_POST['hl'] ?? 'fr');
$gl = strtoupper($_POST['gl'] ?? 'FR');

if (!in_array($hl, $allowed_hl)) {
    $hl = 'fr';
}
if (!in_array($gl, $allowed_gl)) {
    $gl = 'FR';
}

$suggestions = getSuggestions($query, $hl, $gl);

// Track quota after successful API call
\Platform\Module\Quota::track('suggest');

$normalizedQuery = normalize($query);
$present = false;
$match = null;

foreach ($suggestions as $s) {
    if (normalize($s) === $normalizedQuery) {
        $present = true;
        $match = $s;
        break;
    }
}

echo json_encode([
    'query' => $query,
    'present' => $present,
    'suggestions' => $suggestions,
    'match' => $match
]);
?>