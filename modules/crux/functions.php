<?php
// =======================
// CONFIG
// =======================

// Charge .env si présent
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env') as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) {
            [$k, $v] = explode('=', trim($line), 2);
            putenv("$k=$v");
        }
    }
}

// Clé API depuis la variable d'environnement
define('CRUX_API_KEY', getenv('CRUX_API_KEY') ?: '');
define('CRUX_HISTORY_ENDPOINT', 'https://chromeuxreport.googleapis.com/v1/records:queryHistoryRecord');

const CRUX_METRICS = [
    'ttfb' => 'experimental_time_to_first_byte',
    'lcp'  => 'largest_contentful_paint',
    'cls'  => 'cumulative_layout_shift',
    'inp'  => 'interaction_to_next_paint',
];

const CWV_THRESHOLDS = [
    'ttfb' => ['good' => 800,  'poor' => 1800],
    'lcp'  => ['good' => 2500, 'poor' => 4000],
    'cls'  => ['good' => 0.1,  'poor' => 0.25],
    'inp'  => ['good' => 200,  'poor' => 500],
];

// =======================
// HELPER cURL
// =======================

/**
 * Crée un handle cURL avec les options communes (SSL, timeout, etc.).
 * $opts permet de surcharger/ajouter des options.
 */
function create_curl_handle(string $url, array $opts = []) {
    $ch = curl_init($url);
    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    foreach ($defaults as $k => $v) {
        curl_setopt($ch, $k, $v);
    }
    foreach ($opts as $k => $v) {
        curl_setopt($ch, $k, $v);
    }
    return $ch;
}

/**
 * Appelle une API CrUX (POST JSON), gère les erreurs cURL / HTTP / record absent.
 */
function _call_crux_api(string $endpoint, array $payload, int $timeout = 10): array {
    $apiKey = CRUX_API_KEY;
    if (empty($apiKey)) {
        return ['status' => 'error', 'httpCode' => 0, 'error' => 'Clé API manquante (CRUX_API_KEY).', 'rawResponse' => null];
    }

    $url = $endpoint . '?key=' . urlencode($apiKey);
    $ch = create_curl_handle($url, [
        CURLOPT_POST       => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => $timeout,
    ]);

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'httpCode' => 0, 'error' => 'Erreur cURL : ' . $error, 'rawResponse' => null];
    }
    curl_close($ch);

    $data = json_decode($body, true);

    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? "Erreur HTTP $httpCode";
        return ['status' => 'error', 'httpCode' => $httpCode, 'error' => $msg, 'rawResponse' => $data];
    }

    if (!isset($data['record'])) {
        return ['status' => 'no_data', 'httpCode' => $httpCode, 'error' => 'Aucun record CrUX', 'rawResponse' => $data];
    }

    return ['status' => 'ok', 'httpCode' => $httpCode, 'record' => $data['record'], 'rawResponse' => $data];
}

// =======================
// HELPER DATES
// =======================

/**
 * Extrait les dates de fin de période depuis les collectionPeriods CrUX.
 * Retourne un tableau de strings 'YYYY-MM-DD'.
 */
function extract_dates_from_periods(array $periods): array {
    $dates = [];
    foreach ($periods as $p) {
        $last = $p['lastDate'];
        $dates[] = sprintf('%04d-%02d-%02d', $last['year'], $last['month'], $last['day']);
    }
    return $dates;
}

// =======================
// FONCTIONS API / MÉTRIQUES
// =======================

/**
 * Appelle l'API CrUX (période courante) pour obtenir les bins détaillés de l'histogramme.
 */
function call_crux_record($input, $mode, $formFactor) {
    $payload = ['metrics' => array_values(CRUX_METRICS)];
    if ($mode === 'origin') { $payload['origin'] = $input; }
    else                    { $payload['url']    = $input; }
    if ($formFactor === 'MOBILE')  { $payload['formFactor'] = 'PHONE';   }
    elseif ($formFactor === 'DESKTOP') { $payload['formFactor'] = 'DESKTOP'; }

    return _call_crux_api('https://chromeuxreport.googleapis.com/v1/records:queryRecord', $payload);
}

/**
 * Extrait les bins granulaires de l'histogramme depuis un record CrUX (période courante).
 * Retourne [{start, end|null, density}]
 */
function extract_histogram_bins($record, $metricKey) {
    if (!isset($record['metrics'][$metricKey]['histogram'])) return [];
    return array_map(function ($b) {
        return [
            'start'   => floatval($b['start']),
            'end'     => isset($b['end']) ? floatval($b['end']) : null,
            'density' => floatval($b['density'] ?? 0),
        ];
    }, $record['metrics'][$metricKey]['histogram']);
}

function call_crux_history($input, $mode, $formFactor) {
    $payload = [
        'collectionPeriodCount' => 40,
        'metrics'               => array_merge(array_values(CRUX_METRICS), ['navigation_types']),
    ];

    if ($mode === 'origin') { $payload['origin'] = $input; }
    else                    { $payload['url']    = $input; }
    if ($formFactor === 'MOBILE')  { $payload['formFactor'] = 'PHONE';   }
    elseif ($formFactor === 'DESKTOP') { $payload['formFactor'] = 'DESKTOP'; }

    return _call_crux_api(CRUX_HISTORY_ENDPOINT, $payload, 15);
}

/**
 * Extrait la timeseries des 3 catégories (Bon / À améliorer / Mauvais) pour un metric donné.
 * Retourne [$dates, $pctGood, $pctNI, $pctPoor].
 */
