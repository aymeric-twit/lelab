<?php
$ongletActif = $onglet ?? 'plugins';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Plugins</h2>
    <a href="/admin/plugins/installer" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Installer un plugin
    </a>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'plugins' ? 'active' : '' ?>"
           id="tab-plugins-btn" data-bs-toggle="tab" href="#tab-plugins"
           role="tab" aria-selected="<?= $ongletActif === 'plugins' ? 'true' : 'false' ?>">
            <i class="bi bi-puzzle me-1"></i> Plugins
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'categories' ? 'active' : '' ?>"
           id="tab-categories-btn" data-bs-toggle="tab" href="#tab-categories"
           role="tab" aria-selected="<?= $ongletActif === 'categories' ? 'true' : 'false' ?>">
            <i class="bi bi-folder me-1"></i> Cat&eacute;gories
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'cles-api' ? 'active' : '' ?>"
           id="tab-cles-api-btn" data-bs-toggle="tab" href="#tab-cles-api"
           role="tab" aria-selected="<?= $ongletActif === 'cles-api' ? 'true' : 'false' ?>">
            <i class="bi bi-key me-1"></i> Cl&eacute;s d'API
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'maj-git' ? 'active' : '' ?>"
           id="tab-maj-git-btn" data-bs-toggle="tab" href="#tab-maj-git"
           role="tab" aria-selected="<?= $ongletActif === 'maj-git' ? 'true' : 'false' ?>">
            <i class="bi bi-github me-1"></i> MAJ Github
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Onglet Plugins -->
    <div class="tab-pane fade <?= $ongletActif === 'plugins' ? 'show active' : '' ?>"
         id="tab-plugins" role="tabpanel">

        <p class="text-muted mb-3" style="font-size:0.9rem;">
            <i class="bi bi-grip-vertical me-1"></i> Glissez les plugins pour r&eacute;ordonner ou d&eacute;placer entre cat&eacute;gories.
        </p>

        <div id="pluginsContainer">
            <?php foreach ($modulesParCategorie as $catId => $catData):
                $catNom = $catId === 0 ? 'Non class&eacute;' : htmlspecialchars($catData['nom'] ?? 'Sans nom');
                $catIcone = $catId === 0 ? 'bi-folder' : htmlspecialchars($catData['icone'] ?? 'bi-folder');
                $nbPlugins = count($catData['modules']);
            ?>
            <div class="card mb-3 categorie-bloc" data-categorie-id="<?= $catId ?>">
                <div class="card-header d-flex align-items-center py-2">
                    <i class="bi <?= $catIcone ?> me-2" style="color: var(--brand-teal);"></i>
                    <strong><?= $catNom ?></strong>
                    <span class="badge bg-secondary ms-2"><?= $nbPlugins ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Module</th>
                                    <th>Source</th>
                                    <th>Version</th>
                                    <th>API</th>
                                    <th>Quota</th>
                                    <th>Statut</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="plugins-sortable" data-categorie-id="<?= $catId ?>">
                                <?php foreach ($catData['modules'] as $mod):
                                    $qm = \Platform\Enum\QuotaMode::tryFrom($mod['quota_mode'] ?? 'none') ?? \Platform\Enum\QuotaMode::None;
                                ?>
                                <tr data-id="<?= $mod['id'] ?>">
                                    <td class="drag-handle" style="cursor: grab; color: var(--brand-teal);">
                                        <i class="bi bi-grip-vertical"></i>
                                    </td>
                                    <td>
                                        <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> me-1" style="color: var(--brand-teal);"></i>
                                        <strong><?= htmlspecialchars($mod['name']) ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($mod['slug']) ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($mod['git_url'])): ?>
                                            <span class="badge bg-dark"><i class="bi bi-github me-1"></i>Git</span>
                                            <small class="text-muted d-block mt-1" title="<?= htmlspecialchars($mod['git_url']) ?>">
                                                <?= htmlspecialchars($mod['git_branche'] ?? 'main') ?>
                                                <?php if (!empty($mod['git_dernier_commit'])): ?>
                                                    &middot; <code><?= htmlspecialchars(substr($mod['git_dernier_commit'], 0, 7)) ?></code>
                                                <?php endif; ?>
                                            </small>
                                        <?php elseif ($mod['chemin_source']): ?>
                                            <span class="badge badge-source-external">Externe</span>
                                        <?php else: ?>
                                            <span class="badge badge-source-embedded">Embarqu&eacute;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($mod['version'] ?? '-') ?></td>
                                    <td>
                                        <?php if (empty($mod['_cles_env_liste'])): ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php else: ?>
                                            <?php
                                                $toutesPresentes = !empty($mod['_cles_env_statut']) && !in_array(false, $mod['_cles_env_statut'], true);
                                                $aucunePresente = !empty($mod['_cles_env_statut']) && !in_array(true, $mod['_cles_env_statut'], true);
                                            ?>
                                            <?php if ($toutesPresentes): ?>
                                                <span class="badge badge-active" title="<?= htmlspecialchars(implode(', ', $mod['_cles_env_liste'])) ?>">
                                                    <i class="bi bi-check-circle me-1"></i>OK
                                                </span>
                                            <?php elseif ($aucunePresente): ?>
                                                <span class="badge badge-inactive" title="<?= htmlspecialchars(implode(', ', $mod['_cles_env_liste'])) ?>">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>Manquante<?= count($mod['_cles_env_liste']) > 1 ? 's' : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(249, 115, 22, 0.12); color: #f97316; font-weight: 600;">
                                                    <i class="bi bi-exclamation-circle me-1"></i>Partiel
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($qm->estSuivi()): ?>
                                            <span class="text-muted" style="font-size:0.85rem;"><?= htmlspecialchars($qm->label()) ?></span>
                                            <strong><?= (int) $mod['default_quota'] ?></strong>
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
                                            <a href="/admin/plugins/<?= $mod['id'] ?>/editer" class="btn btn-sm btn-outline-secondary" title="&Eacute;diter">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if (!empty($mod['git_url'])): ?>
                                            <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/maj-git" class="d-inline">
                                                <?= \Platform\Http\Csrf::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Mettre &agrave; jour depuis Git">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/basculer" class="d-inline">
                                                <?= \Platform\Http\Csrf::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $mod['enabled'] ? 'D&eacute;sactiver' : 'Activer' ?>">
                                                    <i class="bi <?= $mod['enabled'] ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="D&eacute;sinstaller"
                                                    data-bs-toggle="modal" data-bs-target="#modalDesinstaller<?= $mod['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if ($nbPlugins === 0): ?>
                                <tr class="plugin-placeholder">
                                    <td colspan="8" class="text-center text-muted py-3" style="font-size:0.85rem;">
                                        D&eacute;posez un plugin ici
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($modulesParCategorie)): ?>
                <div class="card">
                    <div class="card-body text-center text-muted py-4">Aucun module install&eacute;.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Onglet Cat&eacute;gories -->
    <div class="tab-pane fade <?= $ongletActif === 'categories' ? 'show active' : '' ?>"
         id="tab-categories" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0" style="font-size:0.9rem;">
                <i class="bi bi-grip-vertical me-1"></i> Glissez les lignes pour r&eacute;ordonner les cat&eacute;gories.
            </p>
            <a href="/admin/categories/creer" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Nouvelle cat&eacute;gorie
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Nom</th>
                                <th>Ic&ocirc;ne</th>
                                <th>Ordre</th>
                                <th>Plugins</th>
                                <th style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesBody">
                            <?php foreach ($categories as $cat): ?>
                            <tr data-id="<?= $cat['id'] ?>">
                                <td class="drag-handle" style="cursor: grab; color: var(--brand-teal);">
                                    <i class="bi bi-grip-vertical"></i>
                                </td>
                                <td><strong><?= htmlspecialchars($cat['nom']) ?></strong></td>
                                <td>
                                    <i class="bi <?= htmlspecialchars($cat['icone'] ?? 'bi-folder') ?> me-1" style="color: var(--brand-teal);"></i>
                                    <code style="font-size: 0.8rem;"><?= htmlspecialchars($cat['icone'] ?? 'bi-folder') ?></code>
                                </td>
                                <td class="sort-order-cell"><?= (int) $cat['sort_order'] ?></td>
                                <td>
                                    <?php if ((int) $cat['nb_plugins'] > 0): ?>
                                        <span class="badge badge-active"><?= (int) $cat['nb_plugins'] ?></span>
                                        <?php if (!empty($cat['plugins_noms'])): ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars(implode(', ', explode('||', $cat['plugins_noms']))) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="/admin/categories/<?= $cat['id'] ?>/editer" class="btn btn-sm btn-outline-secondary" title="&Eacute;diter">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="/admin/categories/<?= $cat['id'] ?>/supprimer" class="d-inline"
                                              onsubmit="return confirm('Supprimer la cat&eacute;gorie &laquo; <?= addslashes($cat['nom']) ?> &raquo; ? Les plugins associ&eacute;s deviendront non class&eacute;s.')">
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
                                <td colspan="6" class="text-center text-muted py-4">Aucune cat&eacute;gorie cr&eacute;&eacute;e.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Cl&eacute;s d'API -->
    <div class="tab-pane fade <?= $ongletActif === 'cles-api' ? 'show active' : '' ?>"
         id="tab-cles-api" role="tabpanel">
        <p class="text-muted mb-3" style="font-size:0.9rem;">
            G&eacute;rez les cl&eacute;s d'API utilis&eacute;es par les plugins. Les valeurs sont stock&eacute;es dans le fichier <code>.env</code> de la plateforme.
        </p>

        <?php if (empty($modulesAvecCles)): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-4">
                    Aucun plugin ne d&eacute;clare de cl&eacute; d'environnement.
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Cl&eacute;</th>
                                    <th>Valeur actuelle</th>
                                    <th>Statut</th>
                                    <th style="width: 200px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modulesAvecCles as $mod): ?>
                                    <?php foreach ($mod['_cles_env_liste'] as $cle): ?>
                                        <?php
                                            $valeur = array_key_exists($cle, $_ENV) ? (string) $_ENV[$cle] : '';
                                            if ($valeur === '') {
                                                $envGetenv = getenv($cle);
                                                $valeur = $envGetenv !== false ? $envGetenv : '';
                                            }
                                            $estPresente = $valeur !== '';
                                            $valeurMasquee = $estPresente && strlen($valeur) > 4
                                                ? str_repeat('&bull;', 4) . htmlspecialchars(substr($valeur, -4))
                                                : ($estPresente ? '****' : '');
                                        ?>
                                        <tr>
                                            <td>
                                                <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> me-1" style="color: var(--brand-teal);"></i>
                                                <?= htmlspecialchars($mod['name']) ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($cle) ?></code></td>
                                            <td class="cle-valeur-masquee"><?= $valeurMasquee ?: '<span class="text-muted">&mdash;</span>' ?></td>
                                            <td>
                                                <?php if ($estPresente): ?>
                                                    <span class="badge badge-active"><i class="bi bi-check-circle me-1"></i>Configur&eacute;e</span>
                                                <?php else: ?>
                                                    <span class="badge badge-inactive"><i class="bi bi-exclamation-triangle me-1"></i>Manquante</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="cle-api-action" data-cle="<?= htmlspecialchars($cle) ?>">
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-modifier-cle">
                                                        <i class="bi bi-pencil me-1"></i> <?= $estPresente ? 'Modifier' : 'D&eacute;finir' ?>
                                                    </button>
                                                    <div class="cle-api-edit d-none">
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control cle-api-input"
                                                                   placeholder="Nouvelle valeur"
                                                                   value="">
                                                            <button type="button" class="btn btn-primary btn-sauver-cle">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary btn-annuler-cle">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Onglet MAJ Github -->
    <div class="tab-pane fade <?= $ongletActif === 'maj-git' ? 'show active' : '' ?>"
         id="tab-maj-git" role="tabpanel">

        <?php if (!empty($modulesGit)): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-muted mb-0" style="font-size:0.9rem;">
                    Plugins connect&eacute;s &agrave; un d&eacute;p&ocirc;t Git. Mettez &agrave; jour individuellement ou tous en une fois.
                </p>
                <button type="button" class="btn btn-primary btn-sm" id="btnMajTousGit">
                    <i class="bi bi-arrow-repeat me-1"></i> Tout mettre &agrave; jour
                </button>
            </div>

            <div id="majGitResultats" class="mb-3" style="display:none;"></div>

            <div class="card mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>D&eacute;p&ocirc;t</th>
                                    <th>Branche</th>
                                    <th>Dernier commit</th>
                                    <th>Dernier pull</th>
                                    <th>Version</th>
                                    <th style="width: 140px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modulesGit as $mod): ?>
                                <tr>
                                    <td>
                                        <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> me-1" style="color: var(--brand-teal);"></i>
                                        <strong><?= htmlspecialchars($mod['name']) ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($mod['slug']) ?></small>
                                    </td>
                                    <td>
                                        <a href="<?= htmlspecialchars($mod['git_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none" style="font-size: 0.85rem;">
                                            <?= htmlspecialchars(preg_replace('#^https://(github|gitlab)\.com/#', '', $mod['git_url'])) ?>
                                            <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                                        </a>
                                    </td>
                                    <td><code><?= htmlspecialchars($mod['git_branche'] ?? 'main') ?></code></td>
                                    <td>
                                        <?php if (!empty($mod['git_dernier_commit'])): ?>
                                            <code title="<?= htmlspecialchars($mod['git_dernier_commit']) ?>"><?= htmlspecialchars(substr($mod['git_dernier_commit'], 0, 7)) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($mod['git_dernier_pull'])): ?>
                                            <span style="font-size: 0.85rem;"><?= htmlspecialchars($mod['git_dernier_pull']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($mod['version'] ?? '-') ?></td>
                                    <td>
                                        <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/maj-git" class="d-inline">
                                            <input type="hidden" name="retour" value="maj-git">
                                            <?= \Platform\Http\Csrf::field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-arrow-repeat me-1"></i> Mettre &agrave; jour
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body text-center text-muted py-4">
                    Aucun plugin n'est connect&eacute; &agrave; un d&eacute;p&ocirc;t Git.
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($modulesSansGit)): ?>
            <h6 class="text-muted mt-4 mb-3"><i class="bi bi-info-circle me-1"></i> Plugins sans d&eacute;p&ocirc;t Git</h6>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Source</th>
                                    <th>Version</th>
                                    <th style="width: 160px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modulesSansGit as $mod): ?>
                                <tr>
                                    <td>
                                        <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> me-1" style="color: var(--brand-teal);"></i>
                                        <strong><?= htmlspecialchars($mod['name']) ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($mod['slug']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($mod['chemin_source']): ?>
                                            <span class="badge badge-source-external">Externe</span>
                                            <span class="chemin-source-text d-block mt-1" style="font-size: 0.8rem;"><?= htmlspecialchars($mod['chemin_source']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-source-embedded">Embarqu&eacute;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($mod['version'] ?? '-') ?></td>
                                    <td>
                                        <a href="/admin/plugins/<?= $mod['id'] ?>/editer" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-link-45deg me-1"></i> Associer un d&eacute;p&ocirc;t
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modales de d&eacute;sinstallation -->
<?php foreach ($modules as $mod): ?>
<div class="modal fade" id="modalDesinstaller<?= $mod['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/plugins/<?= $mod['id'] ?>/desinstaller">
                <?= \Platform\Http\Csrf::field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">D&eacute;sinstaller &laquo; <?= htmlspecialchars($mod['name']) ?> &raquo;</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>
                        <?php if ($mod['chemin_source']): ?>
                            <?php if (str_contains($mod['chemin_source'], 'storage/plugins')): ?>
                                Le r&eacute;pertoire extrait sera supprim&eacute;.
                            <?php else: ?>
                                Les fichiers sources ne seront pas touch&eacute;s.
                            <?php endif; ?>
                        <?php else: ?>
                            Le r&eacute;pertoire <code>modules/<?= htmlspecialchars($mod['slug']) ?>/</code> sera supprim&eacute;.
                        <?php endif; ?>
                    </p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="conserverReglages<?= $mod['id'] ?>"
                               checked onchange="document.getElementById('conserverReglagesInput<?= $mod['id'] ?>').value = this.checked ? '1' : '0'">
                        <label class="form-check-label" for="conserverReglages<?= $mod['id'] ?>">
                            Conserver les r&eacute;glages (acc&egrave;s, quotas, historique) pour une r&eacute;installation future
                        </label>
                        <input type="hidden" name="conserver_reglages" id="conserverReglagesInput<?= $mod['id'] ?>" value="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> D&eacute;sinstaller
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Drag-and-drop plugins entre catégories (SortableJS) ---
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function sauvegarderOrdrePlugins() {
        const donnees = [];
        document.querySelectorAll('.plugins-sortable').forEach(tb => {
            const catId = tb.dataset.categorieId;
            tb.querySelectorAll('tr[data-id]').forEach((tr, i) => {
                donnees.push({
                    id: tr.dataset.id,
                    sort_order: (i + 1) * 10,
                    categorie_id: catId === '0' ? '' : catId
                });
            });
        });

        const formData = new FormData();
        donnees.forEach((p, i) => {
            formData.append('plugins[' + i + '][id]', p.id);
            formData.append('plugins[' + i + '][sort_order]', p.sort_order);
            formData.append('plugins[' + i + '][categorie_id]', p.categorie_id);
        });
        formData.append('_csrf_token', csrfToken);

        fetch('/admin/plugins/reordonner', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // Mettre à jour les compteurs dans les en-têtes
                document.querySelectorAll('.categorie-bloc').forEach(bloc => {
                    const count = bloc.querySelectorAll('tr[data-id]').length;
                    const badge = bloc.querySelector('.card-header .badge');
                    if (badge) badge.textContent = count;
                });
            } else {
                console.error('Erreur réordonnement plugins:', data.erreur);
            }
        })
        .catch(err => console.error('Erreur réseau:', err));
    }

    if (typeof Sortable !== 'undefined') {
        document.querySelectorAll('.plugins-sortable').forEach(tbody => {
            Sortable.create(tbody, {
                group: 'plugins',
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: sauvegarderOrdrePlugins
            });
        });
    }

    // --- Drag-and-drop catégories (SortableJS) ---
    const categoriesBody = document.getElementById('categoriesBody');
    if (categoriesBody && typeof Sortable !== 'undefined') {
        Sortable.create(categoriesBody, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const lignes = categoriesBody.querySelectorAll('tr[data-id]');
                const ordres = [];
                lignes.forEach((tr, index) => {
                    ordres.push(tr.dataset.id);
                    // Mettre à jour l'affichage de l'ordre
                    const cellOrdre = tr.querySelector('.sort-order-cell');
                    if (cellOrdre) cellOrdre.textContent = (index + 1) * 10;
                });

                const formData = new FormData();
                ordres.forEach((id, i) => formData.append('ordres[' + i + ']', id));

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                    || document.querySelector('input[name="_csrf_token"]')?.value || '';
                formData.append('_csrf_token', csrfToken);

                fetch('/admin/categories/reordonner', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        // Recharger pour mettre à jour la sidebar
                        window.location.href = '/admin/plugins?onglet=categories';
                    } else {
                        console.error('Erreur reordonner:', data.erreur);
                    }
                })
                .catch(err => console.error('Erreur réseau:', err));
            }
        });
    }

    // --- Gestion des clés d'API ---
    document.querySelectorAll('.cle-api-action').forEach(container => {
        const cle = container.dataset.cle;
        const btnModifier = container.querySelector('.btn-modifier-cle');
        const editZone = container.querySelector('.cle-api-edit');
        const input = container.querySelector('.cle-api-input');
        const btnSauver = container.querySelector('.btn-sauver-cle');
        const btnAnnuler = container.querySelector('.btn-annuler-cle');

        if (!btnModifier) return;

        btnModifier.addEventListener('click', () => {
            btnModifier.classList.add('d-none');
            editZone.classList.remove('d-none');
            input.focus();
        });

        btnAnnuler.addEventListener('click', () => {
            editZone.classList.add('d-none');
            btnModifier.classList.remove('d-none');
            input.value = '';
        });

        btnSauver.addEventListener('click', () => {
            const valeur = input.value.trim();
            if (valeur === '') return;

            const formData = new FormData();
            formData.append('cle', cle);
            formData.append('valeur', valeur);

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_csrf_token"]')?.value || '';
            formData.append('_csrf_token', csrfToken);

            btnSauver.disabled = true;

            fetch('/admin/plugins/cles-env', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    // Recharger la page sur l'onglet clés d'API
                    window.location.href = '/admin/plugins?onglet=cles-api';
                } else {
                    alert(data.erreur || 'Erreur lors de la sauvegarde.');
                    btnSauver.disabled = false;
                }
            })
            .catch(() => {
                alert('Erreur réseau.');
                btnSauver.disabled = false;
            });
        });

        // Soumettre avec Entrée
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                btnSauver.click();
            }
            if (e.key === 'Escape') {
                btnAnnuler.click();
            }
        });
    });

    // --- Bouton MAJ tous Git ---
    const btnMajTous = document.getElementById('btnMajTousGit');
    if (btnMajTous) {
        btnMajTous.addEventListener('click', () => {
            btnMajTous.disabled = true;
            btnMajTous.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mise à jour en cours…';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_csrf_token"]')?.value || '';

            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            fetch('/admin/plugins/maj-git-tous', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                const zone = document.getElementById('majGitResultats');
                if (!data.ok || !data.resultats) {
                    zone.innerHTML = '<div class="alert alert-danger">Erreur lors de la mise à jour.</div>';
                    zone.style.display = '';
                    btnMajTous.disabled = false;
                    btnMajTous.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Tout mettre à jour';
                    return;
                }

                let html = '';
                data.resultats.forEach(r => {
                    if (r.succes) {
                        html += `<div class="alert alert-success py-2 mb-1"><i class="bi bi-check-circle me-1"></i> <strong>${r.name}</strong> — commit <code>${r.commit}</code>${r.version ? ' — v' + r.version : ''}</div>`;
                    } else {
                        html += `<div class="alert alert-danger py-2 mb-1"><i class="bi bi-x-circle me-1"></i> <strong>${r.name}</strong> — ${r.erreur}</div>`;
                    }
                });

                zone.innerHTML = html;
                zone.style.display = '';

                // Recharger après un délai pour mettre à jour le tableau
                setTimeout(() => window.location.href = '/admin/plugins?onglet=maj-git', 2000);
            })
            .catch(() => {
                const zone = document.getElementById('majGitResultats');
                zone.innerHTML = '<div class="alert alert-danger">Erreur réseau.</div>';
                zone.style.display = '';
                btnMajTous.disabled = false;
                btnMajTous.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Tout mettre à jour';
            });
        });
    }
});
</script>
