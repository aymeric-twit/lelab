<h2 class="mb-4"><?= isset($groupe) ? 'Modifier le groupe' : 'Nouveau groupe' ?></h2>

<form method="POST">
    <?= \Platform\Http\Csrf::field() ?>

    <div class="row g-4">
        <!-- Colonne gauche : Infos groupe -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Informations</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nom du groupe</label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?= htmlspecialchars($groupe['name'] ?? '') ?>"
                               placeholder="Ex : &Eacute;quipe SEO, Clients Premium">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Description optionnelle du groupe"><?= htmlspecialchars($groupe['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne centre : Acc&egrave;s modules -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Acc&egrave;s aux modules</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Cochez les modules accessibles pour les membres de ce groupe.</p>

                    <div class="mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTousModules">Tout cocher</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAucunModule">Tout d&eacute;cocher</button>
                    </div>

                    <?php if (!empty($modules)): ?>
                    <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($modules as $mod):
                            $modId = (int) $mod['id'];
                            $estCoche = isset($accesModuleIds) && in_array($modId, $accesModuleIds);
                        ?>
                        <label class="list-group-item d-flex align-items-center gap-2 py-2" style="cursor: pointer;">
                            <input type="checkbox" class="form-check-input mt-0 cb-module"
                                   name="modules_access[]" value="<?= $modId ?>"
                                   <?= $estCoche ? 'checked' : '' ?>>
                            <?php if (!empty($mod['icon'])): ?>
                                <i class="bi <?= htmlspecialchars($mod['icon']) ?>" style="color:var(--brand-teal);"></i>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($mod['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <p class="text-muted">Aucun module actif.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne droite : Membres -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Membres</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">S&eacute;lectionnez les utilisateurs membres de ce groupe.</p>

                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" id="filtreUtilisateurs"
                               placeholder="Filtrer par nom...">
                    </div>

                    <div class="mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTousMembres">Tout cocher</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAucunMembre">Tout d&eacute;cocher</button>
                    </div>

                    <?php if (!empty($utilisateurs)): ?>
                    <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;" id="listeUtilisateurs">
                        <?php foreach ($utilisateurs as $u):
                            $uid = (int) $u['id'];
                            $estMembre = isset($membresIds) && in_array($uid, $membresIds);
                        ?>
                        <label class="list-group-item d-flex align-items-center gap-2 py-2 item-utilisateur" style="cursor: pointer;"
                               data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>">
                            <input type="checkbox" class="form-check-input mt-0 cb-membre"
                                   name="membres[]" value="<?= $uid ?>"
                                   <?= $estMembre ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($u['username']) ?></span>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-role-admin ms-auto">admin</span>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <p class="text-muted">Aucun utilisateur.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <?= isset($groupe) ? 'Enregistrer' : 'Cr&eacute;er' ?>
        </button>
        <a href="/admin/groups" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>

<script>
(function () {
    // Tout cocher / décocher modules
    document.getElementById('btnTousModules').addEventListener('click', function () {
        document.querySelectorAll('.cb-module').forEach(function (cb) { cb.checked = true; });
    });
    document.getElementById('btnAucunModule').addEventListener('click', function () {
        document.querySelectorAll('.cb-module').forEach(function (cb) { cb.checked = false; });
    });

    // Tout cocher / décocher membres
    document.getElementById('btnTousMembres').addEventListener('click', function () {
        document.querySelectorAll('.cb-membre').forEach(function (cb) { cb.checked = true; });
    });
    document.getElementById('btnAucunMembre').addEventListener('click', function () {
        document.querySelectorAll('.cb-membre').forEach(function (cb) { cb.checked = false; });
    });

    // Filtre utilisateurs
    var filtre = document.getElementById('filtreUtilisateurs');
    if (filtre) {
        filtre.addEventListener('input', function () {
            var val = this.value.trim().toLowerCase();
            document.querySelectorAll('.item-utilisateur').forEach(function (item) {
                var username = item.getAttribute('data-username') || '';
                item.style.display = username.indexOf(val) !== -1 ? '' : 'none';
            });
        });
    }
})();
</script>
