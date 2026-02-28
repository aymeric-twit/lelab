<h2 class="mb-1">Dashboard</h2>
<p class="text-muted mb-4" style="font-size:0.9rem;">Bienvenue, <?= htmlspecialchars($currentUser['username'] ?? '') ?>. S&eacute;lectionnez un outil pour commencer.</p>

<?php
// Grouper les modules par catégorie (même logique que la sidebar)
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

// Trier : "Non classé" (key 0) en dernier, puis par sort_order catégorie
uksort($modulesParCategorie, function ($a, $b) use ($modulesParCategorie) {
    if ($a === 0) return 1;
    if ($b === 0) return -1;
    return $modulesParCategorie[$a]['sort_order'] <=> $modulesParCategorie[$b]['sort_order'];
});

$qs = $quotaSummary ?? [];
$estAdmin = ($currentUser['role'] ?? '') === 'admin';

foreach ($modulesParCategorie as $catId => $catData):
    $catNom = $catId === 0 ? 'Non class&eacute;' : htmlspecialchars($catData['nom']);
    $catIcone = $catId === 0 ? 'bi-folder' : htmlspecialchars($catData['icone']);
?>
<div class="mb-4">
    <h5 class="mb-3" style="color: var(--brand-dark);">
        <i class="bi <?= $catIcone ?> me-2"></i><?= $catNom ?>
    </h5>
    <div class="row g-3">
        <?php foreach ($catData['modules'] as $mod):
            $modSlug = $mod['slug'];
            $aQuota = isset($qs[$modSlug])
                && $qs[$modSlug]['quota_mode'] !== \Platform\Enum\QuotaMode::None;
            $quotaLimit = $aQuota ? (int) $qs[$modSlug]['limit'] : 0;
            $quotaUsage = $aQuota ? (int) $qs[$modSlug]['usage'] : 0;
        ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
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
