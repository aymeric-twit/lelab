<?php

namespace Platform\Repository;

use Platform\Database\Connection;
use Platform\Enum\TypeNotification;
use PDO;

class EmailLogRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    public function enregistrer(
        string $destinataire,
        string $sujet,
        ?string $typeEmail,
        string $statut,
        ?string $erreur = null,
        ?int $userId = null,
    ): void {
        $this->db->prepare(
            'INSERT INTO email_log (destinataire, sujet, type_email, statut, erreur, user_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$destinataire, $sujet, $typeEmail, $statut, $erreur, $userId]);
    }

    /**
     * @param array{date_debut?: string, date_fin?: string, type?: string, statut?: string, destinataire?: string} $filtres
     * @return array{donnees: array, total: int, page: int, parPage: int, totalPages: int}
     */
    public function rechercher(array $filtres, int $page = 1, int $parPage = 30): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        [$whereSql, $params] = $this->construireFiltres($filtres);

        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM email_log WHERE 1=1 {$whereSql}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "SELECT * FROM email_log WHERE 1=1 {$whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?";
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
     * @return array<int, array<string, mixed>>
     */
    public function rechercherTout(array $filtres): array
    {
        [$whereSql, $params] = $this->construireFiltres($filtres);

        $sql = "SELECT * FROM email_log WHERE 1=1 {$whereSql} ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array{envoye: int, echec: int}
     */
    public function compterParStatut(int $joursRecents = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT statut, COUNT(*) as total FROM email_log
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY statut'
        );
        $stmt->execute([$joursRecents]);
        $rows = $stmt->fetchAll();

        $result = ['envoye' => 0, 'echec' => 0];
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function construireFiltres(array $filtres): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filtres['date_debut']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['date_debut'])) {
            $conditions[] = 'created_at >= ?';
            $params[] = $filtres['date_debut'] . ' 00:00:00';
        }

        if (!empty($filtres['date_fin']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['date_fin'])) {
            $conditions[] = 'created_at <= ?';
            $params[] = $filtres['date_fin'] . ' 23:59:59';
        }

        if (!empty($filtres['type']) && TypeNotification::tryFrom($filtres['type']) !== null) {
            $conditions[] = 'type_email = ?';
            $params[] = $filtres['type'];
        }

        if (!empty($filtres['statut']) && in_array($filtres['statut'], ['envoye', 'echec'], true)) {
            $conditions[] = 'statut = ?';
            $params[] = $filtres['statut'];
        }

        if (!empty($filtres['destinataire'])) {
            $conditions[] = 'destinataire LIKE ?';
            $params[] = '%' . mb_substr($filtres['destinataire'], 0, 100) . '%';
        }

        $whereSql = $conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }
}
