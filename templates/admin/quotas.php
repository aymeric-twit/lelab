<h2 class="mb-4">Quotas mensuels</h2>
<p class="text-muted mb-4">Definissez les limites d'utilisation par utilisateur et par module. Laissez vide pour utiliser la valeur par defaut du module. <code>0</code> = illimite.</p>

<form method="POST" action="/admin/quotas">
    <?= \Platform\Http\Csrf::field() ?>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Utilisateur</th>
                    <?php foreach ($modules as $mod): ?>
                        <th class="text-center" style="min-width: 120px;">
                            <i class="bi <?= htmlspecialchars($mod['icon']) ?> d-block mb-1"></i>
                            <?= htmlspecialchars($mod['name']) ?>
                            <div class="small text-muted">
                                <?= htmlspecialchars($mod['quota_mode']) ?>
                                <?php if ((int) $mod['default_quota'] > 0): ?>
                                    <br>defaut: <?= (int) $mod['default_quota'] ?>
                                <?php else: ?>
                                    <br>defaut: illimite
                                <?php endif; ?>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge bg-primary ms-1">admin</span>
                            <div class="small text-muted">Exempt des quotas</div>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($modules as $mod): ?>
                        <td class="text-center">
                            <?php if ($mod['quota_mode'] === 'none'): ?>
                                <span class="text-muted small">—</span>
                            <?php else: ?>
                                <input type="number"
                                       class="form-control form-control-sm text-center"
                                       name="quotas[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                       value="<?= isset($matrix[$u['id']][$mod['id']]) ? (int) $matrix[$u['id']][$mod['id']] : '' ?>"
                                       min="0"
                                       placeholder="<?= (int) $mod['default_quota'] ?>"
                                       style="width: 90px; margin: 0 auto;">
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i> Enregistrer les quotas
    </button>
</form>