function extract_metric_categories_timeseries($record, $metricKey) {
    if (!isset($record['metrics'][$metricKey])) {
        throw new Exception("Metric $metricKey absent du record.");
    }

    $periods    = $record['collectionPeriods'];
    $metric     = $record['metrics'][$metricKey];
    $histSeries = $metric['histogramTimeseries'];

    if (empty($histSeries)) {
        throw new Exception("Pas de histogramTimeseries pour $metricKey.");
    }

    $nPeriods = count($periods);
    $nBins    = count($histSeries);

    // Densités par bin, paddées à nPeriods
    $binDens = [];
    for ($b = 0; $b < $nBins; $b++) {
        $dens = isset($histSeries[$b]['densities']) ? $histSeries[$b]['densities'] : [];
        if (count($dens) < $nPeriods) {
            $dens = array_merge($dens, array_fill(0, $nPeriods - count($dens), null));
        }
        $binDens[$b] = $dens;
    }

    // Densité totale par période (pour normalisation)
    $totalDensity = array_fill(0, $nPeriods, 0.0);
    for ($b = 0; $b < $nBins; $b++) {
        for ($i = 0; $i < $nPeriods; $i++) {
            $d = $binDens[$b][$i];
            if ($d === null) continue;
            if (is_string($d) && !is_numeric($d)) continue;
            $totalDensity[$i] += floatval($d);
        }
    }

    $dates   = extract_dates_from_periods($periods);
    $pctGood = [];
    $pctNI   = [];
    $pctPoor = [];

    for ($i = 0; $i < $nPeriods; $i++) {
        $tot = $totalDensity[$i];
        if ($tot === 0.0) {
            $pctGood[] = null;
            $pctNI[]   = null;
            $pctPoor[]  = null;
            continue;
        }

        $pctFromBin = function($b) use ($binDens, $i, $tot, $nBins) {
            if ($b >= $nBins) return null;
            $d = $binDens[$b][$i];
            if ($d === null) return null;
            if (is_string($d) && !is_numeric($d)) return null;
            return floatval($d) * 100.0 / $tot;
        };

        $pctGood[] = $pctFromBin(0); // Bin 0 = Bon
        $pctNI[]   = $pctFromBin(1); // Bin 1 = À améliorer
        $pctPoor[]  = $pctFromBin(2); // Bin 2 = Mauvais
    }

    return [$dates, $pctGood, $pctNI, $pctPoor];
}

/**
 * Résumé sur les 2 dernières périodes (p75 + % good/ni/poor).
 */
function compute_metric_summary_latest_two($record, $metricKey, $metricCode) {
    if (!isset($record['metrics'][$metricKey])) {
        return null;
    }

    $periods    = $record['collectionPeriods'];
    $metric     = $record['metrics'][$metricKey];
    $histSeries = $metric['histogramTimeseries'];

    $nPeriods = count($periods);
    if ($nPeriods === 0 || empty($histSeries)) {
        return null;
    }

    $currIndex = $nPeriods - 1;
    $prevIndex = $nPeriods >= 2 ? $nPeriods - 2 : null;

    if (isset(CWV_THRESHOLDS[$metricCode])) {
        $goodThreshold = CWV_THRESHOLDS[$metricCode]['good'];
        $poorThreshold = CWV_THRESHOLDS[$metricCode]['poor'];
    } else {
        $goodThreshold = null;
        $poorThreshold = null;
    }

    $p75s = null;
    if (isset($metric['percentilesTimeseries']) && isset($metric['percentilesTimeseries']['p75s'])) {
        $p75s = $metric['percentilesTimeseries']['p75s'];
    }

    $allDates = extract_dates_from_periods($periods);
    $computeForIndex = function($idx) use ($allDates, $periods, $histSeries, $p75s, $goodThreshold, $poorThreshold) {
        if ($idx === null) return null;

        $dateStr = $allDates[$idx];

        $p75 = null;
        if (is_array($p75s) && array_key_exists($idx, $p75s)) {
            $val = $p75s[$idx];
            if ($val !== null && $val !== 'NaN') {
                $p75 = floatval($val);
            }
        }

        $good = 0.0;
        $ni   = 0.0;
        $poor = 0.0;
        $total = 0.0;

        foreach ($histSeries as $b) {
            $densities = isset($b['densities']) ? $b['densities'] : [];
            if (!array_key_exists($idx, $densities)) {
                continue;
            }
            $d = $densities[$idx];
            if ($d === null) continue;
            if (is_string($d)) {
                if (!is_numeric($d)) continue;
                $d = floatval($d);
            }
            $d = floatval($d);
            if ($d <= 0) continue;

            $start = isset($b['start']) ? $b['start'] : null;
            $end   = isset($b['end'])   ? $b['end']   : null;

            $total += $d;

            if ($goodThreshold === null || $poorThreshold === null) {
                $good += $d;
                continue;
            }

            if ($end !== null && $end <= $goodThreshold) {
                $good += $d;
            } elseif ($start !== null && $start >= $poorThreshold) {
                $poor += $d;
            } else {
                $ni += $d;
            }
        }

        if ($total > 0) {
            $goodPct = $good / $total * 100.0;
            $niPct   = $ni   / $total * 100.0;
            $poorPct = $poor / $total * 100.0;
        } else {
            $goodPct = $niPct = $poorPct = null;
        }

        return [
            'date' => $dateStr,
            'p75'  => $p75,
            'good' => $goodPct,
            'ni'   => $niPct,
            'poor' => $poorPct,
        ];
    };

    $prev = $computeForIndex($prevIndex);
    $curr = $computeForIndex($currIndex);

    if ($prev === null && $curr === null) {
        return null;
    }

    return [
        'prev' => $prev,
        'curr' => $curr,
    ];
}

