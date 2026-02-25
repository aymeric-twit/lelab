<?php

namespace Platform\Http;

use Platform\Database\Connection;
use PDO;

/**
 * Rate limiter générique basé sur la table audit_log.
 * Peut limiter par IP, par utilisateur, ou par combinaison des deux.
 */
class RateLimiter
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    /**
     * Vérifie si une action est limitée pour une IP donnée.
     */
    public function estLimiteParIp(string $action, string $ip, int $maxTentatives, int $fenetreSecondes): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM audit_log
             WHERE action = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$action, $ip, $fenetreSecondes]);
        $row = $stmt->fetch();
        return ($row['cnt'] ?? 0) >= $maxTentatives;
    }

    /**
     * Vérifie si une action est limitée pour un utilisateur donné.
     */
    public function estLimiteParUtilisateur(string $action, int $userId, int $maxTentatives, int $fenetreSecondes): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM audit_log
             WHERE action = ? AND user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$action, $userId, $fenetreSecondes]);
        $row = $stmt->fetch();
        return ($row['cnt'] ?? 0) >= $maxTentatives;
    }
}
