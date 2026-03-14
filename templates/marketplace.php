<?php
$modulesParCat = $modulesParCategorie ?? [];
$estAdmin = ($currentUser['role'] ?? '') === 'admin';
?>

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 style="color: var(--brand-dark); font-weight: 700; margin-bottom: 4px;">
                <i class="bi bi-shop me-2"></i>Marketplace
            </h4>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                <?= $totalModules ?? 0 ?> outils disponibles &middot;
                <?= $totalAccessibles ?? 0 ?> dans votre abonnement
            </p>
        </div>
    </div>
</div>

<?php foreach ($modulesParCat as $catId => $catData):
    $catNom = htmlspecialchars($catData['nom']);
    $catIcone = htmlspecialchars($catData['icone']);
?>
<div class="mb-4">
    <h5 class="mb-3" style="color: var(--brand-dark); font-weight: 600; font-size: 1rem;">
        <i class="bi <?= $catIcone ?> me-2"></i><?= $catNom ?>
    </h5>
    <div class="row g-3">
        <?php foreach ($catData['modules'] as $mod):
            $accessible = $mod['_accessible'] ?? false;
            $slug = htmlspecialchars($mod['slug']);
            $quotaMode = $mod['quota_mode'] ?? 'none';
            $defaultQuota = (int) ($mod['default_quota'] ?? 0);
        ?>
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100" style="border-radius: 1rem; <?= $accessible ? '' : 'opacity: 0.75;' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start gap-3 mb-2">
                        <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?>" style="font-size: 2rem; color: var(--brand-teal);"></i>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="font-weight: 700; color: var(--brand-dark);">
                                <?= htmlspecialchars($mod['name']) ?>
                            </h6>
                            <span class="badge" style="background: <?= $accessible ? 'var(--brand-teal)' : '#dee2e6' ?>; color: <?= $accessible ? '#fff' : '#666' ?>; font-size: 0.65rem;">
                                <?= $accessible ? 'Accessible' : 'Non inclus' ?>
                            </span>
                        </div>
                    </div>
                    <p class="text-muted mb-2" style="font-size: 0.82rem; line-height: 1.4;">
                        <?= htmlspecialchars($mod['description'] ?? '') ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size: 0.72rem;">
                            v<?= htmlspecialchars($mod['version'] ?? '1.0') ?>
                            <?php if ($quotaMode !== 'none' && $defaultQuota > 0): ?>
                                &middot; <?= $defaultQuota ?> req/mois
                            <?php endif; ?>
                        </span>
                        <?php if ($accessible): ?>
                            <a href="/m/<?= $slug ?>" class="btn btn-sm btn-primary">Ouvrir</a>
                        <?php else: ?>
                            <form method="POST" action="/m/<?= $slug ?>/demander-acces" class="d-inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Platform\Http\Csrf::token()) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Demander l'acc&egrave;s</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
