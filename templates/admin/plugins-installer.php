<h2 class="mb-4">Installer un plugin</h2>

<!-- Phase 1 : Détection (2 onglets) -->
<div class="card mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-zip" data-bs-toggle="tab" data-bs-target="#pane-zip"
                        type="button" role="tab" aria-controls="pane-zip" aria-selected="true">
                    <i class="bi bi-file-earmark-zip me-1"></i> Upload ZIP
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-git" data-bs-toggle="tab" data-bs-target="#pane-git"
                        type="button" role="tab" aria-controls="pane-git" aria-selected="false">
                    <i class="bi bi-github me-1"></i> GitHub
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-chemin" data-bs-toggle="tab" data-bs-target="#pane-chemin"
                        type="button" role="tab" aria-controls="pane-chemin" aria-selected="false">
                    <i class="bi bi-folder me-1"></i> Chemin sur disque <small class="text-muted">(avancé)</small>
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <!-- Onglet ZIP -->
            <div class="tab-pane fade show active" id="pane-zip" role="tabpanel" aria-labelledby="tab-zip">
                <div class="row align-items-end">
                    <div class="col-lg-9">
                        <label for="fichier_zip" class="form-label">Fichier ZIP du plugin</label>
                        <input type="file" class="form-control" id="fichier_zip" accept=".zip">
                        <small class="text-muted">Le ZIP doit contenir un fichier <code>module.json</code> à la racine (ou dans un sous-dossier unique). Taille max : 50 Mo.</small>
                    </div>
                    <div class="col-lg-3 mt-2 mt-lg-0">
                        <button type="button" class="btn btn-primary w-100" id="btn-analyser-zip">
                            <i class="bi bi-search me-1"></i> Analyser le ZIP
                        </button>
                    </div>
                </div>
                <div id="zip-resultat" class="mt-3" style="display: none;"></div>
            </div>

            <!-- Onglet GitHub -->
            <div class="tab-pane fade" id="pane-git" role="tabpanel" aria-labelledby="tab-git">
                <div class="row align-items-end">
                    <div class="col-lg-7">
                        <label for="git_url_detect" class="form-label">URL du dépôt GitHub</label>
                        <input type="text" class="form-control" id="git_url_detect"
                               placeholder="https://github.com/user/mon-plugin">
                        <small class="text-muted">URL HTTPS d'un dépôt GitHub ou GitLab (repos privés supportés via token).</small>
                    </div>
                    <div class="col-lg-2 mt-2 mt-lg-0">
                        <label for="git_branche_detect" class="form-label">Branche</label>
                        <input type="text" class="form-control" id="git_branche_detect" value="main"
                               placeholder="main">
                    </div>
                    <div class="col-lg-3 mt-2 mt-lg-0">
                        <button type="button" class="btn btn-primary w-100" id="btn-detecter-git">
                            <i class="bi bi-github me-1"></i> Détecter
                        </button>
                    </div>
                </div>
                <div id="git-resultat" class="mt-3" style="display: none;"></div>
            </div>

            <!-- Onglet Chemin -->
            <div class="tab-pane fade" id="pane-chemin" role="tabpanel" aria-labelledby="tab-chemin">
                <div class="row align-items-end">
                    <div class="col-lg-9">
                        <label for="chemin_detect" class="form-label">Chemin du projet sur le disque</label>
                        <input type="text" class="form-control" id="chemin_detect"
                               placeholder="/home/aymeric/projects/mon-outil">
                    </div>
                    <div class="col-lg-3 mt-2 mt-lg-0">
                        <button type="button" class="btn btn-primary w-100" id="btn-detecter">
                            <i class="bi bi-folder-check me-1"></i> Détecter
                        </button>
                    </div>
                </div>
                <div id="detection-resultat" class="mt-3" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Phase 2 : Formulaire (caché par défaut) -->
