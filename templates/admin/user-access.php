<h2 class="mb-1">Matrice d'accès</h2>
<p class="text-muted mb-4" style="font-size:0.9rem;">Cochez les modules auxquels chaque utilisateur a accès.</p>

<form method="POST" action="/admin/access">
    <?= \Platform\Http\Csrf::field() ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered access-matrix mb-0">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <?php foreach ($modules as $mod): ?>
                                <th class="text-center" style="min-width: 100px;">
                                    <i class="bi <?= htmlspecialchars($mod['icon']) ?> d-block mb-1"></i>
                                    <?= htmlspecialchars($mod['name']) ?>
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
                                    <span class="badge badge-role-admin ms-1">admin</span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($modules as $mod): ?>
                                <td class="text-center">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="access[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                           value="1"
                                           <?= !empty($matrix[$u['id']][$mod['id']]) ? 'checked' : '' ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-3">
        <i class="bi bi-check-lg me-1"></i> Enregistrer les accès
    </button>
</form>
