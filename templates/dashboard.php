<?php
use Platform\Controller\DashboardController;
use Platform\Enum\AuditAction;

// Grouper les modules par catégorie
$modulesParCategorie = [];
foreach ($accessibleModules ?? [] as $mod) {
    $catKey = $mod['categorie_id'] ?? 0;
    if (!isset($modulesParCategorie[$catKey])) {
        $modulesParCategorie[$catKey] = [
            'nom'        => $mod['categorie_nom'] ?? null,
            'icone'      => $mod['categorie_icone'] ?? 'bi-folder',
            'sort_order' => $mod['categorie_sort_order'] ?? 9999,
            'modules'    => [],
        ];
    }
    $modulesParCategorie[$catKey]['modules'][] = $mod;
}

uksort($modulesParCategorie, function ($a, $b) use ($modulesParCategorie) {
    if ($a === 0) return 1;
    if ($b === 0) return -1;
    return $modulesParCategorie[$a]['sort_order'] <=> $modulesParCategorie[$b]['sort_order'];
});

$qs = $quotaSummary ?? [];
$estAdmin = ($currentUser['role'] ?? '') === 'admin';
$journal = $journalActivite ?? [];

// KPI : compteurs
$nbOutils = count($accessibleModules ?? []);
$quotasAlerte = [];
foreach ($qs as $slug => $q) {
    if ($q['quota_mode'] === \Platform\Enum\QuotaMode::None) continue;
    $limit = (int) $q['limit'];
    $usage = (int) $q['usage'];
    if ($estAdmin || $limit === 0) continue;
    $pct = round(($usage / $limit) * 100);
    if ($pct >= 80) {
        $nomModule = $slug;
        foreach ($accessibleModules ?? [] as $m) {
            if ($m['slug'] === $slug) { $nomModule = $m['name']; break; }
        }
        $quotasAlerte[] = ['nom' => $nomModule, 'pct' => $pct, 'slug' => $slug];
    }
}
$resetRelatif = DashboardController::tempsRelatifFutur($dateResetQuota ?? date('Y-m-01', strtotime('+1 month')));
?>

