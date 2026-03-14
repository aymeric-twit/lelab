<?php

namespace Platform\Service;

use Platform\Database\Connection;
use PDO;

/**
 * Service de notifications in-app (cloche dans la navbar).
 */
class NotificationInAppService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Crée une notification pour un utilisateur spécifique.
     */
    public function notifier(int $userId, string $type, string $titre, string $message, ?string $lien = null, string $icone = 'bi-bell'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, titre, message, lien, icone) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $titre, $message, $lien, $icone]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Crée une notification pour tous les utilisateurs actifs (broadcast).
     */
    public function diffuser(string $type, string $titre, string $message, ?string $lien = null, string $icone = 'bi-megaphone'): int
    {
        $users = $this->db->query('SELECT id FROM users WHERE deleted_at IS NULL AND active = 1')->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, titre, message, lien, icone) VALUES (?, ?, ?, ?, ?, ?)'
        );

        $count = 0;
        foreach ($users as $userId) {
            $stmt->execute([$userId, $type, $titre, $message, $lien, $icone]);
            $count++;
        }

        return $count;
    }

    /**
     * Retourne les notifications non lues d'un utilisateur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nonLues(int $userId, int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications WHERE user_id = ? AND lue = 0 ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retourne le nombre de notifications non lues.
     */
    public function compterNonLues(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lue = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne toutes les notifications (lues + non lues) paginées.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toutes(int $userId, int $limite = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Marque une notification comme lue.
     */
    public function marquerLue(int $notificationId, int $userId): void
    {
        $this->db->prepare('UPDATE notifications SET lue = 1 WHERE id = ? AND user_id = ?')
            ->execute([$notificationId, $userId]);
    }

    /**
     * Marque toutes les notifications comme lues.
     */
    public function marquerToutesLues(int $userId): void
    {
        $this->db->prepare('UPDATE notifications SET lue = 1 WHERE user_id = ? AND lue = 0')
            ->execute([$userId]);
    }

    /**
     * Supprime les notifications plus anciennes que N jours.
     */
    public function purger(int $joursAConserver = 90): int
    {
        $stmt = $this->db->prepare('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $stmt->execute([$joursAConserver]);
        return $stmt->rowCount();
    }
}
