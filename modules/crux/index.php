<?php
require_once __DIR__ . '/functions.php';

$view = handle_crux_form();

$results           = $view['results'];
$series            = $view['series'];
$seriesP75         = $view['seriesP75'];
$summaries         = $view['summaries'];
$histogramBins     = $view['histogramBins'];
$navTypes          = $view['navTypes'];
$formFactor        = $view['formFactor'];
$mode              = $view['mode'];
$inputText         = $view['inputText'];
$hasPost           = $view['hasPost'];
$jsSeries          = $view['jsSeries'];
$jsSeriesP75       = $view['jsSeriesP75'];
$jsHistogramBins   = $view['jsHistogramBins'];
$jsNavTypes        = $view['jsNavTypes'];
$apiKeyMissing     = $view['apiKeyMissing'];
$originSummary     = $view['originSummary'];
$sitemapError      = $view['sitemapError'];
$sitemapDiscovered = $view['sitemapDiscovered'];
$sitemapSampled    = $view['sitemapSampled'];
$sitemapReplaced   = $view['sitemapReplaced'];
$tab               = $view['tab'];
$apiLog            = $view['apiLog'];
$bulkDomain        = $view['bulkDomain'];
$sitemapUrl        = $view['sitemapUrl'];
$bulkSource        = $view['bulkSource'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>CrUX History – TTFB / LCP / CLS / INP</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="styles.css?v2">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<nav class="navbar mb-4">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            CrUX History Explorer
            <span class="d-block d-sm-inline ms-sm-2">TTFB / LCP / CLS / INP</span>
        </span>
    </div>
</nav>

<div class="container-lg pb-5">

    <?php if ($apiKeyMissing): ?>
        <div class="alert alert-warning border-warning-subtle bg-warning-subtle text-dark">
            <strong>Attention :</strong> la variable d'environnement <code>CRUX_API_KEY</code> n'est pas définie.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4" id="configCard">
                <div class="card-header pb-0">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <h5 class="mb-0 fw-bold">Configuration</h5>
                            <small class="graph-main-sub">Interroge l'API CrUX History</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                                API CrUX
                            </span>
                            <button class="btn btn-sm btn-link text-secondary p-0 config-collapse-toggle"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#configBody"
                                    aria-expanded="<?= ($hasPost && !empty($results)) ? 'false' : 'true' ?>">
                                <span class="collapse-icon">▼</span>
                            </button>
                        </div>
                    </div>
                    <ul class="nav nav-tabs border-0" id="configTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $tab === 'url' ? 'active' : '' ?>"
                                    id="config-tab-url" data-bs-toggle="tab" data-bs-target="#config-pane-url"
                                    type="button" role="tab">Comparaison</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $tab === 'bulk' ? 'active' : '' ?>"
                                    id="config-tab-bulk" data-bs-toggle="tab" data-bs-target="#config-pane-bulk"
                                    type="button" role="tab">Dynamique</button>
                        </li>
                    </ul>
                </div>
                <div class="collapse <?= ($hasPost && !empty($results)) ? '' : 'show' ?>" id="configBody">
                <div class="card-body">
                    <form method="post" class="d-flex flex-column gap-3">
                        <input type="hidden" name="tab" id="tabInput" value="<?= htmlspecialchars($tab) ?>">

                        <div class="tab-content">
                            <!-- Onglet URL -->
                            <div class="tab-pane fade <?= $tab === 'url' ? 'show active' : '' ?>" id="config-pane-url" role="tabpanel">
                                <div class="row g-3 align-items-start">
                                    <!-- Colonne gauche : textarea -->
                                    <div class="col-md-5">
                                        <textarea
                                            id="urls"
                                            name="urls"
                                            rows="9"
                                            required
                                            class="form-control form-control-sm"
                                            placeholder="https://www.example.com&#10;https://www.site-demo.fr&#10;https://www.mon-site.com"
                                        ><?php echo htmlspecialchars($inputText); ?></textarea>
                                        <div class="form-text text-secondary">
                                            Une URL ou un origin par ligne. Seules les 10 premières lignes seront prises en compte.
                                        </div>
                                    </div>
                                    <!-- Colonne centrale : Mode + Device + bouton -->
                                    <div class="col-md-4 d-flex flex-column gap-3">
                                        <fieldset class="border rounded-3 p-3 border-secondary-subtle">
                                            <legend class="fs-6 fw-semibold text-secondary px-2">Mode</legend>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="query_type" id="modeOrigin" value="origin"
                                                    <?php if ($mode === 'origin') echo 'checked'; ?>>
                                                <label class="form-check-label" for="modeOrigin">
                                                    Par origin
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="query_type" id="modeUrl" value="url"
                                                    <?php if ($mode === 'url') echo 'checked'; ?>>
                                                <label class="form-check-label" for="modeUrl">
                                                    Par URL
                                                </label>
                                            </div>
                                        </fieldset>

                                        <?= render_device_fieldset('url', $tab === 'url' ? $formFactor : 'ALL') ?>

                                        <div class="d-flex justify-content-end mt-auto">
                                            <button type="submit" id="submitBtn_url" class="btn btn-primary px-4">
                                                Lancer CrUX History
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Colonne droite : panneau d'aide -->
                                    <div class="col-md-3">
                                        <div class="config-help-panel">
                                            <div class="help-title">Mode d'emploi</div>
                                            Comparez jusqu'à 10 URLs ou origins sur les 4 métriques Core Web Vitals (TTFB, LCP, CLS, INP).
                                            <ul class="mt-2">
                                                <li>Une entrée par ligne</li>
                                                <li>Origin = données agrégées du domaine</li>
                                                <li>URL = données d'une page précise</li>
                                                <li>Appareil : filtre les données par type de device</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Bulk -->
                            <div class="tab-pane fade <?= $tab === 'bulk' ? 'show active' : '' ?>" id="config-pane-bulk" role="tabpanel">
                                <div class="d-flex gap-3 align-items-start">
                                    <div class="flex-grow-1">
                                        <input type="text"
                                            id="bulk_domain"
                                            name="bulk_domain"
                                            required
                                            class="form-control form-control-sm"
                                            placeholder="example.com"
                                            value="<?php echo htmlspecialchars($bulkDomain); ?>">
                                        <div class="form-text text-secondary" id="bulk-help-text">
                                            <?= $bulkSource === 'wayback'
                                                ? 'Les URLs sont découvertes via la Wayback Machine (pages HTML archivées depuis moins d\'un an).'
                                                : 'Les URLs sont extraites du sitemap.xml (max 250 résultats).' ?>
                                        </div>
                                        <div id="sitemap-fields" style="<?= $bulkSource === 'wayback' ? 'display:none' : '' ?>">
                                            <input type="text"
                                                id="sitemap_url"
                                                name="sitemap_url"
                                                class="form-control form-control-sm mt-2"
                                                placeholder="https://example.com/sitemap.xml"
                                                value="<?php echo htmlspecialchars($sitemapUrl); ?>">
                                            <div class="form-text text-secondary">
                                                Optionnel — par défaut /sitemap.xml
                                            </div>
                                        </div>
                                        <div id="wayback-fields" style="<?= $bulkSource === 'wayback' ? '' : 'display:none' ?>">
                                            <div class="form-text text-secondary mt-2">
                                                <small>Seules les pages HTML avec un code 200, archivées depuis moins d'un an, seront récupérées.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-3">
                                            <button type="submit" id="submitBtn_bulk" class="btn btn-primary px-4">
                                                Lancer CrUX History
                                            </button>
                                        </div>
                                    </div>
                                    <?= render_source_fieldset($bulkSource) ?>
                                    <?= render_device_fieldset('bulk', $formFactor) ?>
                                    <div class="config-help-panel" style="min-width:220px">
                                        <div class="help-title">Mode d'emploi</div>
                                        Scannez un site entier : les URLs sont découvertes automatiquement puis interrogées en lot via l'API CrUX.
                                        <ul class="mt-2">
                                            <li>Sitemap : extrait les URLs du sitemap.xml (max 250)</li>
                                            <li>Wayback Machine : retrouve les pages HTML archivées depuis moins d'un an</li>
                                            <li>Les résultats sont affichés dans un tableau triable par métrique</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>

                    <script>
                        // Activer/désactiver les champs required selon l'onglet actif
                        function updateRequiredFields(isBulk) {
                            document.getElementById('urls').required = !isBulk;
                            document.getElementById('bulk_domain').required = isBulk;
                        }
                        updateRequiredFields(document.getElementById('tabInput').value === 'bulk');

                        // Auto-discovery du sitemap via robots.txt (préremplissage silencieux)
                        document.getElementById('bulk_domain').addEventListener('blur', function() {
                            var domain = this.value.trim();
                            var sitemapField = document.getElementById('sitemap_url');
                            if (!domain || sitemapField.value.trim() !== '') return;

                            fetch((window.MODULE_BASE_URL || '') + '/robots_sitemap.php?domain=' + encodeURIComponent(domain))
                                .then(function(r) { return r.json(); })
                                .then(function(data) {
                                    if (data.sitemap_url && sitemapField.value.trim() === '') {
                                        sitemapField.value = data.sitemap_url;
                                    }
                                })
                                .catch(function() {});
                        });

                        // Toggle source sitemap / wayback
                        document.querySelectorAll('input[name="bulk_source"]').forEach(function(radio) {
                            radio.addEventListener('change', function() {
                                document.getElementById('sitemap-fields').style.display = this.value === 'sitemap' ? '' : 'none';
                                document.getElementById('wayback-fields').style.display = this.value === 'wayback' ? '' : 'none';
                                var helpText = document.getElementById('bulk-help-text');
                                if (helpText) {
                                    helpText.textContent = this.value === 'wayback'
                                        ? 'Les URLs sont découvertes via la Wayback Machine (pages HTML archivées depuis moins d\'un an).'
                                        : 'Les URLs sont extraites du sitemap.xml (max 250 résultats).';
                                }
                            });
                        });

                        document.querySelectorAll('#configTabs button').forEach(function(btn) {
                            btn.addEventListener('shown.bs.tab', function() {
                                var isBulk = btn.id === 'config-tab-bulk';
                                document.getElementById('tabInput').value = isBulk ? 'bulk' : 'url';
                                updateRequiredFields(isBulk);

                                var urlResults  = document.getElementById('results-url');
                                var bulkResults = document.getElementById('results-bulk');
                                if (urlResults)  urlResults.style.display  = isBulk ? 'none' : '';
                                if (bulkResults) bulkResults.style.display = isBulk ? '' : 'none';

                                // Déplier la config si l'onglet cible n'a pas encore de résultats
                                var targetResults = isBulk
                                    ? document.getElementById('results-bulk')
                                    : document.getElementById('results-url');
                                var hasResults = targetResults && targetResults.querySelector('.card');
                                if (!hasResults) {
                                    var configBody = document.getElementById('configBody');
                                    var bsCollapse = bootstrap.Collapse.getOrCreateInstance(configBody, {toggle: false});
                                    bsCollapse.show();
                                }
                            });
                        });
                        document.querySelector('form').addEventListener('submit', function (e) {
                            // Replier la config immédiatement
                            var configBody = document.getElementById('configBody');
                            if (configBody) {
                                var bsCollapse = bootstrap.Collapse.getOrCreateInstance(configBody, {toggle: false});
                                bsCollapse.hide();
                            }

                            var form = this;
                            var tab = document.getElementById('tabInput').value;
                            var btn = document.getElementById('submitBtn_' + tab);

                            if (tab !== 'bulk') {
                                // Mode URL : comportement inchangé
                                btn.disabled = true;
                                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Chargement…';
                                return;
                            }

                            // Mode Bulk : intercepter le submit pour SSE
                            e.preventDefault();
                            btn.disabled = true;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Streaming…';

                            var domain = document.getElementById('bulk_domain').value.trim();
                            var device = document.querySelector('input[name="device"]:checked');
                            device = device ? device.value : 'ALL';

                            // Injecter le bloc log streaming dans la colonne résultats
                            var resultsCol = document.getElementById('results-bulk');
                            if (resultsCol) resultsCol.style.display = '';
                            // Supprimer un éventuel ancien bloc streaming
                            var oldCard = document.getElementById('streaming-log-card');
                            if (oldCard) oldCard.remove();

                            var card = document.createElement('div');
                            card.className = 'card shadow-sm mb-4';
                            card.id = 'streaming-log-card';
                            card.innerHTML =
                                '<div class="card-header">' +
                                    '<div class="graph-main-title">' +
                                        'Log API <span class="spinner-border spinner-border-sm ms-2" id="streaming-spinner"></span>' +
                                    '</div>' +
                                    '<div class="graph-main-sub" id="streaming-counter">En attente…</div>' +
                                '</div>' +
                                '<div class="card-body">' +
                                    '<textarea readonly id="streaming-log" rows="14" ' +
                                        'class="form-control form-control-sm bg-dark text-light border-secondary bulk-api-log">' +
                                    '</textarea>' +
                                '</div>';
                            resultsCol.insertBefore(card, resultsCol.firstChild);

                            var logArea = document.getElementById('streaming-log');
                            var counter = document.getElementById('streaming-counter');
                            var lineCount = 0;

                            var sitemapUrl = document.getElementById('sitemap_url').value.trim();
                            var bulkSource = document.querySelector('input[name="bulk_source"]:checked');
                            bulkSource = bulkSource ? bulkSource.value : 'sitemap';
                            var url = (window.MODULE_BASE_URL || '') + '/bulk_stream.php?domain=' + encodeURIComponent(domain) + '&device=' + encodeURIComponent(device) + '&source=' + encodeURIComponent(bulkSource) + (sitemapUrl ? '&sitemap_url=' + encodeURIComponent(sitemapUrl) : '');
                            var evtSource = new EventSource(url);

                            evtSource.addEventListener('log', function(e) {
                                var data = JSON.parse(e.data);
                                logArea.value += data.message + '\n';
                                logArea.scrollTop = logArea.scrollHeight;
                                lineCount++;
                                counter.textContent = lineCount + ' ligne' + (lineCount > 1 ? 's' : '') + ' reçue' + (lineCount > 1 ? 's' : '');
                            });

                            evtSource.addEventListener('done', function(e) {
                                evtSource.close();
                                var spinner = document.getElementById('streaming-spinner');
                                if (spinner) spinner.remove();
                                counter.textContent = 'Terminé — chargement des résultats…';

                                // Injecter cache_id dans le form et soumettre
                                var input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'cache_id';
                                input.value = JSON.parse(e.data).cacheId;
                                form.appendChild(input);
                                form.submit();
                            });

                            evtSource.onerror = function() {
                                evtSource.close();
                                var spinner = document.getElementById('streaming-spinner');
                                if (spinner) spinner.remove();
                                logArea.value += '\n⚠ Connexion interrompue. Veuillez réessayer.\n';
                                logArea.scrollTop = logArea.scrollHeight;
                                counter.textContent = 'Erreur de connexion';
                                btn.disabled = false;
                                btn.innerHTML = 'Lancer CrUX History';
                            };
                        });
                    </script>

                    <?php if ($hasPost && empty($results)): ?>
                        <div class="mt-3 small text-warning">
                            Aucune entrée valide n'a été trouvée dans le champ URLs / origins.
                        </div>
                    <?php endif; ?>
                </div>
                </div>
    </div>
            <?php
            // ── Variables partagées : bloc KPI + carte onglets ─────────────────
            $availMetrics = array_values(array_filter(['ttfb','lcp','cls','inp'], fn($m) => !empty($series[$m])));
            $metricMeta   = [
                'ttfb' => ['goal' => 'Objectif Core Web Vitals : au moins 75&nbsp;% de sessions avec un TTFB ≤ 800 ms.', 'trendfmt' => 'ms',  'p75fmt' => fn($v) => round($v) . ' ms'],
                'lcp'  => ['goal' => 'Objectif Core Web Vitals : au moins 75&nbsp;% de sessions avec un LCP ≤ 2,5 s.',   'trendfmt' => 'ms',  'p75fmt' => fn($v) => round($v) . ' ms'],
                'cls'  => ['goal' => 'Objectif Core Web Vitals : au moins 75&nbsp;% de sessions avec un CLS ≤ 0,1.',     'trendfmt' => 'cls', 'p75fmt' => fn($v) => number_format($v, 3, ',', ' ')],
                'inp'  => ['goal' => 'Objectif Core Web Vitals : au moins 75&nbsp;% de sessions avec un INP ≤ 200 ms.', 'trendfmt' => 'ms',  'p75fmt' => fn($v) => round($v) . ' ms'],
            ];
            $fmtPct   = fn($v) => $v === null ? '–' : number_format($v, 1, ',', ' ') . ' %';
            $cwvGauge = [
                'ttfb' => ['good' => 800,  'poor' => 1800, 'max' => 3000, 'labels' => ['0', '800 ms', '1,8 s', '3 s']],
                'lcp'  => ['good' => 2500, 'poor' => 4000, 'max' => 6000, 'labels' => ['0', '2,5 s', '4 s', '6 s']],
                'cls'  => ['good' => 0.1,  'poor' => 0.25, 'max' => 0.5,  'labels' => ['0', '0,1', '0,25', '0,5']],
                'inp'  => ['good' => 200,  'poor' => 500,  'max' => 700,  'labels' => ['0', '200 ms', '500 ms', '700 ms']],
            ];
            $thresholdLabels = [
                'ttfb' => ['good' => '≤ 800 ms',  'ni' => '800 – 1 800 ms',   'poor' => '≥ 1 800 ms'],
                'lcp'  => ['good' => '≤ 2,5 s',   'ni' => '2,5 – 4 s',        'poor' => '≥ 4 s'],
                'cls'  => ['good' => '≤ 0,1',      'ni' => '0,1 – 0,25',       'poor' => '≥ 0,25'],
                'inp'  => ['good' => '≤ 200 ms',  'ni' => '200 – 500 ms',     'poor' => '≥ 500 ms'],
            ];
            $urlCount    = 0;
            foreach ($availMetrics as $_m) { if (!empty($summaries[$_m])) { $urlCount = count($summaries[$_m]); break; } }
            $isSingleUrl = ($urlCount === 1);
            $kpiColor    = fn(string $m, $v) => match(p75_color_class($m, $v)) { 'status-ok' => '#22c55e', 'status-error' => '#ef4444', default => '#f97316' };
            $kpiLabel    = fn(string $m, $v) => match(p75_color_class($m, $v)) { 'status-ok' => 'Bon', 'status-error' => 'Mauvais', default => 'À améliorer' };
            // Données KPI pré-calculées pour toutes les URLs (utilisées par switchKpiUrl en JS)
            $kpiData = [];
            if (!empty($availMetrics) && !empty($summaries[$availMetrics[0]])) {
                foreach ($summaries[$availMetrics[0]] as $urlIdx => $urlRow) {
                    $urlEntry = ['label' => $urlRow['label'], 'metrics' => []];
                    foreach ($availMetrics as $m) {
                        $curr = $summaries[$m][$urlIdx]['curr'] ?? null;
                        $g    = $cwvGauge[$m];
                        $urlEntry['metrics'][$m] = $curr ? [
                            'valFmt' => ($metricMeta[$m]['p75fmt'])($curr['p75']),
                            'color'  => ($kpiColor)($m, $curr['p75']),
                            'label'  => ($kpiLabel)($m, $curr['p75']),
                            'pos'    => min(round($curr['p75'] / $g['max'] * 100), 97),
                        ] : ['valFmt' => '–', 'color' => '#6b7280', 'label' => '', 'pos' => 0];
                    }
                    $kpiData[] = $urlEntry;
                }
            }
            ?>

            <div id="results-url" style="<?= $tab === 'bulk' ? 'display:none' : '' ?>">
            <?php if ($tab !== 'bulk'): ?>
            <!-- ═══ MODE URL ═══════════════════════════════════════════════════ -->

            <?php
            $hasApiErrors = !empty($results) && (bool) array_filter($results, fn($r) => $r['result']['status'] !== 'ok');
            ?>
            <?php if ($hasApiErrors): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">Résultats bruts</h5>
                        <small class="graph-main-sub">Statut des appels API par entrée</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle w-100 api-status-table">
                                <thead>
                                <tr>
                                    <th scope="col">Entrée</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Détail</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($results as $row): ?>
                                    <?php
                                    $input = $row['input'];
                                    $res   = $row['result'];
                                    $statusClass = 'status-' . $res['status'];
                                    ?>
                                    <tr>
                                        <td class="small"><?php echo htmlspecialchars($input); ?></td>
                                        <td class="<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($res['status']); ?>
                                        </td>
                                        <td class="small text-secondary">
                                            <?php
                                            if ($res['status'] === 'error') {
                                                echo 'HTTP ' . intval($res['httpCode']) . ' – ' . htmlspecialchars($res['error']);
                                            } else {
                                                echo 'HTTP ' . intval($res['httpCode']);
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($hasPost): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <span class="text-warning">Aucun appel API réalisé ou aucun résultat exploitable.</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ── Layout 2 colonnes : sidebar (KPI + slider) + main (charts) ── -->
            <?php if (!empty($availMetrics)): ?>
            <div class="results-grid">
                <div class="results-sidebar">
                    <!-- ── Bloc "Performances actuelles" ────────────────────────────── -->
                    <?php if ($urlCount > 0): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <div class="graph-main-title">Performances actuelles</div>
                            <div class="graph-main-sub">Dernière période CrUX · 28 jours glissants</div>
                        </div>
                        <div class="card-body">

                            <?php if ($isSingleUrl): ?>
                            <!-- 1 URL : 4 cartes KPI avec jauge tricolore -->
                            <div class="row g-3">
                                <?php foreach ($availMetrics as $m):
                                    $g      = $cwvGauge[$m];
                                    $curr   = $summaries[$m][0]['curr'] ?? null;
                                    $goodW  = round($g['good'] / $g['max'] * 100);
                                    $niW    = round(($g['poor'] - $g['good']) / $g['max'] * 100);
                                    $poorW  = 100 - $goodW - $niW;
                                    $p75Pos = $curr ? min(round($curr['p75'] / $g['max'] * 100), 97) : null;
                                    $color  = $curr ? ($kpiColor)($m, $curr['p75']) : '#6b7280';
                                    $label  = $curr ? ($kpiLabel)($m, $curr['p75']) : '–';
                                    $valFmt = $curr ? ($metricMeta[$m]['p75fmt'])($curr['p75']) : '–';
                                ?>
                                <div class="col-6 col-lg-3">
                                    <div class="cwv-kpi-card" style="border-left-color: <?= $color ?>" onclick="activateMetricTab('<?= $m ?>')">
                                        <div class="cwv-kpi-name"><?= strtoupper($m) ?></div>
                                        <div class="cwv-kpi-value" style="color:<?= $color ?>"><?= $valFmt ?></div>
                                        <div class="cwv-kpi-label" style="color:<?= $color ?>">● <?= $label ?></div>
                                        <?php if ($curr && $p75Pos !== null): ?>
                                        <div class="cwv-gauge">
                                            <div class="cwv-gauge-bar">
                                                <div style="width:<?= $goodW ?>%;background:var(--cwv-good)"></div>
                                                <div style="width:<?= $niW ?>%;background:var(--cwv-ni)"></div>
                                                <div style="width:<?= $poorW ?>%;background:var(--cwv-poor)"></div>
                                            </div>
                                            <div class="cwv-gauge-marker" style="left:<?= $p75Pos ?>%"></div>
                                        </div>
                                        <div class="cwv-gauge-labels">
                                            <?php foreach ($g['labels'] as $lbl): ?><span><?= $lbl ?></span><?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php else: ?>
                            <!-- N URLs : sélecteur (pills ≤ 3 / dropdown > 3) + 4 cartes KPI dynamiques -->

                            <select class="form-select form-select-sm w-auto mb-3"
                                    onchange="switchKpiUrl(parseInt(this.value))">
                                <?php foreach ($kpiData as $i => $ud): ?>
                                <option value="<?= $i ?>"><?= htmlspecialchars($ud['label']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- 4 cartes KPI (première URL au chargement, mises à jour par JS) -->
                            <div class="row g-3">
                                <?php foreach ($availMetrics as $m):
                                    $g    = $cwvGauge[$m];
                                    $d    = $kpiData[0]['metrics'][$m] ?? ['valFmt' => '–', 'color' => '#6b7280', 'label' => '', 'pos' => 0];
                                    $goodW = round($g['good'] / $g['max'] * 100);
                                    $niW   = round(($g['poor'] - $g['good']) / $g['max'] * 100);
                                    $poorW = 100 - $goodW - $niW;
                                ?>
                                <div class="col-6 col-lg-3">
                                    <div class="cwv-kpi-card" style="border-left-color: <?= $d['color'] ?>" onclick="activateMetricTab('<?= $m ?>')">
                                        <div class="cwv-kpi-name"><?= strtoupper($m) ?></div>
                                        <div class="cwv-kpi-value" id="kpi-val-<?= $m ?>" style="color:<?= $d['color'] ?>"><?= $d['valFmt'] ?></div>
                                        <div class="cwv-kpi-label" id="kpi-lbl-<?= $m ?>" style="color:<?= $d['color'] ?>">● <?= $d['label'] ?></div>
                                        <div class="cwv-gauge">
                                            <div class="cwv-gauge-bar">
                                                <div style="width:<?= $goodW ?>%;background:var(--cwv-good)"></div>
                                                <div style="width:<?= $niW ?>%;background:var(--cwv-ni)"></div>
                                                <div style="width:<?= $poorW ?>%;background:var(--cwv-poor)"></div>
                                            </div>
                                            <div class="cwv-gauge-marker" id="kpi-marker-<?= $m ?>" style="left:<?= $d['pos'] ?>%"></div>
                                        </div>
                                        <div class="cwv-gauge-labels">
                                            <?php foreach ($g['labels'] as $lbl): ?><span><?= $lbl ?></span><?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ── Slider fenêtre temporelle ─────────────────────────────── -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <div class="graph-main-title">Fenêtre temporelle</div>
                            <div class="graph-main-sub" id="rangeGlobalLabel">Plage affichée : –</div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex gap-2 mb-3">
                                <button class="btn btn-sm btn-outline-secondary period-btn" data-months="3">3 mois</button>
                                <button class="btn btn-sm btn-outline-secondary period-btn" data-months="6">6 mois</button>
                                <button class="btn btn-sm btn-outline-secondary period-btn active" data-months="0">Tout</button>
                            </div>
                            <div class="range-wrapper" style="height: 2rem;">
                                <div class="range-track" id="rangeTrack"></div>
                                <input type="range" id="rangeStart" min="0" value="0">
                                <input type="range" id="rangeEnd" min="0" value="0">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="results-main">
                    <div class="card shadow-sm mb-4" id="metricsCard">
                        <div class="card-header pb-0">
                            <div class="graph-main-title">Core Web Vitals <span class="graph-main-sub" id="cwvSubtitle"></span></div>
                            <ul class="nav nav-tabs border-0" id="metricTabs" role="tablist">
                                <?php foreach ($availMetrics as $i => $m): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                            id="tab-btn-<?= $m ?>"
                                            data-bs-toggle="tab"
                                            data-bs-target="#tab-<?= $m ?>"
                                            type="button" role="tab">
                                        <?= strtoupper($m) ?>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="metricTabContent">
                                <?php foreach ($availMetrics as $i => $m):
                                    $meta = $metricMeta[$m];
                                ?>
                                <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="tab-<?= $m ?>" role="tabpanel">

                                    <!-- Toggles mode + catégories -->
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="btn-group btn-group-sm mode-toggle" data-metric="<?= $m ?>" role="group">
                                            <button type="button" class="btn btn-outline-secondary active" data-mode="p75">P75</button>
                                            <button type="button" class="btn btn-outline-secondary" data-mode="distribution">Distribution</button>
                                        </div>
                                        <div class="btn-group btn-group-sm metric-toggle" data-metric="<?= $m ?>" role="group" style="display:none">
                                            <button type="button" class="btn btn-outline-success active" data-view="good">Bon</button>
                                            <button type="button" class="btn btn-outline-warning" data-view="ni">À améliorer</button>
                                            <button type="button" class="btn btn-outline-danger" data-view="poor">Mauvais</button>
                                        </div>
                                    </div>

                                    <!-- Graphique -->
                                    <canvas id="<?= $m ?>Chart" class="metric-chart"></canvas>

                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-secondary"><?= $meta['goal'] ?></small>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="exportChart('<?= $m ?>')">Exporter PNG</button>
                                    </div>

                                    <!-- Tableau de synthèse -->
                                    <?php if (!empty($summaries[$m])): ?>
                                    <div class="mt-3 summary-table-wrapper">
                                        <div class="metric-subtitle fw-semibold mb-2">
                                            Synthèse <?= strtoupper($m) ?> – dernière période CrUX (28 jours glissants)
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0 summary-table">
                                                <thead><tr>
                                                    <th>Entrée</th>
                                                    <th>Date fin période</th>
                                                    <th>P75</th>
                                                    <th>Good</th>
                                                    <th>À améliorer</th>
                                                    <th>Mauvais</th>
                                                </tr></thead>
                                                <tbody>
                                                <?php foreach ($summaries[$m] as $s):
                                                    $curr     = $s['curr'];
                                                    $prev     = $s['prev'];
                                                    $cwvAttrs = $curr ? 'data-cwv-metric="'.$m.'" data-cwv-p75="'.$curr['p75'].'" data-cwv-good="'.round($curr['good'],1).'" data-cwv-ni="'.round($curr['ni'],1).'" data-cwv-poor="'.round($curr['poor'],1).'"' : '';
                                                ?>
                                                <tr>
                                                    <td class="small"><?= htmlspecialchars($s['label']) ?></td>
                                                    <td class="small"><?= $curr ? htmlspecialchars($curr['date']) : '–' ?></td>
                                                    <td class="small <?= $curr ? p75_color_class($m, $curr['p75']) : '' ?>" <?= $cwvAttrs ?>><?php if ($curr): ?><span class="p75-dot <?= p75_color_class($m, $curr['p75']) ?>"></span><?php endif; ?><?= $curr ? ($meta['p75fmt'])($curr['p75']) : '–' ?><?= trend_badge($curr['p75'] ?? null, $prev['p75'] ?? null, false, $meta['trendfmt']) ?></td>
                                                    <td class="small"><?= $curr ? $fmtPct($curr['good']) : '–' ?><?= trend_badge($curr['good'] ?? null, $prev['good'] ?? null, true) ?></td>
                                                    <td class="small"><?= $curr ? $fmtPct($curr['ni'])   : '–' ?><?= trend_badge($curr['ni']   ?? null, $prev['ni']   ?? null, false) ?></td>
                                                    <td class="small"><?= $curr ? $fmtPct($curr['poor']) : '–' ?><?= trend_badge($curr['poor'] ?? null, $prev['poor'] ?? null, false) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ── Bloc "Types de navigation" ────────────────────────────── -->
                    <?php if (!empty($navTypes)): ?>
                    <div class="card shadow-sm mb-4" id="navTypesCard">
                        <div class="card-header">
                            <div class="graph-main-title">Types de navigation <span class="graph-main-sub" id="navSubtitle"></span></div>
                        </div>
                        <div class="card-body">
                            <?php if (count($navTypes) > 1): ?>
                                <?php if (count($navTypes) <= 3): ?>
                                <div class="d-flex gap-2 flex-wrap mb-3">
                                    <?php foreach ($navTypes as $i => $nt): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary nav-url-btn <?= $i === 0 ? 'active' : '' ?>"
                                            onclick="switchNavUrl(<?= $i ?>)">
                                        <?= htmlspecialchars($nt['label']) ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <select class="form-select form-select-sm w-auto mb-3"
                                        onchange="switchNavUrl(parseInt(this.value))">
                                    <?php foreach ($navTypes as $i => $nt): ?>
                                    <option value="<?= $i ?>"><?= htmlspecialchars($nt['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            <?php endif; ?>
                            <canvas id="navTypesChart" class="metric-chart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
            </div>

            <div id="results-bulk" style="<?= $tab !== 'bulk' ? 'display:none' : '' ?>">
            <?php if ($tab === 'bulk'): ?>
            <!-- ═══ MODE BULK ═══════════════════════════════════════════════════ -->

            <?php if (!empty($apiLog) || $sitemapDiscovered !== null || !empty($sitemapError)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="graph-main-title">Log API</div>
                        <div class="graph-main-sub">Statut des appels CrUX par URL</div>
                    </div>
                    <button class="btn btn-sm btn-link text-secondary p-0 config-collapse-toggle"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#apiLogBody"
                            aria-expanded="false">
                        <span class="collapse-icon">▼</span>
                    </button>
                </div>
                <?php if (!empty($apiLog)): ?>
                <div class="collapse" id="apiLogBody">
                    <div class="card-body pb-0">
                        <textarea readonly class="form-control form-control-sm bg-dark text-light border-secondary bulk-api-log" rows="8"><?= htmlspecialchars(implode("\n", $apiLog)) ?></textarea>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($sitemapError)): ?>
                <div class="card-body pt-2">
                    <div class="alert alert-danger border-danger-subtle bg-danger-subtle text-danger-emphasis mb-0">
                        <strong>Erreur <?= $bulkSource === 'wayback' ? 'Wayback Machine' : 'sitemap' ?> :</strong> <?php echo htmlspecialchars($sitemapError); ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($sitemapDiscovered !== null): ?>
                <?php
                    $cruxCount = count(array_filter($results, fn($r) => $r['result']['status'] === 'ok'));
                ?>
                <div class="card-body pt-2">
                    <div class="alert alert-info border-info-subtle bg-info-subtle text-info-emphasis mb-0">
                        <strong><?= $bulkSource === 'wayback' ? 'Wayback Machine' : 'Sitemap' ?> :</strong>
                        <?= $sitemapDiscovered ?> URL<?= $sitemapDiscovered > 1 ? 's' : '' ?> <?= $bulkSource === 'wayback' ? 'via Wayback Machine' : 'dans le sitemap' ?><?php
                        if ($sitemapSampled !== null): ?>, <?= $sitemapSampled ?> échantillonnée<?= $sitemapSampled > 1 ? 's' : '' ?><?php endif;
                        ?>, <?= $cruxCount ?> avec des données CrUX<?php
                        if ($sitemapReplaced !== null && $sitemapReplaced > 0): ?> (<?= $sitemapReplaced ?> remplacée<?= $sitemapReplaced > 1 ? 's' : '' ?>)<?php endif; ?>.
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php
                $originAvailMetrics = !empty($originSummary)
                    ? array_keys(array_filter($originSummary, fn($v) => $v['p75'] !== null))
                    : [];
                $hasBulkSidebar = !empty($originAvailMetrics);
            ?>

            <?php if ($hasBulkSidebar): ?>
            <div class="results-grid">
                <div class="results-sidebar">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <div class="graph-main-title">Performances actuelles</div>
                            <div class="graph-main-sub">Origine <?= htmlspecialchars($bulkDomain) ?> · 28 jours glissants</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($originAvailMetrics as $m):
                                    $g      = $cwvGauge[$m];
                                    $os     = $originSummary[$m];
                                    $goodW  = round($g['good'] / $g['max'] * 100);
                                    $niW    = round(($g['poor'] - $g['good']) / $g['max'] * 100);
                                    $poorW  = 100 - $goodW - $niW;
                                    $p75Pos = $os['p75'] !== null ? min(round($os['p75'] / $g['max'] * 100), 97) : null;
                                    $color  = $os['p75'] !== null ? ($kpiColor)($m, $os['p75']) : '#6b7280';
                                    $label  = $os['p75'] !== null ? ($kpiLabel)($m, $os['p75']) : '–';
                                    $valFmt = $os['p75'] !== null ? ($metricMeta[$m]['p75fmt'])($os['p75']) : '–';
                                ?>
                                <div class="col-6 col-lg-3">
                                    <div class="cwv-kpi-card" style="border-left-color: <?= $color ?>">
                                        <div class="cwv-kpi-name"><?= strtoupper($m) ?></div>
                                        <div class="cwv-kpi-value" style="color:<?= $color ?>"><?= $valFmt ?></div>
                                        <div class="cwv-kpi-label" style="color:<?= $color ?>">● <?= $label ?></div>
                                        <?php if ($p75Pos !== null): ?>
                                        <div class="cwv-gauge">
                                            <div class="cwv-gauge-bar">
                                                <div style="width:<?= $goodW ?>%;background:var(--cwv-good)"></div>
                                                <div style="width:<?= $niW ?>%;background:var(--cwv-ni)"></div>
                                                <div style="width:<?= $poorW ?>%;background:var(--cwv-poor)"></div>
                                            </div>
                                            <div class="cwv-gauge-marker" style="left:<?= $p75Pos ?>%"></div>
                                        </div>
                                        <div class="cwv-gauge-labels">
                                            <?php foreach ($g['labels'] as $lbl): ?><span><?= $lbl ?></span><?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="results-main">
            <?php endif; ?>

            <?php if (!empty($availMetrics)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header pb-0">
                    <div class="graph-main-title">Core Web Vitals</div>
                    <ul class="nav nav-tabs border-0" id="bulkMetricTabs" role="tablist">
                        <?php foreach ($availMetrics as $i => $m): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                    data-bs-toggle="tab"
                                    data-bs-target="#bulk-tab-<?= $m ?>"
                                    type="button" role="tab">
                                <?= strtoupper($m) ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <?php foreach ($availMetrics as $i => $m):
                            $meta = $metricMeta[$m];
                            $thr  = $thresholdLabels[$m];
                        ?>
                        <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="bulk-tab-<?= $m ?>" role="tabpanel">
                            <?php if (!empty($summaries[$m])): ?>
                            <div class="bulk-page-info mt-2 mb-1" id="page-info-bulk-table-<?= $m ?>"></div>
                            <div class="summary-table-wrapper">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 summary-table" id="bulk-table-<?= $m ?>">
                                        <thead><tr>
                                            <th class="sortable-th" data-sort-col="0" data-sort-type="string">URL</th>
                                            <th class="sortable-th" data-sort-col="1" data-sort-type="number">P75</th>
                                            <th class="sortable-th" data-sort-col="2" data-sort-type="number" style="color:var(--cwv-good)">Good<br><small class="fw-normal text-secondary"><?= $thr['good'] ?></small></th>
                                            <th class="sortable-th" data-sort-col="3" data-sort-type="number" style="color:var(--cwv-ni)">À améliorer<br><small class="fw-normal text-secondary"><?= $thr['ni'] ?></small></th>
                                            <th class="sortable-th" data-sort-col="4" data-sort-type="number" style="color:var(--cwv-poor)">Mauvais<br><small class="fw-normal text-secondary"><?= $thr['poor'] ?></small></th>
                                            <th class="sortable-th" data-sort-col="5" data-sort-type="number">Tendance<br>P75</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php foreach ($summaries[$m] as $s):
                                            $curr = $s['curr'];
                                            $prev = $s['prev'];
                                        ?>
                                        <tr>
                                            <?php $bulkDisplayLabel = str_starts_with($s['label'], 'http') ? (parse_url($s['label'], PHP_URL_PATH) ?: '/') : $s['label']; ?>
                                            <td class="small bulk-url-cell" data-sort-value="<?= htmlspecialchars($bulkDisplayLabel) ?>" title="<?= htmlspecialchars($s['label']) ?>"><?= htmlspecialchars($bulkDisplayLabel) ?></td>
                                            <td class="small" data-sort-value="<?= $curr['p75'] ?? '' ?>"><?php if ($curr): ?><span class="p75-dot <?= p75_color_class($m, $curr['p75']) ?>"></span><?= ($meta['p75fmt'])($curr['p75']) ?><?php else: ?>–<?php endif; ?></td>
                                            <td class="small" data-sort-value="<?= $curr ? round($curr['good'], 2) : -1 ?>"><?= $curr ? $fmtPct($curr['good']) : '–' ?></td>
                                            <td class="small" data-sort-value="<?= $curr ? round($curr['ni'], 2) : -1 ?>"><?= $curr ? $fmtPct($curr['ni'])   : '–' ?></td>
                                            <td class="small" data-sort-value="<?= $curr ? round($curr['poor'], 2) : -1 ?>"><?= $curr ? $fmtPct($curr['poor']) : '–' ?></td>
                                            <td class="small" data-sort-value="<?= ($curr['p75'] ?? 0) - ($prev['p75'] ?? 0) ?>"><?= trend_badge($curr['p75'] ?? null, $prev['p75'] ?? null, false, $meta['trendfmt']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <nav class="bulk-pagination mt-2" id="pagination-bulk-table-<?= $m ?>">
                                <ul class="pagination pagination-sm justify-content-center mb-0"></ul>
                            </nav>
                            <?php else: ?>
                                <p class="text-secondary small mb-0">Aucune donnée disponible pour <?= strtoupper($m) ?>.</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
            <?php endif; ?>

            <?php if ($hasBulkSidebar): ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
            </div>
</div>

<!-- On expose les séries PHP au JS global -->
<script>
    window.cruxSeries        = <?php echo $jsSeries; ?>;
    window.cruxSeriesP75     = <?php echo $jsSeriesP75; ?>;
    window.cruxHistogramBins = <?php echo $jsHistogramBins; ?>;
    window.cruxKpiData       = <?php echo json_encode($kpiData ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.cruxNavTypes      = <?php echo $jsNavTypes; ?>;
    window.cruxThresholds    = <?php echo json_encode(CWV_THRESHOLDS, JSON_UNESCAPED_SLASHES); ?>;
</script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
></script>

<script src="app.js"></script>

</body>
</html>
