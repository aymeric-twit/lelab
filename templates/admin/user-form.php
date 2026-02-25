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
                        <label for="password" class="form-label">
                            Mot de passe <?= isset($editUser) ? '(laisser vide pour ne pas changer)' : '' ?>
                        </label>
                        <input type="password" class="form-control" id="password" name="password"
                               <?= isset($editUser) ? '' : 'required' ?>>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle</label>
                        <select class="form-select" id="role" name="role">
                            <option value="user" <?= ($editUser['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                        </select>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="active" name="active" value="1"
                               <?= ($editUser['active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Compte actif</label>
                    </div>
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
                                    <th class="text-center" style="width: 80px;">Accès</th>
                                    <th style="width: 200px;">Quota mensuel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $mod):
                                    $modId = (int) $mod['id'];
                                    $hasAccess = !empty($userAccess[$modId]);
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
                                        <input type="checkbox" class="form-check-input"
                                               name="access[<?= $modId ?>]" value="1"
                                               <?= $hasAccess ? 'checked' : '' ?>>
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