/**
 * Extrait la timeseries P75 pour un metric donné.
 */
function extract_metric_p75_timeseries($record, $metricKey) {
    if (!isset($record['metrics'][$metricKey])) {
        throw new Exception("Metric $metricKey absent du record.");
    }

    $periods = $record['collectionPeriods'];
    $metric  = $record['metrics'][$metricKey];
    $nPeriods = count($periods);

    if (!isset($metric['percentilesTimeseries']['p75s'])) {
        throw new Exception("Pas de percentilesTimeseries.p75s pour $metricKey.");
    }

    $p75s = $metric['percentilesTimeseries']['p75s'];

    $dates  = extract_dates_from_periods($periods);
    $values = [];

    for ($i = 0; $i < $nPeriods; $i++) {
        $val = isset($p75s[$i]) ? $p75s[$i] : null;
        if ($val === null || $val === 'NaN') {
            $values[] = null;
        } else {
            $values[] = floatval($val);
        }
    }

    return [$dates, $values];
}

/**
 * Extrait la timeseries des types de navigation (fractionTimeseries).
 * Retourne ['dates' => [...], 'types' => ['navigate' => [...], 'reload' => [...], ...]]
 */
function extract_navigation_types_timeseries($record) {
    if (!isset($record['metrics']['navigation_types'])) {
        return null;
    }

    $periods  = $record['collectionPeriods'];
    $metric   = $record['metrics']['navigation_types'];
    $nPeriods = count($periods);

    $dates = extract_dates_from_periods($periods);

    $fractionTimeseries = isset($metric['fractionTimeseries']) ? $metric['fractionTimeseries'] : [];
    $types = [];
    foreach ($fractionTimeseries as $typeName => $data) {
        $fractions = isset($data['fractions']) ? $data['fractions'] : [];
        $pcts = [];
        for ($i = 0; $i < $nPeriods; $i++) {
            $f = isset($fractions[$i]) ? $fractions[$i] : null;
            if ($f === null || (is_string($f) && !is_numeric($f))) {
                $pcts[] = null;
            } else {
                $pcts[] = round(floatval($f) * 100.0, 2);
            }
        }
        $types[$typeName] = $pcts;
    }

    return ['dates' => $dates, 'types' => $types];
}

// =======================
// SITEMAP
// =======================

/**
 * Récupère les URLs d'un sitemap.xml pour un domaine donné.
 * Gère les sitemap index (parcourt les 3 premiers sous-sitemaps) et le gzip.
 * Retourne ['status' => 'ok', 'urls' => [...]] ou ['status' => 'error', 'error' => '...']
 */
function fetch_sitemap_urls(string $domain, int $limit = 20, string $customSitemapUrl = ''): array
{
    // Normaliser le domaine
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = rtrim($domain, '/');
    $sitemapUrl = $customSitemapUrl !== '' ? $customSitemapUrl : 'https://' . $domain . '/sitemap.xml';

    $xml = _fetch_sitemap_content($sitemapUrl);

    // Fallback : chercher le sitemap via robots.txt si le défaut échoue
    if ($xml === null && $customSitemapUrl === '') {
        $robotsSitemap = fetch_sitemap_from_robots($domain);
        if ($robotsSitemap !== null) {
            $sitemapUrl = $robotsSitemap;
            $xml = _fetch_sitemap_content($sitemapUrl);
        }
    }

    if ($xml === null) {
        return ['status' => 'error', 'error' => "Impossible de récupérer le sitemap : $sitemapUrl"];
    }

    $urls = [];

    // Sitemap index → parcourir les sous-sitemaps
    if (stripos($xml, '<sitemapindex') !== false) {
        $sitemapLocs = _extract_locs_from_xml($xml, 'sitemap');
        $subCount = 0;
        foreach ($sitemapLocs as $subUrl) {
            if ($subCount >= 10) break;
            $subXml = _fetch_sitemap_content($subUrl);
            if ($subXml !== null) {
                $urls = array_merge($urls, _extract_locs_from_xml($subXml, 'url'));
            }
            $subCount++;
            if (count($urls) >= $limit) break;
        }
    } else {
        $urls = _extract_locs_from_xml($xml, 'url');
    }

    if (empty($urls)) {
        return ['status' => 'error', 'error' => "Aucune URL trouvée dans le sitemap de $domain."];
    }

    $urls = array_slice($urls, 0, $limit);
    return ['status' => 'ok', 'urls' => $urls];
}

/**
 * Cherche la première directive Sitemap: dans le robots.txt du domaine.
 * Retourne l'URL du sitemap ou null si non trouvé / erreur.
 */
function fetch_sitemap_from_robots(string $domain): ?string
{
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = rtrim($domain, '/');

    $url = 'https://' . $domain . '/robots.txt';
    $ch = create_curl_handle($url, [
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'CrUX-History-Explorer/1.0',
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode !== 200) {
        return null;
    }

    foreach (explode("\n", $body) as $line) {
        if (preg_match('/^\s*Sitemap:\s*(.+)/i', $line, $m)) {
            $sitemapUrl = trim($m[1]);
            if (filter_var($sitemapUrl, FILTER_VALIDATE_URL)) {
                return $sitemapUrl;
            }
        }
    }

    return null;
}

/**
 * Télécharge le contenu d'un sitemap (gère gzip).
 */
function _fetch_sitemap_content(string $url): ?string
{
    $ch = create_curl_handle($url, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'CrUX-History-Explorer/1.0',
        CURLOPT_ENCODING       => '', // accepte gzip/deflate automatiquement
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode !== 200) {
        return null;
    }

    // Détection gzip manuelle (si le serveur ne l'a pas décompressé)
    if (strlen($body) >= 2 && $body[0] === "\x1f" && $body[1] === "\x8b") {
        $body = @gzdecode($body);
        if ($body === false) return null;
    }

    return $body;
}

/**
 * Extrait les <loc> d'un XML sitemap.
 * $parentTag : 'url' pour urlset, 'sitemap' pour sitemapindex.
 */
function _extract_locs_from_xml(string $xml, string $parentTag): array
{
    // Supprimer les namespaces pour simplifier le parsing
    $xml = preg_replace('#xmlns\s*=\s*"[^"]*"#', '', $xml);

    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();
    if ($doc === false) return [];

    $urls = [];
    foreach ($doc->{$parentTag} as $entry) {
        if (isset($entry->loc)) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $urls[] = $loc;
            }
        }
    }
    return $urls;
}

