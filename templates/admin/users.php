<?php
$ongletActif = $onglet ?? 'utilisateurs';
$filtres = $filtres ?? ['q' => '', 'role' => '', 'actif' => ''];
$filtreParams = array_filter($filtres, fn($v) => $v !== '');
$filtreQueryString = $filtreParams ? '&' . http_build_query($filtreParams) : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Utilisateurs</h2>
    <a href="/admin/users/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nouvel utilisateur
    </a>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'utilisateurs' ? 'active' : '' ?>"
           id="tab-utilisateurs-btn" data-bs-toggle="tab" href="#tab-utilisateurs"
           role="tab" aria-selected="<?= $ongletActif === 'utilisateurs' ? 'true' : 'false' ?>">
            <i class="bi bi-people me-1"></i> Utilisateurs
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'acces' ? 'active' : '' ?>"
           id="tab-acces-btn" data-bs-toggle="tab" href="#tab-acces"
           role="tab" aria-selected="<?= $ongletActif === 'acces' ? 'true' : 'false' ?>">
            <i class="bi bi-shield-check me-1"></i> Acc&egrave;s
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'quotas' ? 'active' : '' ?>"
           id="tab-quotas-btn" data-bs-toggle="tab" href="#tab-quotas"
           role="tab" aria-selected="<?= $ongletActif === 'quotas' ? 'true' : 'false' ?>">
            <i class="bi bi-speedometer2 me-1"></i> Quotas
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $ongletActif === 'defauts' ? 'active' : '' ?>"
           id="tab-defauts-btn" data-bs-toggle="tab" href="#tab-defauts"
           role="tab" aria-selected="<?= $ongletActif === 'defauts' ? 'true' : 'false' ?>">
            <i class="bi bi-sliders me-1"></i> Quotas par d&eacute;faut
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Onglet Utilisateurs -->
    <div class="tab-pane fade <?= $ongletActif === 'utilisateurs' ? 'show active' : '' ?>"
         id="tab-utilisateurs" role="tabpanel">

        <form method="GET" action="/admin/users" class="mb-3">
            <input type="hidden" name="onglet" value="utilisateurs">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="filtre-q" class="form-label small mb-1">Rechercher</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="filtre-q" name="q"
                               value="<?= htmlspecialchars($filtres['q']) ?>"
                               placeholder="Nom ou email...">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="filtre-role" class="form-label small mb-1">Rôle</label>
                    <select class="form-select form-select-sm" id="filtre-role" name="role">
                        <option value="">Tous</option>
                        <option value="admin" <?= $filtres['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="user" <?= $filtres['role'] === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtre-actif" class="form-label small mb-1">Statut</label>
                    <select class="form-select form-select-sm" id="filtre-actif" name="actif">
                        <option value="">Tous</option>
                        <option value="1" <?= $filtres['actif'] === '1' ? 'selected' : '' ?>>Actif</option>
                        <option value="0" <?= $filtres['actif'] === '0' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filtrer
                    </button>
                    <?php if ($filtreParams): ?>
                    <a href="/admin/users" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Réinitialiser
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="text-muted small mb-0">
                <strong><?= $pagination['total'] ?></strong> utilisateur(s) trouvé(s)
                <?php if ($filtreParams): ?>
                    — <a href="/admin/users" class="text-decoration-none">Voir tous</a>
                <?php endif; ?>
            </p>
            <div class="d-flex align-items-center gap-2" id="barreActionsGroupees" style="display:none!important;">
                <span id="compteurSelection" class="text-muted small">0 sélectionné(s)</span>
                <select class="form-select form-select-sm" id="actionGroupee" style="width:auto;">
                    <option value="">— Action —</option>
                    <option value="activer">Activer</option>
                    <option value="desactiver">Désactiver</option>
                    <option value="supprimer">Supprimer</option>
                </select>
                <button class="btn btn-sm btn-danger" id="btnExecuterAction" disabled>
                    <i class="bi bi-play-fill me-1"></i>Exécuter
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="checkTous" title="Sélectionner tous">
                                </th>
                                <th style="width:50px;">ID</th>
                                <th>Nom d'utilisateur</th>
                                <th>Email</th>
                                <th>R&ocirc;le</th>
                                <th>Groupe</th>
                                <th>Actif</th>
                                <th>Derni&egrave;re connexion</th>
                                <th style="width:100px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                    Aucun utilisateur ne correspond aux critères.
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ((int) $u['id'] !== $currentUser['id']): ?>
                                    <input type="checkbox" class="form-check-input check-utilisateur" value="<?= $u['id'] ?>">
                                    <?php endif; ?>
                                </td>
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
                                    <?php if (!empty($u['groupe_nom'])): ?>
                                        <span class="badge" style="background: var(--brand-teal-light); color: var(--brand-dark); font-weight: 600;"><?= htmlspecialchars($u['groupe_nom']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
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
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-voir-profil" data-user-id="<?= $u['id'] ?>" title="Voir le profil">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="/admin/users/<?= $u['id'] ?>/edit" class="btn btn-sm btn-outline-secondary" title="Modifier">
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

        <!-- Modale confirmation action groupée -->
        <div class="modal fade" id="modalActionGroupee" tabindex="-1">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Confirmation</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="modalActionMessage"></div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-sm" id="btnConfirmerAction">Confirmer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modale profil utilisateur (A5) -->
        <div class="modal fade" id="modalProfil" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title"><i class="bi bi-person-circle me-1"></i> <span id="profilUsername"></span></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="profilContenu">
                        <div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <a href="#" class="btn btn-sm btn-primary" id="profilLienEditer"><i class="bi bi-pencil me-1"></i>Modifier</a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($pagination) && $pagination['totalPages'] > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center">
                <?php if ($pagination['page'] > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="/admin/users?page=<?= $pagination['page'] - 1 ?><?= $filtreQueryString ?>">Pr&eacute;c&eacute;dent</a>
                    </li>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                    <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="/admin/users?page=<?= $p ?><?= $filtreQueryString ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                    <li class="page-item">
                        <a class="page-link" href="/admin/users?page=<?= $pagination['page'] + 1 ?><?= $filtreQueryString ?>">Suivant</a>
                    </li>
                <?php endif; ?>
            </ul>
            <p class="text-center small text-muted"><?= $pagination['total'] ?> utilisateur(s)</p>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Onglet Acc&egrave;s -->
    <div class="tab-pane fade <?= $ongletActif === 'acces' ? 'show active' : '' ?>"
         id="tab-acces" role="tabpanel">
        <p class="text-muted mb-3" style="font-size:0.9rem;">Cochez les modules auxquels chaque utilisateur a acc&egrave;s.</p>

        <form method="POST" action="/admin/access">
            <?= \Platform\Http\Csrf::field() ?>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered access-matrix mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <?php foreach ($modulesActifs as $mod): ?>
                                        <th class="text-center" style="min-width: 130px;">
                                            <i class="bi <?= htmlspecialchars($mod['icon']) ?> d-block mb-1"></i>
                                            <?= htmlspecialchars($mod['name']) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tousLesUtilisateurs as $u): ?>
                                <tr data-user-row="<?= $u['id'] ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge badge-role-admin ms-1">admin</span>
                                        <?php endif; ?>
                                        <div class="mt-1">
                                            <button type="button" class="btn btn-outline-secondary btn-xs" onclick="toggleTousAcces(<?= $u['id'] ?>, true)">Tout</button>
                                            <button type="button" class="btn btn-outline-secondary btn-xs" onclick="toggleTousAcces(<?= $u['id'] ?>, false)">Aucun</button>
                                        </div>
                                    </td>
                                    <?php foreach ($modulesActifs as $mod):
                                        $acces = $matriceAcces[$u['id']][$mod['id']] ?? null;
                                        $estAccorde = !empty($acces['granted']);
                                        $expireAt = $acces['expires_at'] ?? null;
                                    ?>
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   class="form-check-input cb-acces"
                                                   name="access[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                                   value="1"
                                                   data-uid="<?= $u['id'] ?>" data-mid="<?= $mod['id'] ?>"
                                                   <?= $estAccorde ? 'checked' : '' ?>>
                                            <div class="mt-1 wrapper-expiration" style="<?= $estAccorde ? '' : 'display:none;' ?>">
                                                <input type="date"
                                                       class="form-control form-control-sm input-expiration"
                                                       name="access_expires[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                                       value="<?= $expireAt ? date('Y-m-d', strtotime($expireAt)) : '' ?>"
                                                       style="width:120px; font-size:0.7rem; margin:0 auto;"
                                                       title="Date d'expiration (optionnel)">
                                                <?php if ($expireAt): ?>
                                                    <span class="badge bg-warning text-dark mt-1" style="font-size:0.65rem;">
                                                        Expire le <?= date('d/m', strtotime($expireAt)) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
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
                <i class="bi bi-check-lg me-1"></i> Enregistrer les acc&egrave;s
            </button>
        </form>
    </div>

    <!-- Onglet Quotas -->
    <div class="tab-pane fade <?= $ongletActif === 'quotas' ? 'show active' : '' ?>"
         id="tab-quotas" role="tabpanel">
        <p class="text-muted mb-3" style="font-size:0.9rem;">D&eacute;finissez les limites d'utilisation par utilisateur et par module. Laissez vide pour utiliser la valeur par d&eacute;faut du module. <code style="background:var(--brand-teal-light);color:var(--brand-dark);padding:0.15em 0.35em;border-radius:0.25rem;">0</code> = illimit&eacute;.</p>

        <form method="POST" action="/admin/quotas">
            <?= \Platform\Http\Csrf::field() ?>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <?php foreach ($modulesActifs as $mod): ?>
                                        <th class="text-center" style="min-width: 120px;">
                                            <i class="bi <?= htmlspecialchars($mod['icon']) ?> d-block mb-1"></i>
                                            <?= htmlspecialchars($mod['name']) ?>
                                            <div class="small" style="color: var(--text-muted); font-weight:400;">
                                                <?= htmlspecialchars($mod['quota_mode']) ?>
                                                <?php if ((int) $mod['default_quota'] > 0): ?>
                                                    <br>d&eacute;faut: <?= (int) $mod['default_quota'] ?>
                                                <?php else: ?>
                                                    <br>d&eacute;faut: illimit&eacute;
                                                <?php endif; ?>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tousLesUtilisateurs as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge badge-role-admin ms-1">admin</span>
                                            <div class="small" style="color:var(--text-muted);">Exempt des quotas</div>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($modulesActifs as $mod): ?>
                                        <td class="text-center">
                                            <?php if ($mod['quota_mode'] === 'none'): ?>
                                                <span style="color:var(--text-muted);" class="small">&mdash;</span>
                                            <?php else:
                                                $usageCourant = $matriceUsage[$u['id']][$mod['id']] ?? 0;
                                                $limiteEffective = isset($matriceQuotas[$u['id']][$mod['id']])
                                                    ? (int) $matriceQuotas[$u['id']][$mod['id']]
                                                    : (int) $mod['default_quota'];
                                            ?>
                                                <?php $estPersonnalise = isset($matriceQuotas[$u['id']][$mod['id']]); ?>
                                                <input type="number"
                                                       class="form-control form-control-sm text-center <?= $estPersonnalise ? 'quota-personnalise' : '' ?>"
                                                       name="quotas[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                                       value="<?= $estPersonnalise ? (int) $matriceQuotas[$u['id']][$mod['id']] : '' ?>"
                                                       min="0"
                                                       placeholder="<?= (int) $mod['default_quota'] ?>"
                                                       style="width: 90px; margin: 0 auto;"
                                                       title="<?= $estPersonnalise ? 'Quota personnalisé' : 'Quota par défaut (' . (int) $mod['default_quota'] . ')' ?>"
                                                >
                                                <?php if ($usageCourant > 0 && $u['role'] !== 'admin'): ?>
                                                    <div class="small mt-1" style="font-size: 0.7rem; color: <?= ($limiteEffective > 0 && $usageCourant >= $limiteEffective) ? 'var(--bs-danger)' : (($limiteEffective > 0 && $usageCourant >= $limiteEffective * 0.8) ? 'var(--color-warn)' : 'var(--text-muted)') ?>;">
                                                        <?= $usageCourant ?><?php if ($limiteEffective > 0): ?> / <?= $limiteEffective ?><?php endif; ?> utilis&eacute;s
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
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
                <i class="bi bi-check-lg me-1"></i> Enregistrer les quotas
            </button>
        </form>
    </div>

    <!-- Onglet Quotas par d&eacute;faut -->
    <div class="tab-pane fade <?= $ongletActif === 'defauts' ? 'show active' : '' ?>"
         id="tab-defauts" role="tabpanel">
        <p class="text-muted mb-3" style="font-size:0.9rem;">D&eacute;finissez le quota mensuel par d&eacute;faut pour chaque plugin. Cette valeur s'applique &agrave; tout nouvel utilisateur. <code style="background:var(--brand-teal-light);color:var(--brand-dark);padding:0.15em 0.35em;border-radius:0.25rem;">0</code> = illimit&eacute;.</p>

        <form method="POST" action="/admin/quotas/defauts">
            <?= \Platform\Http\Csrf::field() ?>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th style="width: 150px;">Mode de quota</th>
                                    <th style="width: 180px;">Quota par d&eacute;faut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modulesActifs as $mod):
                                    if (($mod['quota_mode'] ?? 'none') === 'none') continue;
                                    $qm = \Platform\Enum\QuotaMode::tryFrom($mod['quota_mode']) ?? \Platform\Enum\QuotaMode::None;
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($mod['icon'])): ?>
                                            <i class="bi <?= htmlspecialchars($mod['icon']) ?> me-1" style="color:var(--brand-teal);"></i>
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($mod['name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--brand-teal-light); color: var(--brand-dark);">
                                            <?= htmlspecialchars($qm->label()) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="number"
                                               class="form-control form-control-sm text-center"
                                               name="defauts[<?= (int) $mod['id'] ?>]"
                                               value="<?= (int) ($mod['default_quota'] ?? 0) ?>"
                                               min="0"
                                               style="width: 120px; margin: 0 auto;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">
                <i class="bi bi-check-lg me-1"></i> Enregistrer les quotas par d&eacute;faut
            </button>
        </form>
    </div>
</div>

<style>
.btn-xs { font-size: 0.65rem; padding: 0.1rem 0.35rem; }
.quota-personnalise { font-weight: 600; border-color: var(--brand-teal) !important; background-color: var(--brand-teal-light) !important; }
</style>
<script>
function toggleTousAcces(userId, cocher) {
    var ligne = document.querySelector('tr[data-user-row="' + userId + '"]');
    if (!ligne) return;
    ligne.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = cocher; });
    ligne.querySelectorAll('.wrapper-expiration').forEach(function (w) { w.style.display = cocher ? '' : 'none'; });
}

