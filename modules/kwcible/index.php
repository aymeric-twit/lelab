<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';

$view = handle_seo_form();

$hasPost         = $view['hasPost'];
$url             = $view['url'];
$error           = $view['error'];
$parsed          = $view['parsed'];
$keywords        = $view['keywords'];
$diagnostic      = $view['diagnostic'];
$recommendations = $view['recommendations'];
$fetchInfo       = $view['fetchInfo'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KWCible — Analyse sémantique SEO</title>

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
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            KWCible
            <span class="d-block d-sm-inline ms-sm-2">Analyse sémantique SEO</span>
        </span>
    </div>
</nav>

<div class="container-lg pb-5">

    <!-- ─── Config Card ─────────────────────────────────────────────────── -->
    <div class="card mb-4" id="configCard">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-8">
                        <label for="urlInput" class="form-label fw-semibold">URL à analyser</label>
                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control"
                                id="urlInput"
                                name="url"
                                placeholder="https://example.com/page-a-analyser"
                                value="<?= htmlspecialchars($url) ?>"
                                required
                            >
                            <button type="submit" class="btn btn-primary px-4">
                                Analyser
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="config-help-panel">
                            <div class="help-title">Comment ça marche ?</div>
                            <ul>
                                <li>Entrez l'URL d'une page web publique</li>
                                <li>L'outil identifie la <strong>requête clé principale</strong></li>
                                <li>Diagnostic SEO complet avec recommandations</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($hasPost && $error): ?>
        <!-- ─── Erreur ──────────────────────────────────────────────────── -->
        <div class="alert alert-warning border-warning-subtle bg-warning-subtle text-dark">
            <strong>Erreur :</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($hasPost && !$error): ?>

        <!-- ─── Structure de la page ────────────────────────────────────── -->
        <div class="card mb-4">
            <div class="card-header py-3">
                <h2 class="h6 mb-0 fw-bold">Structure de la page</h2>
                <small class="text-muted"><?= htmlspecialchars($fetchInfo['finalUrl'] ?? $url) ?></small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:180px">Élément</th>
                                <th>Contenu</th>
                                <th style="width:100px" class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Title -->
                            <tr>
                                <td class="fw-semibold">Title</td>
                                <td class="truncate-text"><?= htmlspecialchars($parsed['title'] ?: '(aucun)') ?></td>
                                <td class="text-center">
                                    <?php
                                    $tLen = mb_strlen($parsed['title']);
                                    if ($tLen === 0) echo '<span class="badge-error">Absent</span>';
                                    elseif ($tLen <= 60) echo '<span class="badge-ok">' . $tLen . ' car.</span>';
                                    else echo '<span class="badge-warn">' . $tLen . ' car.</span>';
                                    ?>
                                </td>
                            </tr>
                            <!-- Meta Description -->
                            <tr>
                                <td class="fw-semibold">Meta Description</td>
                                <td class="truncate-text"><?= htmlspecialchars($parsed['meta_description'] ?: '(aucune)') ?></td>
                                <td class="text-center">
                                    <?php
                                    $mLen = mb_strlen($parsed['meta_description']);
                                    if ($mLen === 0) echo '<span class="badge-error">Absente</span>';
                                    elseif ($mLen <= 160) echo '<span class="badge-ok">' . $mLen . ' car.</span>';
                                    else echo '<span class="badge-warn">' . $mLen . ' car.</span>';
                                    ?>
                                </td>
                            </tr>
                            <!-- H1 -->
                            <tr>
                                <td class="fw-semibold">H1</td>
                                <td>
                                    <?php if (empty($parsed['h1'])): ?>
                                        <em class="text-muted">(aucun)</em>
                                    <?php else: ?>
                                        <?php foreach ($parsed['h1'] as $h): ?>
                                            <div><?= htmlspecialchars($h) ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $h1c = count($parsed['h1']);
                                    if ($h1c === 1) echo '<span class="badge-ok">1 H1</span>';
                                    elseif ($h1c === 0) echo '<span class="badge-error">0 H1</span>';
                                    else echo '<span class="badge-warn">' . $h1c . ' H1</span>';
                                    ?>
                                </td>
                            </tr>
                            <!-- H2 -->
                            <tr>
                                <td class="fw-semibold">H2</td>
                                <td>
                                    <?php if (empty($parsed['h2'])): ?>
                                        <em class="text-muted">(aucun)</em>
                                    <?php else: ?>
                                        <?php foreach (array_slice($parsed['h2'], 0, 8) as $h): ?>
                                            <span class="kw-badge"><?= htmlspecialchars($h) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($parsed['h2']) > 8): ?>
                                            <span class="text-muted ms-1">+<?= count($parsed['h2']) - 8 ?> autres</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge-ok"><?= count($parsed['h2']) ?></span>
                                </td>
                            </tr>
                            <!-- H3 -->
                            <?php if (!empty($parsed['h3'])): ?>
                            <tr>
                                <td class="fw-semibold">H3</td>
                                <td>
                                    <?php foreach (array_slice($parsed['h3'], 0, 6) as $h): ?>
                                        <span class="kw-badge"><?= htmlspecialchars($h) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($parsed['h3']) > 6): ?>
                                        <span class="text-muted ms-1">+<?= count($parsed['h3']) - 6 ?> autres</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge-ok"><?= count($parsed['h3']) ?></span>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <!-- Canonical -->
                            <tr>
                                <td class="fw-semibold">Canonical</td>
                                <td class="truncate-text"><?= htmlspecialchars($parsed['canonical'] ?: '(aucun)') ?></td>
                                <td class="text-center">
                                    <?= $parsed['canonical'] ? '<span class="badge-ok">OK</span>' : '<span class="badge-warn">Absent</span>' ?>
                                </td>
                            </tr>
                            <!-- Word Count -->
                            <tr>
                                <td class="fw-semibold">Nombre de mots</td>
                                <td><?= number_format($parsed['word_count'], 0, ',', ' ') ?> mots</td>
                                <td class="text-center">
                                    <?php
                                    if ($parsed['word_count'] >= 300) echo '<span class="badge-ok">OK</span>';
                                    elseif ($parsed['word_count'] >= 100) echo '<span class="badge-warn">Court</span>';
                                    else echo '<span class="badge-error">Très court</span>';
                                    ?>
                                </td>
                            </tr>
                            <!-- URL Parts -->
                            <?php if (!empty($parsed['url_parts'])): ?>
                            <tr>
                                <td class="fw-semibold">Segments URL</td>
                                <td>
                                    <?php foreach ($parsed['url_parts'] as $seg): ?>
                                        <span class="kw-badge"><?= htmlspecialchars($seg) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge-ok"><?= count($parsed['url_parts']) ?></span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ─── Requête clé principale ──────────────────────────────────── -->
        <div class="card mb-4">
            <div class="card-header py-3">
                <h2 class="h6 mb-0 fw-bold">Requête clé principale</h2>
            </div>
            <div class="card-body">
                <div class="kw-primary-card mb-3">
                    <div class="kw-label">Mot-clé principal identifié</div>
                    <div class="kw-value"><?= htmlspecialchars($keywords['primary_keyword']) ?></div>
                    <div>
                        <span class="kw-intent">
                            <?= htmlspecialchars($keywords['intent']['label']) ?>
                        </span>
                        <span class="kw-competition" style="background: <?= htmlspecialchars($keywords['competition']['color']) ?>20; color: <?= htmlspecialchars($keywords['competition']['color']) ?>">
                            Concurrence : <?= htmlspecialchars($keywords['competition']['label']) ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($keywords['variants'])): ?>
                    <div class="mt-3">
                        <div class="fw-semibold mb-2" style="font-size: 0.85rem; color: #64748b;">Variantes secondaires</div>
                        <div>
                            <?php foreach ($keywords['variants'] as $v): ?>
                                <span class="kw-badge">
                                    <?= htmlspecialchars($v['term']) ?>
                                    <span class="kw-badge-score"><?= round($v['score']) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ─── Optimisation sémantique ───────────────────────────────────── -->
        <?php if (!empty($keywords['term_details'])): ?>
        <div class="card mb-4">
            <div class="card-header py-3">
                <h2 class="h6 mb-0 fw-bold">Optimisation sémantique</h2>
            </div>
            <div class="card-body">
                <!-- Jauges SOSEO / DSEO côte à côte -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <?php
                        $soseo = $keywords['soseo'];
                        if ($soseo >= 60) $soseoColor = 'var(--seo-good)';
                        elseif ($soseo >= 30) $soseoColor = 'var(--seo-warn)';
                        else $soseoColor = 'var(--seo-bad)';
                        $circumSoseo = 2 * M_PI * 34;
                        $offsetSoseo = $circumSoseo - ($circumSoseo * $soseo / 100);
                        ?>
                        <div class="seo-score-gauge">
                            <div class="seo-score-circle">
                                <svg viewBox="0 0 80 80">
                                    <circle class="score-bg" cx="40" cy="40" r="34"/>
                                    <circle class="score-fill" cx="40" cy="40" r="34"
                                            stroke="<?= $soseoColor ?>"
                                            stroke-dasharray="<?= round($circumSoseo, 1) ?>"
                                            stroke-dashoffset="<?= round($offsetSoseo, 1) ?>"/>
                                </svg>
                                <div class="seo-score-number" style="color: <?= $soseoColor ?>"><?= round($soseo) ?>%</div>
                            </div>
                            <div class="seo-score-label">
                                <strong>SOSEO — Optimisation</strong>
                                Couverture sémantique des termes importants dans les zones stratégiques.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php
                        $dseo = $keywords['dseo'];
                        if ($dseo < 20) $dseoColor = 'var(--seo-good)';
                        elseif ($dseo <= 50) $dseoColor = 'var(--seo-warn)';
                        else $dseoColor = 'var(--seo-bad)';
                        $circumDseo = 2 * M_PI * 34;
                        $offsetDseo = $circumDseo - ($circumDseo * $dseo / 100);
                        ?>
                        <div class="seo-score-gauge">
                            <div class="seo-score-circle">
                                <svg viewBox="0 0 80 80">
                                    <circle class="score-bg" cx="40" cy="40" r="34"/>
                                    <circle class="score-fill" cx="40" cy="40" r="34"
                                            stroke="<?= $dseoColor ?>"
                                            stroke-dasharray="<?= round($circumDseo, 1) ?>"
                                            stroke-dashoffset="<?= round($offsetDseo, 1) ?>"/>
                                </svg>
                                <div class="seo-score-number" style="color: <?= $dseoColor ?>"><?= round($dseo) ?>%</div>
                            </div>
                            <div class="seo-score-label">
                                <strong>DSEO — Sur-optimisation</strong>
                                <?php
                                if ($dseo < 20) echo 'Aucun risque de sur-optimisation détecté.';
                                elseif ($dseo <= 50) echo 'Attention, certains termes sont trop répétés.';
                                else echo 'Risque élevé de keyword stuffing.';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau des termes importants -->
                <div class="table-responsive">
                    <table class="table table-sm term-details-table mb-0">
                        <thead>
                            <tr>
                                <th>Terme</th>
                                <th class="text-center" style="width:100px">Score</th>
                                <th style="width:180px">Zones</th>
                                <th class="text-center" style="width:80px">Densité</th>
                                <th class="text-center" style="width:100px">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $maxTermScore = $keywords['term_details'][0]['score'] ?? 1;
                            foreach ($keywords['term_details'] as $td):
                                $barWidth = $maxTermScore > 0 ? round($td['score'] / $maxTermScore * 100) : 0;
                                if ($td['status'] === 'optimal') {
                                    $statusClass = 'badge-ok';
                                    $statusLabel = 'Optimal';
                                } elseif ($td['status'] === 'sur-optimisé') {
                                    $statusClass = 'badge-error';
                                    $statusLabel = 'Sur-optimisé';
                                } else {
                                    $statusClass = 'badge-warn';
                                    $statusLabel = 'Sous-optimisé';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="font-size:0.85rem"><?= htmlspecialchars($td['term']) ?></div>
                                    <div class="term-bar-track">
                                        <div class="term-bar-fill <?= $td['status'] === 'sur-optimisé' ? 'over' : ($td['status'] === 'sous-optimisé' ? 'under' : '') ?>" style="width:<?= $barWidth ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-center"><?= $td['score'] ?></td>
                                <td>
                                    <?php foreach ($td['zones'] as $z): ?>
                                        <span class="zone-tag zone-<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-center"><?= $td['density'] ?>%</td>
                                <td class="text-center"><span class="<?= $statusClass ?>"><?= $statusLabel ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ─── Diagnostic SEO ──────────────────────────────────────────── -->
        <div class="card mb-4">
            <div class="card-header py-3">
                <h2 class="h6 mb-0 fw-bold">Diagnostic SEO</h2>
            </div>
            <div class="card-body">
                <!-- Score gauge -->
                <?php
                $pct = $diagnostic['percentage'];
                if ($pct >= 70) $scoreColor = 'var(--seo-good)';
                elseif ($pct >= 40) $scoreColor = 'var(--seo-warn)';
                else $scoreColor = 'var(--seo-bad)';
                $circumference = 2 * M_PI * 34;
                $offset = $circumference - ($circumference * $pct / 100);
                ?>
                <div class="seo-score-gauge">
                    <div class="seo-score-circle">
                        <svg viewBox="0 0 80 80">
                            <circle class="score-bg" cx="40" cy="40" r="34"/>
                            <circle class="score-fill" cx="40" cy="40" r="34"
                                    stroke="<?= $scoreColor ?>"
                                    stroke-dasharray="<?= round($circumference, 1) ?>"
                                    stroke-dashoffset="<?= round($offset, 1) ?>"/>
                        </svg>
                        <div class="seo-score-number" style="color: <?= $scoreColor ?>"><?= $pct ?>%</div>
                    </div>
                    <div class="seo-score-label">
                        <strong>Score SEO global</strong>
                        <?= $diagnostic['score'] ?> / <?= $diagnostic['max_score'] ?> points —
                        <?php
                        if ($pct >= 70) echo 'Bon niveau d\'optimisation.';
                        elseif ($pct >= 40) echo 'Optimisation partielle, des améliorations sont possibles.';
                        else echo 'Optimisation insuffisante, des actions correctives sont nécessaires.';
                        ?>
                    </div>
                </div>

                <!-- Checks list -->
                <?php foreach ($diagnostic['checks'] as $check): ?>
                    <div class="seo-check-row">
                        <div class="status-icon <?= htmlspecialchars($check['status']) ?>">
                            <?php
                            if ($check['status'] === 'bon') echo '&#10003;';
                            elseif ($check['status'] === 'attention') echo '!';
                            else echo '&#10005;';
                            ?>
                        </div>
                        <div class="check-content">
                            <div class="check-label"><?= htmlspecialchars($check['label']) ?></div>
                            <div class="check-message"><?= htmlspecialchars($check['message']) ?></div>
                        </div>
                        <div class="check-points"><?= $check['points'] ?> pts</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ─── Recommandations ─────────────────────────────────────────── -->
        <?php if (!empty($recommendations)): ?>
        <div class="card mb-4">
            <div class="card-header py-3">
                <h2 class="h6 mb-0 fw-bold">Recommandations</h2>
            </div>
            <div class="card-body">
                <?php foreach ($recommendations as $rec): ?>
                    <div class="rec-item">
                        <div class="rec-label"><?= htmlspecialchars($rec['label']) ?></div>
                        <div class="rec-reason"><?= htmlspecialchars($rec['reason']) ?></div>
                        <div class="rec-compare">
                            <div class="rec-current">
                                <span class="rec-tag">Actuel</span>
                                <?= htmlspecialchars($rec['current']) ?>
                            </div>
                            <div class="rec-proposed">
                                <span class="rec-tag">Proposé</span>
                                <?= htmlspecialchars($rec['proposed']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
></script>

</body>
</html>