// =======================
// ÉCHANTILLONNAGE DIVERSIFIÉ
// =======================

/**
 * Échantillonne $target URLs depuis le pool en maximisant la diversité.
 * Groupement par "{premier_segment}:{profondeur}" + round-robin.
 * Retourne [$selected, $remaining].
 */
function sample_diverse_urls(array $urls, int $target): array
{
    if (count($urls) <= $target) {
        return [$urls, []];
    }

    // Grouper par clé composite "premier_segment:profondeur"
    $groups = [];
    foreach ($urls as $url) {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
        $depth = count($segments);
        $firstSeg = $depth > 0 ? $segments[0] : '_root';
        $key = $firstSeg . ':' . $depth;
        $groups[$key][] = $url;
    }

    // Mélanger à l'intérieur de chaque groupe
    foreach ($groups as &$g) {
        shuffle($g);
    }
    unset($g);

    // Round-robin : prendre 1 URL par groupe à chaque tour
    $selected = [];
    while (count($selected) < $target) {
        $picked = false;
        foreach ($groups as $key => &$g) {
            if (empty($g)) continue;
            $selected[] = array_shift($g);
            $picked = true;
            if (count($selected) >= $target) break;
        }
        unset($g);
        if (!$picked) break;
    }

    // URLs restantes = tout ce qui n'a pas été sélectionné
    $remaining = [];
    foreach ($groups as $g) {
        foreach ($g as $url) {
            $remaining[] = $url;
        }
    }
    shuffle($remaining);

    // Mélanger le résultat final
    shuffle($selected);

    return [$selected, $remaining];
}

// =======================
// CACHE BULK (fichiers temporaires)
// =======================

function generate_cache_id(): string {
    return bin2hex(random_bytes(16));
}

function get_cache_path(string $id): string {
    if (!preg_match('/^[0-9a-f]{32}$/', $id)) {
        throw new InvalidArgumentException('Cache ID invalide.');
    }
    $dir = sys_get_temp_dir() . '/crux_cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir . '/' . $id . '.json';
}

