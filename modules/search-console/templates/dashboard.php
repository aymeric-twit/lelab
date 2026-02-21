<?php
$pageTitle   = 'Dashboard — Search Console';
$currentPage = 'dashboard';
ob_start();
?>

<!-- Barre de filtres -->
<form class="filters-bar" method="GET" action="<?= defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '' ?>">
    <div>
        <label for="site_id">Site</label>
        <select name="site_id" id="site_id">
            <?php foreach ($sites as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $siteId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['label'] ?: $s['site_url']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="from">Du</label>
        <input type="date" name="from" id="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div>
        <label for="to">Au</label>
        <input type="date" name="to" id="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div>
        <label for="device">Appareil</label>
        <select name="device" id="device">
            <option value="">Tous</option>
            <option value="DESKTOP" <?= ($filters['device'] ?? '') === 'DESKTOP' ? 'selected' : '' ?>>Desktop</option>
            <option value="MOBILE" <?= ($filters['device'] ?? '') === 'MOBILE' ? 'selected' : '' ?>>Mobile</option>
            <option value="TABLET" <?= ($filters['device'] ?? '') === 'TABLET' ? 'selected' : '' ?>>Tablet</option>
        </select>
    </div>
    <div>
        <label for="country">Pays</label>
        <input type="text" name="country" id="country" placeholder="ex: FRA" value="<?= htmlspecialchars($filters['country'] ?? '') ?>" style="width:80px">
    </div>
    <div>
        <label for="filter_query">Requete</label>
        <input type="text" name="filter_query" id="filter_query" placeholder="Filtrer..." value="<?= htmlspecialchars($filters['query'] ?? '') ?>">
    </div>
    <div>
        <label for="filter_page">Page</label>
        <input type="text" name="filter_page" id="filter_page" placeholder="/chemin..." value="<?= htmlspecialchars($filters['page'] ?? '') ?>">
    </div>
    <button type="submit">Filtrer</button>
</form>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Clicks</div>
        <div class="value"><?= number_format((int)($totals['clicks'] ?? 0), 0, ',', ' ') ?></div>
        <?php if ($comparison): ?>
            <?php $d = $comparison['diff']['clicks']; ?>
            <div class="diff <?= $d >= 0 ? 'positive' : 'negative' ?>">
                <?= $d >= 0 ? '+' : '' ?><?= number_format($d, 0, ',', ' ') ?> vs periode prec.
            </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="label">Impressions</div>
        <div class="value"><?= number_format((int)($totals['impressions'] ?? 0), 0, ',', ' ') ?></div>
        <?php if ($comparison): ?>
            <?php $d = $comparison['diff']['impressions']; ?>
            <div class="diff <?= $d >= 0 ? 'positive' : 'negative' ?>">
                <?= $d >= 0 ? '+' : '' ?><?= number_format($d, 0, ',', ' ') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="label">CTR moyen</div>
        <div class="value"><?= number_format(($totals['ctr'] ?? 0) * 100, 2, ',', ' ') ?>%</div>
        <?php if ($comparison): ?>
            <?php $d = $comparison['diff']['ctr'] * 100; ?>
            <div class="diff <?= $d >= 0 ? 'positive' : 'negative' ?>">
                <?= $d >= 0 ? '+' : '' ?><?= number_format($d, 2, ',', ' ') ?> pts
            </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="label">Position moyenne</div>
        <div class="value"><?= number_format($totals['position'] ?? 0, 1, ',', ' ') ?></div>
        <?php if ($comparison): ?>
            <?php $d = $comparison['diff']['position']; ?>
            <div class="diff <?= $d <= 0 ? 'positive' : 'negative' ?>">
                <?= $d >= 0 ? '+' : '' ?><?= number_format($d, 1, ',', ' ') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Graphique tendance -->
<div class="chart-container">
    <h3>Tendance quotidienne</h3>
    <canvas id="trendChart" height="100"></canvas>
</div>

<!-- Graphiques appareils + pays -->
<div class="grid-2">
    <div class="chart-container">
        <h3>Repartition par appareil</h3>
        <canvas id="deviceChart" height="200"></canvas>
    </div>
    <div class="chart-container">
        <h3>Top pays</h3>
        <canvas id="countryChart" height="200"></canvas>
    </div>
</div>

<!-- Tableaux Top Requetes + Top Pages -->
<div class="grid-2">
    <div class="data-table-wrap">
        <h3>Top requetes</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Requete</th>
                    <th>Clicks</th>
                    <th>Impressions</th>
                    <th>CTR</th>
                    <th>Position</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topQueries as $q): ?>
                <tr>
                    <td class="truncate"><?= htmlspecialchars($q['query']) ?></td>
                    <td class="num"><?= number_format((int)$q['clicks'], 0, ',', ' ') ?></td>
                    <td class="num"><?= number_format((int)$q['impressions'], 0, ',', ' ') ?></td>
                    <td class="num"><?= number_format($q['ctr'] * 100, 2) ?>%</td>
                    <td class="num"><?= number_format($q['position'], 1) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($topQueries)): ?>
                <tr><td colspan="5" style="text-align:center;color:#999">Aucune donnee</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="data-table-wrap">
        <h3>Top pages</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Clicks</th>
                    <th>Impressions</th>
                    <th>CTR</th>
                    <th>Position</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topPages as $p): ?>
                <tr>
                    <td class="truncate"><?= htmlspecialchars($p['page']) ?></td>
                    <td class="num"><?= number_format((int)$p['clicks'], 0, ',', ' ') ?></td>
                    <td class="num"><?= number_format((int)$p['impressions'], 0, ',', ' ') ?></td>
                    <td class="num"><?= number_format($p['ctr'] * 100, 2) ?>%</td>
                    <td class="num"><?= number_format($p['position'], 1) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($topPages)): ?>
                <tr><td colspan="5" style="text-align:center;color:#999">Aucune donnee</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Injection des données pour Chart.js -->
<script>
window.dashboardData = {
    dailyTrend: <?= json_encode($dailyTrend, JSON_UNESCAPED_UNICODE) ?>,
    devices:    <?= json_encode($devices, JSON_UNESCAPED_UNICODE) ?>,
    countries:  <?= json_encode($countries, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="/module-assets/search-console/public/assets/js/dashboard.js"></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
