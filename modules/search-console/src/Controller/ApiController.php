<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;
use App\Model\PerformanceData;
use App\Model\Site;
use App\Model\SyncLog;

/**
 * Endpoints API JSON pour exposer les données à un front JS ou outil externe.
 *
 * Toutes les réponses sont en JSON.
 */
class ApiController
{
    private PerformanceData $perfModel;
    private Site $siteModel;
    private SyncLog $syncLog;

    public function __construct()
    {
        $oauth = new GoogleOAuth();
        if (!$oauth->hasToken()) {
            $this->json(['error' => 'Non authentifié'], 401);
            exit;
        }

        $this->perfModel = new PerformanceData();
        $this->siteModel = new Site();
        $this->syncLog   = new SyncLog();
    }

    /** GET /api/sites — Liste des sites. */
    public function sites(): void
    {
        $this->json($this->siteModel->allActive());
    }

    /** GET /api/daily-trend?site_id=X&from=Y&to=Z */
    public function dailyTrend(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $data = $this->perfModel->getDailyTrend($siteId, $from, $to, $filters);
        $this->json($data);
    }

    /** GET /api/top-queries?site_id=X&from=Y&to=Z&limit=50 */
    public function topQueries(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $limit = (int) ($_GET['limit'] ?? 50);
        $data = $this->perfModel->topQueries($siteId, $from, $to, $limit, $filters);
        $this->json($data);
    }

    /** GET /api/top-pages?site_id=X&from=Y&to=Z&limit=50 */
    public function topPages(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $limit = (int) ($_GET['limit'] ?? 50);
        $data = $this->perfModel->topPages($siteId, $from, $to, $limit, $filters);
        $this->json($data);
    }

    /** GET /api/devices?site_id=X&from=Y&to=Z */
    public function devices(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $data = $this->perfModel->byDevice($siteId, $from, $to, $filters);
        $this->json($data);
    }

    /** GET /api/countries?site_id=X&from=Y&to=Z */
    public function countries(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $limit = (int) ($_GET['limit'] ?? 20);
        $data = $this->perfModel->byCountry($siteId, $from, $to, $limit, $filters);
        $this->json($data);
    }

    /** GET /api/totals?site_id=X&from=Y&to=Z */
    public function totals(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $data = $this->perfModel->periodTotals($siteId, $from, $to, $filters);
        $this->json($data);
    }

    /** GET /api/compare?site_id=X&from1=Y&to1=Z&from2=A&to2=B */
    public function compare(): void
    {
        $siteId = (int) ($_GET['site_id'] ?? 0);
        $from1 = $_GET['from1'] ?? date('Y-m-d', strtotime('-33 days'));
        $to1   = $_GET['to1']   ?? date('Y-m-d', strtotime('-3 days'));
        $from2 = $_GET['from2'] ?? date('Y-m-d', strtotime('-63 days'));
        $to2   = $_GET['to2']   ?? date('Y-m-d', strtotime('-34 days'));

        $filters = $this->parseFilters();
        $data = $this->perfModel->comparePeriods($siteId, $from1, $to1, $from2, $to2, $filters);
        $this->json($data);
    }

    /** GET /api/sync-logs */
    public function syncLogs(): void
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $this->json($this->syncLog->recent($limit));
    }

    /** POST /api/sync — Déclenche une synchronisation manuelle. */
    public function triggerSync(): void
    {
        $siteId = isset($_GET['site_id']) ? (int) $_GET['site_id'] : null;

        $sync = new SyncController();
        $results = $sync->run(true, $siteId);

        $this->json(['status' => 'done', 'results' => $results]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function parseParams(): array
    {
        $siteId = (int) ($_GET['site_id'] ?? 0);
        $from   = $_GET['from'] ?? date('Y-m-d', strtotime('-33 days'));
        $to     = $_GET['to']   ?? date('Y-m-d', strtotime('-3 days'));

        return [$siteId, $from, $to, $this->parseFilters()];
    }

    private function parseFilters(): array
    {
        return array_filter([
            'device'      => $_GET['device']      ?? '',
            'country'     => $_GET['country']      ?? '',
            'search_type' => $_GET['search_type']  ?? '',
            'query'       => $_GET['filter_query'] ?? '',
            'page'        => $_GET['filter_page']  ?? '',
        ]);
    }

    private function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
