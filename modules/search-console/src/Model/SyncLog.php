<?php

namespace App\Model;

use App\Database\Connection;
use PDO;

/**
 * Journal de synchronisation.
 * Trace chaque exécution de sync (début, fin, statut, erreurs).
 */
class SyncLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    /** Démarre un nouvel enregistrement de sync. */
    public function start(int $siteId, string $searchType, string $dateFrom, string $dateTo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sync_logs (site_id, search_type, date_from, date_to, status, started_at)
             VALUES (:site_id, :search_type, :date_from, :date_to, "running", NOW())'
        );

        $stmt->execute([
            'site_id'     => $siteId,
            'search_type' => $searchType,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Marque une sync comme réussie. */
    public function success(int $logId, int $rowsFetched, int $rowsInserted, float $duration): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_logs
             SET status = "success", rows_fetched = :fetched, rows_inserted = :inserted,
                 duration_sec = :duration, finished_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id'       => $logId,
            'fetched'  => $rowsFetched,
            'inserted' => $rowsInserted,
            'duration' => $duration,
        ]);
    }

    /** Marque une sync comme échouée. */
    public function error(int $logId, string $message, float $duration): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_logs
             SET status = "error", error_message = :msg, duration_sec = :duration, finished_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id'       => $logId,
            'msg'      => mb_substr($message, 0, 5000),
            'duration' => $duration,
        ]);
    }

    /** Derniers logs de synchronisation. */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT sl.*, s.site_url
             FROM sync_logs sl
             JOIN sites s ON s.id = sl.site_id
             ORDER BY sl.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Dernière sync réussie pour un site et type. */
    public function lastSuccess(int $siteId, string $searchType): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sync_logs
             WHERE site_id = :site_id AND search_type = :st AND status = "success"
             ORDER BY date_to DESC LIMIT 1'
        );
        $stmt->execute(['site_id' => $siteId, 'st' => $searchType]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
