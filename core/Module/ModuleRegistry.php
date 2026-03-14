<?php

namespace Platform\Module;

use PDO;
use Platform\Enum\QuotaMode;

class ModuleRegistry
{
    private static array $modules = [];
    private static bool $loaded = false;

    /** Chemin du fichier de cache des modules embarqués */
    private static function cheminCache(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/modules_registry.json';
    }

    public static function discover(string $modulesPath): void
    {
        if (self::$loaded) {
            return;
        }

        // Tenter de charger depuis le cache fichier
        $cachePath = self::cheminCache();
        if (file_exists($cachePath)) {
            $cacheData = json_decode(file_get_contents($cachePath), true);
            if (is_array($cacheData)) {
                foreach ($cacheData as $entry) {
                    if (isset($entry['slug'], $entry['path']) && is_dir($entry['path'])) {
                        self::$modules[$entry['slug']] = new ModuleDescriptor($entry['path'], $entry['data']);
                    }
                }
                self::$loaded = true;
                return;
            }
        }

        // Scan filesystem et construction du cache
        $cacheEntries = [];
        $dirs = glob($modulesPath . '/*/module.json');
        foreach ($dirs as $jsonFile) {
            $basePath = dirname($jsonFile);
            // Skip the _template directory (scaffolding only)
            if (basename($basePath) === '_template') {
                continue;
            }
            $data = json_decode(file_get_contents($jsonFile), true);
            if ($data && isset($data['slug'])) {
                self::$modules[$data['slug']] = new ModuleDescriptor($basePath, $data);
                $cacheEntries[] = ['slug' => $data['slug'], 'path' => $basePath, 'data' => $data];
            }
        }

        // Persister le cache
        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cachePath, json_encode($cacheEntries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        self::$loaded = true;
    }

    /**
     * Charge les plugins externes depuis la base de données.
     * Ne remplace pas un module embarqué qui aurait le même slug.
     */
    public static function chargerDepuisBase(PDO $db): void
    {
        $stmt = $db->query(
            'SELECT * FROM modules WHERE chemin_source IS NOT NULL AND enabled = 1 AND desinstalle_le IS NULL'
        );

        foreach ($stmt->fetchAll() as $row) {
            $slug = $row['slug'];

            if (isset(self::$modules[$slug])) {
                continue;
            }

            if (!is_dir($row['chemin_source'])) {
                continue;
            }

            // Relire module.json pour les champs contrôlés par le plugin
            $moduleJson = [];
            $jsonPath = $row['chemin_source'] . '/module.json';
            if (file_exists($jsonPath)) {
                $moduleJson = json_decode(file_get_contents($jsonPath), true) ?? [];
            }

            $data = [
                'slug'            => $slug,
                'name'            => $moduleJson['name'] ?? $row['name'],
                'description'     => $moduleJson['description'] ?? $row['description'] ?? '',
                'version'         => $moduleJson['version'] ?? $row['version'] ?? '1.0.0',
                'icon'            => $moduleJson['icon'] ?? $row['icon'] ?? 'bi-tools',
                'entry_point'     => $moduleJson['entry_point'] ?? $row['point_entree'] ?? 'index.php',
                'sort_order'      => (int) ($row['sort_order'] ?? $moduleJson['sort_order'] ?? 100),
                'env_keys'        => $moduleJson['env_keys'] ?? ($row['cles_env'] ? json_decode($row['cles_env'], true) : []),
                'routes'          => $moduleJson['routes'] ?? ($row['routes_config'] ? json_decode($row['routes_config'], true) : []),
                'passthrough_all' => (bool) ($row['passthrough_all'] ?? false),
                'display_mode'    => $moduleJson['display_mode'] ?? $row['mode_affichage'] ?? 'embedded',
                'quota_mode'      => $moduleJson['quota_mode'] ?? $row['quota_mode'] ?? 'none',
                'default_quota'   => (int) ($moduleJson['default_quota'] ?? $row['default_quota'] ?? 0),
                'categorie_id'    => $row['categorie_id'] ?? null,
                'languages'       => $moduleJson['languages'] ?? (!empty($row['langues']) ? json_decode($row['langues'], true) : []),
                'domain_field'    => $moduleJson['domain_field'] ?? $row['domain_field'] ?? null,
            ];

            self::$modules[$slug] = new ModuleDescriptor($row['chemin_source'], $data);

            // Synchroniser les champs contrôlés par le plugin vers la BDD si module.json a changé
            if (!empty($moduleJson)) {
                $champsASync = [];
                $params = [];

                $mapping = [
                    'name'           => ['db' => 'name',           'val' => $data['name']],
                    'description'    => ['db' => 'description',    'val' => $data['description']],
                    'version'        => ['db' => 'version',        'val' => $data['version']],
                    'icon'           => ['db' => 'icon',           'val' => $data['icon']],
                    'quota_mode'     => ['db' => 'quota_mode',     'val' => $data['quota_mode']],
                    'default_quota'  => ['db' => 'default_quota',  'val' => (string) $data['default_quota']],
                    'display_mode'   => ['db' => 'mode_affichage', 'val' => $data['display_mode']],
                    'domain_field'   => ['db' => 'domain_field',   'val' => $data['domain_field']],
                ];

                foreach ($mapping as $jsonKey => $info) {
                    $dbVal = $row[$info['db']] ?? null;
                    if ((string) $dbVal !== (string) $info['val']) {
                        $champsASync[] = "{$info['db']} = ?";
                        $params[] = $info['val'];
                    }
                }

                // Synchroniser aussi routes, langues et cles_env
                $routesJson = json_encode($data['routes'] ?? [], JSON_UNESCAPED_UNICODE);
                if ($routesJson !== ($row['routes_config'] ?? '[]')) {
                    $champsASync[] = 'routes_config = ?';
                    $params[] = $routesJson;
                }

                $languesJson = json_encode($data['languages'] ?? [], JSON_UNESCAPED_UNICODE);
                if ($languesJson !== ($row['langues'] ?? '[]')) {
                    $champsASync[] = 'langues = ?';
                    $params[] = $languesJson;
                }

                $envKeysJson = json_encode($data['env_keys'] ?? [], JSON_UNESCAPED_UNICODE);
                if ($envKeysJson !== ($row['cles_env'] ?? '[]')) {
                    $champsASync[] = 'cles_env = ?';
                    $params[] = $envKeysJson;
                }

                if (!empty($champsASync)) {
                    $params[] = (int) $row['id'];
                    $sql = 'UPDATE modules SET ' . implode(', ', $champsASync) . ' WHERE id = ?';
                    try {
                        $db->prepare($sql)->execute($params);
                    } catch (\PDOException $e) {
                        // Valeur incompatible avec le schéma BDD (ex: ENUM pas encore migré)
                        // On ignore silencieusement — le ModuleDescriptor en mémoire a la bonne valeur
                    }
                }
            }
        }
    }

    /**
     * Retourne toutes les catégories ordonnées par sort_order.
     *
     * @return array<int, array{id: int, nom: string, icone: string, sort_order: int}>
     */
    public static function chargerCategories(PDO $db): array
    {
        return $db->query(
            'SELECT * FROM categories ORDER BY sort_order, nom'
        )->fetchAll();
    }

    /**
     * Invalide le cache fichier des modules embarqués.
     * À appeler après installation/désinstallation/mise à jour d'un plugin.
     */
    public static function invaliderCache(): void
    {
        $cachePath = self::cheminCache();
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
        self::$modules = [];
        self::$loaded = false;
    }

    public static function get(string $slug): ?ModuleDescriptor
    {
        return self::$modules[$slug] ?? null;
    }

    public static function all(): array
    {
        return self::$modules;
    }

    /**
     * Synchronise les modules embarqués vers la base.
     * Protège les plugins externes : le ON DUPLICATE KEY UPDATE ne touche
     * que les lignes où chemin_source IS NULL (modules embarqués).
     * Crée aussi les symlinks d'assets manquants pour tous les modules.
     */
    public static function syncToDatabase(PDO $db): void
    {
        $stmt = $db->prepare('
            INSERT INTO modules (slug, name, description, version, icon, sort_order, quota_mode, default_quota, mode_affichage, domain_field)
            VALUES (:slug, :name, :description, :version, :icon, :sort_order, :quota_mode, :default_quota, :mode_affichage, :domain_field)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                version = VALUES(version),
                icon = VALUES(icon),
                sort_order = IF(chemin_source IS NULL, VALUES(sort_order), sort_order),
                quota_mode = VALUES(quota_mode),
                default_quota = VALUES(default_quota),
                mode_affichage = VALUES(mode_affichage),
                domain_field = VALUES(domain_field),
                desinstalle_le = IF(chemin_source IS NULL, NULL, desinstalle_le),
                desinstalle_par = IF(chemin_source IS NULL, NULL, desinstalle_par),
                enabled = IF(chemin_source IS NULL, 1, enabled)
        ');

        foreach (self::$modules as $module) {
            $stmt->execute([
                'slug'           => $module->slug,
                'name'           => $module->name,
                'description'    => $module->description,
                'version'        => $module->version,
                'icon'           => $module->icon,
                'sort_order'     => $module->sortOrder,
                'quota_mode'     => $module->quotaMode->value,
                'default_quota'  => $module->defaultQuota,
                'mode_affichage' => $module->modeAffichage->value,
                'domain_field'   => $module->domainField,
            ]);
        }

        // Créer les symlinks d'assets manquants pour les modules embarqués
        self::creerSymlinksManquants();
    }

    /**
     * Crée les symlinks public/module-assets/{slug} manquants pour chaque module du registre.
     */
    private static function creerSymlinksManquants(): void
    {
        $repertoireAssets = dirname(__DIR__, 2) . '/public/module-assets';
        if (!is_dir($repertoireAssets)) {
            mkdir($repertoireAssets, 0755, true);
        }

        foreach (self::$modules as $module) {
            $lien = $repertoireAssets . '/' . $module->slug;
            if (!is_link($lien) && !is_dir($lien)) {
                @symlink($module->path, $lien);
            }
        }
    }
}