function save_bulk_cache(string $id, array $data): void {
    $path = get_cache_path($id);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function load_bulk_cache(string $id): ?array {
    try {
        $path = get_cache_path($id);
    } catch (InvalidArgumentException $e) {
        return null;
    }
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    @unlink($path); // usage unique
    return $data;
}

function cleanup_bulk_cache(): void {
    $dir = sys_get_temp_dir() . '/crux_cache';
    if (!is_dir($dir)) return;
    $now = time();
    foreach (glob($dir . '/*.json') as $file) {
        if ($now - filemtime($file) > 1800) { // 30 min
            @unlink($file);
        }
    }
}

// =======================
// PROCESS BULK
// =======================

/**
 * Traitement bulk complet : sitemap + échantillonnage + boucle API + labels.
 * $onLog reçoit une ligne de log à chaque étape (null = pas de log).
 */
function process_bulk(string $bulkDomain, string $formFactor, ?callable $onLog = null, string $sitemapUrl = '', string $source = 'sitemap'): array {
    $log = function(string $msg) use ($onLog) {
        if ($onLog !== null) {
            $onLog($msg);
        }
    };

    $results        = [];
    $series         = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $seriesP75      = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $summaries      = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $histogramBins  = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $navTypes       = [];
    $apiLog         = [];
    $originSummary  = null;
    $sitemapError   = null;
    $sitemapDiscovered = null;
    $sitemapSampled    = null;
    $sitemapReplaced   = null;

    if ($bulkDomain === '') {
        $sitemapError = 'Veuillez saisir un nom de domaine.';
        return compact(
            'results', 'series', 'seriesP75', 'summaries', 'histogramBins',
            'navTypes', 'apiLog', 'originSummary', 'sitemapError', 'sitemapDiscovered',
            'sitemapSampled', 'sitemapReplaced', 'bulkDomain', 'source'
        );
    }

    if ($source === 'wayback') {
        $log("Récupération des URLs via Wayback Machine pour {$bulkDomain}…");
        $urlResult = fetch_wayback_urls($bulkDomain, 2000);
    } else {
        $log("Récupération du sitemap de {$bulkDomain}…");
        $urlResult = fetch_sitemap_urls($bulkDomain, 2000, $sitemapUrl);
    }
    if ($urlResult['status'] === 'error') {
        $sitemapError = $urlResult['error'];
        $log("Erreur : {$sitemapError}");
        return compact(
            'results', 'series', 'seriesP75', 'summaries', 'histogramBins',
            'navTypes', 'apiLog', 'originSummary', 'sitemapError', 'sitemapDiscovered',
            'sitemapSampled', 'sitemapReplaced', 'bulkDomain', 'source'
        );
    }

    // Appel API CrUX (période courante) pour l'origine entière
    $originDomain = preg_replace('#^https?://#i', '', trim($bulkDomain));
    $originDomain = rtrim($originDomain, '/');
    $originUrl = 'https://' . $originDomain;
    $log("Récupération des performances de l'origine {$originUrl}…");
    $originRes = call_crux_record($originUrl, 'origin', $formFactor);
    if ($originRes['status'] === 'ok') {
        $originSummary = compute_origin_summary_from_record($originRes['record']);
        $log("Performances de l'origine récupérées.");
    } else {
        $log("Pas de données origine (HTTP " . intval($originRes['httpCode']) . ").");
    }

    $allUrls = $urlResult['urls'];
    $sitemapDiscovered = count($allUrls);
    $sourceLabel = $source === 'wayback' ? 'Wayback Machine' : 'sitemap';
    $log("{$sitemapDiscovered} URLs découvertes via {$sourceLabel}.");

    // Échantillonnage diversifié
    $maxApiCalls = 300;
    [$selected, $remaining] = sample_diverse_urls($allUrls, $maxApiCalls);
    $sitemapSampled = count($selected);
    $groupCount = 0;
    // Compter les groupes
    $groups = [];
    foreach ($selected as $url) {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
        $depth = count($segments);
        $firstSeg = $depth > 0 ? $segments[0] : '_root';
        $groups[$firstSeg . ':' . $depth] = true;
    }
    $groupCount = count($groups);
    $log("{$sitemapSampled} URLs échantillonnées — {$groupCount} groupes préfixe/profondeur, " . count($remaining) . " en réserve.");

    // Boucle avec remplacement — max 300 appels API, rate limit 140 req/min
    $lines = [];
    $noDataCount = 0;
    $okCount = 0;
    $queue = $selected;
    $displayLabels = [];
    $hostnames = [];
    $callIndex = 0;
    $totalCalls = count($queue);
    $minInterval = 60.0 / 140; // ~0.4286s entre chaque requête (quota 140/min)
    $lastCallTime = 0.0;

    while (!empty($queue) && $callIndex < $maxApiCalls) {
        $url = array_shift($queue);
        $callIndex++;

        // Rate limiting : respecter le quota de 140 req/min
        $now = microtime(true);
        $elapsed = $now - $lastCallTime;
        if ($lastCallTime > 0 && $elapsed < $minInterval) {
            usleep((int)(($minInterval - $elapsed) * 1_000_000));
        }
        $lastCallTime = microtime(true);

        $apiRes = call_crux_history($url, 'url', $formFactor);

        $logStatus = $apiRes['status'];
        $logDetail = $logStatus === 'error'
            ? $logStatus . ' (HTTP ' . intval($apiRes['httpCode']) . ')'
            : $logStatus;
        $apiLog[] = $url . ' → ' . $logDetail;
        $log("[{$callIndex}/{$totalCalls}] {$url} → {$logDetail}");

        if ($apiRes['status'] === 'ok') {
            $okCount++;
            $results[] = ['input' => $url, 'result' => $apiRes];
            $lines[] = $url;
            $parsed = parse_url($url);
            $hostnames[] = $parsed['host'] ?? '';

            $record = $apiRes['record'];

            // Navigation types
            $navData = extract_navigation_types_timeseries($record);
            if ($navData !== null) {
                $navTypes[] = ['label' => $url, 'dates' => $navData['dates'], 'types' => $navData['types']];
            }

            process_metrics_from_record($record, $url, $series, $seriesP75, $summaries);
        } else {
            $noDataCount++;
            // Piocher un remplacement si on n'a pas atteint le cap d'appels
            if (!empty($remaining) && ($callIndex + count($queue)) < $maxApiCalls) {
                $replacement = array_shift($remaining);
                $queue[] = $replacement;
                $totalCalls++;
                $log("  ↳ Remplacement depuis le pool (" . count($remaining) . " restantes)");
            }
        }
    }

    $sitemapReplaced = $noDataCount;
    $log("Terminé : {$okCount} avec données, {$noDataCount} sans données sur {$callIndex} appels.");

    // Recalculer les labels (path court si même host)
    $displayLabels = compute_display_labels($lines);
    $sameHost = !empty($lines) && ($displayLabels[$lines[0]] !== $lines[0]);

    // Mettre à jour les labels dans les séries
    if ($sameHost) {
        foreach (['series', 'seriesP75', 'summaries'] as $varName) {
            foreach (CRUX_METRICS as $code => $metricKey) {
                foreach ($$varName[$code] as &$entry) {
                    if (isset($displayLabels[$entry['label']])) {
                        $entry['label'] = $displayLabels[$entry['label']];
                    }
                }
                unset($entry);
            }
        }
        foreach ($histogramBins as $code => &$entries) {
            foreach ($entries as &$entry) {
                if (isset($displayLabels[$entry['label']])) {
                    $entry['label'] = $displayLabels[$entry['label']];
                }
            }
            unset($entry);
        }
        unset($entries);
        foreach ($navTypes as &$entry) {
            if (isset($displayLabels[$entry['label']])) {
                $entry['label'] = $displayLabels[$entry['label']];
            }
        }
        unset($entry);
    }

    return compact(
        'results', 'series', 'seriesP75', 'summaries', 'histogramBins',
        'navTypes', 'apiLog', 'originSummary', 'sitemapError', 'sitemapDiscovered',
        'sitemapSampled', 'sitemapReplaced', 'bulkDomain', 'source'
    );
}

// =======================
// WAYBACK MACHINE
// =======================

/**
 * Récupère les URLs d'un domaine via l'API CDX de la Wayback Machine.
 * Retourne ['status' => 'ok', 'urls' => [...]] ou ['status' => 'error', 'error' => '...']
 */
function fetch_wayback_urls(string $domain, int $limit = 2000): array
{
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = rtrim($domain, '/');

    $dateFrom = date('Ymd', strtotime('-1 year'));

    $cdxUrl = 'https://web.archive.org/cdx/search/cdx?' . http_build_query([
        'url'        => $domain,
        'matchType'  => 'domain',
        'from'       => $dateFrom,
        'collapse'   => 'urlkey',
        'output'     => 'json',
        'fl'         => 'original',
        'limit'      => 5000,
    ]) . '&filter=statuscode:200&filter=mimetype:text/html';

    $ch = create_curl_handle($cdxUrl, [
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'CrUX-History-Explorer/1.0',
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'error' => 'Erreur cURL Wayback : ' . $error];
    }
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['status' => 'error', 'error' => "Erreur Wayback Machine (HTTP {$httpCode})."];
    }

    $data = json_decode($body, true);
    if (!is_array($data) || count($data) < 2) {
        return ['status' => 'error', 'error' => "Aucune URL trouvée via Wayback Machine pour {$domain}."];
    }

    // Première ligne = header ["original"], on la saute
    array_shift($data);

    $urls = [];
    foreach ($data as $row) {
        if (is_array($row) && !empty($row[0])) {
            $urls[] = $row[0];
        }
    }

    $urls = array_unique($urls);

    // Filtrer les extensions statiques et fichiers non-HTML
    $excludeExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'xml', 'json', 'pdf'];
    $urls = array_filter($urls, function ($url) use ($excludeExtensions) {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        // Exclure robots.txt
        if (str_contains($path, 'robots.txt')) return false;
        // Exclure les extensions statiques
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, $excludeExtensions)) return false;
        return true;
    });

    // Dédoublonner en retirant les query strings (tracking)
    $seen = [];
    $cleaned = [];
    foreach ($urls as $url) {
        $base = strtok($url, '?');
        if (!isset($seen[$base])) {
            $seen[$base] = true;
            $cleaned[] = $base;
        }
    }

    $cleaned = array_slice($cleaned, 0, $limit);

    if (empty($cleaned)) {
        return ['status' => 'error', 'error' => "Aucune URL HTML trouvée via Wayback Machine pour {$domain}."];
    }

    return ['status' => 'ok', 'urls' => array_values($cleaned)];
}

