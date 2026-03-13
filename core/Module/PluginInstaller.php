<?php

namespace Platform\Module;

use PDO;
use Platform\Enum\QuotaMode;
use ZipArchive;

class PluginInstaller
{
    private const int TAILLE_MAX_ZIP = 50 * 1024 * 1024; // 50 Mo

    private const array EXTENSIONS_INTERDITES = ['sh', 'exe', 'phar', 'bat', 'cmd', 'com', 'msi'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Lit module.json s'il existe dans le chemin donné.
     *
     * @return array<string, mixed>|null
     */
    public function detecterModuleJson(string $chemin): ?array
    {
        $fichier = rtrim($chemin, '/') . '/module.json';

        if (!is_file($fichier) || !is_readable($fichier)) {
            return null;
        }

        $contenu = file_get_contents($fichier);
        if ($contenu === false) {
            return null;
        }

        $data = json_decode($contenu, true);
        if (!is_array($data) || !isset($data['slug'])) {
            return null;
        }

        return $data;
    }

    /**
     * Détecte le point d'entrée parmi les fichiers courants.
     */
    public function detecterPointEntree(string $chemin): string
    {
        $chemin = rtrim($chemin, '/');
        $candidats = ['index.php', 'public/index.php', 'adapter.php'];

        foreach ($candidats as $candidat) {
            if (is_file($chemin . '/' . $candidat)) {
                return $candidat;
            }
        }

        return 'index.php';
    }

    /**
     * Vérifie que le chemin existe, est lisible, et n'est pas déjà installé.
     *
     * @return string|null Message d'erreur, null si valide
     */
    public function validerChemin(string $chemin): ?string
    {
        if (!is_dir($chemin)) {
            return "Le répertoire n'existe pas.";
        }

        if (!is_readable($chemin)) {
            return "Le répertoire n'est pas lisible.";
        }

        $stmt = $this->db->prepare('SELECT id FROM modules WHERE chemin_source = ? AND desinstalle_le IS NULL');
        $stmt->execute([$chemin]);

        if ($stmt->fetch()) {
            return 'Ce chemin est déjà installé comme plugin.';
        }

        return null;
    }

    /**
     * Vérifie l'unicité du slug en base.
     *
     * @return string|null Message d'erreur, null si valide
     */
    public function validerSlug(string $slug): ?string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,48}[a-z0-9]$/', $slug)) {
            return 'Le slug doit contenir uniquement des lettres minuscules, chiffres et tirets (2-50 caractères).';
        }

        $stmt = $this->db->prepare('SELECT id FROM modules WHERE slug = ? AND desinstalle_le IS NULL');
        $stmt->execute([$slug]);

        if ($stmt->fetch()) {
            return 'Ce slug est déjà utilisé.';
        }

        return null;
    }

    /**
     * Insère un plugin en base, ou réactive un module soft-deleted ayant le même slug.
     *
     * @param array<string, mixed> $donnees
     * @return int ID du module créé ou réactivé
     */
    public function installer(array $donnees, int $installeParId): int
    {
        // Vérifier si un module soft-deleted avec le même slug existe
        $stmtSoftDeleted = $this->db->prepare(
            'SELECT id FROM modules WHERE slug = ? AND desinstalle_le IS NOT NULL'
        );
        $stmtSoftDeleted->execute([$donnees['slug']]);
        $moduleSoftDeleted = $stmtSoftDeleted->fetch();

        if ($moduleSoftDeleted) {
            return $this->reactiver((int) $moduleSoftDeleted['id'], $donnees, $installeParId);
        }

        $stmt = $this->db->prepare('
            INSERT INTO modules (
                slug, name, description, version, icon, sort_order,
                quota_mode, default_quota, api_credits_period, api_credits_default, enabled,
                chemin_source, point_entree, cles_env, routes_config, passthrough_all,
                mode_affichage, langues, domain_field, categorie_id, installe_par, installe_le
            ) VALUES (
                :slug, :name, :description, :version, :icon, :sort_order,
                :quota_mode, :default_quota, :api_credits_period, :api_credits_default, 1,
                :chemin_source, :point_entree, :cles_env, :routes_config, :passthrough_all,
                :mode_affichage, :langues, :domain_field, :categorie_id, :installe_par, NOW()
            )
        ');

        $stmt->execute([
            'slug'                => $donnees['slug'],
            'name'                => $donnees['name'],
            'description'         => $donnees['description'] ?? '',
            'version'             => $donnees['version'] ?? '1.0.0',
            'icon'                => $donnees['icon'] ?? 'bi-tools',
            'sort_order'          => (int) ($donnees['sort_order'] ?? 100),
            'quota_mode'          => $donnees['quota_mode'] ?? 'none',
            'default_quota'       => (int) ($donnees['default_quota'] ?? 0),
            'api_credits_period'  => $donnees['api_credits_period'] ?? 'mensuel',
            'api_credits_default' => (int) ($donnees['api_credits_default'] ?? 0),
            'chemin_source'       => $donnees['chemin_source'],
            'point_entree'        => $donnees['point_entree'] ?? 'index.php',
            'cles_env'            => !empty($donnees['cles_env']) ? json_encode($donnees['cles_env']) : null,
            'routes_config'       => !empty($donnees['routes_config']) ? json_encode($donnees['routes_config']) : null,
            'passthrough_all'     => !empty($donnees['passthrough_all']) ? 1 : 0,
            'mode_affichage'      => $donnees['mode_affichage'] ?? 'embedded',
            'langues'             => !empty($donnees['langues']) ? json_encode($donnees['langues']) : null,
            'domain_field'        => $donnees['domain_field'] ?? null,
            'categorie_id'        => $donnees['categorie_id'] ?? null,
            'installe_par'        => $installeParId,
        ]);

        $moduleId = (int) $this->db->lastInsertId();

        // Initialiser la config crédits API par défaut pour chaque clé d'env
        $this->initialiserCreditsApiDefauts($donnees);

        // Auto-attribution de l'accès à tous les utilisateurs actifs
        $this->accorderAccesTousUtilisateurs($moduleId, $installeParId);

        // Créer le symlink pour les assets statiques
        if (!empty($donnees['chemin_source'])) {
            $this->creerSymlinkAssets($donnees['slug'], $donnees['chemin_source']);
        }

        return $moduleId;
    }

    /**
     * Réactive un module précédemment soft-deleted.
     * Conserve le même ID → toutes les FK (accès, quotas, usage) restent intactes.
     *
     * @param array<string, mixed> $donnees
     */
    private function reactiver(int $moduleId, array $donnees, int $installeParId): int
    {
        $stmt = $this->db->prepare('
            UPDATE modules SET
                name = :name,
                description = :description,
                version = :version,
                icon = :icon,
                sort_order = :sort_order,
                quota_mode = :quota_mode,
                default_quota = :default_quota,
                api_credits_period = :api_credits_period,
                api_credits_default = :api_credits_default,
                enabled = 1,
                chemin_source = :chemin_source,
                point_entree = :point_entree,
                cles_env = :cles_env,
                routes_config = :routes_config,
                passthrough_all = :passthrough_all,
                mode_affichage = :mode_affichage,
                langues = :langues,
                domain_field = :domain_field,
                categorie_id = :categorie_id,
                installe_par = :installe_par,
                installe_le = NOW(),
                desinstalle_le = NULL,
                desinstalle_par = NULL
            WHERE id = :id
        ');

        $stmt->execute([
            'id'                  => $moduleId,
            'name'                => $donnees['name'],
            'description'         => $donnees['description'] ?? '',
            'version'             => $donnees['version'] ?? '1.0.0',
            'icon'                => $donnees['icon'] ?? 'bi-tools',
            'sort_order'          => (int) ($donnees['sort_order'] ?? 100),
            'quota_mode'          => $donnees['quota_mode'] ?? 'none',
            'default_quota'       => (int) ($donnees['default_quota'] ?? 0),
            'api_credits_period'  => $donnees['api_credits_period'] ?? 'mensuel',
            'api_credits_default' => (int) ($donnees['api_credits_default'] ?? 0),
            'chemin_source'       => $donnees['chemin_source'],
            'point_entree'        => $donnees['point_entree'] ?? 'index.php',
            'cles_env'            => !empty($donnees['cles_env']) ? json_encode($donnees['cles_env']) : null,
            'routes_config'       => !empty($donnees['routes_config']) ? json_encode($donnees['routes_config']) : null,
            'passthrough_all'     => !empty($donnees['passthrough_all']) ? 1 : 0,
            'mode_affichage'      => $donnees['mode_affichage'] ?? 'embedded',
            'langues'             => !empty($donnees['langues']) ? json_encode($donnees['langues']) : null,
            'domain_field'        => $donnees['domain_field'] ?? null,
            'categorie_id'        => $donnees['categorie_id'] ?? null,
            'installe_par'        => $installeParId,
        ]);

        // Accorder l'accès à tous les utilisateurs actifs (réactivation = nouveau plugin pour eux)
        $this->accorderAccesTousUtilisateurs($moduleId, $installeParId);

        // Recréer le symlink pour les assets statiques
        if (!empty($donnees['chemin_source'])) {
            $this->creerSymlinkAssets($donnees['slug'], $donnees['chemin_source']);
        }

        return $moduleId;
    }

    /**
     * Met à jour un plugin existant.
     *
     * @param array<string, mixed> $donnees
     */
    public function mettreAJour(int $moduleId, array $donnees): void
    {
        $stmt = $this->db->prepare('
            UPDATE modules SET
                name = :name,
                description = :description,
                version = :version,
                icon = :icon,
                sort_order = :sort_order,
                quota_mode = :quota_mode,
                default_quota = :default_quota,
                api_credits_period = :api_credits_period,
                api_credits_default = :api_credits_default,
                point_entree = :point_entree,
                cles_env = :cles_env,
                routes_config = :routes_config,
                passthrough_all = :passthrough_all,
                mode_affichage = :mode_affichage,
                langues = :langues,
                domain_field = :domain_field,
                categorie_id = :categorie_id
            WHERE id = :id
        ');

        $stmt->execute([
            'id'                  => $moduleId,
            'name'                => $donnees['name'],
            'description'         => $donnees['description'] ?? '',
            'version'             => $donnees['version'] ?? '1.0.0',
            'icon'                => $donnees['icon'] ?? 'bi-tools',
            'sort_order'          => (int) ($donnees['sort_order'] ?? 100),
            'quota_mode'          => $donnees['quota_mode'] ?? 'none',
            'default_quota'       => (int) ($donnees['default_quota'] ?? 0),
            'api_credits_period'  => $donnees['api_credits_period'] ?? 'mensuel',
            'api_credits_default' => (int) ($donnees['api_credits_default'] ?? 0),
            'point_entree'        => $donnees['point_entree'] ?? 'index.php',
            'cles_env'            => !empty($donnees['cles_env']) ? json_encode($donnees['cles_env']) : null,
            'routes_config'       => !empty($donnees['routes_config']) ? json_encode($donnees['routes_config']) : null,
            'passthrough_all'     => !empty($donnees['passthrough_all']) ? 1 : 0,
            'mode_affichage'      => $donnees['mode_affichage'] ?? 'embedded',
            'langues'             => !empty($donnees['langues']) ? json_encode($donnees['langues']) : null,
            'domain_field'        => $donnees['domain_field'] ?? null,
            'categorie_id'        => $donnees['categorie_id'] ?? null,
        ]);
    }

    /**
     * Initialise la config crédits API par défaut pour les clés d'env du module.
     * Ne remplace pas une config existante.
     *
     * @param array<string, mixed> $donnees
     */
    private function initialiserCreditsApiDefauts(array $donnees): void
    {
        $apiCreditsDefault = (int) ($donnees['api_credits_default'] ?? 0);
        $apiCreditsPeriod = $donnees['api_credits_period'] ?? 'mensuel';
        $clesEnv = $donnees['cles_env'] ?? [];

        if ($apiCreditsDefault <= 0 || empty($clesEnv)) {
            return;
        }

        try {
            $settingsRepo = new \Platform\Repository\SettingsRepository($this->db);

            foreach ($clesEnv as $envKey) {
                $existant = $settingsRepo->obtenir('api_credits', $envKey);
                if ($existant !== null) {
                    continue;
                }

                $settingsRepo->definir('api_credits', $envKey, json_encode([
                    'credits_mensuels' => $apiCreditsDefault,
                    'periode' => $apiCreditsPeriod,
                ], JSON_UNESCAPED_UNICODE));
            }
        } catch (\PDOException) {
            // Table settings pas encore créée
        }
    }

    /**
     * Accorde l'accès au module pour tous les utilisateurs actifs.
     * Compatible MySQL (INSERT IGNORE) et SQLite (INSERT OR IGNORE).
     */
    private function accorderAccesTousUtilisateurs(int $moduleId, int $grantedBy): void
    {
        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $ignore = $driver === 'sqlite' ? 'OR IGNORE' : 'IGNORE';

        $this->db->prepare(
            "INSERT {$ignore} INTO user_module_access (user_id, module_id, granted, granted_by)
             SELECT id, ?, 1, ? FROM users WHERE deleted_at IS NULL AND active = 1"
        )->execute([$moduleId, $grantedBy]);
    }

    /**
     * Désinstalle un module.
     *
     * - conserverReglages = true (défaut) → soft delete : marque le module comme désinstallé
     *   mais conserve les accès, quotas et historique pour une réinstallation future.
     * - conserverReglages = false → hard delete : supprime le module et toutes les données associées.
     *
     * Dans les deux cas, les fichiers sources sont nettoyés selon le type de plugin.
     */
    public function desinstaller(int $moduleId, bool $conserverReglages = true, ?int $desinstalleParId = null): void
    {
        $stmt = $this->db->prepare('SELECT chemin_source, slug FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $cheminSource = $row['chemin_source'];
        $slug = $row['slug'];

        // Supprimer le symlink des assets
        $this->supprimerSymlinkAssets($slug);

        if ($conserverReglages) {
            // Soft delete : conserver la ligne modules et les FK dépendantes
            $stmt = $this->db->prepare(
                'UPDATE modules SET desinstalle_le = NOW(), desinstalle_par = ?, enabled = 0 WHERE id = ?'
            );
            $stmt->execute([$desinstalleParId, $moduleId]);
        } else {
            // Hard delete : supprimer toutes les données associées
            $this->db->prepare('DELETE FROM user_module_access WHERE module_id = ?')->execute([$moduleId]);
            $this->db->prepare('DELETE FROM user_module_quotas WHERE module_id = ?')->execute([$moduleId]);
            $this->db->prepare('DELETE FROM module_usage WHERE module_id = ?')->execute([$moduleId]);
            $this->db->prepare('DELETE FROM modules WHERE id = ?')->execute([$moduleId]);
        }

        // Nettoyage fichiers dans les deux cas
        if ($cheminSource !== null) {
            $repertoirePlugins = $this->repertoirePlugins();
            if (str_starts_with(realpath($cheminSource) ?: '', realpath($repertoirePlugins) ?: "\0")) {
                $this->supprimerRepertoire($cheminSource);
            }
        } else {
            $cheminEmbarque = $this->repertoireModulesEmbarques() . '/' . $slug;
            if (is_dir($cheminEmbarque)) {
                $this->supprimerRepertoire($cheminEmbarque);
            }
        }
    }

    /**
     * Relit le module.json depuis le chemin stocké et retourne les données.
     *
     * @return array<string, mixed>|null
     */
    public function resynchroniser(int $moduleId): ?array
    {
        $stmt = $this->db->prepare('SELECT chemin_source FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $row = $stmt->fetch();

        if (!$row || !$row['chemin_source']) {
            return null;
        }

        return $this->detecterModuleJson($row['chemin_source']);
    }

    /**
     * Valide un fichier ZIP uploadé.
     *
     * @param array<string, mixed> $fichierUpload Entrée de $_FILES
     * @return string|null Message d'erreur, null si valide
     */
    public function validerFichierZip(array $fichierUpload): ?string
    {
        if (empty($fichierUpload['tmp_name']) || ($fichierUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $codeErreur = $fichierUpload['error'] ?? UPLOAD_ERR_NO_FILE;

            // Quand post_max_size est dépassé, PHP vide $_POST et $_FILES silencieusement.
            // On détecte ce cas en vérifiant CONTENT_LENGTH vs les limites PHP.
            if ($codeErreur === UPLOAD_ERR_NO_FILE || $codeErreur === UPLOAD_ERR_INI_SIZE) {
                $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
                $postMaxSize = self::convertirEnOctets(ini_get('post_max_size') ?: '8M');
                $uploadMaxSize = self::convertirEnOctets(ini_get('upload_max_filesize') ?: '2M');

                if ($contentLength > 0 && $contentLength > $postMaxSize) {
                    return sprintf(
                        'Le fichier dépasse la limite post_max_size de PHP (%s). '
                        . 'Lancez le serveur avec : php -d post_max_size=55M -d upload_max_filesize=50M -S localhost:8000 -t public',
                        ini_get('post_max_size')
                    );
                }

                if ($contentLength > 0 && $contentLength > $uploadMaxSize && $codeErreur === UPLOAD_ERR_NO_FILE) {
                    return sprintf(
                        'Le fichier dépasse la limite upload_max_filesize de PHP (%s). '
                        . 'Lancez le serveur avec : php -d post_max_size=55M -d upload_max_filesize=50M -S localhost:8000 -t public',
                        ini_get('upload_max_filesize')
                    );
                }
            }

            return match ($codeErreur) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                    'Le fichier dépasse la taille maximale autorisée (upload_max_filesize = %s).',
                    ini_get('upload_max_filesize')
                ),
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été envoyé.',
                default => 'Erreur lors de l\'upload du fichier (code ' . $codeErreur . ').',
            };
        }

        if ($fichierUpload['size'] > self::TAILLE_MAX_ZIP) {
            return 'Le fichier ZIP ne doit pas dépasser 50 Mo.';
        }

        $nomFichier = $fichierUpload['name'] ?? '';
        if (strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION)) !== 'zip') {
            return 'Seuls les fichiers .zip sont acceptés.';
        }

        $zip = new ZipArchive();
        $resultat = $zip->open($fichierUpload['tmp_name'], ZipArchive::RDONLY);
        if ($resultat !== true) {
            return 'Le fichier ZIP est invalide ou corrompu.';
        }

        // Vérifier les noms de fichiers dans le ZIP (sécurité)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nomEntry = $zip->getNameIndex($i);
            if ($nomEntry === false) {
                $zip->close();
                return 'Le fichier ZIP contient une entrée illisible.';
            }

            // Interdire les traversées de répertoire
            if (str_contains($nomEntry, '..') || str_starts_with($nomEntry, '/')) {
                $zip->close();
                return 'Le fichier ZIP contient des chemins dangereux (traversée de répertoire).';
            }

            // Interdire les extensions exécutables (hors .php)
            $extension = strtolower(pathinfo($nomEntry, PATHINFO_EXTENSION));
            if (in_array($extension, self::EXTENSIONS_INTERDITES, true)) {
                $zip->close();
                return "Le fichier ZIP contient un fichier interdit : {$nomEntry}";
            }
        }

        $zip->close();

        return null;
    }

    /**
     * Lit le module.json contenu dans un ZIP sans l'extraire.
     *
     * @param array<string, mixed> $fichierUpload Entrée de $_FILES
     * @return array{donnees: array<string, mixed>, prefixe: string}|null
     */
    public function lireModuleJsonDepuisZip(array $fichierUpload): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($fichierUpload['tmp_name'], ZipArchive::RDONLY) !== true) {
            return null;
        }

        $contenu = false;
        $prefixe = '';

        // Détecter un sous-dossier unique englobant tout le contenu
        $sousDossierUnique = $this->detecterSousDossierUniqueZip($zip);

        if ($sousDossierUnique !== null) {
            // Chercher module.json dans le sous-dossier unique
            $prefixe = $sousDossierUnique . '/';
            $contenu = $zip->getFromName($prefixe . 'module.json');
        }

        // Sinon, chercher module.json à la racine
        if ($contenu === false) {
            $contenu = $zip->getFromName('module.json');
            $prefixe = '';
        }

        $zip->close();

        if ($contenu === false) {
            return null;
        }

        $data = json_decode($contenu, true);
        if (!is_array($data) || !isset($data['slug'])) {
            return null;
        }

        return ['donnees' => $data, 'prefixe' => $prefixe];
    }

    /**
     * Convertit une valeur PHP ini (ex: '2M', '128K', '1G') en octets.
     */
    private static function convertirEnOctets(string $valeur): int
    {
        $valeur = trim($valeur);
        $nombre = (int) $valeur;
        $suffixe = strtoupper(substr($valeur, -1));

        return match ($suffixe) {
            'G' => $nombre * 1024 * 1024 * 1024,
            'M' => $nombre * 1024 * 1024,
            'K' => $nombre * 1024,
            default => $nombre,
        };
    }

    /**
     * Détecte si toutes les entrées du ZIP (hors fichiers à la racine) sont dans un sous-dossier unique.
     * Ex: un ZIP contenant crux/index.php, crux/app.js, module.json → retourne "crux".
     */
    private function detecterSousDossierUniqueZip(ZipArchive $zip): ?string
    {
        $sousDossier = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = $zip->getNameIndex($i);
            if ($nom === false) {
                continue;
            }

            // Ignorer les fichiers à la racine (sans /)
            if (!str_contains($nom, '/')) {
                continue;
            }

            $dossierRacine = explode('/', $nom)[0];

            if ($sousDossier === null) {
                $sousDossier = $dossierRacine;
            } elseif ($sousDossier !== $dossierRacine) {
                // Plusieurs sous-dossiers différents → pas de sous-dossier unique
                return null;
            }
        }

        return $sousDossier;
    }

    /**
     * Installe un plugin depuis un dépôt Git (GitHub/GitLab).
     *
     * @return array{module_id: int, slug: string, commit: string|null}
     * @throws \RuntimeException
     */
    public function installerDepuisGit(string $url, string $branche = 'main', int $installeParId = 0, ?int $categorieId = null): array
    {
        if (!GitClient::validerUrl($url)) {
            throw new \RuntimeException('URL de dépôt Git invalide. Seuls GitHub et GitLab en HTTPS sont acceptés.');
        }

        $slug = GitClient::extraireSlug($url);
        if ($slug === null) {
            throw new \RuntimeException('Impossible de déterminer le slug depuis l\'URL.');
        }

        $erreurSlug = $this->validerSlug($slug);
        if ($erreurSlug !== null) {
            throw new \RuntimeException($erreurSlug);
        }

        $cheminDestination = $this->repertoirePlugins() . '/' . $slug;

        if (is_dir($cheminDestination)) {
            throw new \RuntimeException("Le répertoire de destination existe déjà : storage/plugins/{$slug}/");
        }

        $gitClient = new GitClient();

        if (!$gitClient->cloner($url, $cheminDestination, $branche)) {
            throw new \RuntimeException('Échec du clonage du dépôt. Vérifiez l\'URL, la branche et le token GitHub.');
        }

        $moduleJson = $this->detecterModuleJson($cheminDestination);
        if ($moduleJson === null) {
            // Nettoyage si pas de module.json
            $this->supprimerRepertoire($cheminDestination);
            throw new \RuntimeException('Aucun module.json valide trouvé dans le dépôt.');
        }

        if ($moduleJson['slug'] !== $slug) {
            $this->supprimerRepertoire($cheminDestination);
            throw new \RuntimeException(
                "Le slug du module.json (« {$moduleJson['slug']} ») ne correspond pas au nom du dépôt (« {$slug} »)."
            );
        }

        $commit = $gitClient->getDernierCommit($cheminDestination);
        $pointEntree = $this->detecterPointEntree($cheminDestination);
        $clesEnv = !empty($moduleJson['env_keys']) ? $moduleJson['env_keys'] : null;

        $donneesInstall = [
            'slug'            => $slug,
            'name'            => $moduleJson['name'] ?? $slug,
            'description'     => $moduleJson['description'] ?? '',
            'version'         => $moduleJson['version'] ?? '1.0.0',
            'icon'            => $moduleJson['icon'] ?? 'bi-tools',
            'sort_order'      => (int) ($moduleJson['sort_order'] ?? 100),
            'quota_mode'      => $moduleJson['quota_mode'] ?? 'none',
            'default_quota'   => (int) ($moduleJson['default_quota'] ?? 0),
            'chemin_source'   => $cheminDestination,
            'point_entree'    => $moduleJson['entry_point'] ?? $pointEntree,
            'cles_env'        => $clesEnv,
            'routes_config'   => !empty($moduleJson['routes']) ? $moduleJson['routes'] : null,
            'passthrough_all' => !empty($moduleJson['passthrough_all']),
            'mode_affichage'  => $moduleJson['display_mode'] ?? 'embedded',
            'langues'         => $moduleJson['languages'] ?? [],
            'domain_field'    => $moduleJson['domain_field'] ?? null,
            'categorie_id'    => $categorieId,
        ];

        try {
            $moduleId = $this->installer($donneesInstall, $installeParId);
        } catch (\Throwable $e) {
            // Nettoyage du répertoire cloné si l'INSERT échoue
            $this->supprimerRepertoire($cheminDestination);
            throw $e;
        }

        // Enregistrer les infos Git en BDD
        $stmt = $this->db->prepare('
            UPDATE modules SET git_url = :git_url, git_branche = :git_branche,
                   git_dernier_pull = NOW(), git_dernier_commit = :git_dernier_commit
            WHERE id = :id
        ');
        $stmt->execute([
            'git_url'            => $url,
            'git_branche'        => $branche,
            'git_dernier_commit' => $commit,
            'id'                 => $moduleId,
        ]);

        // Installer les dépendances Composer/npm
        $depInstaller = new DependencyInstaller();
        $depResultat = $depInstaller->installerDependances($cheminDestination);

        return [
            'module_id'    => $moduleId,
            'slug'         => $slug,
            'commit'       => $commit,
            'dependances'  => $depResultat,
        ];
    }

    /**
     * Met à jour un plugin installé via Git (git pull).
     *
     * @return array{succes: bool, commit: string|null, version: string|null}
     * @throws \RuntimeException
     */
    public function mettreAJourDepuisGit(int $moduleId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();

        if (!$module) {
            throw new \RuntimeException('Module introuvable.');
        }

        if (empty($module['git_url'])) {
            throw new \RuntimeException('Ce module n\'est pas installé via Git.');
        }

        $cheminSource = $module['chemin_source'];
        if (!is_dir($cheminSource)) {
            throw new \RuntimeException('Le répertoire source du plugin est introuvable.');
        }

        $gitClient = new GitClient();

        if (!$gitClient->pull($cheminSource)) {
            throw new \RuntimeException('Échec du git pull. Vérifiez la connexion et les permissions.');
        }

        $commit = $gitClient->getDernierCommit($cheminSource);

        // Re-détecter module.json pour mettre à jour les métadonnées
        $moduleJson = $this->detecterModuleJson($cheminSource);
        $version = $module['version'];

        if ($moduleJson !== null) {
            $clesEnv = !empty($moduleJson['env_keys']) ? $moduleJson['env_keys'] : null;

            $this->mettreAJour($moduleId, [
                'name'            => $moduleJson['name'] ?? $module['name'],
                'description'     => $moduleJson['description'] ?? $module['description'],
                'version'         => $moduleJson['version'] ?? $module['version'],
                'icon'            => $moduleJson['icon'] ?? $module['icon'],
                'sort_order'      => (int) ($moduleJson['sort_order'] ?? $module['sort_order']),
                'quota_mode'      => $moduleJson['quota_mode'] ?? $module['quota_mode'],
                'default_quota'   => (int) ($moduleJson['default_quota'] ?? $module['default_quota']),
                'point_entree'    => $moduleJson['entry_point'] ?? $module['point_entree'],
                'cles_env'        => $clesEnv,
                'routes_config'   => !empty($moduleJson['routes']) ? $moduleJson['routes'] : null,
                'passthrough_all' => !empty($moduleJson['passthrough_all']),
                'mode_affichage'  => $moduleJson['display_mode'] ?? $module['mode_affichage'],
                'langues'         => $moduleJson['languages'] ?? [],
                'domain_field'    => $moduleJson['domain_field'] ?? null,
                'categorie_id'    => $module['categorie_id'],
            ]);

            $version = $moduleJson['version'] ?? $module['version'];
        }

        // Mettre à jour les infos Git
        $stmtGit = $this->db->prepare('
            UPDATE modules SET git_dernier_pull = NOW(), git_dernier_commit = :commit
            WHERE id = :id
        ');
        $stmtGit->execute(['commit' => $commit, 'id' => $moduleId]);

        // Réinstaller les dépendances Composer/npm après le pull
        $depInstaller = new DependencyInstaller();
        $depResultat = $depInstaller->installerDependances($cheminSource);

        return [
            'succes'      => true,
            'commit'      => $commit,
            'version'     => $version,
            'dependances' => $depResultat,
        ];
    }

    /**
     * Installe un plugin depuis un fichier ZIP uploadé.
     *
     * @param array<string, mixed> $fichierUpload Entrée de $_FILES
     * @return int ID du module créé
     * @throws \RuntimeException Si l'extraction échoue
     */
    public function installerDepuisZip(array $fichierUpload, int $installeParId, ?int $categorieId = null): int
    {
        $erreur = $this->validerFichierZip($fichierUpload);
        if ($erreur !== null) {
            throw new \RuntimeException($erreur);
        }

        $infoModule = $this->lireModuleJsonDepuisZip($fichierUpload);
        if ($infoModule === null) {
            throw new \RuntimeException('Aucun module.json valide trouvé dans le ZIP.');
        }

        $donnees = $infoModule['donnees'];
        $slug = $donnees['slug'];

        $erreurSlug = $this->validerSlug($slug);
        if ($erreurSlug !== null) {
            throw new \RuntimeException($erreurSlug);
        }

        $cheminDestination = $this->repertoirePlugins() . '/' . $slug;

        if (is_dir($cheminDestination)) {
            throw new \RuntimeException("Le répertoire de destination existe déjà : storage/plugins/{$slug}/");
        }

        // Extraction
        $zip = new ZipArchive();
        if ($zip->open($fichierUpload['tmp_name'], ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier ZIP.');
        }

        $prefixe = $infoModule['prefixe'];

        if ($prefixe === '') {
            // Extraire tout dans le dossier slug
            if (!mkdir($cheminDestination, 0755, true)) {
                $zip->close();
                throw new \RuntimeException('Impossible de créer le répertoire de destination.');
            }
            $zip->extractTo($cheminDestination);
        } else {
            // Extraire le contenu du sous-dossier dans le dossier slug
            if (!mkdir($cheminDestination, 0755, true)) {
                $zip->close();
                throw new \RuntimeException('Impossible de créer le répertoire de destination.');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nomEntry = $zip->getNameIndex($i);
                if ($nomEntry === false || !str_starts_with($nomEntry, $prefixe)) {
                    continue;
                }

                $cheminRelatif = substr($nomEntry, strlen($prefixe));
                if ($cheminRelatif === '') {
                    continue;
                }

                $cheminComplet = $cheminDestination . '/' . $cheminRelatif;

                // Créer les sous-répertoires si nécessaire
                if (str_ends_with($nomEntry, '/')) {
                    if (!is_dir($cheminComplet)) {
                        mkdir($cheminComplet, 0755, true);
                    }
                    continue;
                }

                $dossierParent = dirname($cheminComplet);
                if (!is_dir($dossierParent)) {
                    mkdir($dossierParent, 0755, true);
                }

                $contenuFichier = $zip->getFromIndex($i);
                if ($contenuFichier !== false) {
                    file_put_contents($cheminComplet, $contenuFichier);
                }
            }
        }

        $zip->close();

        // Détecter le point d'entrée et les routes
        $pointEntree = $this->detecterPointEntree($cheminDestination);
        $clesEnv = !empty($donnees['env_keys']) ? $donnees['env_keys'] : null;

        $donneesInstall = [
            'slug'            => $slug,
            'name'            => $donnees['name'] ?? $slug,
            'description'     => $donnees['description'] ?? '',
            'version'         => $donnees['version'] ?? '1.0.0',
            'icon'            => $donnees['icon'] ?? 'bi-tools',
            'sort_order'      => (int) ($donnees['sort_order'] ?? 100),
            'quota_mode'      => $donnees['quota_mode'] ?? 'none',
            'default_quota'   => (int) ($donnees['default_quota'] ?? 0),
            'chemin_source'   => $cheminDestination,
            'point_entree'    => $donnees['entry_point'] ?? $pointEntree,
            'cles_env'        => $clesEnv,
            'routes_config'   => !empty($donnees['routes']) ? $donnees['routes'] : null,
            'passthrough_all' => !empty($donnees['passthrough_all']),
            'mode_affichage'  => $donnees['display_mode'] ?? 'embedded',
            'langues'         => $donnees['languages'] ?? [],
            'domain_field'    => $donnees['domain_field'] ?? null,
            'categorie_id'    => $categorieId,
        ];

        try {
            $moduleId = $this->installer($donneesInstall, $installeParId);
        } catch (\Throwable $e) {
            // Nettoyage du répertoire extrait si l'INSERT échoue
            $this->supprimerRepertoire($cheminDestination);
            throw $e;
        }

        // Sauvegarder git_url depuis module.json si présent
        if (!empty($donnees['git_url']) && GitClient::validerUrl($donnees['git_url'])) {
            $gitBranche = $donnees['git_branche'] ?? 'main';
            $this->db->prepare('UPDATE modules SET git_url = ?, git_branche = ? WHERE id = ?')
                ->execute([$donnees['git_url'], $gitBranche, $moduleId]);
        }

        // Installer les dépendances Composer/npm
        $depInstaller = new DependencyInstaller();
        $depInstaller->installerDependances($cheminDestination);

        return $moduleId;
    }

    /**
     * Crée un symlink public/module-assets/{slug} → répertoire source du plugin.
     * Permet au serveur PHP intégré de servir les fichiers statiques directement.
     */
    public function creerSymlinkAssets(string $slug, string $cheminSource): void
    {
        $repertoireAssets = $this->repertoireAssetsPublics();
        if (!is_dir($repertoireAssets)) {
            mkdir($repertoireAssets, 0755, true);
        }

        $lien = $repertoireAssets . '/' . $slug;

        // Supprimer l'ancien symlink s'il existe (réinstallation)
        if (is_link($lien)) {
            unlink($lien);
        } elseif (is_dir($lien)) {
            // Si c'est un vrai dossier (cas anormal), ne pas toucher
            return;
        }

        symlink($cheminSource, $lien);
    }

    /**
     * Supprime le symlink public/module-assets/{slug} s'il existe.
     */
    public function supprimerSymlinkAssets(string $slug): void
    {
        $lien = $this->repertoireAssetsPublics() . '/' . $slug;
        if (is_link($lien)) {
            unlink($lien);
        }
    }

    /**
     * Retourne le chemin absolu du répertoire public des assets modules.
     */
    public function repertoireAssetsPublics(): string
    {
        return dirname(__DIR__, 2) . '/public/module-assets';
    }

    /**
     * Retourne le chemin absolu du répertoire des plugins extraits.
     */
    public function repertoirePlugins(): string
    {
        return dirname(__DIR__, 2) . '/storage/plugins';
    }

    /**
     * Retourne le chemin absolu du répertoire des modules embarqués.
     */
    private function repertoireModulesEmbarques(): string
    {
        return dirname(__DIR__, 2) . '/modules';
    }

    /**
     * Suppression récursive sécurisée d'un répertoire.
     * Ne supprime que si le chemin est sous storage/plugins/ ou modules/.
     * Les symlinks sont supprimés sans suivre leur cible.
     */
    private function supprimerRepertoire(string $chemin): void
    {
        $cheminReel = realpath($chemin);
        if ($cheminReel === false) {
            return;
        }

        // Sécurité : ne supprimer que sous storage/plugins/ ou modules/
        $repertoirePlugins = realpath($this->repertoirePlugins());
        $repertoireModules = realpath($this->repertoireModulesEmbarques());

        $sousPlugins = $repertoirePlugins !== false && str_starts_with($cheminReel, $repertoirePlugins);
        $sousModules = $repertoireModules !== false && str_starts_with($cheminReel, $repertoireModules);

        if (!$sousPlugins && !$sousModules) {
            return;
        }

        $this->supprimerContenuRepertoire($cheminReel);
        rmdir($cheminReel);
    }

    /**
     * Supprime récursivement le contenu d'un répertoire sans suivre les symlinks.
     */
    private function supprimerContenuRepertoire(string $chemin): void
    {
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
                $this->supprimerContenuRepertoire($cheminComplet);
                rmdir($cheminComplet);
            } else {
                unlink($cheminComplet);
            }
        }
    }
}
