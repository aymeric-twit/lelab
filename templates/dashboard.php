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
?>

<div class="row g-4">
    <!-- Colonne gauche : Mon compte + Quotas -->
    <div class="col-lg-3">

        <!-- Card Mon compte -->
        <div class="card dashboard-card mb-3">
            <div class="card-header">
                <i class="bi bi-person-circle me-1"></i> Mon compte
            </div>
            <div class="card-body">
                <dl class="dashboard-info-list mb-0">
                    <dt>Utilisateur</dt>
                    <dd class="fw-bold"><?= htmlspecialchars($currentUser['username'] ?? '') ?></dd>

                    <dt>R&ocirc;le</dt>
                    <dd>
                        <?php if ($estAdmin): ?>
                            <span class="badge badge-role-admin">admin</span>
                        <?php else: ?>
                            <span class="badge badge-role-user">utilisateur</span>
                        <?php endif; ?>
                    </dd>

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
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </dd>

                    <dt>Membre depuis</dt>
                    <dd><?= htmlspecialchars(DashboardController::dateFrancaise($currentUser['created_at'] ?? 'now')) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Card Quotas -->
        <div class="card dashboard-card mb-3">
            <div class="card-header">
                <i class="bi bi-speedometer2 me-1"></i> Mes quotas
            </div>
            <div class="card-body">
                <?php
                $aDesQuotas = false;
                foreach ($qs as $slug => $q):
                    if ($q['quota_mode'] === \Platform\Enum\QuotaMode::None) continue;
                    $aDesQuotas = true;
                    $limit = (int) $q['limit'];
                    $usage = (int) $q['usage'];
                    $nomModule = $slug;
                    foreach ($accessibleModules ?? [] as $m) {
                        if ($m['slug'] === $slug) { $nomModule = $m['name']; break; }
                    }
                ?>
                    <div class="dashboard-quota-item mb-2">
                        <div class="d-flex justify-content-between align-items-center" style="font-size: 0.82rem;">
                            <span class="text-truncate me-2"><?= htmlspecialchars($nomModule) ?></span>
                            <?php if ($estAdmin || $limit === 0): ?>
                                <span class="text-muted" style="font-size: 0.75rem; white-space: nowrap;">Illimit&eacute;</span>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; white-space: nowrap;"><?= $usage ?> / <?= $limit ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$estAdmin && $limit > 0):
                            $pct = min(100, round(($usage / $limit) * 100));
                            $barClass = $pct >= 100 ? 'progress-bar-danger' : ($pct >= 80 ? 'progress-bar-warn' : 'progress-bar-teal');
                        ?>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (!$aDesQuotas): ?>
                    <p class="text-muted small mb-0">Aucun quota actif.</p>
                <?php endif; ?>

                <?php if ($aDesQuotas && !$estAdmin): ?>
                    <div class="mt-2 pt-2 border-top" style="font-size: 0.72rem; color: var(--text-muted);">
                        <i class="bi bi-arrow-repeat me-1"></i>R&eacute;initialisation le <?= htmlspecialchars(DashboardController::dateFrancaise($dateResetQuota ?? date('Y-m-01', strtotime('+1 month')))) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Colonne droite : Activité récente + Grille plugins -->
    <div class="col-lg-9">

        <!-- Card Activité récente (3 visibles + collapse) -->
        <?php if (!empty($journal)): ?>
        <div class="card dashboard-card mb-4">
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
                                    <span class="text-muted">— <?= htmlspecialchars($entry['username']) ?></span>
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

        <!-- Grille plugins -->
        <h5 class="mb-3" style="color: var(--brand-dark); font-weight: 600;">Mes outils</h5>

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
                                        <h6 class="card-title mb-1" style="color: var(--text-primary); font-size: 0.95rem;"><?= htmlspecialchars($mod['name']) ?></h6>
                                        <?php
                                            $desc = $mod['description'] ?? '';
                                            $descTronquee = mb_strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '...' : $desc;
                                        ?>
                                        <p class="card-text mb-2" style="color: var(--text-secondary); font-size: 0.8rem; line-height: 1.3;"><?= htmlspecialchars($descTronquee) ?></p>
                                        <?php if ($aQuota): ?>
                                            <?php if ($quotaLimit > 0): ?>
                                                <?php
                                                    $qPct = min(100, round(($quotaUsage / $quotaLimit) * 100));
                                                    $qBarClass = $qPct >= 100 ? 'progress-bar-danger' : ($qPct >= 80 ? 'progress-bar-warn' : 'progress-bar-teal');
                                                ?>
                                                <div class="d-flex justify-content-between" style="font-size: 0.7rem; color: var(--text-muted);">
                                                    <span><?= htmlspecialchars($qs[$modSlug]['quota_mode']->label()) ?></span>
                                                    <span><?= $estAdmin ? 'Illimit&eacute;' : ($quotaUsage . ' / ' . $quotaLimit) ?></span>
                                                </div>
                                                <?php if (!$estAdmin): ?>
                                                <div class="progress mt-1" style="height: 4px;">
                                                    <div class="progress-bar <?= $qBarClass ?>" style="width: <?= $qPct ?>%"></div>
                                                </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">
                                                    <?= htmlspecialchars($qs[$modSlug]['quota_mode']->label()) ?> &middot; Illimit&eacute;
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted);">Pas de quota</div>
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>