// =======================
// PRÉSENTATION
// =======================

/**
 * Génère le HTML du fieldset "Source des URLs" (2 radios : sitemap / wayback).
 */
function render_source_fieldset(string $currentSource): string {
    $sources = [
        'sitemap'  => 'Sitemap',
        'wayback'  => 'Wayback Machine',
    ];
    $html = '<fieldset class="border rounded-3 p-3 border-secondary-subtle">';
    $html .= '<legend class="fs-6 fw-semibold text-secondary px-2">Source des URLs</legend>';
    foreach ($sources as $value => $label) {
        $id = 'source_' . $value;
        $checked = ($currentSource === $value) ? ' checked' : '';
        $html .= '<div class="form-check">';
        $html .= '<input class="form-check-input" type="radio" name="bulk_source" id="' . $id . '" value="' . $value . '"' . $checked . '>';
        $html .= '<label class="form-check-label" for="' . $id . '">' . $label . '</label>';
        $html .= '</div>';
    }
    $html .= '</fieldset>';
    return $html;
}

/**
 * Génère le HTML du fieldset "Type de device" (3 radios : ALL / MOBILE / DESKTOP).
 * $suffix : suffixe unique pour les IDs (ex: 'url', 'bulk').
 * $formFactor : valeur courante sélectionnée.
 */
function render_device_fieldset(string $suffix, string $formFactor): string {
    $devices = [
        'ALL'     => 'Tous (ALL)',
        'MOBILE'  => 'Mobile (PHONE)',
        'DESKTOP' => 'Desktop',
    ];
    $html = '<fieldset class="border rounded-3 p-3 border-secondary-subtle">';
    $html .= '<legend class="fs-6 fw-semibold text-secondary px-2">Type de device</legend>';
    foreach ($devices as $value => $label) {
        $id = 'device' . $value . '_' . $suffix;
        $checked = ($formFactor === $value) ? ' checked' : '';
        $html .= '<div class="form-check' . ($suffix !== 'bulk' ? ' form-check-inline' : '') . '">';
        $html .= '<input class="form-check-input" type="radio" name="device" id="' . $id . '" value="' . $value . '"' . $checked . '>';
        $html .= '<label class="form-check-label" for="' . $id . '">' . $label . '</label>';
        $html .= '</div>';
    }
    $html .= '</fieldset>';
    return $html;
}

/**
 * Retourne un badge de tendance coloré (↑ / ↓) comparant curr à prev.
 * $higherIsBetter = true pour % Good, false pour P75.
 * $format : 'ms', 'cls', 'pct'
 */
function trend_badge($curr_val, $prev_val, $higherIsBetter, $format = 'pct') {
    if ($curr_val === null || $prev_val === null) return '';
    $diff = $curr_val - $prev_val;
    if (abs($diff) < 0.0001) return '';
    $improved = $higherIsBetter ? $diff > 0 : $diff < 0;
    $cls   = $improved ? 'trend-good' : 'trend-bad';
    $arrow = $diff > 0 ? '↑' : '↓';
    if ($format === 'ms') {
        $val = round(abs($diff)) . ' ms';
    } elseif ($format === 'cls') {
        $val = number_format(abs($diff), 3, ',', ' ');
    } else {
        $val = number_format(abs($diff), 1, ',', ' ') . ' %';
    }
    return sprintf(' <span class="%s" title="vs période précédente (28 j glissants)">%s %s</span>', $cls, $arrow, $val);
}

