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
            $this->db->exec($sql);

            $stmt = $this->db->prepare('INSERT INTO migrations (migration) VALUES (?)');
            $stmt->execute([$name]);

            $ran[] = $name;
        }

        return $ran;
    }

    public function seed(string $seedFile): void
    {
        $sql = file_get_contents($seedFile);
        $this->db->exec($sql);
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        return $files;
    }
}
