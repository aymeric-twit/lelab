<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;
use App\Model\PerformanceData;
use App\Model\Site;
use App\Model\SyncLog;

/**
 * Contrôleur pour l'interface web du dashboard.
 */
class DashboardController
{
    private PerformanceData $perfModel;
    private Site $siteModel;
    private SyncLog $syncLog;

    public function __construct()
    {
        // Vérifier l'authentification
        $oauth = new GoogleOAuth();
        if (!$oauth->hasToken()) {
            $prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
            header('Location: ' . $prefix . '/auth');
            exit;
        }

        $this->perfModel = new PerformanceData();
        $this->siteModel = new Site();
        $this->syncLog   = new SyncLog();
    }

    /** Page principale du dashboard. */
    public function index(): void
    {
        $sites = $this->siteModel->allActive();

        // Site sélectionné (par défaut le premier)
        $siteId = isset($_GET['site_id']) ? (int) $_GET['site_id'] : ($sites[0]['id'] ?? 0);
        $currentSite = $this->siteModel->find($siteId);

        // Période par défaut : 30 derniers jours
        $to   = $_GET['to']   ?? date('Y-m-d', strtotime('-3 days'));
        $from = $_GET['from'] ?? date('Y-m-d', strtotime("{$to} -29 days"));

        // Filtres
        $filters = array_filter([
            'device'      => $_GET['device']      ?? '',
            'country'     => $_GET['country']      ?? '',
            'search_type' => $_GET['search_type']  ?? '',
            'query'       => $_GET['filter_query'] ?? '',
            'page'        => $_GET['filter_page']  ?? '',
        ]);

        // Données pour le dashboard
        $dailyTrend  = [];
        $topQueries  = [];
        $topPages    = [];
        $devices     = [];
        $countries   = [];
        $totals      = ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];
        $comparison  = null;
        $dateRange   = ['min_date' => null, 'max_date' => null];

        if ($siteId > 0) {
            $dailyTrend = $this->perfModel->getDailyTrend($siteId, $from, $to, $filters);
            $topQueries = $this->perfModel->topQueries($siteId, $from, $to, 20, $filters);
            $topPages   = $this->perfModel->topPages($siteId, $from, $to, 20, $filters);
            $devices    = $this->perfModel->byDevice($siteId, $from, $to, $filters);
            $countries  = $this->perfModel->byCountry($siteId, $from, $to, 10, $filters);
            $totals     = $this->perfModel->periodTotals($siteId, $from, $to, $filters);
            $dateRange  = $this->perfModel->dateRange($siteId);

            // Comparaison avec la période précédente (même durée)
            $days  = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
            $from2 = date('Y-m-d', strtotime("{$from} -{$days} days"));
            $to2   = date('Y-m-d', strtotime("{$from} -1 day"));
            $comparison = $this->perfModel->comparePeriods($siteId, $from, $to, $from2, $to2, $filters);
        }

        require __DIR__ . '/../../templates/dashboard.php';
    }

    /** Page de statut des synchronisations. */
    public function syncStatus(): void
    {
        $logs = $this->syncLog->recent(100);
        require __DIR__ . '/../../templates/sync-status.php';
    }
}
