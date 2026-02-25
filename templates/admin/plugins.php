<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Plugins</h2>
    <a href="/admin/plugins/installer" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Installer un plugin
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Source</th>
                        <th>Version</th>
                        <th>Mode quota</th>
                        <th>Quota défaut</th>
                        <th>Statut</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $mod): ?>
                    <tr>
                        <td>
                            <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> me-1" style="color: var(--brand-teal);"></i>
                            <strong><?= htmlspecialchars($mod['name']) ?></strong>
                            <small class="text-muted d-block"><?= htmlspecialchars($mod['slug']) ?></small>
                        </td>
                        <td>
                            <?php if ($mod['chemin_source']): ?>
                                <span class="badge badge-source-external">Externe</span>
                                <span class="chemin-source-text d-block mt-1"><?= htmlspecialchars($mod['chemin_source']) ?></span>
                            <?php else: ?>
                                <span class="badge badge-source-embedded">Embarqué</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($mod['version'] ?? '-') ?></td>
                        <td>
                            <?php
                                $qm = \Platform\Enum\QuotaMode::tryFrom($mod['quota_mode'] ?? 'none') ?? \Platform\Enum\QuotaMode::None;
                                echo htmlspecialchars($qm->label());
                            ?>
                        </td>
                        <td>
                            <?php if ($qm->estSuivi()): ?>
                                <?= (int) $mod['default_quota'] ?>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($mod['enabled']): ?>
                                <span class="badge badge-active">Actif</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/plugins/<?= $mod['id'] ?>/editer" class="btn btn-sm btn-outline-secondary" title="Éditer">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/basculer" class="d-inline">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $mod['enabled'] ? 'Désactiver' : 'Activer' ?>">
                                        <i class="bi <?= $mod['enabled'] ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                    </button>
                                </form>
                                <?php
                                    $confirmMsg = $mod['chemin_source']
                                        ? (str_contains($mod['chemin_source'], 'storage/plugins')
                                            ? 'Désinstaller « ' . addslashes($mod['name']) . ' » ? Le répertoire extrait sera supprimé.'
                                            : 'Désinstaller « ' . addslashes($mod['name']) . ' » ? Les fichiers sources ne seront pas touchés.')
                                        : 'Supprimer le module « ' . addslashes($mod['name']) . ' » et son répertoire modules/' . addslashes($mod['slug']) . '/ ?';
                                ?>
                                <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/desinstaller" class="d-inline"
                                      onsubmit="return confirm('<?= $confirmMsg ?>')">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($modules)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Aucun module installé.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
