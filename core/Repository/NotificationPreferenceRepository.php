<?php

namespace Platform\Repository;

use Platform\Database\Connection;
use PDO;

class NotificationPreferenceRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Vérifie si un type de notification est désactivé par l'utilisateur.
     */
    public function estDesactive(int $userId, string $type): bool
    {
        $stmt = $this->db->prepare(
            'SELECT actif FROM user_notification_preferences WHERE user_id = ? AND type_notification = ?'
        );
        $stmt->execute([$userId, $type]);
        $result = $stmt->fetchColumn();

        // Pas de ligne = actif par défaut
        if ($result === false) {
            return false;
        }

        return (int) $result === 0;
    }

    /**
     * Retourne toutes les préférences d'un utilisateur.
     *
     * @return array<string, bool> type_notification => actif
     */
    public function obtenirPreferences(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT type_notification, actif FROM user_notification_preferences WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['type_notification']] = (bool) $row['actif'];
        }

        return $prefs;
    }

    /**
     * Met à jour une préférence de notification pour un utilisateur.
     */
    public function mettreAJour(int $userId, string $type, bool $actif): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';

        if ($driver === 'sqlite') {
            $sql = "INSERT OR REPLACE INTO user_notification_preferences (user_id, type_notification, actif, updated_at)
                    VALUES (?, ?, ?, {$now})";
        } else {
            $sql = "INSERT INTO user_notification_preferences (user_id, type_notification, actif, updated_at)
                    VALUES (?, ?, ?, {$now})
                    ON DUPLICATE KEY UPDATE actif = VALUES(actif), updated_at = {$now}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $type, $actif ? 1 : 0]);
    }

    /**
     * Met à jour plusieurs préférences en une fois.
     *
     * @param array<string, bool> $preferences type => actif
     */
    public function mettreAJourMultiple(int $userId, array $preferences): void
    {
        foreach ($preferences as $type => $actif) {
            $this->mettreAJour($userId, $type, $actif);
        }
    }
}
