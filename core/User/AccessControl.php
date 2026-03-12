<?php

namespace Platform\User;

use Platform\Database\Connection;
use PDO;

class AccessControl
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    public function hasAccess(int $userId, string $moduleSlug): bool
    {
        // Vérifier l'accès direct
        $stmt = $this->db->prepare(
            'SELECT uma.granted FROM user_module_access uma
             JOIN modules m ON m.id = uma.module_id
             WHERE uma.user_id = ? AND m.slug = ? AND m.enabled = 1
             AND (uma.expires_at IS NULL OR uma.expires_at > NOW())'
        );
        $stmt->execute([$userId, $moduleSlug]);
        $row = $stmt->fetch();
        if ($row && $row['granted']) {
            return true;
        }

        // Vérifier l'accès via les groupes
        $stmtGroupe = $this->db->prepare(
            'SELECT 1 FROM user_group_members ugm
             JOIN group_module_access gma ON gma.group_id = ugm.group_id
             JOIN modules m ON m.id = gma.module_id
             WHERE ugm.user_id = ? AND m.slug = ? AND m.enabled = 1 AND gma.granted = 1'
        );
        $stmtGroupe->execute([$userId, $moduleSlug]);
        return (bool) $stmtGroupe->fetch();
    }

    public function getAccessibleModules(int $userId): array
    {
        // Modules accessibles via accès direct OU via groupes (UNION pour dédupliquer)
        $stmt = $this->db->prepare(
            'SELECT m.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.sort_order AS categorie_sort_order
             FROM modules m
             LEFT JOIN categories c ON c.id = m.categorie_id
             WHERE m.enabled = 1 AND (
                 m.id IN (
                     SELECT uma.module_id FROM user_module_access uma
                     WHERE uma.user_id = ? AND uma.granted = 1
                     AND (uma.expires_at IS NULL OR uma.expires_at > NOW())
                 )
                 OR m.id IN (
                     SELECT gma.module_id FROM group_module_access gma
                     JOIN user_group_members ugm ON ugm.group_id = gma.group_id
                     WHERE ugm.user_id = ? AND gma.granted = 1
                 )
             )
             ORDER BY COALESCE(c.sort_order, 9999), c.nom, m.sort_order'
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    public function getAccessMatrix(): array
    {
        $stmt = $this->db->query(
            'SELECT uma.user_id, uma.module_id, uma.granted, uma.expires_at
             FROM user_module_access uma'
        );
        $rows = $stmt->fetchAll();
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['user_id']][$row['module_id']] = [
                'granted'    => (bool) $row['granted'],
                'expires_at' => $row['expires_at'],
            ];
        }
        return $matrix;
    }

    public function setAccess(int $userId, int $moduleId, bool $granted, ?int $grantedBy = null, ?string $expiresAt = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_module_access (user_id, module_id, granted, granted_by, expires_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE granted = VALUES(granted), granted_by = VALUES(granted_by), granted_at = NOW(), expires_at = VALUES(expires_at)'
        );
        $stmt->execute([$userId, $moduleId, (int) $granted, $grantedBy, $expiresAt]);
    }

    public function removeAccess(int $userId, int $moduleId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM user_module_access WHERE user_id = ? AND module_id = ?'
        );
        $stmt->execute([$userId, $moduleId]);
    }

    /**
     * Accorde l'accès à un module pour tous les utilisateurs actifs (non supprimés).
     * Utilisé lors de l'installation d'un nouveau plugin.
     */
    public function accorderAccesTousUtilisateurs(int $moduleId, ?int $grantedBy = null): void
    {
        $this->db->prepare(
            'INSERT IGNORE INTO user_module_access (user_id, module_id, granted, granted_by)
             SELECT id, ?, 1, ? FROM users WHERE deleted_at IS NULL AND active = 1'
        )->execute([$moduleId, $grantedBy]);
    }
}