function p75_color_class(string $metric, $value): string {
    if ($value === null) return '';
    if (!isset(CWV_THRESHOLDS[$metric])) return '';
    $t = CWV_THRESHOLDS[$metric];
    if ($value <= $t['good']) return 'status-ok';
    if ($value >= $t['poor']) return 'status-error';
    return 'status-no_data';
}

// =======================
// RÉSUMÉ ORIGINE (PÉRIODE COURANTE)
// =======================

/**
 * Extrait les KPI (P75, good/ni/poor %) depuis un record CrUX courant (API queryRecord).
 * Structure différente de l'historique : percentiles.p75 et histogram[].density (pas de timeseries).
 */
function compute_origin_summary_from_record(array $record): array {
    $summary = [];
    foreach (CRUX_METRICS as $code => $metricKey) {
        if (!isset($record['metrics'][$metricKey])) continue;
        $m = $record['metrics'][$metricKey];
        $p75 = $m['percentiles']['p75'] ?? null;
        if ($p75 !== null) $p75 = floatval($p75);
        $good = $ni = $poor = 0.0;
        $t = CWV_THRESHOLDS[$code];
        foreach ($m['histogram'] as $b) {
            $d = floatval($b['density'] ?? 0);
            $start = $b['start'] ?? null;
            $end   = $b['end'] ?? null;
            if ($end !== null && $end <= $t['good']) $good += $d;
            elseif ($start !== null && $start >= $t['poor']) $poor += $d;
            else $ni += $d;
        }
        $total = $good + $ni + $poor;
        $summary[$code] = [
            'p75'  => $p75,
            'good' => $total > 0 ? $good / $total * 100 : null,
            'ni'   => $total > 0 ? $ni / $total * 100 : null,
            'poor' => $total > 0 ? $poor / $total * 100 : null,
        ];
    }
    return $summary;
}

// =======================
// HELPER LABELS
// =======================

/**
 * Si toutes les URLs partagent le même hostname, retourne le path seul comme label.
 * Sinon retourne l'URL complète.
 * Retourne un tableau associatif [url => label].
 */
function compute_display_labels(array $urls): array {
    $hostnames = [];
    foreach ($urls as $url) {
        $parsed = parse_url($url);
        $hostnames[] = $parsed['host'] ?? '';
    }
    $uniqueHosts = array_unique(array_filter($hostnames));
    $sameHost = count($uniqueHosts) === 1 && count($urls) > 1;

    $labels = [];
    foreach ($urls as $url) {
        if ($sameHost) {
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '/';
            $labels[$url] = $path === '' ? '/' : $path;
        } else {
            $labels[$url] = $url;
        }
    }
    return $labels;
}

// =======================
// HELPER MÉTRIQUES
// =======================

/**
 * Extrait categories, P75, et summary depuis un record CrUX pour toutes les métriques.
 * Alimente directement les tableaux $series, $seriesP75 et $summaries par référence.
 */
function process_metrics_from_record(array $record, string $label, array &$series, array &$seriesP75, array &$summaries): void {
    foreach (CRUX_METRICS as $code => $metricKey) {
        try {
            list($dates, $pctGood, $pctNI, $pctPoor) = extract_metric_categories_timeseries($record, $metricKey);
            $series[$code][] = [
                'label' => $label,
                'dates' => $dates,
                'good'  => $pctGood,
                'ni'    => $pctNI,
                'poor'  => $pctPoor,
            ];

            try {
                list($datesP75, $valuesP75) = extract_metric_p75_timeseries($record, $metricKey);
                $seriesP75[$code][] = [
                    'label'  => $label,
                    'dates'  => $datesP75,
                    'values' => $valuesP75,
                ];
            } catch (Exception $e2) {}

            $sum = compute_metric_summary_latest_two($record, $metricKey, $code);
            if ($sum !== null && $sum['curr'] !== null && $sum['curr']['good'] !== null) {
                $sum['label'] = $label;
                $summaries[$code][] = $sum;
            }
        } catch (Exception $e) {
            continue;
        }
    }
}

// =======================
// TRAITEMENT FORMULAIRE
// =======================

