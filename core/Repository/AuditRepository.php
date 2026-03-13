<?php

namespace Platform\Repository;

use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use PDO;

class AuditRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    /**
     * @param array{date_debut?: string, date_fin?: string, action?: string, utilisateur?: string, ip?: string} $filtres
     * @return array{donnees: array, total: int, page: int, parPage: int, totalPages: int}
     */
    public function rechercher(array $filtres, int $page = 1, int $parPage = 50): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        [$whereSql, $params] = $this->construireFiltres($filtres);

        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1 {$whereSql}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1 {$whereSql} ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }
        $stmt->bindValue($i++, $parPage, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'donnees'    => $stmt->fetchAll(),
            'total'      => $total,
            'page'       => $page,
            'parPage'    => $parPage,
            'totalPages' => max(1, (int) ceil($total / $parPage)),
        ];
    }

    /**
     * @param array{date_debut?: string, date_fin?: string, action?: string, utilisateur?: string, ip?: string} $filtres
     * @return array<int, array<string, mixed>>
     */
    public function rechercherTout(array $filtres): array
    {
        [$whereSql, $params] = $this->construireFiltres($filtres);

        $sql = "SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1 {$whereSql} ORDER BY a.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Supprime les entrées d'audit dans une plage de dates.
     * Retourne le nombre de lignes supprimées.
     */
    public function purgerParPlage(string $dateDebut, string $dateFin): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM audit_log WHERE created_at >= ? AND created_at <= ?'
        );
        $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);

        return $stmt->rowCount();
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function construireFiltres(array $filtres): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filtres['date_debut']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['date_debut'])) {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $filtres['date_debut'] . ' 00:00:00';
        }

        if (!empty($filtres['date_fin']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['date_fin'])) {
            $conditions[] = 'a.created_at <= ?';
            $params[] = $filtres['date_fin'] . ' 23:59:59';
        }

        if (!empty($filtres['action']) && AuditAction::tryFrom($filtres['action']) !== null) {
            $conditions[] = 'a.action = ?';
            $params[] = $filtres['action'];
        }

        if (!empty($filtres['utilisateur'])) {
            $conditions[] = 'u.username LIKE ?';
            $params[] = '%' . mb_substr($filtres['utilisateur'], 0, 100) . '%';
        }

        if (!empty($filtres['ip'])) {
            $conditions[] = 'a.ip_address LIKE ?';
            $params[] = '%' . mb_substr($filtres['ip'], 0, 45) . '%';
        }

        $whereSql = $conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }
}
