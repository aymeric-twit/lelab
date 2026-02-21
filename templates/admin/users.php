<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Utilisateurs</h2>
    <a href="/admin/users/create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nouvel utilisateur
    </a>
</div>

<div class="table-responsive">
    <table class="table table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Nom d'utilisateur</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actif</th>
                <th>Derniere connexion</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                <td>
                    <?php if ($u['role'] === 'admin'): ?>
                        <span class="badge bg-primary">Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">User</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['active']): ?>
                        <span class="badge bg-success">Oui</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Non</span>
                    <?php endif; ?>
                </td>
                <td><?= $u['last_login'] ? htmlspecialchars($u['last_login']) : '<span class="text-muted">Jamais</span>' ?></td>
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
