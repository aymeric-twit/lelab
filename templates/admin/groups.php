<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Groupes</h2>
    <a href="/admin/groups/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nouveau groupe
    </a>
</div>

<p class="text-muted mb-3" style="font-size:0.9rem;">
    Les groupes permettent de g&eacute;rer l'acc&egrave;s aux modules pour plusieurs utilisateurs &agrave; la fois.
    L'acc&egrave;s est additif : si un utilisateur a un acc&egrave;s direct ou via un groupe, il y a acc&egrave;s.
</p>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Nom</th>
                        <th>Description</th>
                        <th class="text-center">Membres</th>
                        <th class="text-center">Modules</th>
                        <th>Cr&eacute;&eacute; le</th>
                        <th style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groupes)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-people d-block mb-2" style="font-size: 2rem;"></i>
                            Aucun groupe cr&eacute;&eacute;.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($groupes as $g): ?>
                    <tr>
                        <td class="text-muted"><?= $g['id'] ?></td>
                        <td><strong><?= htmlspecialchars($g['name']) ?></strong></td>
                        <td style="color: var(--text-muted); font-size:0.85rem;">
                            <?= htmlspecialchars($g['description'] ?? '—') ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int) $g['nb_membres'] > 0): ?>
                                <span class="badge badge-active"><?= (int) $g['nb_membres'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int) $g['nb_modules'] > 0): ?>
                                <span class="badge" style="background: var(--brand-teal-light); color: var(--brand-dark);"><?= (int) $g['nb_modules'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted); font-size:0.85rem;">
                            <?= htmlspecialchars($g['created_at'] ?? '') ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/groups/<?= $g['id'] ?>/edit" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/groups/<?= $g['id'] ?>/supprimer" class="d-inline"
                                      onsubmit="return confirm('Supprimer le groupe &laquo; <?= htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8') ?> &raquo; ? Les acc\u00e8s via ce groupe seront retir\u00e9s.')">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