function handle_crux_form(): array {
    $results        = [];
    $series         = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $seriesP75      = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $summaries      = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $histogramBins  = ['ttfb' => [], 'lcp' => [], 'cls' => [], 'inp' => []];
    $navTypes       = [];
    $apiLog          = [];
    $originSummary   = null;
    $bulkDomain      = '';
    $tab             = 'url';

    $formFactor      = 'ALL';
    $mode            = 'origin';
    $inputText       = '';
    $sitemapUrl      = '';
    $bulkSource      = 'sitemap';
    $hasPost         = ($_SERVER['REQUEST_METHOD'] === 'POST');
    $apiKeyMissing   = empty(CRUX_API_KEY);
    $sitemapError     = null;
    $sitemapDiscovered = null;
    $sitemapSampled    = null;
    $sitemapReplaced   = null;

    if ($hasPost && $apiKeyMissing) {
        // Pas de clé API → on ne tente aucun appel
        $hasPost = false;
    }

    if ($hasPost) {
        $tab        = isset($_POST['tab']) ? $_POST['tab'] : 'url';
        $formFactor = isset($_POST['device']) ? $_POST['device'] : 'ALL';

        if ($tab === 'bulk') {
            set_time_limit(0); // Bulk : nombreux appels API séquentiels

            $bulkDomain = isset($_POST['bulk_domain']) ? trim($_POST['bulk_domain']) : '';
            $sitemapUrl = trim($_POST['sitemap_url'] ?? '');
            $bulkSource = $_POST['bulk_source'] ?? 'sitemap';

            // Si cache_id présent → charger résultat SSE
            if (!empty($_POST['cache_id'])) {
                $cached = load_bulk_cache($_POST['cache_id']);
                if ($cached !== null) {
                    $results        = $cached['results'];
                    $series         = $cached['series'];
                    $seriesP75      = $cached['seriesP75'];
                    $summaries      = $cached['summaries'];
                    $histogramBins  = $cached['histogramBins'];
                    $navTypes       = $cached['navTypes'];
                    $apiLog         = $cached['apiLog'];
                    $originSummary  = $cached['originSummary'] ?? null;
                    $sitemapError   = $cached['sitemapError'];
                    $sitemapDiscovered = $cached['sitemapDiscovered'];
                    $sitemapSampled   = $cached['sitemapSampled'];
                    $sitemapReplaced  = $cached['sitemapReplaced'];
                    $bulkDomain       = $cached['bulkDomain'];
                    $bulkSource       = $cached['source'] ?? 'sitemap';
                    $lines = []; // pas besoin de retraiter
                    $effectiveMode = 'url';

                    // Sauter le traitement standard des URLs ci-dessous
                    goto bulk_done;
                }
            }

            // Fallback synchrone (sans JS / SSE)
            $bulkResult = process_bulk($bulkDomain, $formFactor, null, $sitemapUrl, $bulkSource);
            $results        = $bulkResult['results'];
            $series         = $bulkResult['series'];
            $seriesP75      = $bulkResult['seriesP75'];
            $summaries      = $bulkResult['summaries'];
            $histogramBins  = $bulkResult['histogramBins'];
            $navTypes       = $bulkResult['navTypes'];
            $apiLog         = $bulkResult['apiLog'];
            $originSummary  = $bulkResult['originSummary'] ?? null;
            $sitemapError   = $bulkResult['sitemapError'];
            $sitemapDiscovered = $bulkResult['sitemapDiscovered'];
            $sitemapSampled   = $bulkResult['sitemapSampled'];
            $sitemapReplaced  = $bulkResult['sitemapReplaced'];
            $bulkDomain       = $bulkResult['bulkDomain'];
            $bulkSource       = $bulkResult['source'] ?? 'sitemap';
            $lines = [];

            $effectiveMode = 'url';
            bulk_done:
        } else {
            $inputText  = isset($_POST['urls']) ? trim($_POST['urls']) : '';
            $mode       = isset($_POST['query_type']) ? $_POST['query_type'] : 'origin';

            $lines = preg_split('/\R/', $inputText);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines);
            $lines = array_slice($lines, 0, 10);

            $effectiveMode = $mode;
        }

        // Mode URL : traitement standard (le mode Bulk a déjà traité ci-dessus)
        if ($tab !== 'bulk') {
            $displayLabels = compute_display_labels($lines);

            // Rate limiting : respecter le quota de 140 req/min (identique au mode Bulk)
            $minInterval = 60.0 / 140;
            $lastCallTime = 0.0;

            foreach ($lines as $line) {
                if ($line === '') continue;
                $label = $displayLabels[$line] ?? $line;

                // Rate limiting entre les appels API
                $now = microtime(true);
                $elapsed = $now - $lastCallTime;
                if ($lastCallTime > 0 && $elapsed < $minInterval) {
                    usleep((int)(($minInterval - $elapsed) * 1_000_000));
                }
                $lastCallTime = microtime(true);

                $apiRes = call_crux_history($line, $effectiveMode, $formFactor);
                $results[] = [
                    'input'  => $line,
                    'result' => $apiRes,
                ];

                if ($apiRes['status'] !== 'ok') {
                    continue;
                }

                $record = $apiRes['record'];

                // Appel API CrUX (période courante) pour les bins granulaires
                $now = microtime(true);
                $elapsed = $now - $lastCallTime;
                if ($lastCallTime > 0 && $elapsed < $minInterval) {
                    usleep((int)(($minInterval - $elapsed) * 1_000_000));
                }
                $lastCallTime = microtime(true);
                $recordRes = call_crux_record($line, $effectiveMode, $formFactor);
                if ($recordRes['status'] === 'ok') {
                    foreach (CRUX_METRICS as $code => $metricKey) {
                        $bins = extract_histogram_bins($recordRes['record'], $metricKey);
                        if (!empty($bins)) {
                            $histogramBins[$code][] = ['label' => $label, 'bins' => $bins];
                        }
                    }
                }

                // Navigation types
                $navData = extract_navigation_types_timeseries($record);
                if ($navData !== null) {
                    $navTypes[] = ['label' => $label, 'dates' => $navData['dates'], 'types' => $navData['types']];
                }

                process_metrics_from_record($record, $label, $series, $seriesP75, $summaries);
            }
        }
    }

    $jsSeries        = json_encode($series,        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsSeriesP75     = json_encode($seriesP75,     JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsHistogramBins = json_encode($histogramBins, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsNavTypes      = json_encode($navTypes,      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return compact(
        'results',
        'series',
        'seriesP75',
        'summaries',
        'histogramBins',
        'navTypes',
        'formFactor',
        'mode',
        'inputText',
        'hasPost',
        'jsSeries',
        'jsSeriesP75',
        'jsHistogramBins',
        'jsNavTypes',
        'apiKeyMissing',
        'originSummary',
        'sitemapError',
        'sitemapDiscovered',
        'sitemapSampled',
        'sitemapReplaced',
        'tab',
        'apiLog',
        'bulkDomain',
        'sitemapUrl',
        'bulkSource'
    );
}
