<?php

namespace Platform\Repository;

use Platform\Database\Connection;
use PDO;

class SettingsRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    public function obtenir(string $groupe, string $cle, ?string $defaut = null): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT `value` FROM settings WHERE `group` = ? AND `key` = ? LIMIT 1'
        );
        $stmt->execute([$groupe, $cle]);
        $row = $stmt->fetch();

        if ($row === false || $row['value'] === null || $row['value'] === '') {
            return $defaut;
        }

        return $row['value'];
    }

    /**
     * @return array<string, string|null>
     */
    public function obtenirGroupe(string $groupe): array
    {
        $stmt = $this->db->prepare(
            'SELECT `key`, `value` FROM settings WHERE `group` = ?'
        );
        $stmt->execute([$groupe]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }

        return $result;
    }

    public function definir(string $groupe, string $cle, ?string $valeur): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->db->prepare(
                'INSERT OR REPLACE INTO settings (`group`, `key`, `value`, updated_at) VALUES (?, ?, ?, datetime("now"))'
            )->execute([$groupe, $cle, $valeur]);
        } else {
            $this->db->prepare(
                'INSERT INTO settings (`group`, `key`, `value`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()'
            )->execute([$groupe, $cle, $valeur]);
        }
    }

    /**
     * @param array<string, string|null> $valeurs
     */
    public function definirGroupe(string $groupe, array $valeurs): void
    {
        foreach ($valeurs as $cle => $valeur) {
            $this->definir($groupe, $cle, $valeur);
        }
    }

    public function supprimer(string $groupe, string $cle): void
    {
        $this->db->prepare(
            'DELETE FROM settings WHERE `group` = ? AND `key` = ?'
        )->execute([$groupe, $cle]);
    }
}