// Afficher/masquer le champ date d'expiration selon la checkbox d'acces
document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('cb-acces')) return;
    var wrapper = e.target.closest('td').querySelector('.wrapper-expiration');
    if (wrapper) wrapper.style.display = e.target.checked ? '' : 'none';
});

// --- Actions groupées ---
(function () {
    var checkTous = document.getElementById('checkTous');
    var barre = document.getElementById('barreActionsGroupees');
    var compteur = document.getElementById('compteurSelection');
    var selectAction = document.getElementById('actionGroupee');
    var btnExecuter = document.getElementById('btnExecuterAction');
    var btnConfirmer = document.getElementById('btnConfirmerAction');
    var modalMessage = document.getElementById('modalActionMessage');

    if (!checkTous) return;

    var actionLabels = {
        supprimer:   { message: 'Voulez-vous supprimer', icon: 'bi-trash', btnClass: 'btn-danger', btnLabel: 'Supprimer' },
        activer:     { message: 'Voulez-vous activer',   icon: 'bi-check-circle', btnClass: 'btn-success', btnLabel: 'Activer' },
        desactiver:  { message: 'Voulez-vous désactiver', icon: 'bi-slash-circle', btnClass: 'btn-warning', btnLabel: 'Désactiver' }
    };

    function getCheckboxes() { return document.querySelectorAll('.check-utilisateur'); }
    function getSelectionnes() { return document.querySelectorAll('.check-utilisateur:checked'); }

    function mettreAJourBarre() {
        var n = getSelectionnes().length;
        compteur.textContent = n + ' sélectionné(s)';
        barre.style.cssText = n > 0 ? '' : 'display:none!important';
        btnExecuter.disabled = n === 0 || !selectAction.value;
        var total = getCheckboxes().length;
        checkTous.checked = total > 0 && n === total;
        checkTous.indeterminate = n > 0 && n < total;
    }

    checkTous.addEventListener('change', function () {
        getCheckboxes().forEach(function (cb) { cb.checked = checkTous.checked; });
        mettreAJourBarre();
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('check-utilisateur')) mettreAJourBarre();
    });

    selectAction.addEventListener('change', function () {
        btnExecuter.disabled = getSelectionnes().length === 0 || !selectAction.value;
    });

    btnExecuter.addEventListener('click', function () {
        var n = getSelectionnes().length;
        var action = selectAction.value;
        if (n === 0 || !action) return;
        var label = actionLabels[action];
        if (!label) return;
        modalMessage.innerHTML = label.message + ' <strong>' + n + '</strong> utilisateur(s) ?';
        btnConfirmer.className = 'btn btn-sm ' + label.btnClass;
        btnConfirmer.innerHTML = '<i class="bi ' + label.icon + ' me-1"></i>' + label.btnLabel;
        btnConfirmer.dataset.action = action;
        new bootstrap.Modal(document.getElementById('modalActionGroupee')).show();
    });

    btnConfirmer.addEventListener('click', function () {
        var ids = [];
        getSelectionnes().forEach(function (cb) { ids.push(parseInt(cb.value)); });
        var action = btnConfirmer.dataset.action;

        btnConfirmer.disabled = true;
        btnConfirmer.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>En cours...';

        fetch('/admin/users/bulk-action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, ids: ids })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            bootstrap.Modal.getInstance(document.getElementById('modalActionGroupee')).hide();
            if (res.ok) {
                afficherToast(res.data.message, 'success');
                setTimeout(function () { location.reload(); }, 800);
            } else {
                afficherToast(res.data.erreur || 'Erreur inconnue.', 'danger');
                btnConfirmer.disabled = false;
            }
        })
        .catch(function () {
            afficherToast('Erreur réseau.', 'danger');
            btnConfirmer.disabled = false;
        });
    });
})();

