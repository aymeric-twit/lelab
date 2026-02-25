<?php
$estExterne = !empty($module['chemin_source']);
$clesEnv = $module['cles_env'] ? json_decode($module['cles_env'], true) : [];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Modifier le plugin</h2>
    <div class="d-flex align-items-center gap-2">
        <?php if ($estExterne): ?>
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
            <i class="bi bi-folder2-open me-1" style="color: var(--brand-gold);"></i>
            <span class="chemin-source-text"><?= htmlspecialchars($module['chemin_source']) ?></span>
            <?php if ($module['installe_le']): ?>
                <small class="text-muted ms-2">
                    Installé le <?= htmlspecialchars($module['installe_le']) ?>
                </small>
            <?php endif; ?>
        </div>
        <form method="POST" class="d-inline">
            <?= \Platform\Http\Csrf::field() ?>
            <input type="hidden" name="resync" value="1">
            <button type="submit" class="btn btn-sm btn-outline-primary" title="Relire module.json">
                <i class="bi bi-arrow-repeat me-1"></i> Resynchroniser
            </button>
        </form>
    </div>
</div>
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

                    <div class="form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="passthrough_all" name="passthrough_all" value="1"
                               <?= !empty($module['passthrough_all']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="passthrough_all">
                            Mode passthrough <small class="text-muted">(toutes les sous-routes sont transmises)</small>
                        </label>
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

        <?php
            $confirmEditer = $estExterne
                ? (str_contains($module['chemin_source'] ?? '', 'storage/plugins')
                    ? 'Désinstaller « ' . addslashes($module['name']) . ' » ? Le répertoire extrait sera supprimé.'
                    : 'Désinstaller « ' . addslashes($module['name']) . ' » ? Les fichiers sources ne seront pas touchés.')
                : 'Supprimer le module « ' . addslashes($module['name']) . ' » et son répertoire modules/' . addslashes($module['slug']) . '/ ?';
        ?>
        <form method="POST" action="/admin/plugins/<?= $module['id'] ?>/desinstaller" class="d-inline"
              onsubmit="return confirm('<?= $confirmEditer ?>')">
            <?= \Platform\Http\Csrf::field() ?>
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-trash me-1"></i> Supprimer
            </button>
        </form>
    </div>
</form>
