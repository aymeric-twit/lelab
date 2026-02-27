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
    public function index(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();

        $modules = $db->query(
            'SELECT m.*, u.username AS installe_par_nom, c.nom AS categorie_nom
             FROM modules m
             LEFT JOIN users u ON u.id = m.installe_par
             LEFT JOIN categories c ON c.id = m.categorie_id
             ORDER BY m.sort_order'
        )->fetchAll();

        Layout::render('layout', [
            'template'          => 'admin/plugins',
            'pageTitle'         => 'Plugins',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'plugins',
            'modules'           => $modules,
        ]);
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

        $erreur = $installer->validerFichierZip($fichierUpload);
        if ($erreur !== null) {
            Flash::error($erreur);
            Response::redirect('/admin/plugins/installer');
        }

        try {
            $moduleId = $installer->installerDepuisZip($fichierUpload, Auth::id());

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

        // Récupérer routes_config depuis module.json si détecté
        $moduleJson = $installer->detecterModuleJson($chemin);
        if ($moduleJson !== null && !empty($moduleJson['routes'])) {
            $donnees['routes_config'] = $moduleJson['routes'];
        }

        try {
            $moduleId = $installer->installer($donnees, Auth::id());

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

        Response::redirect('/admin/plugins');
    }

    private function installerDepuisGit(Request $req, PluginInstaller $installer): void
    {
        $url = trim($req->post('git_url', ''));
        $branche = trim($req->post('git_branche', 'main')) ?: 'main';

        if (!GitClient::validerUrl($url)) {
            Flash::error('URL de dépôt Git invalide.');
            Response::redirect('/admin/plugins/installer');
        }

        try {
            $resultat = $installer->installerDepuisGit($url, $branche, Auth::id());

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
            'routes_config'   => null,
            'passthrough_all' => $req->post('mode_affichage', 'embedded') === 'passthrough',
            'mode_affichage'  => $req->post('mode_affichage', 'embedded'),
            'categorie_id'    => $categorieId !== '' ? (int) $categorieId : null,
        ]);

        AuditLogger::instance()->log(
            AuditAction::PluginUpdate, $req->ip(), Auth::id(), 'module', $moduleId
        );

        Flash::success('Plugin mis à jour.');
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

        $installer = new PluginInstaller($db);
        $installer->desinstaller($moduleId);

        AuditLogger::instance()->log(
            AuditAction::PluginUninstall, $req->ip(), Auth::id(), 'module', $moduleId,
            ['slug' => $module['slug'], 'chemin' => $module['chemin_source'] ?? null]
        );

        Flash::success("Module « {$module['name']} » supprimé.");
        Response::redirect('/admin/plugins');
    }
}
