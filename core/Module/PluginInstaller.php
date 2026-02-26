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

        $stmt = $this->db->prepare('SELECT id FROM modules WHERE chemin_source = ?');
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

        $stmt = $this->db->prepare('SELECT id FROM modules WHERE slug = ?');
        $stmt->execute([$slug]);

        if ($stmt->fetch()) {
            return 'Ce slug est déjà utilisé.';
        }

        return null;
    }

    /**
     * Insère un plugin en base.
     *
     * @param array<string, mixed> $donnees
     * @return int ID du module créé
     */
    public function installer(array $donnees, int $installeParId): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO modules (
                slug, name, description, version, icon, sort_order,
                quota_mode, default_quota, enabled,
                chemin_source, point_entree, cles_env, routes_config, passthrough_all,
                mode_affichage, categorie_id, installe_par, installe_le
            ) VALUES (
                :slug, :name, :description, :version, :icon, :sort_order,
                :quota_mode, :default_quota, 1,
                :chemin_source, :point_entree, :cles_env, :routes_config, :passthrough_all,
                :mode_affichage, :categorie_id, :installe_par, NOW()
            )
        ');

        $stmt->execute([
            'slug'            => $donnees['slug'],
            'name'            => $donnees['name'],
            'description'     => $donnees['description'] ?? '',
            'version'         => $donnees['version'] ?? '1.0.0',
            'icon'            => $donnees['icon'] ?? 'bi-tools',
            'sort_order'      => (int) ($donnees['sort_order'] ?? 100),
            'quota_mode'      => $donnees['quota_mode'] ?? 'none',
            'default_quota'   => (int) ($donnees['default_quota'] ?? 0),
            'chemin_source'   => $donnees['chemin_source'],
            'point_entree'    => $donnees['point_entree'] ?? 'index.php',
            'cles_env'        => !empty($donnees['cles_env']) ? json_encode($donnees['cles_env']) : null,
            'routes_config'   => !empty($donnees['routes_config']) ? json_encode($donnees['routes_config']) : null,
            'passthrough_all' => !empty($donnees['passthrough_all']) ? 1 : 0,
            'mode_affichage'  => $donnees['mode_affichage'] ?? 'embedded',
            'categorie_id'    => $donnees['categorie_id'] ?? null,
            'installe_par'    => $installeParId,
        ]);

        $moduleId = (int) $this->db->lastInsertId();

        // Auto-attribution de l'accès à l'admin qui installe
        $stmtAccess = $this->db->prepare(
            'INSERT INTO user_module_access (user_id, module_id, granted, granted_by)
             VALUES (?, ?, 1, ?)'
        );
        $stmtAccess->execute([$installeParId, $moduleId, $installeParId]);

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
                point_entree = :point_entree,
                cles_env = :cles_env,
                routes_config = :routes_config,
                passthrough_all = :passthrough_all,
                mode_affichage = :mode_affichage,
                categorie_id = :categorie_id
            WHERE id = :id
        ');

        $stmt->execute([
            'id'              => $moduleId,
            'name'            => $donnees['name'],
            'description'     => $donnees['description'] ?? '',
            'version'         => $donnees['version'] ?? '1.0.0',
            'icon'            => $donnees['icon'] ?? 'bi-tools',
            'sort_order'      => (int) ($donnees['sort_order'] ?? 100),
            'quota_mode'      => $donnees['quota_mode'] ?? 'none',
            'default_quota'   => (int) ($donnees['default_quota'] ?? 0),
            'point_entree'    => $donnees['point_entree'] ?? 'index.php',
            'cles_env'        => !empty($donnees['cles_env']) ? json_encode($donnees['cles_env']) : null,
            'routes_config'   => !empty($donnees['routes_config']) ? json_encode($donnees['routes_config']) : null,
            'passthrough_all' => !empty($donnees['passthrough_all']) ? 1 : 0,
            'mode_affichage'  => $donnees['mode_affichage'] ?? 'embedded',
            'categorie_id'    => $donnees['categorie_id'] ?? null,
        ]);
    }

    /**
     * Supprime un module de la base et nettoie les fichiers selon le type :
     * - Plugin ZIP (chemin_source sous storage/plugins/) → supprime le répertoire extrait
     * - Module embarqué (chemin_source NULL) → supprime modules/{slug}/
     * - Plugin chemin externe → ne touche pas aux fichiers sources
     */
    public function desinstaller(int $moduleId): void
    {
        $stmt = $this->db->prepare('SELECT chemin_source, slug FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $cheminSource = $row['chemin_source'];
        $slug = $row['slug'];

        $this->db->prepare('DELETE FROM user_module_access WHERE module_id = ?')->execute([$moduleId]);
        $this->db->prepare('DELETE FROM user_module_quotas WHERE module_id = ?')->execute([$moduleId]);
        $this->db->prepare('DELETE FROM module_usage WHERE module_id = ?')->execute([$moduleId]);
        $this->db->prepare('DELETE FROM modules WHERE id = ?')->execute([$moduleId]);

        if ($cheminSource !== null) {
            // Plugin ZIP extrait sous storage/plugins/ → supprimer le répertoire
            $repertoirePlugins = $this->repertoirePlugins();
            if (str_starts_with(realpath($cheminSource) ?: '', realpath($repertoirePlugins) ?: "\0")) {
                $this->supprimerRepertoire($cheminSource);
            }
            // Plugin chemin externe → ne rien faire
        } else {
            // Module embarqué → supprimer modules/{slug}/
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
            return match ($codeErreur) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée.',
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
     * Installe un plugin depuis un fichier ZIP uploadé.
     *
     * @param array<string, mixed> $fichierUpload Entrée de $_FILES
     * @return int ID du module créé
     * @throws \RuntimeException Si l'extraction échoue
     */
    public function installerDepuisZip(array $fichierUpload, int $installeParId): int
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
        ];

        return $this->installer($donneesInstall, $installeParId);
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
