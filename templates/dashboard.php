<h2 class="mb-4">Dashboard</h2>
<p class="text-muted mb-4">Bienvenue, <?= htmlspecialchars($currentUser['username'] ?? '') ?>. Selectionnez un outil pour commencer.</p>

<div class="row g-4">
    <?php foreach ($accessibleModules ?? [] as $mod): ?>
        <div class="col-sm-6 col-lg-4">
            <a href="/m/<?= htmlspecialchars($mod['slug']) ?>" class="text-decoration-none">
                <div class="card h-100 module-card border shadow-sm">
                    <div class="card-body text-center py-4">
                        <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> fs-1 text-primary mb-3 d-block"></i>
                        <h5 class="card-title text-dark"><?= htmlspecialchars($mod['name']) ?></h5>
                        <p class="card-text text-muted small"><?= htmlspecialchars($mod['description'] ?? '') ?></p>
                        <span class="badge bg-light text-muted">v<?= htmlspecialchars($mod['version'] ?? '1.0.0') ?></span>
                    </div>
                    <?php
                    $qs = $quotaSummary ?? [];
                    $modSlug = $mod['slug'];
                    if (($currentUser['role'] ?? '') !== 'admin'
                        && isset($qs[$modSlug])
                        && $qs[$modSlug]['quota_mode'] !== 'none'
                        && $qs[$modSlug]['limit'] > 0
                    ):
                        $qUsage = $qs[$modSlug]['usage'];
                        $qLimit = $qs[$modSlug]['limit'];
                        $qPct = min(100, round(($qUsage / $qLimit) * 100));
                        $qBarClass = $qPct >= 100 ? 'bg-danger' : ($qPct >= 80 ? 'bg-warning' : 'bg-primary');
                    ?>
                    <div class="card-footer bg-transparent border-top-0 px-3 pb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Quota</span>
                            <span><?= $qUsage ?> / <?= $qLimit ?></span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar <?= $qBarClass ?>" style="width: <?= $qPct ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
