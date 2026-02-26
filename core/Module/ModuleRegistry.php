<?php

namespace Platform\Module;

use PDO;
use Platform\Enum\QuotaMode;

class ModuleRegistry
{
    private static array $modules = [];
    private static bool $loaded = false;

    public static function discover(string $modulesPath): void
    {
        if (self::$loaded) {
            return;
        }

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
            }
        }

        self::$loaded = true;
    }

    /**
     * Charge les plugins externes depuis la base de données.
     * Ne remplace pas un module embarqué qui aurait le même slug.
     */
    public static function chargerDepuisBase(PDO $db): void
    {
        $stmt = $db->query(
            'SELECT * FROM modules WHERE chemin_source IS NOT NULL AND enabled = 1'
        );

        foreach ($stmt->fetchAll() as $row) {
            $slug = $row['slug'];

            if (isset(self::$modules[$slug])) {
                continue;
            }

            if (!is_dir($row['chemin_source'])) {
                continue;
            }

            $data = [
                'slug'            => $slug,
                'name'            => $row['name'],
                'description'     => $row['description'] ?? '',
                'version'         => $row['version'] ?? '1.0.0',
                'icon'            => $row['icon'] ?? 'bi-tools',
                'entry_point'     => $row['point_entree'] ?? 'index.php',
                'sort_order'      => (int) $row['sort_order'],
                'env_keys'        => $row['cles_env'] ? json_decode($row['cles_env'], true) : [],
                'routes'          => $row['routes_config'] ? json_decode($row['routes_config'], true) : [],
                'passthrough_all' => (bool) $row['passthrough_all'],
                'display_mode'    => $row['mode_affichage'] ?? 'embedded',
                'quota_mode'      => $row['quota_mode'] ?? 'none',
                'default_quota'   => (int) ($row['default_quota'] ?? 0),
                'categorie_id'    => $row['categorie_id'] ?? null,
            ];

            self::$modules[$slug] = new ModuleDescriptor($row['chemin_source'], $data);
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
     */
    public static function syncToDatabase(PDO $db): void
    {
        $stmt = $db->prepare('
            INSERT INTO modules (slug, name, description, version, icon, sort_order, quota_mode, default_quota, mode_affichage)
            VALUES (:slug, :name, :description, :version, :icon, :sort_order, :quota_mode, :default_quota, :mode_affichage)
            ON DUPLICATE KEY UPDATE
                name = IF(chemin_source IS NULL, VALUES(name), name),
                description = IF(chemin_source IS NULL, VALUES(description), description),
                version = IF(chemin_source IS NULL, VALUES(version), version),
                icon = IF(chemin_source IS NULL, VALUES(icon), icon),
                sort_order = IF(chemin_source IS NULL, VALUES(sort_order), sort_order),
                quota_mode = IF(chemin_source IS NULL, VALUES(quota_mode), quota_mode),
                default_quota = IF(chemin_source IS NULL, VALUES(default_quota), default_quota),
                mode_affichage = IF(chemin_source IS NULL, VALUES(mode_affichage), mode_affichage)
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
            ]);
        }
    }
}
