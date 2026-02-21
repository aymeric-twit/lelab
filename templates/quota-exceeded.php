<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-warning shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                    <h3 class="mt-3 mb-2">Quota depasse</h3>
                    <p class="text-muted mb-4">
                        Vous avez atteint la limite d'utilisation mensuelle pour le module
                        <strong><?= htmlspecialchars($moduleName ?? '') ?></strong>.
                    </p>

                    <?php
                    $usage = $quotaUsage ?? 0;
                    $limit = $quotaLimit ?? 0;
                    $pct = $limit > 0 ? min(100, round(($usage / $limit) * 100)) : 100;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Utilisation</span>
                            <span><?= $usage ?> / <?= $limit ?></span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-danger" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>

                    <p class="text-muted small mb-4">
                        Les quotas sont reinitialises chaque mois. Contactez votre administrateur
                        si vous avez besoin d'une limite plus elevee.
                    </p>

                    <a href="/" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i> Retour au Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
