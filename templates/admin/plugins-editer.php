<?php
$estExterne = !empty($module['chemin_source']);
$clesEnv = $module['cles_env'] ? json_decode($module['cles_env'], true) : [];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Modifier le plugin</h2>
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($module['git_url'])): ?>
            <span class="badge bg-dark"><i class="bi bi-github me-1"></i>Git</span>
        <?php elseif ($estExterne): ?>
            <span class="badge badge-source-external">Externe</span>
        <?php else: ?>
            <span class="badge badge-source-embedded">Embarqué</span>
        <?php endif; ?>
        <span class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($module['slug']) ?></span>
    </div>
</div>

<?php if ($estExterne): ?>
<div class="card card-accent-gold mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-2">
        <div>
            <?php if (!empty($module['git_url'])): ?>
                <i class="bi bi-github me-1" style="color: var(--brand-gold);"></i>
                <span class="chemin-source-text"><?= htmlspecialchars($module['git_url']) ?></span>
                <small class="text-muted ms-2">
                    branche <strong><?= htmlspecialchars($module['git_branche'] ?? 'main') ?></strong>
                </small>
            <?php else: ?>
                <i class="bi bi-folder2-open me-1" style="color: var(--brand-gold);"></i>
                <span class="chemin-source-text"><?= htmlspecialchars($module['chemin_source']) ?></span>
            <?php endif; ?>
            <?php if ($module['installe_le']): ?>
                <small class="text-muted ms-2">
                    Installé le <?= htmlspecialchars($module['installe_le']) ?>
                </small>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($module['git_url'])): ?>
                <form method="POST" action="/admin/plugins/<?= $module['id'] ?>/maj-git" class="d-inline">
                    <?= \Platform\Http\Csrf::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Mettre à jour depuis Git">
                        <i class="bi bi-arrow-repeat me-1"></i> Mettre à jour (Git)
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" class="d-inline">
                    <?= \Platform\Http\Csrf::field() ?>
                    <input type="hidden" name="resync" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Relire module.json">
                        <i class="bi bi-arrow-repeat me-1"></i> Resynchroniser
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if (!empty($module['git_url'])): ?>
<div class="card mb-4" style="border-left: 3px solid var(--brand-dark);">
    <div class="card-body py-2">
        <div class="row text-center">
            <div class="col-md-3">
                <small class="text-muted d-block">Dépôt</small>
                <strong><?= htmlspecialchars($module['git_url']) ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Branche</small>
                <strong><?= htmlspecialchars($module['git_branche'] ?? 'main') ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Dernier commit</small>
                <?php if (!empty($module['git_dernier_commit'])): ?>
                    <code title="<?= htmlspecialchars($module['git_dernier_commit']) ?>"><?= htmlspecialchars(substr($module['git_dernier_commit'], 0, 7)) ?></code>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Dernier pull</small>
                <?php if (!empty($module['git_dernier_pull'])): ?>
                    <strong><?= htmlspecialchars($module['git_dernier_pull']) ?></strong>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Chemin local</small>
                <small class="chemin-source-text"><?= htmlspecialchars($module['chemin_source']) ?></small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<form method="POST">
    <?= \Platform\Http\Csrf::field() ?>

    <div class="row g-4">
        <!-- Colonne gauche : Infos de base -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Informations</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($module['slug']) ?>" disabled>
                        <small class="text-muted">Le slug ne peut pas être modifié</small>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?= htmlspecialchars($module['name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= htmlspecialchars($module['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="version" class="form-label">Version</label>
                                <input type="text" class="form-control" id="version" name="version"
                                       value="<?= htmlspecialchars($module['version'] ?? '1.0.0') ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="icon" class="form-label">Icône Bootstrap</label>
                                <input type="text" class="form-control" id="icon" name="icon"
                                       value="<?= htmlspecialchars($module['icon'] ?? 'bi-tools') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="point_entree" class="form-label">Point d'entrée</label>
                                <input type="text" class="form-control" id="point_entree" name="point_entree"
                                       value="<?= htmlspecialchars($module['point_entree'] ?? 'index.php') ?>">
                                <small class="text-muted">
                                    Fichier appelé :
                                    <code><?= htmlspecialchars(
                                        ($module['chemin_source'] ?? dirname(__DIR__, 2) . '/modules/' . $module['slug'])
                                        . '/' . ($module['point_entree'] ?? 'index.php')
                                    ) ?></code>
                                </small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="sort_order" class="form-label">Ordre d'affichage</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order"
                                       value="<?= (int) $module['sort_order'] ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="categorie_id" class="form-label">Catégorie</label>
                        <select class="form-select" id="categorie_id" name="categorie_id">
                            <option value="">— Aucune (Non classé) —</option>
                            <?php foreach ($categories ?? [] as $cat): ?>
                                <option value="<?= $cat['id'] ?>"
                                    <?= ((int) ($module['categorie_id'] ?? 0)) === (int) $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne droite : Config avancée -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Configuration avancée</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="quota_mode" class="form-label">Mode de quota</label>
                        <select class="form-select" id="quota_mode" name="quota_mode">
                            <?php foreach (\Platform\Enum\QuotaMode::cases() as $mode): ?>
                                <option value="<?= $mode->value ?>"
                                    <?= ($module['quota_mode'] ?? 'none') === $mode->value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mode->label()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="default_quota" class="form-label">Quota mensuel par défaut</label>
                        <input type="number" class="form-control" id="default_quota" name="default_quota"
                               value="<?= (int) ($module['default_quota'] ?? 0) ?>" min="0">
                    </div>

                    <div class="mb-3">
                        <label for="cles_env" class="form-label">Clés d'environnement</label>
                        <input type="text" class="form-control" id="cles_env" name="cles_env"
                               value="<?= htmlspecialchars(implode(', ', $clesEnv)) ?>"
                               placeholder="API_KEY, SECRET_TOKEN">
                        <small class="text-muted">Séparées par des virgules</small>
                    </div>

                    <div class="mb-3">
                        <label for="mode_affichage" class="form-label">Mode d'affichage</label>
                        <select class="form-select" id="mode_affichage" name="mode_affichage">
                            <?php foreach (\Platform\Enum\ModeAffichage::cases() as $mode): ?>
                                <option value="<?= $mode->value ?>"
                                    <?= ($module['mode_affichage'] ?? 'embedded') === $mode->value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mode->label()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Intégré : extraction HTML. Iframe : app complète isolée. Passthrough : sans layout.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Enregistrer
            </button>
            <a href="/admin/plugins" class="btn btn-outline-secondary">Annuler</a>
        </div>

        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDesinstallerEditer">
            <i class="bi bi-trash me-1"></i> Désinstaller
        </button>
    </div>
</form>

<div class="modal fade" id="modalDesinstallerEditer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/plugins/<?= $module['id'] ?>/desinstaller">
                <?= \Platform\Http\Csrf::field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Désinstaller « <?= htmlspecialchars($module['name']) ?> »</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>
                        <?php if ($estExterne): ?>
                            <?php if (str_contains($module['chemin_source'] ?? '', 'storage/plugins')): ?>
                                Le répertoire extrait sera supprimé.
                            <?php else: ?>
                                Les fichiers sources ne seront pas touchés.
                            <?php endif; ?>
                        <?php else: ?>
                            Le répertoire <code>modules/<?= htmlspecialchars($module['slug']) ?>/</code> sera supprimé.
                        <?php endif; ?>
                    </p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="conserverReglagesEditer"
                               checked onchange="document.getElementById('conserverReglagesInputEditer').value = this.checked ? '1' : '0'">
                        <label class="form-check-label" for="conserverReglagesEditer">
                            Conserver les réglages (accès, quotas, historique) pour une réinstallation future
                        </label>
                        <input type="hidden" name="conserver_reglages" id="conserverReglagesInputEditer" value="1">
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