<form method="POST" action="/admin/plugins/installer" id="form-installer" enctype="multipart/form-data" style="display: none;">
    <?= \Platform\Http\Csrf::field() ?>
    <input type="hidden" name="mode_installation" id="mode_installation" value="chemin">
    <input type="hidden" name="chemin_source" id="chemin_source">
    <input type="hidden" name="git_url" id="git_url">
    <input type="hidden" name="git_branche" id="git_branche" value="main">

    <div class="row g-4">
        <!-- Colonne gauche : Infos de base -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Informations</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" id="slug" name="slug" required
                               pattern="[a-z0-9][a-z0-9-]*[a-z0-9]"
                               placeholder="mon-outil">
                        <small class="text-muted">Lettres minuscules, chiffres et tirets uniquement</small>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="name" name="name" required
                               placeholder="Mon Outil SEO">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                                  placeholder="Description courte de l'outil"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="version" class="form-label">Version</label>
                                <input type="text" class="form-control" id="version" name="version" value="1.0.0">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="icon" class="form-label">Icône Bootstrap</label>
                                <input type="text" class="form-control" id="icon" name="icon" value="bi-tools"
                                       placeholder="bi-tools">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="point_entree" class="form-label">Point d'entrée</label>
                                <input type="text" class="form-control" id="point_entree" name="point_entree" value="index.php">
                                <small class="text-muted">Nom du fichier relatif au répertoire source (ex: index.php, adapter.php)</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="sort_order" class="form-label">Ordre d'affichage</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="100" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="categorie_id" class="form-label">Catégorie</label>
                        <select class="form-select" id="categorie_id" name="categorie_id">
                            <option value="">— Aucune (Non classé) —</option>
                            <?php foreach ($categories ?? [] as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
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
                                <option value="<?= $mode->value ?>"><?= htmlspecialchars($mode->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="default_quota" class="form-label">Quota mensuel par défaut</label>
                        <input type="number" class="form-control" id="default_quota" name="default_quota" value="0" min="0">
                    </div>

                    <div class="mb-3">
                        <label for="cles_env" class="form-label">Clés d'environnement</label>
                        <input type="text" class="form-control" id="cles_env" name="cles_env"
                               placeholder="API_KEY, SECRET_TOKEN">
                        <small class="text-muted">Séparées par des virgules</small>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="mode_affichage" class="form-label">Mode d'affichage</label>
                        <select class="form-select" id="mode_affichage" name="mode_affichage">
                            <?php foreach (\Platform\Enum\ModeAffichage::cases() as $mode): ?>
                                <option value="<?= $mode->value ?>"><?= htmlspecialchars($mode->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Intégré : extraction HTML. Iframe : app complète isolée. Passthrough : sans layout.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-download me-1"></i> Installer
        </button>
        <a href="/admin/plugins" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>

<?= $footExtra = '' ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-installer');
    const csrfToken = document.querySelector('[name="_csrf_token"]').value;

    // ========================================
    // Onglet ZIP
    // ========================================
    const btnAnalyserZip = document.getElementById('btn-analyser-zip');
    const fichierZipInput = document.getElementById('fichier_zip');
    const zipResultat = document.getElementById('zip-resultat');

    // Variable pour stocker le fichier ZIP sélectionné
    let fichierZipSelectionne = null;

    fichierZipInput.addEventListener('change', function() {
        fichierZipSelectionne = this.files[0] || null;
    });

    btnAnalyserZip.addEventListener('click', function() {
        if (!fichierZipSelectionne) {
            zipResultat.style.display = 'block';
            zipResultat.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Veuillez sélectionner un fichier ZIP.</div>';
            return;
        }

        // Vérification taille côté client (50 Mo max)
        const TAILLE_MAX = 50 * 1024 * 1024;
        if (fichierZipSelectionne.size > TAILLE_MAX) {
            zipResultat.style.display = 'block';
            zipResultat.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> Le fichier fait ' + (fichierZipSelectionne.size / 1024 / 1024).toFixed(1) + ' Mo. Taille max : 50 Mo.</div>';
            return;
        }

        btnAnalyserZip.disabled = true;
        btnAnalyserZip.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analyse...';

        const formData = new FormData();
        formData.append('fichier_zip', fichierZipSelectionne);
        formData.append('_csrf_token', csrfToken);

        fetch('/admin/plugins/analyser-zip', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
            body: formData
        })
        .then(gererReponse)
        .then(data => {
            zipResultat.style.display = 'block';

            if (!data.succes) {
                zipResultat.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> ' + escapeHtml(data.erreur) + '</div>';
                form.style.display = 'none';
                return;
            }

            if (data.detecte && data.donnees) {
                zipResultat.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> <strong>module.json détecté !</strong> Les champs ont été pré-remplis.</div>';
                remplirFormulaire('', data.donnees, data.donnees.entry_point || 'index.php');
            } else {
                zipResultat.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Aucun module.json trouvé dans le ZIP. Remplissez les champs manuellement.</div>';
                remplirFormulaire('', {}, 'index.php');
            }

            document.getElementById('mode_installation').value = 'zip';

            form.style.display = 'block';
        })
        .catch(() => {
            zipResultat.style.display = 'block';
            zipResultat.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> Erreur de communication avec le serveur.</div>';
        })
        .finally(() => {
            btnAnalyserZip.disabled = false;
            btnAnalyserZip.innerHTML = '<i class="bi bi-search me-1"></i> Analyser le ZIP';
        });
    });

    // Avant le submit natif, injecter le fichier ZIP dans le formulaire
    // via DataTransfer pour que le navigateur l'envoie avec la redirection
    // (fetch avalait les flash messages en suivant le 302 silencieusement)
    form.addEventListener('submit', function(e) {
        if (document.getElementById('mode_installation').value !== 'zip') {
            return; // Laisser le submit natif pour le mode chemin et git
        }

        if (!fichierZipSelectionne) {
            e.preventDefault();
            return;
        }

        // Injecter le fichier dans un input caché à l'intérieur du formulaire
        let hiddenFileInput = form.querySelector('input[name="fichier_zip"]');
        if (!hiddenFileInput) {
            hiddenFileInput = document.createElement('input');
            hiddenFileInput.type = 'file';
            hiddenFileInput.name = 'fichier_zip';
            hiddenFileInput.style.display = 'none';
            form.appendChild(hiddenFileInput);
        }

        const dt = new DataTransfer();
        dt.items.add(fichierZipSelectionne);
        hiddenFileInput.files = dt.files;

        // Laisser le submit natif se poursuivre → le navigateur suit le 302
        // et affiche correctement les flash messages (succès ou erreur)
    });

    // ========================================
    // Onglet GitHub
    // ========================================
    const btnDetecterGit = document.getElementById('btn-detecter-git');
    const gitUrlInput = document.getElementById('git_url_detect');
    const gitBrancheInput = document.getElementById('git_branche_detect');
    const gitResultat = document.getElementById('git-resultat');

    btnDetecterGit.addEventListener('click', function() {
        const url = gitUrlInput.value.trim();
        const branche = gitBrancheInput.value.trim() || 'main';
        if (!url) return;

        btnDetecterGit.disabled = true;
        btnDetecterGit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Détection...';

        fetch('/admin/plugins/detecter-git', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: 'git_url=' + encodeURIComponent(url) + '&git_branche=' + encodeURIComponent(branche) + '&_csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(gererReponse)
        .then(data => {
            gitResultat.style.display = 'block';

            if (!data.succes) {
                gitResultat.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> ' + escapeHtml(data.erreur) + '</div>';
                form.style.display = 'none';
                return;
            }

            if (data.detecte && data.donnees) {
                gitResultat.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> <strong>module.json détecté !</strong> Les champs ont été pré-remplis.</div>';
                remplirFormulaire('', data.donnees, data.point_entree || 'index.php');
            } else {
                gitResultat.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Aucun module.json trouvé dans le dépôt. Remplissez les champs manuellement.</div>';
                remplirFormulaire('', {slug: data.slug || ''}, 'index.php');
            }

            document.getElementById('mode_installation').value = 'git';
            document.getElementById('git_url').value = url;
            document.getElementById('git_branche').value = branche;

            form.style.display = 'block';
        })
        .catch(() => {
            gitResultat.style.display = 'block';
            gitResultat.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> Erreur de communication avec le serveur.</div>';
        })
        .finally(() => {
            btnDetecterGit.disabled = false;
            btnDetecterGit.innerHTML = '<i class="bi bi-github me-1"></i> Détecter';
        });
    });

    // ========================================
    // Onglet Chemin
    // ========================================
    const btnDetecter = document.getElementById('btn-detecter');
    const cheminInput = document.getElementById('chemin_detect');
    const resultat = document.getElementById('detection-resultat');

    btnDetecter.addEventListener('click', function() {
        const chemin = cheminInput.value.trim();
        if (!chemin) return;

        btnDetecter.disabled = true;
        btnDetecter.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Détection...';

        fetch('/admin/plugins/detecter', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: 'chemin=' + encodeURIComponent(chemin) + '&_csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(gererReponse)
        .then(data => {
            resultat.style.display = 'block';

            if (!data.succes) {
                resultat.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> ' + escapeHtml(data.erreur) + '</div>';
                form.style.display = 'none';
                return;
            }

            if (data.detecte && data.donnees) {
                resultat.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> <strong>module.json détecté !</strong> Les champs ont été pré-remplis.</div>';
                remplirFormulaire(chemin, data.donnees, data.point_entree);
            } else {
                resultat.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Aucun module.json trouvé. Remplissez les champs manuellement.</div>';
                remplirFormulaire(chemin, {}, data.point_entree);
            }

            document.getElementById('mode_installation').value = 'chemin';
            form.style.display = 'block';
        })
        .catch(() => {
            resultat.style.display = 'block';
            resultat.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> Erreur de communication avec le serveur.</div>';
        })
        .finally(() => {
            btnDetecter.disabled = false;
            btnDetecter.innerHTML = '<i class="bi bi-folder-check me-1"></i> Détecter';
        });
    });

    // ========================================
    // Fonctions communes
    // ========================================
    function remplirFormulaire(chemin, data, pointEntree) {
        document.getElementById('chemin_source').value = chemin;
        document.getElementById('slug').value = data.slug || '';
        document.getElementById('name').value = data.name || '';
        document.getElementById('description').value = data.description || '';
        document.getElementById('version').value = data.version || '1.0.0';
        document.getElementById('icon').value = data.icon || 'bi-tools';
        document.getElementById('point_entree').value = data.entry_point || pointEntree || 'index.php';
        document.getElementById('sort_order').value = data.sort_order || 100;
        document.getElementById('quota_mode').value = data.quota_mode || 'none';
        document.getElementById('default_quota').value = data.default_quota || 0;
        document.getElementById('cles_env').value = (data.env_keys || []).join(', ');
        document.getElementById('mode_affichage').value = data.display_mode || 'embedded';
        document.getElementById('categorie_id').value = data.categorie_id || '';
    }

    function gererReponse(response) {
        if (response.status === 401) {
            window.location.href = '/login';
            return Promise.reject(new Error('session_expiree'));
        }
        return response.json();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
