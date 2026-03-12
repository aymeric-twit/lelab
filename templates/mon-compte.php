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
                        <div class="password-strength-bar mt-1" style="height:4px;border-radius:2px;background:#e9ecef;"><div id="pwStrengthCompte" style="height:100%;width:0;border-radius:2px;transition:all 0.3s;"></div></div>
                        <small class="password-strength-text" id="pwStrengthCompteText"></small>
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

        <!-- Sécurité — Authentification à deux facteurs -->
        <div class="card dashboard-card mb-4">
            <div class="card-header">
                <i class="bi bi-shield-lock me-1"></i> Authentification à deux facteurs (2FA)
            </div>
            <div class="card-body">
                <?php if (!empty($currentUser['totp_enabled'])): ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge bg-success me-2">Activée</span>
                            <span class="text-muted small">Votre compte est protégé par la 2FA.</span>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalDesactiver2fa">
                            <i class="bi bi-shield-x me-1"></i>Désactiver
                        </button>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge bg-secondary me-2">Désactivée</span>
                            <span class="text-muted small">Ajoutez une couche de sécurité supplémentaire.</span>
                        </div>
                        <a href="/mon-compte/2fa/activer" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-shield-check me-1"></i>Activer la 2FA
                        </a>
                    </div>
                <?php endif; ?>
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

<!-- Dernières connexions -->
<?php $connexions = $dernieresConnexions ?? []; ?>
<?php if (!empty($connexions)): ?>
<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header">
                <i class="bi bi-clock-history me-1"></i> Dernières connexions
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0" style="font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>IP</th>
                                <th>Navigateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($connexions as $cx): ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars(DashboardController::dateFrancaise($cx['created_at'])) ?></td>
                                <td><code><?= htmlspecialchars($cx['ip_address']) ?></code></td>
                                <td class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($cx['user_agent'] ?? '') ?>">
                                    <?= htmlspecialchars(mb_substr($cx['user_agent'] ?? '', 0, 80)) ?><?= mb_strlen($cx['user_agent'] ?? '') > 80 ? '...' : '' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modale de désactivation 2FA -->
<?php if (!empty($currentUser['totp_enabled'])): ?>
<div class="modal fade" id="modalDesactiver2fa" tabindex="-1" aria-labelledby="modalDesactiver2faLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/mon-compte/2fa/desactiver">
                <?= \Platform\Http\Csrf::field() ?>
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalDesactiver2faLabel">
                        <i class="bi bi-shield-x text-warning me-2"></i>Désactiver la 2FA
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Attention :</strong> la désactivation de la 2FA réduit la sécurité de votre compte.
                    </div>
                    <div class="mb-0">
                        <label for="mot_de_passe_2fa" class="form-label">Confirmez votre mot de passe</label>
                        <input type="password" class="form-control" id="mot_de_passe_2fa"
                               name="mot_de_passe_2fa" required
                               placeholder="Entrez votre mot de passe pour confirmer">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-shield-x me-1"></i>Désactiver la 2FA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

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

<script>
(function () {
    var input = document.getElementById('password');
    var bar = document.getElementById('pwStrengthCompte');
    var text = document.getElementById('pwStrengthCompteText');
    if (!input || !bar || !text) return;

    function evaluerForce(val) {
        if (val.length < 8) return { pct: 33, couleur: '#dc3545', label: 'Faible' };
        var types = 0;
        if (/[a-z]/.test(val)) types++;
        if (/[A-Z]/.test(val)) types++;
        if (/[0-9]/.test(val)) types++;
        if (/[^a-zA-Z0-9]/.test(val)) types++;
        if (types >= 3) return { pct: 100, couleur: '#198754', label: 'Fort' };
        if (types >= 2) return { pct: 66, couleur: '#fbb03b', label: 'Moyen' };
        return { pct: 33, couleur: '#dc3545', label: 'Faible' };
    }

    input.addEventListener('input', function () {
        if (!input.value) { bar.style.width = '0'; text.textContent = ''; return; }
        var r = evaluerForce(input.value);
        bar.style.width = r.pct + '%';
        bar.style.background = r.couleur;
        text.textContent = r.label;
        text.style.color = r.couleur;
    });
})();
</script>