<div class="row g-4">
    <!-- Colonne gauche : Mon compte + Quotas -->
    <div class="col-lg-3">

        <!-- Card Mon compte (repliable) -->
        <div class="card dashboard-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#monCompteCollapse" aria-expanded="false" aria-controls="monCompteCollapse">
                <span>
                    <i class="bi bi-person-circle me-1"></i>
                    <strong><?= htmlspecialchars($currentUser['username'] ?? '') ?></strong>
                    <?php if ($estAdmin): ?>
                        <span class="badge badge-role-admin ms-1">admin</span>
                    <?php else: ?>
                        <span class="badge badge-role-user ms-1">utilisateur</span>
                    <?php endif; ?>
                </span>
                <i class="bi bi-chevron-down collapse-toggle-icon" style="font-size: 0.75rem; transition: transform 0.2s;"></i>
            </div>
            <div class="collapse" id="monCompteCollapse">
                <div class="card-body">
                    <dl class="dashboard-info-list mb-0">
                        <?php if (!empty($currentUser['domaine'])): ?>
                        <dt>Domaine</dt>
                        <dd><?= htmlspecialchars($currentUser['domaine']) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($currentUser['email'])): ?>
                        <dt>Email</dt>
                        <dd><?= htmlspecialchars($currentUser['email']) ?></dd>
                        <?php endif; ?>

                        <dt>Derni&egrave;re connexion</dt>
                        <dd>
                            <?php if (!empty($currentUser['last_login'])): ?>
                                <?= htmlspecialchars(DashboardController::tempsRelatif($currentUser['last_login'])) ?>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </dd>

                        <dt>Membre depuis</dt>
                        <dd><?= htmlspecialchars(DashboardController::dateFrancaise($currentUser['created_at'] ?? 'now')) ?></dd>

                        <dt>Alertes email</dt>
                        <dd>
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="alertesEmailToggle"
                                       <?= ($alertesActives ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="alertesEmailToggle" style="font-size: 0.8rem;">
                                    Recevoir les notifications
                                </label>
                            </div>
                            <?php if (!empty($unsubscribeToken)): ?>
                            <a href="/desabonnement?token=<?= htmlspecialchars($unsubscribeToken) ?>"
                               class="text-muted" style="font-size: 0.7rem; text-decoration: underline;">
                                G&eacute;rer mes pr&eacute;f&eacute;rences &rarr;
                            </a>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

    </div>

    <!-- Colonne droite : Outils + Activité récente -->
    <div class="col-lg-9">

        <!-- Grille plugins (en premier) -->
        <h5 class="mb-3" style="color: var(--brand-dark); font-weight: 600;">Mes outils</h5>

        <!-- Bandeau KPI -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card dashboard-kpi">
                    <div class="card-body d-flex align-items-center gap-3 py-2 px-3">
                        <i class="bi bi-tools" style="font-size: 1.5rem; color: var(--brand-teal);"></i>
                        <div>
                            <div class="dashboard-kpi-value"><?= $nbOutils ?></div>
                            <div class="dashboard-kpi-label">Outils disponibles</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-kpi<?= count($quotasAlerte) > 0 ? ' dashboard-kpi-warning' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3 py-2 px-3">
                        <i class="bi bi-<?= count($quotasAlerte) > 0 ? 'exclamation-triangle' : 'check-circle' ?>" style="font-size: 1.5rem; color: <?= count($quotasAlerte) > 0 ? 'var(--brand-gold)' : 'var(--bs-success, #198754)' ?>;"></i>
                        <div>
                            <div class="dashboard-kpi-value"><?= count($quotasAlerte) ?></div>
                            <div class="dashboard-kpi-label"><?= count($quotasAlerte) > 0 ? 'Quotas &agrave; surveiller' : 'Quotas OK' ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-kpi">
                    <div class="card-body d-flex align-items-center gap-3 py-2 px-3">
                        <i class="bi bi-arrow-repeat" style="font-size: 1.5rem; color: var(--brand-teal);"></i>
                        <div>
                            <div class="dashboard-kpi-value" style="font-size: 1.1rem;"><?= ucfirst(htmlspecialchars($resetRelatif)) ?></div>
                            <div class="dashboard-kpi-label">Prochain reset quotas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($quotasAlerte)): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4 py-2 px-3" style="font-size: 0.85rem; border-radius: 8px;">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Quotas &agrave; surveiller :</strong>
                <?php foreach ($quotasAlerte as $i => $qa): ?>
                    <?= htmlspecialchars($qa['nom']) ?> (<?= $qa['pct'] ?>%)<?= $i < count($quotasAlerte) - 1 ? ', ' : '' ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($modulesParCategorie as $catId => $catData):
            $catNom = $catId === 0 ? 'Autres' : htmlspecialchars($catData['nom']);
            $catIcone = $catId === 0 ? 'bi-three-dots' : htmlspecialchars($catData['icone']);
        ?>
        <div class="mb-4">
            <h6 class="mb-3" style="color: var(--text-secondary); font-weight: 600; font-size: 0.9rem;">
                <i class="bi <?= $catIcone ?> me-1"></i><?= $catNom ?>
            </h6>
            <div class="row g-3">
                <?php foreach ($catData['modules'] as $mod):
                    $modSlug = $mod['slug'];
                    $aQuota = isset($qs[$modSlug])
                        && $qs[$modSlug]['quota_mode'] !== \Platform\Enum\QuotaMode::None;
                    $quotaLimit = $aQuota ? (int) $qs[$modSlug]['limit'] : 0;
                    $quotaUsage = $aQuota ? (int) $qs[$modSlug]['usage'] : 0;

                ?>
                    <div class="col-sm-6 col-xl-4">
                        <a href="/m/<?= htmlspecialchars($mod['slug']) ?>" class="text-decoration-none">
                            <div class="card h-100 module-card">
                                <div class="card-body d-flex align-items-start gap-3 py-3 px-3">
                                    <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> module-icon" style="font-size: 1.75rem; line-height: 1;"></i>
                                    <div class="flex-grow-1 min-width-0">
                                        <h6 class="card-title mb-1" style="color: var(--text-primary); font-size: 0.95rem;">
                                            <?= htmlspecialchars($mod['name']) ?>
                                        </h6>
                                        <?php
                                            $desc = $mod['description'] ?? '';
                                            $descTronquee = mb_strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '...' : $desc;
                                        ?>
                                        <p class="card-text mb-1" style="color: var(--text-secondary); font-size: 0.8rem; line-height: 1.3;"><?= htmlspecialchars($descTronquee) ?></p>
                                        <?php if ($aQuota): ?>
                                            <?php if ($estAdmin || $quotaLimit === 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">
                                                    <?= htmlspecialchars($qs[$modSlug]['quota_mode']->label()) ?> &middot; Illimit&eacute;
                                                </div>
                                            <?php else:
                                                $qPct = min(100, round(($quotaUsage / $quotaLimit) * 100));
                                                $qBarClass = $qPct >= 100 ? 'progress-bar-danger' : ($qPct >= 80 ? 'progress-bar-warn' : 'progress-bar-teal');
                                                $restant = max(0, $quotaLimit - $quotaUsage);
                                            ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span><?= htmlspecialchars($qs[$modSlug]['quota_mode']->label()) ?></span>
                                                        <span><?= $quotaUsage ?> / <?= $quotaLimit ?></span>
                                                    </div>
                                                    <div class="progress" style="height: 4px;">
                                                        <div class="progress-bar <?= $qBarClass ?>" style="width: <?= $qPct ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Graphique d'usage mensuel -->
        <?php
        $usageData = $usageParMois ?? ['labels' => [], 'series' => []];
        $aDesUsages = !empty($usageData['series']);
        ?>
        <?php if ($aDesUsages): ?>
        <div class="card dashboard-card mb-4">
            <div class="card-header">
                <i class="bi bi-bar-chart-line me-1"></i>
                <?= $estAdmin ? 'Usage global' : 'Mon usage' ?> — 6 derniers mois
            </div>
            <div class="card-body">
                <canvas id="usageChart" height="220"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card Activité récente (3 visibles + collapse) -->
        <?php if (!empty($journal)): ?>
        <div class="card dashboard-card mb-4 mt-2">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i> Activit&eacute; r&eacute;cente</span>
                <?php if (count($journal) > 3): ?>
                    <a class="btn btn-sm btn-link p-0 text-decoration-none" data-bs-toggle="collapse" href="#journalCollapse" role="button" aria-expanded="false" aria-controls="journalCollapse" id="journalToggle">
                        Voir tout <i class="bi bi-chevron-down"></i>
                    </a>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($journal as $idx => $entry):
                    $action = AuditAction::tryFrom($entry['action']);
                    $label = $action?->label() ?? $entry['action'];
                    $icone = $action?->icone() ?? 'bi-dot';
                    $temps = DashboardController::tempsRelatif($entry['created_at']);

                    if ($action === AuditAction::ModuleUse && !empty($entry['details'])) {
                        $details = json_decode($entry['details'], true);
                        $slugPlugin = $details['slug'] ?? null;
                        if ($slugPlugin) {
                            $nomPlugin = $slugPlugin;
                            foreach ($accessibleModules ?? [] as $m) {
                                if ($m['slug'] === $slugPlugin) { $nomPlugin = $m['name']; break; }
                            }
                            $label = $nomPlugin;
                        }
                    }

                    if ($idx === 3): ?>
                        </ul><div class="collapse" id="journalCollapse"><ul class="list-group list-group-flush">
                    <?php endif; ?>

                <li class="list-group-item dashboard-activity-item px-3 py-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi <?= $icone ?>" style="font-size: 0.85rem; margin-top: 2px;"></i>
                        <div class="flex-grow-1 min-width-0">
                            <div style="font-size: 0.82rem; line-height: 1.3;">
                                <?= htmlspecialchars($label) ?>
                                <?php if ($estAdmin && !empty($entry['username'])): ?>
                                    <span class="text-muted">&mdash; <?= htmlspecialchars($entry['username']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($temps) ?></div>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($journal) > 3): ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Graphique d'usage mensuel (Chart.js)
    <?php if ($aDesUsages): ?>
    (function() {
        var ctx = document.getElementById('usageChart');
        if (!ctx) return;

        var labels = <?= json_encode($usageData['labels']) ?>;
        var colors = ['#004c4c', '#66b2b2', '#fbb03b', '#198754', '#6f42c1'];
        var datasets = [];
        var i = 0;

        <?php foreach ($usageData['series'] as $slug => $info): ?>
        datasets.push({
            label: <?= json_encode($info['name']) ?>,
            data: <?= json_encode($info['data']) ?>,
            backgroundColor: colors[i % colors.length],
            borderRadius: 4,
        });
        i++;
        <?php endforeach; ?>

        new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { family: 'Poppins', size: 11 } } }
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { font: { family: 'Poppins', size: 11 } } },
                    y: { stacked: true, beginAtZero: true, ticks: { font: { family: 'Poppins', size: 11 }, precision: 0 } }
                }
            }
        });
    })();
    <?php endif; ?>
    // Toggle activité récente
    var toggle = document.getElementById('journalToggle');
    var collapse = document.getElementById('journalCollapse');
    if (toggle && collapse) {
        collapse.addEventListener('shown.bs.collapse', function () {
            toggle.innerHTML = 'Réduire <i class="bi bi-chevron-up"></i>';
        });
        collapse.addEventListener('hidden.bs.collapse', function () {
            toggle.innerHTML = 'Voir tout <i class="bi bi-chevron-down"></i>';
        });
    }

    // Mon compte : mémoire localStorage + rotation chevron
    var monCompte = document.getElementById('monCompteCollapse');
    if (monCompte) {
        var wasOpen = localStorage.getItem('dashboard_moncompte_open') === '1';
        if (wasOpen) {
            monCompte.classList.add('show');
            var header = monCompte.previousElementSibling;
            if (header) {
                header.setAttribute('aria-expanded', 'true');
                var icon = header.querySelector('.collapse-toggle-icon');
                if (icon) icon.style.transform = 'rotate(180deg)';
            }
        }
        monCompte.addEventListener('shown.bs.collapse', function () {
            localStorage.setItem('dashboard_moncompte_open', '1');
            var icon = monCompte.previousElementSibling?.querySelector('.collapse-toggle-icon');
            if (icon) icon.style.transform = 'rotate(180deg)';
        });
        monCompte.addEventListener('hidden.bs.collapse', function () {
            localStorage.setItem('dashboard_moncompte_open', '0');
            var icon = monCompte.previousElementSibling?.querySelector('.collapse-toggle-icon');
            if (icon) icon.style.transform = 'rotate(0deg)';
        });
    }

    // Toggle alertes email
    var alerteToggle = document.getElementById('alertesEmailToggle');
    if (alerteToggle) {
        var csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';
        alerteToggle.addEventListener('change', function () {
            fetch('/api/notifications/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: 'actif=' + (this.checked ? '1' : '0') + '&_csrf_token=' + encodeURIComponent(csrfToken)
            });
        });
    }
});
</script>
