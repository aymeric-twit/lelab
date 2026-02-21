<?php

namespace App\Model;

use App\Database\Connection;
use PDO;

/**
 * Modèle pour les données de performance Search Console.
 *
 * Gère l'insertion (UPSERT) et les requêtes d'analyse.
 */
class PerformanceData
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    // ------------------------------------------------------------------
    // Insertion / Upsert
    // ------------------------------------------------------------------

    /**
     * Insère un lot de lignes de performance.
     * Utilise INSERT ... ON DUPLICATE KEY UPDATE pour dédupliquer.
     *
     * @param int    $siteId     ID du site en base
     * @param string $searchType Type de recherche (web, image, video)
     * @param array  $rows       Lignes retournées par SearchConsoleAPI
     * @return int Nombre de lignes effectivement insérées/mises à jour
     */
    public function upsertBatch(int $siteId, string $searchType, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $sql = 'INSERT INTO performance_data
                    (site_id, data_date, page, query, country, device, search_type,
                     clicks, impressions, ctr, position)
                VALUES
                    (:site_id, :data_date, :page, :query, :country, :device, :search_type,
                     :clicks, :impressions, :ctr, :position)
                ON DUPLICATE KEY UPDATE
                    clicks      = VALUES(clicks),
                    impressions = VALUES(impressions),
                    ctr         = VALUES(ctr),
                    position    = VALUES(position)';

        $stmt = $this->db->prepare($sql);
        $affected = 0;

        // Insertion par lots dans une transaction
        $this->db->beginTransaction();

        try {
            foreach ($rows as $row) {
                $stmt->execute([
                    'site_id'     => $siteId,
                    'data_date'   => $row['date'],
                    'page'        => $row['page'],
                    'query'       => $row['query'],
                    'country'     => $row['country'],
                    'device'      => $row['device'],
                    'search_type' => $searchType,
                    'clicks'      => $row['clicks'],
                    'impressions' => $row['impressions'],
                    'ctr'         => $row['ctr'],
                    'position'    => $row['position'],
                ]);
                $affected += $stmt->rowCount();
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $affected;
    }

    // ------------------------------------------------------------------
    // Requêtes d'analyse pour le dashboard
    // ------------------------------------------------------------------

    /**
     * Tendance quotidienne : clicks, impressions, CTR, position.
     */
    public function dailyTrend(int $siteId, string $from, string $to, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT data_date,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions,
                       CASE WHEN SUM(impressions) > 0
                            THEN SUM(clicks) / SUM(impressions)
                            ELSE 0 END  AS ctr,
                       AVG(position)    AS position
                FROM performance_data
                WHERE {$where}
                GROUP BY data_date
                ORDER BY data_date";

        return $this->db->prepare($sql)->execute($params)
            ? $this->db->prepare($sql)->fetchAll()
            : [];
    }

    /**
     * Tendance quotidienne optimisée (exécute et retourne).
     */
    public function getDailyTrend(int $siteId, string $from, string $to, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT data_date,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions,
                       CASE WHEN SUM(impressions) > 0
                            THEN SUM(clicks) / SUM(impressions)
                            ELSE 0 END  AS ctr,
                       AVG(position)    AS position
                FROM performance_data
                WHERE {$where}
                GROUP BY data_date
                ORDER BY data_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Top requêtes triées par clicks. */
    public function topQueries(int $siteId, string $from, string $to, int $limit = 50, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT query,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions,
                       CASE WHEN SUM(impressions) > 0
                            THEN SUM(clicks) / SUM(impressions)
                            ELSE 0 END  AS ctr,
                       AVG(position)    AS position
                FROM performance_data
                WHERE {$where} AND query != ''
                GROUP BY query
                ORDER BY clicks DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Top pages triées par impressions. */
    public function topPages(int $siteId, string $from, string $to, int $limit = 50, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT page,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions,
                       CASE WHEN SUM(impressions) > 0
                            THEN SUM(clicks) / SUM(impressions)
                            ELSE 0 END  AS ctr,
                       AVG(position)    AS position
                FROM performance_data
                WHERE {$where} AND page != ''
                GROUP BY page
                ORDER BY impressions DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Répartition par appareil. */
    public function byDevice(int $siteId, string $from, string $to, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT device,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions
                FROM performance_data
                WHERE {$where} AND device != ''
                GROUP BY device
                ORDER BY clicks DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Répartition par pays. */
    public function byCountry(int $siteId, string $from, string $to, int $limit = 20, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT country,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions
                FROM performance_data
                WHERE {$where} AND country != ''
                GROUP BY country
                ORDER BY clicks DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Comparaison entre deux périodes : totaux agrégés.
     */
    public function comparePeriods(
        int $siteId,
        string $from1, string $to1,
        string $from2, string $to2,
        array $filters = []
    ): array {
        $current  = $this->periodTotals($siteId, $from1, $to1, $filters);
        $previous = $this->periodTotals($siteId, $from2, $to2, $filters);

        return [
            'current'  => $current,
            'previous' => $previous,
            'diff'     => [
                'clicks'      => ($current['clicks'] ?? 0) - ($previous['clicks'] ?? 0),
                'impressions' => ($current['impressions'] ?? 0) - ($previous['impressions'] ?? 0),
                'ctr'         => ($current['ctr'] ?? 0) - ($previous['ctr'] ?? 0),
                'position'    => ($current['position'] ?? 0) - ($previous['position'] ?? 0),
            ],
        ];
    }

    /** Totaux agrégés pour une période. */
    public function periodTotals(int $siteId, string $from, string $to, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions,
                       CASE WHEN SUM(impressions) > 0
                            THEN SUM(clicks) / SUM(impressions)
                            ELSE 0 END  AS ctr,
                       AVG(position)    AS position
                FROM performance_data
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];
    }

    /** Plage de dates disponibles pour un site. */
    public function dateRange(int $siteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT MIN(data_date) AS min_date, MAX(data_date) AS max_date
             FROM performance_data WHERE site_id = :site_id'
        );
        $stmt->execute(['site_id' => $siteId]);

        return $stmt->fetch() ?: ['min_date' => null, 'max_date' => null];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Construit la clause WHERE et les paramètres associés.
     * Filtres supportés : device, country, search_type, query (LIKE), page (LIKE).
     */
    private function buildFilters(int $siteId, string $from, string $to, array $filters): array
    {
        $where  = 'site_id = :site_id AND data_date BETWEEN :from AND :to';
        $params = ['site_id' => $siteId, 'from' => $from, 'to' => $to];

        if (!empty($filters['device'])) {
            $where .= ' AND device = :device';
            $params['device'] = $filters['device'];
        }

        if (!empty($filters['country'])) {
            $where .= ' AND country = :country';
            $params['country'] = $filters['country'];
        }

        if (!empty($filters['search_type'])) {
            $where .= ' AND search_type = :search_type';
            $params['search_type'] = $filters['search_type'];
        }

        if (!empty($filters['query'])) {
            $where .= ' AND query LIKE :query';
            $params['query'] = '%' . $filters['query'] . '%';
        }

        if (!empty($filters['page'])) {
            $where .= ' AND page LIKE :page';
            $params['page'] = '%' . $filters['page'] . '%';
        }

        return [$where, $params];
    }
}
