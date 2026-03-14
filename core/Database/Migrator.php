<?php

namespace Platform\Database;

use PDO;

class Migrator
{
    private PDO $db;
    private string $migrationsPath;

    public function __construct(PDO $db, string $migrationsPath)
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath;
    }

    /**
     * Exécute toutes les migrations non encore appliquées (UP).
     *
     * @return string[] Noms des migrations exécutées
     */
    public function run(): array
    {
        $this->ensureMigrationsTable();
        $executed = $this->getExecutedMigrations();
        $files = $this->getMigrationFiles();
        $ran = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $executed, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            $upSql = $this->extraireSection($sql, 'up') ?? $sql;
            $this->execMultiStatement($upSql);

            $downSql = $this->extraireSection($sql, 'down');
            $stmt = $this->db->prepare(
                'INSERT INTO migrations (migration, down_sql) VALUES (?, ?)'
            );
            $stmt->execute([$name, $downSql]);

            $ran[] = $name;
        }

        return $ran;
    }

    /**
     * Annule la dernière migration appliquée.
     *
     * @return string|null Nom de la migration annulée, ou null si rien à annuler
     */
    public function rollback(): ?string
    {
        $this->ensureMigrationsTable();

        $stmt = $this->db->query(
            'SELECT id, migration, down_sql FROM migrations ORDER BY id DESC LIMIT 1'
        );
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $downSql = $row['down_sql'];
        if ($downSql === null || trim($downSql) === '') {
            throw new \RuntimeException(
                "La migration « {$row['migration']} » n'a pas de section DOWN. Rollback impossible."
            );
        }

        $this->execMultiStatement($downSql);

        $this->db->prepare('DELETE FROM migrations WHERE id = ?')
            ->execute([$row['id']]);

        return $row['migration'];
    }

    /**
     * Annule les N dernières migrations.
     *
     * @return string[] Noms des migrations annulées
     */
    public function rollbackN(int $n): array
    {
        $rolled = [];
        for ($i = 0; $i < $n; $i++) {
            $name = $this->rollback();
            if ($name === null) {
                break;
            }
            $rolled[] = $name;
        }
        return $rolled;
    }

    /**
     * Retourne le statut de toutes les migrations (exécutées ou non).
     *
     * @return array<int, array{migration: string, ran_at: ?string, has_down: bool}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $executed = $this->getExecutedMigrationsDetailed();
        $files = $this->getMigrationFiles();
        $status = [];

        foreach ($files as $file) {
            $name = basename($file);
            $exec = $executed[$name] ?? null;
            $status[] = [
                'migration' => $name,
                'ran_at'    => $exec['ran_at'] ?? null,
                'has_down'  => $exec !== null ? ($exec['down_sql'] !== null && trim($exec['down_sql']) !== '') : $this->fichierASection($file, 'down'),
            ];
        }

        return $status;
    }

    public function seed(string $seedFile): void
    {
        $sql = file_get_contents($seedFile);
        $this->db->exec($sql);
    }

    /**
     * Extrait une section -- @up ou -- @down depuis un fichier SQL.
     * Si aucune section n'est trouvée, retourne null.
     */
    private function extraireSection(string $sql, string $section): ?string
    {
        $marker = '-- @' . $section;
        $otherMarker = $section === 'up' ? '-- @down' : '-- @up';

        $pos = stripos($sql, $marker);
        if ($pos === false) {
            return null;
        }

        $start = $pos + strlen($marker);
        $end = stripos($sql, $otherMarker, $start);

        return $end !== false
            ? trim(substr($sql, $start, $end - $start))
            : trim(substr($sql, $start));
    }

    /**
     * Vérifie si un fichier SQL contient une section donnée.
     */
    private function fichierASection(string $filePath, string $section): bool
    {
        $content = file_get_contents($filePath);
        return stripos($content, '-- @' . $section) !== false;
    }

    /**
     * Exécute un fichier SQL pouvant contenir plusieurs statements séparés par ;
     */
    private function execMultiStatement(string $sql): void
    {
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            $this->db->exec($stmt);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->db->exec('CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                down_sql TEXT DEFAULT NULL,
                ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
        } else {
            $this->db->exec('CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                down_sql TEXT DEFAULT NULL,
                ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
        }

        // Ajouter la colonne down_sql si elle n'existe pas (migration existante)
        try {
            $this->db->query('SELECT down_sql FROM migrations LIMIT 0');
        } catch (\PDOException) {
            $this->db->exec('ALTER TABLE migrations ADD COLUMN down_sql TEXT DEFAULT NULL');
        }
    }

    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array<string, array{ran_at: string, down_sql: ?string}>
     */
    private function getExecutedMigrationsDetailed(): array
    {
        $stmt = $this->db->query('SELECT migration, ran_at, down_sql FROM migrations ORDER BY id');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['migration']] = [
                'ran_at'   => $row['ran_at'],
                'down_sql' => $row['down_sql'],
            ];
        }
        return $result;
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        return $files;
    }
}
