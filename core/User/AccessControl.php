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
        $stmt = $this->db->prepare(
            'SELECT uma.granted FROM user_module_access uma
             JOIN modules m ON m.id = uma.module_id
             WHERE uma.user_id = ? AND m.slug = ? AND m.enabled = 1'
        );
        $stmt->execute([$userId, $moduleSlug]);
        $row = $stmt->fetch();
        return $row && $row['granted'];
    }

    public function getAccessibleModules(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.* FROM modules m
             JOIN user_module_access uma ON uma.module_id = m.id
             WHERE uma.user_id = ? AND uma.granted = 1 AND m.enabled = 1
             ORDER BY m.sort_order'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getAccessMatrix(): array
    {
        $stmt = $this->db->query(
            'SELECT uma.user_id, uma.module_id, uma.granted
             FROM user_module_access uma'
        );
        $rows = $stmt->fetchAll();
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['user_id']][$row['module_id']] = (bool) $row['granted'];
        }
        return $matrix;
    }

    public function setAccess(int $userId, int $moduleId, bool $granted, ?int $grantedBy = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_module_access (user_id, module_id, granted, granted_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE granted = VALUES(granted), granted_by = VALUES(granted_by), granted_at = NOW()'
        );
        $stmt->execute([$userId, $moduleId, (int) $granted, $grantedBy]);
    }

    public function removeAccess(int $userId, int $moduleId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM user_module_access WHERE user_id = ? AND module_id = ?'
        );
        $stmt->execute([$userId, $moduleId]);
    }
}
