<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Enum\QuotaMode;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\GitClient;
use Platform\Module\ModuleRegistry;
use Platform\Module\PluginInstaller;
use Platform\Service\AuditLogger;
use Platform\User\AccessControl;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminPluginController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        $onglet = $req->get('onglet', 'plugins');

        $modules = $db->query(
            'SELECT m.*, u.username AS installe_par_nom, c.nom AS categorie_nom
             FROM modules m
             LEFT JOIN users u ON u.id = m.installe_par
             LEFT JOIN categories c ON c.id = m.categorie_id
             WHERE m.desinstalle_le IS NULL
             ORDER BY m.sort_order'
        )->fetchAll();

        // Enrichir chaque module avec le statut des cles d'environnement
        foreach ($modules as &$mod) {
            $clesEnv = !empty($mod['cles_env']) ? json_decode($mod['cles_env'], true) : [];
            $mod['_cles_env_liste'] = is_array($clesEnv) ? $clesEnv : [];
            $mod['_cles_env_statut'] = [];
            foreach ($mod['_cles_env_liste'] as $cle) {
                $valeur = array_key_exists($cle, $_ENV) ? (string) $_ENV[$cle] : '';
                if ($valeur === '') {
                    $envGetenv = getenv($cle);
                    $valeur = $envGetenv !== false ? $envGetenv : '';
                }
                $mod['_cles_env_statut'][$cle] = $valeur !== '';
            }
            // Lire api_doc_url depuis module.json si disponible
            $mod['_api_doc_url'] = '';
            $cheminSource = $mod['chemin_source'] ?? '';
            if ($cheminSource !== '') {
                $cheminModuleJson = $cheminSource . '/module.json';
                if (file_exists($cheminModuleJson)) {
                    $moduleJsonData = json_decode(file_get_contents($cheminModuleJson), true);
                    $mod['_api_doc_url'] = $moduleJsonData['api_doc_url'] ?? '';
                }
            }
        }
        unset($mod);

        // Catégories avec compteur plugins
        $categories = $db->query(
            "SELECT c.*, COUNT(m.id) AS nb_plugins,
                    GROUP_CONCAT(m.name ORDER BY m.sort_order SEPARATOR '||') AS plugins_noms
             FROM categories c
             LEFT JOIN modules m ON m.categorie_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order, c.nom"
        )->fetchAll();

        // Modules avec clés d'environnement (pour l'onglet Clés d'API)
        $modulesAvecCles = array_filter($modules, fn(array $m) => !empty($m['_cles_env_liste']));

        // Modules Git / sans Git (pour l'onglet MAJ Github)
        $modulesGit = array_filter($modules, fn(array $m) => !empty($m['git_url']));
        $modulesSansGit = array_filter($modules, fn(array $m) => empty($m['git_url']) && $m['enabled']);

        // Grouper les modules Git par catégorie
        $modulesGitParCategorie = [];
        foreach ($modulesGit as $mod) {
            $catId = (int) ($mod['categorie_id'] ?? 0);
            if (!isset($modulesGitParCategorie[$catId])) {
                $modulesGitParCategorie[$catId] = [
                    'nom'     => $mod['categorie_nom'] ?? null,
                    'icone'   => null,
                    'modules' => [],
                ];
            }
            $modulesGitParCategorie[$catId]['modules'][] = $mod;
        }
        foreach ($categories as $cat) {
            $catId = (int) $cat['id'];
            if (isset($modulesGitParCategorie[$catId])) {
                $modulesGitParCategorie[$catId]['icone'] = $cat['icone'] ?? 'bi-folder';
            }
        }
        uksort($modulesGitParCategorie, function (int $a, int $b) use ($categories) {
            if ($a === 0) {
                return 1;
            }
            if ($b === 0) {
                return -1;
            }
            $orderA = $orderB = 9999;
            foreach ($categories as $cat) {
                if ((int) $cat['id'] === $a) {
                    $orderA = (int) $cat['sort_order'];
                }
                if ((int) $cat['id'] === $b) {
                    $orderB = (int) $cat['sort_order'];
                }
            }
            return $orderA <=> $orderB;
        });

        // Grouper les plugins par catégorie (pour l'onglet Plugins)
        $modulesParCategorie = [];
        foreach ($modules as $mod) {
            $catId = (int) ($mod['categorie_id'] ?? 0);
            if (!isset($modulesParCategorie[$catId])) {
                $modulesParCategorie[$catId] = [
                    'nom'    => $mod['categorie_nom'] ?? null,
                    'icone'  => null,
                    'modules' => [],
                ];
            }
            $modulesParCategorie[$catId]['modules'][] = $mod;
        }
        // Enrichir avec icône depuis $categories
        foreach ($categories as $cat) {
            $catId = (int) $cat['id'];
            if (isset($modulesParCategorie[$catId])) {
                $modulesParCategorie[$catId]['icone'] = $cat['icone'] ?? 'bi-folder';
            }
        }
        // Trier : catégories par sort_order, "Non classé" (0) en dernier
        uksort($modulesParCategorie, function (int $a, int $b) use ($categories) {
            if ($a === 0) {
                return 1;
            }
            if ($b === 0) {
                return -1;
            }
            $orderA = 9999;
            $orderB = 9999;
            foreach ($categories as $cat) {
                if ((int) $cat['id'] === $a) {
                    $orderA = (int) $cat['sort_order'];
                }
                if ((int) $cat['id'] === $b) {
                    $orderB = (int) $cat['sort_order'];
                }
            }
            return $orderA <=> $orderB;
        });

        Layout::render('layout', [
            'template'            => 'admin/plugins',
            'pageTitle'           => 'Plugins',
            'currentUser'         => $user,
            'accessibleModules'   => $ac->getAccessibleModules($user['id']),
            'adminPage'           => 'plugins',
            'onglet'              => $onglet,
            'modules'             => $modules,
            'categories'          => $categories,
            'modulesAvecCles'     => $modulesAvecCles,
            'modulesGit'          => $modulesGit,
            'modulesSansGit'      => $modulesSansGit,
            'modulesParCategorie'    => $modulesParCategorie,
            'modulesGitParCategorie' => $modulesGitParCategorie,
        ]);
    }

    /**
     * POST /admin/plugins/reordonner — AJAX : réordonne les plugins et change de catégorie.
     */
    public function reordonnerPlugins(Request $req): void
    {
        $plugins = $req->post('plugins') ?? [];

        if (!is_array($plugins) || $plugins === []) {
            Response::json(['ok' => false, 'erreur' => 'Données manquantes.']);
        }

        $db = Connection::get();
        $stmt = $db->prepare('UPDATE modules SET sort_order = ?, categorie_id = ? WHERE id = ?');

        foreach ($plugins as $p) {
            if (!isset($p['id'], $p['sort_order'])) {
                continue;
            }
            $categorieId = isset($p['categorie_id']) && $p['categorie_id'] !== '' ? (int) $p['categorie_id'] : null;
            $stmt->execute([(int) $p['sort_order'], $categorieId, (int) $p['id']]);
        }

        Response::json(['ok' => true]);
    }

    public function formulaireInstallation(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        Layout::render('layout', [
            'template'          => 'admin/plugins-installer',
            'pageTitle'         => 'Installer un plugin',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'plugins',
            'categories'        => ModuleRegistry::chargerCategories($db),
        ]);
    }

    public function detecter(Request $req): void
    {
        $chemin = trim($req->post('chemin', ''));
        $db = Connection::get();
        $installer = new PluginInstaller($db);

        $erreur = $installer->validerChemin($chemin);
        if ($erreur !== null) {
            Response::json(['succes' => false, 'erreur' => $erreur]);
        }

        $moduleJson = $installer->detecterModuleJson($chemin);
        $pointEntree = $installer->detecterPointEntree($chemin);

        Response::json([
            'succes'       => true,
            'detecte'      => $moduleJson !== null,
            'donnees'      => $moduleJson,
            'point_entree' => $pointEntree,
        ]);
    }

    public function installer(Request $req): void
    {
        $db = Connection::get();
        $installer = new PluginInstaller($db);
        $modeInstallation = $req->post('mode_installation', 'chemin');

        if ($modeInstallation === 'zip') {
            $this->installerDepuisZip($req, $installer);
            return;
        }

        if ($modeInstallation === 'git') {
            $this->installerDepuisGit($req, $installer);
            return;
        }

        $this->installerDepuisChemin($req, $installer);
    }

    public function analyserZip(Request $req): void
    {
        $db = Connection::get();
        $installer = new PluginInstaller($db);

        $fichierUpload = $_FILES['fichier_zip'] ?? [];

        $erreur = $installer->validerFichierZip($fichierUpload);
        if ($erreur !== null) {
            Response::json(['succes' => false, 'erreur' => $erreur]);
        }

        $infoModule = $installer->lireModuleJsonDepuisZip($fichierUpload);

        Response::json([
            'succes'  => true,
            'detecte' => $infoModule !== null,
            'donnees' => $infoModule !== null ? $infoModule['donnees'] : null,
        ]);
    }

    private function installerDepuisZip(Request $req, PluginInstaller $installer): void
    {
        $fichierUpload = $_FILES['fichier_zip'] ?? [];
        $categorieId = $req->post('categorie_id', '');

        $erreur = $installer->validerFichierZip($fichierUpload);
        if ($erreur !== null) {
            Flash::error($erreur);
            Response::redirect('/admin/plugins/installer');
        }

        try {
            $moduleId = $installer->installerDepuisZip($fichierUpload, Auth::id(), $categorieId !== '' ? (int) $categorieId : null);

            $stmt = Connection::get()->prepare('SELECT slug, chemin_source, name FROM modules WHERE id = ?');
            $stmt->execute([$moduleId]);
            $module = $stmt->fetch();

            AuditLogger::instance()->log(
                AuditAction::PluginInstall, $req->ip(), Auth::id(), 'module', $moduleId,
                ['slug' => $module['slug'] ?? '', 'chemin' => $module['chemin_source'] ?? '', 'mode' => 'zip']
            );

            Flash::success("Plugin « {$module['name']} » installé avec succès.");
        } catch (\Throwable $e) {
            Flash::error($e->getMessage() ?: 'Erreur lors de l\'installation du plugin.');
        }

        Response::redirect('/admin/plugins');
    }

    private function installerDepuisChemin(Request $req, PluginInstaller $installer): void
    {
        $chemin = trim($req->post('chemin_source', ''));
        $slug = trim($req->post('slug', ''));

        $validateur = new Validator();
        $valide = $validateur->valider(
            ['chemin_source' => $chemin, 'slug' => $slug, 'name' => trim($req->post('name', ''))],
            ['chemin_source' => 'requis|chemin', 'slug' => 'requis|slug', 'name' => 'requis|min:2|max:100']
        );

        if (!$valide) {
            Flash::error($validateur->premiereErreur() ?? 'Données invalides.');
            Response::redirect('/admin/plugins/installer');
        }

        $erreurChemin = $installer->validerChemin($chemin);
        if ($erreurChemin !== null) {
            Flash::error($erreurChemin);
            Response::redirect('/admin/plugins/installer');
        }

        $erreurSlug = $installer->validerSlug($slug);
        if ($erreurSlug !== null) {
            Flash::error($erreurSlug);
            Response::redirect('/admin/plugins/installer');
        }

        $clesEnv = array_filter(array_map('trim', explode(',', $req->post('cles_env', ''))));

        $categorieId = $req->post('categorie_id', '');

        $donnees = [
            'slug'            => $slug,
            'name'            => trim($req->post('name', '')),
            'description'     => trim($req->post('description', '')),
            'version'         => trim($req->post('version', '1.0.0')),
            'icon'            => trim($req->post('icon', 'bi-tools')),
            'sort_order'      => (int) $req->post('sort_order', 100),
            'quota_mode'      => $req->post('quota_mode', 'none'),
            'default_quota'   => (int) $req->post('default_quota', 0),
            'chemin_source'   => $chemin,
            'point_entree'    => trim($req->post('point_entree', 'index.php')),
            'cles_env'        => $clesEnv !== [] ? $clesEnv : null,
            'routes_config'   => null,
            'passthrough_all' => $req->post('mode_affichage', 'embedded') === 'passthrough',
            'mode_affichage'  => $req->post('mode_affichage', 'embedded'),
            'categorie_id'    => $categorieId !== '' ? (int) $categorieId : null,
        ];

        // Récupérer routes_config, langues et git_url depuis module.json si détecté
        $moduleJson = $installer->detecterModuleJson($chemin);
        $gitUrlDepuisJson = null;
        $gitBrancheDepuisJson = null;
        if ($moduleJson !== null) {
            if (!empty($moduleJson['routes'])) {
                $donnees['routes_config'] = $moduleJson['routes'];
            }
            if (!empty($moduleJson['languages'])) {
                $donnees['langues'] = $moduleJson['languages'];
            }
            if (!empty($moduleJson['domain_field'])) {
                $donnees['domain_field'] = $moduleJson['domain_field'];
            }
            if (!empty($moduleJson['git_url']) && GitClient::validerUrl($moduleJson['git_url'])) {
                $gitUrlDepuisJson = $moduleJson['git_url'];
                $gitBrancheDepuisJson = $moduleJson['git_branche'] ?? 'main';
            }
        }

        try {
            $moduleId = $installer->installer($donnees, Auth::id());

            // Sauvegarder git_url depuis module.json si présent
            if ($gitUrlDepuisJson !== null) {
                $db = Connection::get();
                $db->prepare('UPDATE modules SET git_url = ?, git_branche = ? WHERE id = ?')
                    ->execute([$gitUrlDepuisJson, $gitBrancheDepuisJson, $moduleId]);
            }

            AuditLogger::instance()->log(
                AuditAction::PluginInstall, $req->ip(), Auth::id(), 'module', $moduleId,
                ['slug' => $slug, 'chemin' => $chemin, 'mode' => 'chemin']
            );
        } catch (\Throwable $e) {
            Flash::error('Erreur lors de l\'installation du plugin.');
            Response::redirect('/admin/plugins/installer');
        }

        Flash::success("Plugin « {$donnees['name']} » installé avec succès.");
        Response::redirect('/admin/plugins');
    }

    /**
     * POST /admin/plugins/detecter-git — AJAX : clone temporaire pour détecter module.json.
     */
    public function detecterGit(Request $req): void
    {
        $url = trim($req->post('git_url', ''));
        $branche = trim($req->post('git_branche', 'main')) ?: 'main';

        if (!GitClient::validerUrl($url)) {
            Response::json(['succes' => false, 'erreur' => 'URL de dépôt Git invalide. Seuls GitHub et GitLab en HTTPS sont acceptés.']);
        }

        $slug = GitClient::extraireSlug($url);
        if ($slug === null) {
            Response::json(['succes' => false, 'erreur' => 'Impossible de déterminer le slug depuis l\'URL.']);
        }

        // Clone temporaire pour détecter module.json
        $tmpDir = sys_get_temp_dir() . '/seo-platform-git-detect-' . uniqid();
        $gitClient = new GitClient();

        if (!$gitClient->cloner($url, $tmpDir, $branche)) {
            Response::json(['succes' => false, 'erreur' => 'Échec du clonage. Vérifiez l\'URL, la branche et le token GitHub.']);
        }

        $db = Connection::get();
        $installer = new PluginInstaller($db);
        $moduleJson = $installer->detecterModuleJson($tmpDir);
        $pointEntree = $installer->detecterPointEntree($tmpDir);

        // Nettoyage du clone temporaire
        $this->supprimerRepertoireTemporaire($tmpDir);

        Response::json([
            'succes'       => true,
            'detecte'      => $moduleJson !== null,
            'donnees'      => $moduleJson,
            'point_entree' => $pointEntree,
            'slug'         => $slug,
        ]);
    }

    /**
     * POST /admin/plugins/{id}/maj-git — Met à jour un plugin Git via pull.
     */
    public function mettreAJourGit(Request $req, array $params): void
    {
        $db = Connection::get();
        $moduleId = (int) $params['id'];

        $stmt = $db->prepare('SELECT * FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();

        if (!$module) {
            Response::abort(404);
        }

        if (empty($module['git_url'])) {
            Flash::error('Ce module n\'est pas installé via Git.');
            Response::redirect('/admin/plugins');
        }

        $installer = new PluginInstaller($db);

        try {
            $resultat = $installer->mettreAJourDepuisGit($moduleId);

            AuditLogger::instance()->log(
                AuditAction::PluginUpdate, $req->ip(), Auth::id(), 'module', $moduleId,
                ['action' => 'git-pull', 'commit' => $resultat['commit']]
            );

            Flash::success("Plugin « {$module['name']} » mis à jour (commit : " . substr($resultat['commit'] ?? '', 0, 7) . ').');
        } catch (\Throwable $e) {
            Flash::error('Échec de la mise à jour Git : ' . $e->getMessage());
        }

        $retour = $req->post('retour', '');
        $redirectUrl = $retour === 'maj-git' ? '/admin/plugins?onglet=maj-git' : '/admin/plugins';
        Response::redirect($redirectUrl);
    }

    /**
     * POST /admin/plugins/maj-git-tous — Met à jour tous les plugins Git (AJAX).
     */
    public function mettreAJourTousGit(Request $req): void
    {
        $db = Connection::get();

        $modules = $db->query(
            'SELECT id, name, git_url FROM modules WHERE git_url IS NOT NULL AND desinstalle_le IS NULL'
        )->fetchAll();

        $installer = new PluginInstaller($db);
        $resultats = [];

        foreach ($modules as $mod) {
            try {
                $resultat = $installer->mettreAJourDepuisGit((int) $mod['id']);

                AuditLogger::instance()->log(
                    AuditAction::PluginUpdate, $req->ip(), Auth::id(), 'module', (int) $mod['id'],
                    ['action' => 'git-pull-batch', 'commit' => $resultat['commit']]
                );

                $resultats[] = [
                    'id'      => $mod['id'],
                    'name'    => $mod['name'],
                    'succes'  => true,
                    'commit'  => substr($resultat['commit'] ?? '', 0, 7),
                    'version' => $resultat['version'],
                ];
            } catch (\Throwable $e) {
                $resultats[] = [
                    'id'      => $mod['id'],
                    'name'    => $mod['name'],
                    'succes'  => false,
                    'erreur'  => $e->getMessage(),
                ];
            }
        }

        Response::json(['ok' => true, 'resultats' => $resultats]);
    }

    private function installerDepuisGit(Request $req, PluginInstaller $installer): void
    {
        $url = trim($req->post('git_url', ''));
        $branche = trim($req->post('git_branche', 'main')) ?: 'main';
        $categorieId = $req->post('categorie_id', '');

        if (!GitClient::validerUrl($url)) {
            Flash::error('URL de dépôt Git invalide.');
            Response::redirect('/admin/plugins/installer');
        }

        try {
            $resultat = $installer->installerDepuisGit($url, $branche, Auth::id(), $categorieId !== '' ? (int) $categorieId : null);

            $stmt = Connection::get()->prepare('SELECT slug, chemin_source, name FROM modules WHERE id = ?');
            $stmt->execute([$resultat['module_id']]);
            $module = $stmt->fetch();

            AuditLogger::instance()->log(
                AuditAction::PluginInstall, $req->ip(), Auth::id(), 'module', $resultat['module_id'],
                ['slug' => $resultat['slug'], 'git_url' => $url, 'mode' => 'git']
            );

            Flash::success("Plugin « {$module['name']} » installé depuis Git.");
        } catch (\Throwable $e) {
            Flash::error($e->getMessage() ?: 'Erreur lors de l\'installation du plugin.');
        }

        Response::redirect('/admin/plugins');
    }

    /**
     * Supprime un répertoire temporaire de manière récursive.
     */
    private function supprimerRepertoireTemporaire(string $chemin): void
    {
        if (!is_dir($chemin)) {
            return;
        }

        $elements = scandir($chemin);
        if ($elements === false) {
            return;
        }

        foreach ($elements as $element) {
            if ($element === '.' || $element === '..') {
                continue;
            }

            $cheminComplet = $chemin . '/' . $element;

            if (is_link($cheminComplet)) {
                unlink($cheminComplet);
            } elseif (is_dir($cheminComplet)) {
                $this->supprimerRepertoireTemporaire($cheminComplet);
            } else {
                unlink($cheminComplet);
            }
        }

        rmdir($chemin);
    }

    /**
     * POST /admin/plugins/branches-git — AJAX : liste les branches distantes d'un dépôt.
     */
    public function listerBranchesGit(Request $req): void
    {
        $url = trim($req->post('git_url', ''));

        if (!GitClient::validerUrl($url)) {
            Response::json(['succes' => false, 'erreur' => 'URL de dépôt Git invalide.']);
        }

        $gitClient = new GitClient();
        $branches = $gitClient->listerBranchesDistantes($url);

        if ($branches === []) {
            Response::json(['succes' => false, 'erreur' => 'Impossible de lister les branches. Vérifiez l\'URL et le token GitHub.']);
        }

        Response::json(['succes' => true, 'branches' => $branches]);
    }

    public function formulaireEdition(Request $req, array $params): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        $stmt = $db->prepare('SELECT * FROM modules WHERE id = ?');
        $stmt->execute([(int) $params['id']]);
        $module = $stmt->fetch();

        if (!$module) {
            Response::abort(404);
        }

        Layout::render('layout', [
            'template'          => 'admin/plugins-editer',
            'pageTitle'         => 'Modifier ' . $module['name'],
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'plugins',
            'module'            => $module,
            'categories'        => ModuleRegistry::chargerCategories($db),
        ]);
    }

    public function mettreAJour(Request $req, array $params): void
    {
        $db = Connection::get();
        $moduleId = (int) $params['id'];

        $stmt = $db->prepare('SELECT * FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();

        if (!$module) {
            Response::abort(404);
        }

        // Resynchronisation si demandée
        if ($req->post('resync') && $module['chemin_source']) {
            $installer = new PluginInstaller($db);
            $moduleJson = $installer->resynchroniser($moduleId);

            if ($moduleJson !== null) {
                $clesEnv = !empty($moduleJson['env_keys']) ? $moduleJson['env_keys'] : null;

                // Résolution de la catégorie depuis module.json (par nom)
                $resyncCategorieId = null;
                if (!empty($moduleJson['category'])) {
                    $stmtCat = $db->prepare('SELECT id FROM categories WHERE nom = ?');
                    $stmtCat->execute([$moduleJson['category']]);
                    $catRow = $stmtCat->fetch();
                    if ($catRow) {
                        $resyncCategorieId = (int) $catRow['id'];
                    }
                }

                $installer->mettreAJour($moduleId, [
                    'name'            => $moduleJson['name'],
                    'description'     => $moduleJson['description'] ?? '',
                    'version'         => $moduleJson['version'] ?? '1.0.0',
                    'icon'            => $moduleJson['icon'] ?? 'bi-tools',
                    'sort_order'      => (int) ($moduleJson['sort_order'] ?? 100),
                    'quota_mode'      => $moduleJson['quota_mode'] ?? 'none',
                    'default_quota'   => (int) ($moduleJson['default_quota'] ?? 0),
                    'point_entree'    => $moduleJson['entry_point'] ?? 'index.php',
                    'cles_env'        => $clesEnv,
                    'routes_config'   => !empty($moduleJson['routes']) ? $moduleJson['routes'] : null,
                    'passthrough_all' => !empty($moduleJson['passthrough_all']),
                    'mode_affichage'  => $moduleJson['display_mode'] ?? 'embedded',
                    'langues'         => $moduleJson['languages'] ?? [],
                    'categorie_id'    => $resyncCategorieId,
                ]);

                AuditLogger::instance()->log(
                    AuditAction::PluginUpdate, $req->ip(), Auth::id(), 'module', $moduleId,
                    ['action' => 'resync']
                );

                Flash::success('Plugin resynchronisé depuis module.json.');
            } else {
                Flash::error('Impossible de lire le module.json depuis le chemin source.');
            }

            Response::redirect('/admin/plugins/' . $moduleId . '/editer');
        }

        $validateur = new Validator();
        $valide = $validateur->valider(
            ['name' => trim($req->post('name', ''))],
            ['name' => 'requis|min:2|max:100']
        );

        if (!$valide) {
            Flash::error($validateur->premiereErreur() ?? 'Données invalides.');
            Response::redirect('/admin/plugins/' . $moduleId . '/editer');
        }

        $clesEnv = array_filter(array_map('trim', explode(',', $req->post('cles_env', ''))));
        $categorieId = $req->post('categorie_id', '');

        // Préserver les langues et routes existantes (pas de champ dans le formulaire d'édition)
        $languesExistantes = !empty($module['langues']) ? json_decode($module['langues'], true) : [];
        $routesExistantes = !empty($module['routes_config']) ? json_decode($module['routes_config'], true) : null;

        $installer = new PluginInstaller($db);
        $installer->mettreAJour($moduleId, [
            'name'            => trim($req->post('name', '')),
            'description'     => trim($req->post('description', '')),
            'version'         => trim($req->post('version', '1.0.0')),
            'icon'            => trim($req->post('icon', 'bi-tools')),
            'sort_order'      => (int) $req->post('sort_order', 100),
            'quota_mode'      => $req->post('quota_mode', 'none'),
            'default_quota'   => (int) $req->post('default_quota', 0),
            'point_entree'    => trim($req->post('point_entree', 'index.php')),
            'cles_env'        => $clesEnv !== [] ? $clesEnv : null,
            'routes_config'   => $routesExistantes,
            'passthrough_all' => $req->post('mode_affichage', 'embedded') === 'passthrough',
            'mode_affichage'  => $req->post('mode_affichage', 'embedded'),
            'langues'         => $languesExistantes,
            'categorie_id'    => $categorieId !== '' ? (int) $categorieId : null,
        ]);

        // Sauvegarder git_url et git_branche
        $gitUrl = trim($req->post('git_url', ''));
        $gitBranche = trim($req->post('git_branche', 'main')) ?: 'main';

        if ($gitUrl !== '' && !GitClient::validerUrl($gitUrl)) {
            Flash::error('URL de dépôt Git invalide (GitHub/GitLab HTTPS uniquement).');
            Response::redirect('/admin/plugins/' . $moduleId . '/editer');
        }

        // Détecter un changement de branche sur un plugin Git déjà déployé
        $ancienneBranche = $module['git_branche'] ?? '';
        $brancheChangee = $gitUrl !== '' && $ancienneBranche !== '' && $ancienneBranche !== $gitBranche;

        if ($brancheChangee && !empty($module['chemin_source']) && is_dir($module['chemin_source'])) {
            $gitClient = new GitClient();

            if (!$gitClient->changerBranche($module['chemin_source'], $gitBranche)) {
                Flash::error("Impossible de basculer vers la branche « {$gitBranche} ». Vérifiez qu'elle existe sur le dépôt distant.");
                Response::redirect('/admin/plugins/' . $moduleId . '/editer');
            }

            // Mettre à jour le commit après le changement de branche
            $nouveauCommit = $gitClient->getDernierCommit($module['chemin_source']);

            $db->prepare('UPDATE modules SET git_dernier_commit = ?, git_dernier_pull = NOW() WHERE id = ?')
                ->execute([$nouveauCommit, $moduleId]);

            // Réinstaller les dépendances après le switch
            $depInstaller = new \Platform\Module\DependencyInstaller();
            $depInstaller->installerDependances($module['chemin_source']);

            // Resynchroniser module.json
            $installer->resynchroniser($moduleId);
        }

        $db->prepare('UPDATE modules SET git_url = ?, git_branche = ? WHERE id = ?')
            ->execute([$gitUrl !== '' ? $gitUrl : null, $gitUrl !== '' ? $gitBranche : null, $moduleId]);

        AuditLogger::instance()->log(
            AuditAction::PluginUpdate, $req->ip(), Auth::id(), 'module', $moduleId,
            $brancheChangee ? ['action' => 'branch-switch', 'branche' => $gitBranche] : []
        );

        $message = $brancheChangee
            ? "Plugin mis à jour et basculé sur la branche « {$gitBranche} »."
            : 'Plugin mis à jour.';
        Flash::success($message);
        Response::redirect('/admin/plugins');
    }

    public function basculer(Request $req, array $params): void
    {
        $db = Connection::get();
        $moduleId = (int) $params['id'];

        $stmt = $db->prepare('SELECT id, enabled FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();

        if (!$module) {
            Response::abort(404);
        }

        $nouvelEtat = $module['enabled'] ? 0 : 1;
        $db->prepare('UPDATE modules SET enabled = ? WHERE id = ?')->execute([$nouvelEtat, $moduleId]);

        AuditLogger::instance()->log(
            AuditAction::PluginUpdate, $req->ip(), Auth::id(), 'module', $moduleId,
            ['enabled' => $nouvelEtat]
        );

        Flash::success($nouvelEtat ? 'Plugin activé.' : 'Plugin désactivé.');
        Response::redirect('/admin/plugins');
    }

    public function desinstaller(Request $req, array $params): void
    {
        $db = Connection::get();
        $moduleId = (int) $params['id'];

        $stmt = $db->prepare('SELECT * FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();

        if (!$module) {
            Flash::error('Module introuvable.');
            Response::redirect('/admin/plugins');
        }

        $conserverReglages = (bool) $req->post('conserver_reglages', '1');

        $installer = new PluginInstaller($db);
        $installer->desinstaller($moduleId, $conserverReglages, Auth::id());

        AuditLogger::instance()->log(
            AuditAction::PluginUninstall, $req->ip(), Auth::id(), 'module', $moduleId,
            [
                'slug' => $module['slug'],
                'chemin' => $module['chemin_source'] ?? null,
                'conserver_reglages' => $conserverReglages,
            ]
        );

        $messageSupplement = $conserverReglages
            ? ' Les réglages utilisateurs ont été conservés pour une réinstallation future.'
            : '';
        Flash::success("Module « {$module['name']} » désinstallé.{$messageSupplement}");
        Response::redirect('/admin/plugins');
    }

    /**
     * POST /admin/plugins/cles-env — Met à jour une clé d'environnement dans le .env plateforme.
     */
    public function mettreAJourCleEnv(Request $req): void
    {
        $cle = trim($req->post('cle', ''));
        $valeur = trim($req->post('valeur', ''));

        // Valider que la clé est dans la liste des env_keys déclarées par les modules
        $db = Connection::get();
        $modules = $db->query('SELECT cles_env FROM modules WHERE enabled = 1 AND cles_env IS NOT NULL')->fetchAll();

        $clesAutorisees = [];
        foreach ($modules as $mod) {
            $envKeys = json_decode($mod['cles_env'], true);
            if (is_array($envKeys)) {
                $clesAutorisees = array_merge($clesAutorisees, $envKeys);
            }
        }
        $clesAutorisees = array_unique($clesAutorisees);

        if (!in_array($cle, $clesAutorisees, true)) {
            Response::json(['ok' => false, 'erreur' => 'Clé non autorisée.']);
        }

        // Réécrire le .env
        $envPath = dirname(__DIR__, 2) . '/.env';
        $this->ecrireDansEnv($envPath, $cle, $valeur);

        // Recharger pour la session courante
        $_ENV[$cle] = $valeur;
        putenv("{$cle}={$valeur}");

        Response::json(['ok' => true]);
    }

    /**
     * Écrit ou met à jour une clé dans le fichier .env.
     */
    private function ecrireDansEnv(string $cheminEnv, string $cle, string $valeur): void
    {
        $contenu = file_exists($cheminEnv) ? file_get_contents($cheminEnv) : '';
        $lignes = explode("\n", $contenu);
        $trouve = false;
        $valeurEchappee = '"' . str_replace(['\\', '"', "\n", "\r", "\0"], ['\\\\', '\\"', '\\n', '\\r', ''], $valeur) . '"';

        foreach ($lignes as &$ligne) {
            if (preg_match('/^' . preg_quote($cle, '/') . '\s*=/', $ligne)) {
                $ligne = "{$cle}={$valeurEchappee}";
                $trouve = true;
                break;
            }
        }
        unset($ligne);

        if (!$trouve) {
            $lignes[] = "{$cle}={$valeurEchappee}";
        }

        file_put_contents($cheminEnv, implode("\n", $lignes));
    }
}
