<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm" style="border-top: 3px solid var(--color-warn);">
                <div class="card-body text-center py-5">
                    <i class="bi bi-exclamation-triangle quota-exceeded-icon"></i>
                    <h3 class="mt-3 mb-2" style="font-weight:700;">Quota dépassé</h3>
                    <p class="mb-4" style="color:var(--text-secondary);">
                        Vous avez atteint la limite d'utilisation mensuelle pour le module
                        <strong><?= htmlspecialchars($moduleName ?? '') ?></strong>.
                    </p>

                    <?php
                    $usage = $quotaUsage ?? 0;
                    $limit = $quotaLimit ?? 0;
                    $pct = $limit > 0 ? min(100, round(($usage / $limit) * 100)) : 100;
                    ?>
                    <div class="mb-3 mx-auto" style="max-width:300px;">
                        <div class="d-flex justify-content-between small mb-1" style="color:var(--text-muted);">
                            <span>Utilisation</span>
                            <span><?= $usage ?> / <?= $limit ?></span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-danger" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>

                    <p class="small mb-4" style="color:var(--text-muted);">
                        <?php
                        $dateReset = $dateResetQuota ?? null;
                        if ($dateReset):
                            $dateResetFr = \Platform\Controller\DashboardController::dateFrancaise($dateReset);
                        ?>
                            Votre quota sera r&eacute;initialis&eacute; le <strong><?= htmlspecialchars($dateResetFr) ?></strong>.
                        <?php else: ?>
                            Les quotas sont r&eacute;initialis&eacute;s chaque mois.
                        <?php endif; ?>
                        <br>Contactez votre administrateur si vous avez besoin d'une limite plus &eacute;lev&eacute;e.
                    </p>

                    <a href="/" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i> Retour au Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
