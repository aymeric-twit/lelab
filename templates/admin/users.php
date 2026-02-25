<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Utilisateurs</h2>
    <a href="/admin/users/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nouvel utilisateur
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Actif</th>
                        <th>Dernière connexion</th>
                        <th style="width:70px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-muted"><?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-role-admin">Admin</span>
                            <?php else: ?>
                                <span class="badge badge-role-user">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['active']): ?>
                                <span class="badge badge-active">Oui</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Non</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted); font-size:0.85rem;">
                            <?= $u['last_login'] ? htmlspecialchars($u['last_login']) : 'Jamais' ?>
                        </td>
                        <td>
                            <a href="/admin/users/<?= $u['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (isset($pagination) && $pagination['totalPages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php if ($pagination['page'] > 1): ?>
            <li class="page-item">
                <a class="page-link" href="/admin/users?page=<?= $pagination['page'] - 1 ?>">Précédent</a>
            </li>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
            <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
                <a class="page-link" href="/admin/users?page=<?= $p ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
            <li class="page-item">
                <a class="page-link" href="/admin/users?page=<?= $pagination['page'] + 1 ?>">Suivant</a>
            </li>
        <?php endif; ?>
    </ul>
    <p class="text-center small text-muted"><?= $pagination['total'] ?> utilisateur(s)</p>
</nav>
<?php endif; ?>
