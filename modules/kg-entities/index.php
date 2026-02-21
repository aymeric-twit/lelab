<?php
require_once __DIR__ . '/functions.php';

$view = handle_form();

$query      = $view['query'];
$language   = $view['language'];
$audit      = $view['audit'];
$jsonld     = $view['jsonld'];
$schemaType = $view['schemaType'];
$hasPost    = $view['hasPost'];
$apiKeyOk   = $view['apiKeyOk'];
$activeTab  = $view['activeTab'];

$languages = [
    'fr' => 'Francais',
    'en' => 'English',
    'es' => 'Espanol',
    'de' => 'Deutsch',
    'it' => 'Italiano',
    'pt' => 'Portugues',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KG Entity Audit – Knowledge Graph & Schema.org</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
</head>
<body>

<nav class="navbar mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">
            KG Entity Audit
            <span class="d-block d-sm-inline ms-sm-2">Knowledge Graph & Schema.org</span>
        </span>
    </div>
</nav>

<div class="container">

    <?php if (!$apiKeyOk): ?>
    <div class="alert alert-config mb-4">
        <strong>Configuration requise</strong><br>
        Copiez <code>.env.example</code> vers <code>.env</code> et renseignez votre cle API Google Knowledge Graph.
    </div>
    <?php endif; ?>

    <!-- Formulaire de recherche -->
    <div class="card card-search mb-4">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="query" class="form-label">Nom de l'entite / marque</label>
                        <input
                            type="text"
                            class="form-control"
                            id="query"
                            name="query"
                            placeholder="Ex : Anthropic, Tour Eiffel, Elon Musk..."
                            value="<?= htmlspecialchars($query) ?>"
                            required
                        >
                    </div>
                    <div class="col-md-2">
                        <label for="language" class="form-label">Langue</label>
                        <select class="form-select" id="language" name="language">
                            <?php foreach ($languages as $code => $label): ?>
                            <option value="<?= $code ?>" <?= $language === $code ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Auditer</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($hasPost && $audit !== null): ?>

    <!-- Onglets resultats -->
    <ul class="nav nav-tabs" id="resultTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'audit' ? 'active' : '' ?>" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit-pane" type="button" role="tab" aria-controls="audit-pane" aria-selected="<?= $activeTab === 'audit' ? 'true' : 'false' ?>">
                Audit
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'schema' ? 'active' : '' ?>" id="schema-tab" data-bs-toggle="tab" data-bs-target="#schema-pane" type="button" role="tab" aria-controls="schema-pane" aria-selected="<?= $activeTab === 'schema' ? 'true' : 'false' ?>" <?= empty($audit['found']) ? 'disabled' : '' ?>>
                Schema.org
            </button>
        </li>
    </ul>

    <div class="tab-content" id="resultTabsContent">

        <!-- Onglet Audit -->
        <div class="tab-pane fade <?= $activeTab === 'audit' ? 'show active' : '' ?>" id="audit-pane" role="tabpanel" aria-labelledby="audit-tab">

            <?php if (isset($audit['error'])): ?>
            <div class="alert alert-danger mt-3">
                <?= htmlspecialchars($audit['error']) ?>
            </div>
            <?php elseif (empty($audit['found'])): ?>

            <!-- Entite non trouvee -->
            <div class="card mt-3">
                <div class="card-body text-center py-5">
                    <div class="not-found-icon mb-3">?</div>
                    <h5>Entite non trouvee</h5>
                    <p class="text-muted">Aucune entite correspondant a "<strong><?= htmlspecialchars($query) ?></strong>" n'a ete trouvee dans le Knowledge Graph de Google.</p>
                </div>
            </div>

            <!-- Recommandations meme si non trouve -->
            <?php if (!empty($audit['recommendations'])): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Recommandations SEO</h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($audit['recommendations'] as $rec): ?>
                    <div class="rec-item rec-<?= $rec['level'] ?>">
                        <div class="rec-badge"><?= $rec['icon'] ?></div>
                        <div>
                            <strong><?= htmlspecialchars($rec['title']) ?></strong><br>
                            <span class="rec-message"><?= htmlspecialchars($rec['message']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>

            <!-- Entite trouvee -->
            <div class="row mt-3 g-3">
                <!-- Infos principales -->
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center gap-3">
                            <?php if (!empty($audit['image_url'])): ?>
                            <img src="<?= htmlspecialchars($audit['image_url']) ?>" alt="" class="entity-img">
                            <?php endif; ?>
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($audit['name']) ?></h5>
                                <span class="badge badge-type"><?= htmlspecialchars($audit['type']) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th>Score de confiance</th>
                                        <td>
                                            <?php
                                            $score = $audit['result_score'];
                                            $scoreClass = 'score-high';
                                            if ($score < 100) $scoreClass = 'score-low';
                                            elseif ($score < 500) $scoreClass = 'score-mid';
                                            ?>
                                            <span class="badge <?= $scoreClass ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Le resultScore est un indicateur Google qui reflete la notoriete de l'entite dans le Knowledge Graph. Plus il est eleve, plus l'entite est reconnue. Un score > 500 est excellent, entre 100 et 500 est moyen, et < 100 est faible."><?= number_format($score, 0, ',', ' ') ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Description</th>
                                        <td><?= !empty($audit['description']) ? htmlspecialchars($audit['description']) : '<span class="text-muted">—</span>' ?></td>
                                    </tr>
                                    <?php if (!empty($audit['detailed_description'])): ?>
                                    <tr>
                                        <th>Description detaillee</th>
                                        <td><?= htmlspecialchars($audit['detailed_description']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>URL officielle</th>
                                        <td>
                                            <?php if (!empty($audit['official_url'])): ?>
                                            <a href="<?= htmlspecialchars($audit['official_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($audit['official_url']) ?></a>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($audit['detailed_url'])): ?>
                                    <tr>
                                        <th>Wikipedia</th>
                                        <td><a href="<?= htmlspecialchars($audit['detailed_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($audit['detailed_url']) ?></a></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>MID (Machine ID)</th>
                                        <td><code><?= htmlspecialchars($audit['identifiers']['mid'] ?: '—') ?></code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Score visuel + Completude -->
                <div class="col-lg-4 d-flex flex-column gap-3">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Score de confiance</h6>
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center">
                            <?php
                            $score = $audit['result_score'];
                            if ($score >= 500) {
                                $scoreLabel = 'Excellent';
                                $scoreColor = 'score-high';
                            } elseif ($score >= 100) {
                                $scoreLabel = 'Moyen';
                                $scoreColor = 'score-mid';
                            } else {
                                $scoreLabel = 'Faible';
                                $scoreColor = 'score-low';
                            }
                            ?>
                            <div class="score-circle <?= $scoreColor ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Le resultScore est un indicateur Google qui reflete la notoriete de l'entite dans le Knowledge Graph. Plus il est eleve, plus l'entite est reconnue. Un score > 500 est excellent, entre 100 et 500 est moyen, et < 100 est faible.">
                                <?= number_format($score, 0, ',', ' ') ?>
                            </div>
                            <div class="score-label mt-2 <?= $scoreColor ?>"><?= $scoreLabel ?></div>
                            <small class="text-muted mt-1">resultScore Google KG</small>
                        </div>
                    </div>

                    <?php if (!empty($audit['completeness'])): ?>
                    <?php
                    $comp = $audit['completeness'];
                    $compPct = $comp['score'];
                    if ($compPct >= 75) {
                        $compColor = 'var(--score-high)';
                    } elseif ($compPct >= 50) {
                        $compColor = 'var(--score-mid)';
                    } else {
                        $compColor = 'var(--score-low)';
                    }
                    // SVG arc: circumference = 2 * PI * 26 ≈ 163.36
                    $circumference = 163.36;
                    $dashOffset = $circumference - ($circumference * $compPct / 100);
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Completude</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="completeness-gauge">
                                    <svg class="completeness-svg" viewBox="0 0 64 64" width="64" height="64">
                                        <circle class="completeness-bg" cx="32" cy="32" r="26" fill="none" stroke="#e2e8f0" stroke-width="6"/>
                                        <circle class="completeness-fill" cx="32" cy="32" r="26" fill="none" stroke="<?= $compColor ?>" stroke-width="6" stroke-linecap="round" stroke-dasharray="<?= $circumference ?>" stroke-dashoffset="<?= $dashOffset ?>" transform="rotate(-90 32 32)"/>
                                    </svg>
                                    <span class="completeness-text" style="color: <?= $compColor ?>"><?= $compPct ?>%</span>
                                </div>
                                <div>
                                    <strong><?= $comp['present'] ?>/<?= $comp['total'] ?></strong> criteres<br>
                                    <small class="text-muted">renseignes dans le KG</small>
                                </div>
                            </div>
                            <ul class="completeness-checklist">
                                <?php foreach ($comp['checks'] as $check): ?>
                                <li class="<?= $check['ok'] ? 'check-ok' : 'check-missing' ?>">
                                    <?= $check['ok'] ? '&#10003;' : '&#10007;' ?> <?= htmlspecialchars($check['label']) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Autres resultats -->
            <?php if (count($audit['all_results'] ?? []) > 1): ?>
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Autres entites trouvees</h6>
                    <button class="btn btn-sm btn-outline-light" onclick="exportCsv()">Exporter CSV</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0" id="entities-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="index"># <span class="sort-icon"></span></th>
                                <th class="sortable" data-sort="name">Nom <span class="sort-icon"></span></th>
                                <th class="sortable" data-sort="type">Type <span class="sort-icon"></span></th>
                                <th class="sortable" data-sort="score">Score <span class="sort-icon"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit['all_results'] as $i => $r): ?>
                            <tr class="<?= $i === 0 ? 'row-active' : '' ?>" data-index="<?= $i ?>" data-name="<?= htmlspecialchars($r['name']) ?>" data-type="<?= htmlspecialchars($r['type']) ?>" data-score="<?= $r['score'] ?>">
                                <td><?= $i + 1 ?></td>
                                <td><a href="#" class="entity-link" data-entity-name="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars($r['name']) ?></a></td>
                                <td><span class="badge badge-type-sm"><?= htmlspecialchars($r['type']) ?></span></td>
                                <td><?= number_format($r['score'], 0, ',', ' ') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="pagination-mini" id="entities-pagination"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recommandations -->
            <?php if (!empty($audit['recommendations'])): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Recommandations SEO</h6>
                </div>
                <div class="card-body p-0">
                    <?php
                    $shownTypeHeader = false;
                    foreach ($audit['recommendations'] as $rec):
                        if (!$shownTypeHeader && ($rec['category'] ?? '') === 'type_specific'):
                            $shownTypeHeader = true;
                    ?>
                    <div class="rec-divider">Recommandations specifiques <?= htmlspecialchars($audit['type']) ?></div>
                    <?php endif; ?>
                    <div class="rec-item rec-<?= $rec['level'] ?>">
                        <div class="rec-badge"><?= $rec['icon'] ?></div>
                        <div>
                            <strong><?= htmlspecialchars($rec['title']) ?></strong><br>
                            <span class="rec-message"><?= htmlspecialchars($rec['message']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; /* found */ ?>
        </div>

        <!-- Onglet Schema.org -->
        <div class="tab-pane fade <?= $activeTab === 'schema' ? 'show active' : '' ?>" id="schema-pane" role="tabpanel" aria-labelledby="schema-tab">
            <?php if (!empty($audit['found'])): ?>
            <div class="card mt-3">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="query" value="<?= htmlspecialchars($query) ?>">
                        <input type="hidden" name="language" value="<?= htmlspecialchars($language) ?>">
                        <input type="hidden" name="active_tab" value="schema">
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label for="schema_type" class="form-label">Type schema.org</label>
                                <select class="form-select" id="schema_type" name="schema_type">
                                    <?php foreach (SCHEMA_TYPES as $type): ?>
                                    <option value="<?= $type ?>" <?= $schemaType === $type ? 'selected' : '' ?>><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Generer</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($jsonld)): ?>
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">JSON-LD <span class="badge badge-type-sm"><?= htmlspecialchars($schemaType) ?></span></h6>
                    <button class="btn btn-sm btn-outline-light" id="btn-copy" onclick="copyJsonLd()">
                        Copier
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Copiez ce bloc JSON-LD et ajoutez-le dans une balise <code>&lt;script type="application/ld+json"&gt;</code> sur votre site.
                    </p>
                    <div class="jsonld-block">
                        <pre><code id="jsonld-code">&lt;script type="application/ld+json"&gt;
<?= htmlspecialchars($jsonld) ?>
&lt;/script&gt;</code></pre>
                    </div>
                </div>
            </div>

            <div class="alert alert-placeholder mt-3">
                <strong>Valeurs d'exemple</strong> — Les proprietes specifiques au type <strong><?= htmlspecialchars($schemaType) ?></strong> contiennent des valeurs d'exemple. Remplacez-les par vos donnees reelles avant de deployer le balisage.
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Validation</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Testez votre balisage JSON-LD avec ces outils :</p>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="https://validator.schema.org/" target="_blank" rel="noopener" class="link-tool">Schema Markup Validator</a>
                            — Validation de la syntaxe schema.org
                        </li>
                        <li>
                            <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener" class="link-tool">Google Rich Results Test</a>
                            — Test d'eligibilite aux resultats enrichis
                        </li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <?php endif; /* hasPost */ ?>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
></script>

<script>
// Init Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
tooltipTriggerList.forEach(function (el) { new bootstrap.Tooltip(el) })

// Entity links: click to search
document.addEventListener('click', function(e) {
    var link = e.target.closest('.entity-link');
    if (!link) return;
    e.preventDefault();
    var name = link.getAttribute('data-entity-name');
    var input = document.getElementById('query');
    input.value = name;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    input.closest('form').submit();
});

// Sortable table columns + pagination
(function() {
    var table = document.getElementById('entities-table');
    if (!table) return;
    var thead = table.querySelector('thead');
    var tbody = table.querySelector('tbody');
    var paginationEl = document.getElementById('entities-pagination');
    var perPage = 5;
    var currentPage = 1;
    var currentSort = { key: null, asc: true };

    function getAllRows() {
        return [].slice.call(tbody.querySelectorAll('tr'));
    }

    function renderPage() {
        var rows = getAllRows();
        var totalPages = Math.ceil(rows.length / perPage);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        var start = (currentPage - 1) * perPage;
        var end = start + perPage;
        rows.forEach(function(row, i) {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) { paginationEl.innerHTML = ''; return; }
        var html = '<button class="pg-btn pg-prev" ' + (currentPage <= 1 ? 'disabled' : '') + '>&lsaquo;</button>';
        for (var p = 1; p <= totalPages; p++) {
            html += '<button class="pg-btn' + (p === currentPage ? ' pg-active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button class="pg-btn pg-next" ' + (currentPage >= totalPages ? 'disabled' : '') + '>&rsaquo;</button>';
        paginationEl.innerHTML = html;
    }

    paginationEl.addEventListener('click', function(e) {
        var btn = e.target.closest('.pg-btn');
        if (!btn || btn.disabled) return;
        if (btn.classList.contains('pg-prev')) {
            currentPage--;
        } else if (btn.classList.contains('pg-next')) {
            currentPage++;
        } else {
            currentPage = parseInt(btn.getAttribute('data-page'));
        }
        renderPage();
    });

    // Sort
    thead.addEventListener('click', function(e) {
        var th = e.target.closest('.sortable');
        if (!th) return;
        var key = th.getAttribute('data-sort');
        if (currentSort.key === key) {
            currentSort.asc = !currentSort.asc;
        } else {
            currentSort.key = key;
            currentSort.asc = true;
        }
        thead.querySelectorAll('.sortable').forEach(function(h) {
            h.classList.remove('sort-asc', 'sort-desc');
        });
        th.classList.add(currentSort.asc ? 'sort-asc' : 'sort-desc');
        var rows = getAllRows();
        rows.sort(function(a, b) {
            var va, vb;
            if (key === 'score' || key === 'index') {
                va = parseFloat(a.getAttribute('data-' + key));
                vb = parseFloat(b.getAttribute('data-' + key));
            } else {
                va = (a.getAttribute('data-' + key) || '').toLowerCase();
                vb = (b.getAttribute('data-' + key) || '').toLowerCase();
            }
            if (va < vb) return currentSort.asc ? -1 : 1;
            if (va > vb) return currentSort.asc ? 1 : -1;
            return 0;
        });
        rows.forEach(function(row) { tbody.appendChild(row); });
        currentPage = 1;
        renderPage();
    });

    renderPage();
})();

function exportCsv() {
    var table = document.getElementById('entities-table');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    var csv = '\uFEFF'; // BOM UTF-8 pour Excel
    csv += '"#","Nom","Type","Score"\n';
    rows.forEach(function(tr) {
        var index = tr.getAttribute('data-index');
        var name = tr.getAttribute('data-name') || '';
        var type = tr.getAttribute('data-type') || '';
        var score = tr.getAttribute('data-score') || '';
        csv += '"' + (parseInt(index) + 1) + '","' + name.replace(/"/g, '""') + '","' + type.replace(/"/g, '""') + '","' + score + '"\n';
    });
    var query = document.getElementById('query') ? document.getElementById('query').value : 'export';
    var filename = 'kg-entities-' + query.replace(/[^a-zA-Z0-9_-]/g, '_') + '.csv';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}

function copyJsonLd() {
    const code = document.getElementById('jsonld-code');
    const text = code.textContent;
    navigator.clipboard.writeText(text).then(function() {
        const btn = document.getElementById('btn-copy');
        const original = btn.textContent;
        btn.textContent = 'Copie !';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-light');
        setTimeout(function() {
            btn.textContent = original;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-light');
        }, 2000);
    });
}
</script>

</body>
</html>
