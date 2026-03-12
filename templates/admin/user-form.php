<h2 class="mb-4"><?= isset($editUser) ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' ?></h2>

<form method="POST">
    <?= \Platform\Http\Csrf::field() ?>

    <div class="row g-4">
        <!-- Colonne gauche : Infos utilisateur -->
        <div class="<?= !empty($modules) ? 'col-lg-5' : 'col-lg-6' ?>">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Informations</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="domaine" class="form-label">Domaine</label>
                        <input type="text" class="form-control" id="domaine" name="domaine"
                               placeholder="example.com"
                               value="<?= htmlspecialchars($editUser['domaine'] ?? '') ?>">
                        <small class="text-muted">Pré-rempli automatiquement dans les plugins avec champ domaine</small>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Mot de passe <?= isset($editUser) ? '(laisser vide pour ne pas changer)' : '' ?>
                        </label>
                        <input type="password" class="form-control" id="password" name="password"
                               <?= isset($editUser) ? '' : 'required' ?>>
                        <div class="password-strength-bar mt-1" style="height:4px;border-radius:2px;background:#e9ecef;"><div id="pwStrength" style="height:100%;width:0;border-radius:2px;transition:all 0.3s;"></div></div>
                        <small class="password-strength-text" id="pwStrengthText"></small>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle</label>
                        <select class="form-select" id="role" name="role">
                            <option value="user" <?= ($editUser['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                        </select>
                    </div>

                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="active" name="active" value="1"
                               <?= ($editUser['active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Compte actif</label>
                    </div>

                    <?php if (isset($editUser)): ?>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="force_password_reset" name="force_password_reset" value="1"
                               <?= !empty($editUser['force_password_reset']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="force_password_reset">Forcer la réinitialisation du mot de passe</label>
                    </div>
                    <?php endif; ?>

                    <?php if (!isset($editUser)): ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="envoyer_bienvenue" name="envoyer_bienvenue" value="1" checked>
                        <label class="form-check-label" for="envoyer_bienvenue">Envoyer un e-mail de bienvenue</label>
                    </div>
                    <?php else: ?>
                    <div class="mt-3 pt-3 border-top d-flex gap-2 flex-wrap">
                        <form method="POST" action="/admin/users/<?= $editUser['id'] ?>/renvoyer-bienvenue" class="d-inline">
                            <?= \Platform\Http\Csrf::field() ?>
                            <button type="submit" class="btn btn-outline-secondary btn-sm" <?= empty($editUser['email']) ? 'disabled' : '' ?>>
                                <i class="bi bi-envelope me-1"></i>Renvoyer l'email de bienvenue
                            </button>
                        </form>
                        <form method="POST" action="/admin/users/<?= $editUser['id'] ?>/renvoyer-verification" class="d-inline">
                            <?= \Platform\Http\Csrf::field() ?>
                            <button type="submit" class="btn btn-outline-secondary btn-sm" <?= empty($editUser['email']) ? 'disabled' : '' ?>>
                                <i class="bi bi-shield-check me-1"></i>Renvoyer l'email de vérification
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($modules)): ?>
        <!-- Colonne droite : Acces & Quotas -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Accès aux outils & Quotas</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th class="text-center" style="width: 150px;">
                                        <input type="checkbox" class="form-check-input me-1" id="checkTousAcces" title="Sélectionner tous">
                                        Acc&egrave;s
                                    </th>
                                    <th style="width: 200px;">Quota mensuel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $mod):
                                    $modId = (int) $mod['id'];
                                    $accessInfo = $userAccess[$modId] ?? null;
                                    $hasAccess = !empty($accessInfo['granted']);
                                    $expireAt = $userExpires[$modId] ?? null;
                                    $quotaOverride = $userQuotas[$modId] ?? null;
                                    $hasQuota = ($mod['quota_mode'] ?? '') !== 'none';
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($mod['icon'])): ?>
                                            <i class="bi <?= htmlspecialchars($mod['icon']) ?> me-1" style="color:var(--brand-teal);"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($mod['name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input cb-acces-form"
                                               name="access[<?= $modId ?>]" value="1"
                                               <?= $hasAccess ? 'checked' : '' ?>>
                                        <div class="mt-1 wrapper-expiration-form" style="<?= $hasAccess ? '' : 'display:none;' ?>">
                                            <input type="date"
                                                   class="form-control form-control-sm"
                                                   name="access_expires[<?= $modId ?>]"
                                                   value="<?= $expireAt ? date('Y-m-d', strtotime($expireAt)) : '' ?>"
                                                   style="width:120px; font-size:0.7rem;"
                                                   title="Date d'expiration (optionnel)">
                                            <?php if ($expireAt): ?>
                                                <span class="badge bg-warning text-dark mt-1" style="font-size:0.65rem;">
                                                    Expire le <?= date('d/m', strtotime($expireAt)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($hasQuota): ?>
                                            <input type="number" class="form-control form-control-sm" min="0"
                                                   name="quotas[<?= $modId ?>]"
                                                   placeholder="<?= (int) $mod['default_quota'] ?>"
                                                   value="<?= $quotaOverride !== null ? (int) $quotaOverride : '' ?>">
                                            <small style="color:var(--text-muted);">défaut : <?= (int) $mod['default_quota'] ?></small>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">&mdash; pas de quota</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <?= isset($editUser) ? 'Enregistrer' : 'Créer' ?>
        </button>
        <a href="/admin/users" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>

<script>
(function () {
    var checkAll = document.getElementById('checkTousAcces');
    if (!checkAll) return;

    function getAccessCheckboxes() {
        return document.querySelectorAll('input[name^="access["]');
    }

    checkAll.addEventListener('change', function () {
        getAccessCheckboxes().forEach(function (cb) {
            cb.checked = checkAll.checked;
            var wrapper = cb.closest('td').querySelector('.wrapper-expiration-form');
            if (wrapper) wrapper.style.display = checkAll.checked ? '' : 'none';
        });
    });

    document.addEventListener('change', function (e) {
        if (e.target.name && e.target.name.startsWith('access[')) {
            var boxes = getAccessCheckboxes();
            var checked = document.querySelectorAll('input[name^="access["]:checked');
            checkAll.checked = boxes.length > 0 && checked.length === boxes.length;
            checkAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    });

    // Afficher/masquer le champ date d'expiration selon la checkbox
    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('cb-acces-form')) return;
        var wrapper = e.target.closest('td').querySelector('.wrapper-expiration-form');
        if (wrapper) wrapper.style.display = e.target.checked ? '' : 'none';
    });

    // État initial
    var boxes = getAccessCheckboxes();
    var checked = document.querySelectorAll('input[name^="access["]:checked');
    if (boxes.length > 0 && checked.length === boxes.length) checkAll.checked = true;
    else if (checked.length > 0) checkAll.indeterminate = true;
})();

(function () {
    var input = document.getElementById('password');
    var bar = document.getElementById('pwStrength');
    var text = document.getElementById('pwStrengthText');
    if (!input || !bar || !text) return;

    function evaluerForce(val) {
        if (val.length < 8) return { niveau: 'faible', pct: 33, couleur: '#dc3545', label: 'Faible' };
        var types = 0;
        if (/[a-z]/.test(val)) types++;
        if (/[A-Z]/.test(val)) types++;
        if (/[0-9]/.test(val)) types++;
        if (/[^a-zA-Z0-9]/.test(val)) types++;
        if (types >= 3) return { niveau: 'fort', pct: 100, couleur: '#198754', label: 'Fort' };
        if (types >= 2) return { niveau: 'moyen', pct: 66, couleur: '#fbb03b', label: 'Moyen' };
        return { niveau: 'faible', pct: 33, couleur: '#dc3545', label: 'Faible' };
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
