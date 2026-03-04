<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Catégories</h2>
    <a href="/admin/categories/creer" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nouvelle catégorie
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Nom</th>
                        <th>Icône</th>
                        <th>Ordre</th>
                        <th>Nombre de plugins</th>
                        <th>Installés</th>
                        <th style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="text-muted"><?= $cat['id'] ?></td>
                        <td><strong><?= htmlspecialchars($cat['nom']) ?></strong></td>
                        <td>
                            <i class="bi <?= htmlspecialchars($cat['icone'] ?? 'bi-folder') ?> me-1" style="color: var(--brand-teal);"></i>
                            <code style="font-size: 0.8rem;"><?= htmlspecialchars($cat['icone'] ?? 'bi-folder') ?></code>
                        </td>
                        <td><?= (int) $cat['sort_order'] ?></td>
                        <td>
                            <?php if ((int) $cat['nb_plugins'] > 0): ?>
                                <span class="badge badge-active"><?= (int) $cat['nb_plugins'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($cat['plugins_noms'])): ?>
                                <ul class="mb-0">
                                    <?php foreach (explode('||', $cat['plugins_noms']) as $nomPlugin): ?>
                                        <li><?= htmlspecialchars($nomPlugin) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/categories/<?= $cat['id'] ?>/editer" class="btn btn-sm btn-outline-secondary" title="Éditer">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/categories/<?= $cat['id'] ?>/supprimer" class="d-inline"
                                      onsubmit="return confirm('Supprimer la catégorie « <?= htmlspecialchars($cat['nom'], ENT_QUOTES, 'UTF-8') ?> » ? Les plugins associés deviendront non classés.')">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Aucune catégorie créée.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
