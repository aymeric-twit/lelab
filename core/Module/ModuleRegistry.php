<?php

namespace Platform\Module;

use PDO;

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

    public static function get(string $slug): ?ModuleDescriptor
    {
        return self::$modules[$slug] ?? null;
    }

    public static function all(): array
    {
        return self::$modules;
    }

    public static function syncToDatabase(PDO $db): void
    {
        $stmt = $db->prepare('
            INSERT INTO modules (slug, name, description, version, icon, sort_order, quota_mode, default_quota)
            VALUES (:slug, :name, :description, :version, :icon, :sort_order, :quota_mode, :default_quota)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                version = VALUES(version),
                icon = VALUES(icon),
                sort_order = VALUES(sort_order),
                quota_mode = VALUES(quota_mode),
                default_quota = VALUES(default_quota)
        ');

        foreach (self::$modules as $module) {
            $stmt->execute([
                'slug'          => $module->slug,
                'name'          => $module->name,
                'description'   => $module->description,
                'version'       => $module->version,
                'icon'          => $module->icon,
                'sort_order'    => $module->sortOrder,
                'quota_mode'    => $module->quotaMode,
                'default_quota' => $module->defaultQuota,
            ]);
        }
    }
}
