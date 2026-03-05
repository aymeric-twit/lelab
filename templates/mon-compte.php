<?php
use Platform\Controller\DashboardController;
?>

<h2 class="mb-4"><i class="bi bi-person-circle me-2"></i>Mon compte</h2>

<div class="row g-4">
    <!-- Informations personnelles -->
    <div class="col-lg-6">
        <div class="card dashboard-card">
            <div class="card-header">
                <i class="bi bi-person me-1"></i> Informations personnelles
            </div>
            <div class="card-body">
                <form method="POST" action="/mon-compte">
                    <?= \Platform\Http\Csrf::field() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>"
                               required minlength="3" maxlength="50">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="domaine" class="form-label">Domaine</label>
                        <input type="text" class="form-control" id="domaine" name="domaine"
                               value="<?= htmlspecialchars($currentUser['domaine'] ?? '') ?>"
                               placeholder="exemple.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rôle</label>
                        <div>
                            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                                <span class="badge badge-role-admin">admin</span>
                            <?php else: ?>
                                <span class="badge badge-role-user">utilisateur</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Membre depuis</label>
                        <div class="text-muted small">
                            <?= htmlspecialchars(DashboardController::dateFrancaise($currentUser['created_at'] ?? 'now')) ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>

        <?php if (($currentUser['role'] ?? '') !== 'admin'): ?>
        <button type="button" class="btn btn-outline-danger btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#modalSuppressionCompte">
            <i class="bi bi-trash me-1"></i>Supprimer mon compte
        </button>
        <?php endif; ?>
    </div>

    <!-- Changer le mot de passe -->
    <div class="col-lg-6">
        <div class="card dashboard-card mb-4">
            <div class="card-header">
                <i class="bi bi-key me-1"></i> Changer le mot de passe
            </div>
            <div class="card-body">
                <form method="POST" action="/mon-compte/mot-de-passe">
                    <?= \Platform\Http\Csrf::field() ?>

                    <div class="mb-3">
                        <label for="mot_de_passe_actuel" class="form-label">Mot de passe actuel</label>
                        <input type="password" class="form-control" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        <div class="form-text">8 caractères min., une majuscule, une minuscule, un chiffre.</div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                    </div>

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-key me-1"></i>Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>

        <!-- Mes quotas -->
        <?php
        $qs = $quotaSummary ?? [];
        $estAdmin = ($currentUser['role'] ?? '') === 'admin';
        ?>
        <div class="card dashboard-card">
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
                                <span class="text-muted" style="font-size: 0.75rem; white-space: nowrap;">Illimité</span>
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
            </div>
        </div>
    </div>
</div>

<?php if (($currentUser['role'] ?? '') !== 'admin'): ?>
<!-- Modale de confirmation de suppression -->
<div class="modal fade" id="modalSuppressionCompte" tabindex="-1" aria-labelledby="modalSuppressionCompteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/mon-compte/supprimer">
                <?= \Platform\Http\Csrf::field() ?>
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalSuppressionCompteLabel">
                        <i class="bi bi-exclamation-triangle text-danger me-2"></i>Supprimer mon compte
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Cette action est irréversible.</strong> Toutes vos données seront supprimées :
                        <ul class="mb-0 mt-2">
                            <li>Accès à tous les plugins</li>
                            <li>Historique de quotas</li>
                            <li>Sessions et tokens de connexion</li>
                        </ul>
                    </div>
                    <div class="mb-0">
                        <label for="mot_de_passe_suppression" class="form-label">Confirmez votre mot de passe</label>
                        <input type="password" class="form-control" id="mot_de_passe_suppression"
                               name="mot_de_passe_suppression" required
                               placeholder="Entrez votre mot de passe pour confirmer">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Supprimer définitivement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