// --- A4 : Tri des colonnes ---
(function () {
    var table = document.querySelector('#tab-utilisateurs table');
    if (!table) return;
    var headers = table.querySelectorAll('thead th');
    var tbody = table.querySelector('tbody');
    var sortCol = -1, sortAsc = true;

    headers.forEach(function (th, idx) {
        // Ignorer checkbox et boutons
        if (idx === 0 || idx === headers.length - 1) return;
        th.style.cursor = 'pointer';
        th.title = 'Cliquer pour trier';
        th.addEventListener('click', function () {
            if (sortCol === idx) { sortAsc = !sortAsc; } else { sortCol = idx; sortAsc = true; }
            // Retirer les indicateurs précédents
            headers.forEach(function (h) { var s = h.querySelector('.sort-icon'); if (s) s.remove(); });
            var icon = document.createElement('i');
            icon.className = 'bi bi-caret-' + (sortAsc ? 'up' : 'down') + '-fill ms-1 sort-icon';
            icon.style.fontSize = '0.7rem';
            th.appendChild(icon);

            var rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort(function (a, b) {
                var aCell = a.children[idx], bCell = b.children[idx];
                if (!aCell || !bCell) return 0;
                var aVal = (aCell.textContent || '').trim().toLowerCase();
                var bVal = (bCell.textContent || '').trim().toLowerCase();
                // Trier numériquement si possible
                var aNum = parseFloat(aVal), bNum = parseFloat(bVal);
                if (!isNaN(aNum) && !isNaN(bNum)) return sortAsc ? aNum - bNum : bNum - aNum;
                return sortAsc ? aVal.localeCompare(bVal, 'fr') : bVal.localeCompare(aVal, 'fr');
            });
            rows.forEach(function (row) { tbody.appendChild(row); });
        });
    });
})();

