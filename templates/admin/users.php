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

        <p class="text-muted small mb-2">
            <strong><?= $pagination['total'] ?></strong> utilisateur(s) trouvé(s)
            <?php if ($filtreParams): ?>
                — <a href="/admin/users" class="text-decoration-none">Voir tous</a>
            <?php endif; ?>
        </p>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:50px;">ID</th>
                                <th>Nom d'utilisateur</th>
                                <th>Email</th>
                                <th>R&ocirc;le</th>
                                <th>Actif</th>
                                <th>Derni&egrave;re connexion</th>
                                <th style="width:70px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                    Aucun utilisateur ne correspond aux critères.
                                </td>
                            </tr>
                            <?php endif; ?>
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
                                        <th class="text-center" style="min-width: 100px;">
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
                                    <?php foreach ($modulesActifs as $mod): ?>
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   name="access[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                                   value="1"
                                                   <?= !empty($matriceAcces[$u['id']][$mod['id']]) ? 'checked' : '' ?>>
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
                                                <input type="number"
                                                       class="form-control form-control-sm text-center"
                                                       name="quotas[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                                       value="<?= isset($matriceQuotas[$u['id']][$mod['id']]) ? (int) $matriceQuotas[$u['id']][$mod['id']] : '' ?>"
                                                       min="0"
                                                       placeholder="<?= (int) $mod['default_quota'] ?>"
                                                       style="width: 90px; margin: 0 auto;">
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
</div>

<style>.btn-xs { font-size: 0.65rem; padding: 0.1rem 0.35rem; }</style>
<script>
function toggleTousAcces(userId, cocher) {
    var ligne = document.querySelector('tr[data-user-row="' + userId + '"]');
    if (!ligne) return;
    ligne.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = cocher; });
}
</script>
