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
                        <th>Catégorie</th>
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
                            <?php if (!empty($mod['categorie_nom'])): ?>
                                <span><?= htmlspecialchars($mod['categorie_nom']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($mod['git_url'])): ?>
                                <span class="badge bg-dark"><i class="bi bi-github me-1"></i>Git</span>
                                <small class="text-muted d-block mt-1" title="<?= htmlspecialchars($mod['git_url']) ?>">
                                    <?= htmlspecialchars($mod['git_branche'] ?? 'main') ?>
                                    <?php if (!empty($mod['git_dernier_commit'])): ?>
                                        · <code><?= htmlspecialchars(substr($mod['git_dernier_commit'], 0, 7)) ?></code>
                                    <?php endif; ?>
                                </small>
                                <?php if (!empty($mod['git_dernier_pull'])): ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($mod['git_dernier_pull']) ?></small>
                                <?php endif; ?>
                            <?php elseif ($mod['chemin_source']): ?>
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
                                <?php if (!empty($mod['git_url'])): ?>
                                <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/maj-git" class="d-inline">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Mettre à jour depuis Git">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/basculer" class="d-inline">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $mod['enabled'] ? 'Désactiver' : 'Activer' ?>">
                                        <i class="bi <?= $mod['enabled'] ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Désinstaller"
                                        data-bs-toggle="modal" data-bs-target="#modalDesinstaller<?= $mod['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($modules)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Aucun module installé.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($modules as $mod): ?>
<div class="modal fade" id="modalDesinstaller<?= $mod['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/desinstaller">
                <?= \Platform\Http\Csrf::field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Désinstaller « <?= htmlspecialchars($mod['name']) ?> »</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>
                        <?php if ($mod['chemin_source']): ?>
                            <?php if (str_contains($mod['chemin_source'], 'storage/plugins')): ?>
                                Le répertoire extrait sera supprimé.
                            <?php else: ?>
                                Les fichiers sources ne seront pas touchés.
                            <?php endif; ?>
                        <?php else: ?>
                            Le répertoire <code>modules/<?= htmlspecialchars($mod['slug']) ?>/</code> sera supprimé.
                        <?php endif; ?>
                    </p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="conserverReglages<?= $mod['id'] ?>"
                               checked onchange="document.getElementById('conserverReglagesInput<?= $mod['id'] ?>').value = this.checked ? '1' : '0'">
                        <label class="form-check-label" for="conserverReglages<?= $mod['id'] ?>">
                            Conserver les réglages (accès, quotas, historique) pour une réinstallation future
                        </label>
                        <input type="hidden" name="conserver_reglages" id="conserverReglagesInput<?= $mod['id'] ?>" value="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Désinstaller
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