// --- A5 : Modale profil utilisateur ---
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-voir-profil');
        if (!btn) return;
        var userId = btn.dataset.userId;
        var contenu = document.getElementById('profilContenu');
        var titre = document.getElementById('profilUsername');
        var lienEditer = document.getElementById('profilLienEditer');

        contenu.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>';
        titre.textContent = '';
        lienEditer.href = '/admin/users/' + userId + '/edit';
        new bootstrap.Modal(document.getElementById('modalProfil')).show();

        fetch('/admin/users/' + userId + '/details')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erreur) { contenu.innerHTML = '<div class="text-danger">' + d.erreur + '</div>'; return; }
            titre.textContent = d.username;
            var html = '<table class="table table-sm mb-3">';
            html += '<tr><td class="text-muted" style="width:140px;">Email</td><td>' + esc(d.email) + '</td></tr>';
            html += '<tr><td class="text-muted">Domaine</td><td>' + esc(d.domaine) + '</td></tr>';
            html += '<tr><td class="text-muted">Rôle</td><td>' + (d.role === 'admin' ? '<span class="badge badge-role-admin">Admin</span>' : '<span class="badge badge-role-user">User</span>') + '</td></tr>';
            html += '<tr><td class="text-muted">Statut</td><td>' + (d.active ? '<span class="badge badge-active">Actif</span>' : '<span class="badge badge-inactive">Inactif</span>') + '</td></tr>';
            html += '<tr><td class="text-muted">Dernière connexion</td><td>' + (d.lastLogin || 'Jamais') + '</td></tr>';
            html += '<tr><td class="text-muted">Créé le</td><td>' + (d.createdAt || '-') + '</td></tr>';
            html += '</table>';

            if (d.modules && d.modules.length > 0) {
                html += '<h6 class="small fw-bold mb-2">Modules accessibles (' + d.modules.length + ')</h6>';
                html += '<div class="d-flex flex-wrap gap-1 mb-3">';
                d.modules.forEach(function (m) { html += '<span class="badge" style="background:var(--brand-teal-light);color:var(--brand-dark);">' + esc(m) + '</span>'; });
                html += '</div>';
            }

            if (d.quotas && d.quotas.length > 0) {
                html += '<h6 class="small fw-bold mb-2">Quotas</h6><table class="table table-sm mb-0">';
                html += '<thead><tr><th>Module</th><th class="text-end">Utilisé</th><th class="text-end">Limite</th></tr></thead><tbody>';
                d.quotas.forEach(function (q) {
                    var pct = q.limite > 0 ? Math.round(q.utilise / q.limite * 100) : 0;
                    var couleur = pct >= 100 ? 'var(--bs-danger)' : (pct >= 80 ? 'var(--color-warn)' : 'var(--text-muted)');
                    html += '<tr><td>' + esc(q.module) + '</td><td class="text-end" style="color:' + couleur + ';">' + q.utilise + '</td><td class="text-end">' + (q.limite > 0 ? q.limite : '∞') + '</td></tr>';
                });
                html += '</tbody></table>';
            }

            contenu.innerHTML = html;
        })
        .catch(function () { contenu.innerHTML = '<div class="text-danger">Erreur de chargement.</div>'; });
    });

    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
})();
</script>
