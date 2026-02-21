<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;
use App\Model\PerformanceData;
use App\Model\Site;
use App\Model\SyncLog;
use App\Service\SearchConsoleAPI;

/**
 * Orchestre la synchronisation des données Search Console.
 *
 * Stratégie :
 * 1. Lister les sites actifs en base (ou les importer depuis l'API)
 * 2. Pour chaque site et chaque searchType, déterminer la plage de dates à sync
 * 3. Découper en tranches de 30 jours pour éviter les réponses trop volumineuses
 * 4. Récupérer les données, les insérer en base, loguer le résultat
 */
class SyncController
{
    private SearchConsoleAPI $api;
    private PerformanceData $perfModel;
    private Site $siteModel;
    private SyncLog $syncLog;

    private int $daysBack;
    private string $dataState;
    private array $searchTypes;

    public function __construct()
    {
        $auth = new GoogleOAuth();
        $this->api       = new SearchConsoleAPI($auth);
        $this->perfModel = new PerformanceData();
        $this->siteModel = new Site();
        $this->syncLog   = new SyncLog();

        $this->daysBack    = (int) ($_ENV['SYNC_DAYS_BACK']    ?? 480);
        $this->dataState   = $_ENV['SYNC_DATA_STATE']          ?? 'all';
        $this->searchTypes = array_map('trim', explode(',', $_ENV['SYNC_SEARCH_TYPES'] ?? 'web'));
    }

    /**
     * Lance une synchronisation complète.
     * Appelé par le cron ou manuellement.
     *
     * @param bool $importSites  Si true, importe les sites depuis l'API GSC
     * @param int|null $siteId   Si fourni, ne synchronise que ce site
     */
    public function run(bool $importSites = true, ?int $siteId = null): array
    {
        $results = [];

        // Import des sites depuis l'API si demandé
        if ($importSites) {
            $this->importSites();
        }

        // Déterminer les sites à synchroniser
        if ($siteId !== null) {
            $site = $this->siteModel->find($siteId);
            $sites = $site ? [$site] : [];
        } else {
            $sites = $this->siteModel->allActive();
        }

        if (empty($sites)) {
            $this->log('Aucun site à synchroniser.');
            return $results;
        }

        foreach ($sites as $site) {
            foreach ($this->searchTypes as $searchType) {
                $result = $this->syncSite($site, $searchType);
                $results[] = $result;
            }
        }

        return $results;
    }

    /** Importe la liste des sites depuis l'API et les enregistre en base. */
    public function importSites(): int
    {
        $apiSites = $this->api->listSites();
        $count = 0;

        foreach ($apiSites as $s) {
            $this->siteModel->upsert($s['siteUrl']);
            $count++;
        }

        $this->log("Import : {$count} site(s) synchronisé(s) depuis l'API.");

        return $count;
    }

    /**
     * Synchronise un site pour un type de recherche donné.
     * Découpe la plage en tranches de 30 jours.
     */
    private function syncSite(array $site, string $searchType): array
    {
        $siteId  = (int) $site['id'];
        $siteUrl = $site['site_url'];

        // Déterminer la plage de dates
        // Les données GSC ne sont disponibles qu'avec ~3 jours de retard
        $endDate   = date('Y-m-d', strtotime('-3 days'));
        $startDate = date('Y-m-d', strtotime("-{$this->daysBack} days"));

        // Reprendre là où on s'est arrêté si une sync précédente existe
        $lastSync = $this->syncLog->lastSuccess($siteId, $searchType);
        if ($lastSync) {
            // On reprend au lendemain de la dernière date synchronisée
            $resumeDate = date('Y-m-d', strtotime($lastSync['date_to'] . ' +1 day'));
            if ($resumeDate > $startDate) {
                $startDate = $resumeDate;
            }
        }

        if ($startDate > $endDate) {
            $this->log("[{$siteUrl}][{$searchType}] Déjà à jour.");
            return [
                'site'        => $siteUrl,
                'search_type' => $searchType,
                'status'      => 'up_to_date',
            ];
        }

        $this->log("[{$siteUrl}][{$searchType}] Sync du {$startDate} au {$endDate}...");

        // Découper en tranches de 30 jours
        $chunks = $this->dateChunks($startDate, $endDate, 30);

        $totalFetched  = 0;
        $totalInserted = 0;
        $logId = $this->syncLog->start($siteId, $searchType, $startDate, $endDate);
        $startTime = microtime(true);

        try {
            foreach ($chunks as [$chunkStart, $chunkEnd]) {
                $this->log("  Tranche : {$chunkStart} -> {$chunkEnd}");

                $rows = $this->api->fetchPerformanceData(
                    $siteUrl,
                    $chunkStart,
                    $chunkEnd,
                    $searchType,
                    $this->dataState
                );

                $fetched = count($rows);
                $totalFetched += $fetched;

                if ($fetched > 0) {
                    $inserted = $this->perfModel->upsertBatch($siteId, $searchType, $rows);
                    $totalInserted += $inserted;
                }

                $this->log("  -> {$fetched} lignes récupérées.");
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->syncLog->success($logId, $totalFetched, $totalInserted, $duration);

            $this->log("[{$siteUrl}][{$searchType}] Terminé : {$totalFetched} récupérées, {$totalInserted} insérées en {$duration}s.");

            return [
                'site'          => $siteUrl,
                'search_type'   => $searchType,
                'status'        => 'success',
                'rows_fetched'  => $totalFetched,
                'rows_inserted' => $totalInserted,
                'duration'      => $duration,
            ];
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $this->syncLog->error($logId, $e->getMessage(), $duration);

            $this->log("[{$siteUrl}][{$searchType}] ERREUR : {$e->getMessage()}");

            return [
                'site'        => $siteUrl,
                'search_type' => $searchType,
                'status'      => 'error',
                'error'       => $e->getMessage(),
                'duration'    => $duration,
            ];
        }
    }

    /**
     * Découpe une plage de dates en tranches de $days jours.
     *
     * @return array<array{0: string, 1: string}>
     */
    private function dateChunks(string $start, string $end, int $days): array
    {
        $chunks = [];
        $current = $start;

        while ($current <= $end) {
            $chunkEnd = date('Y-m-d', strtotime("{$current} +{$days} days -1 day"));
            if ($chunkEnd > $end) {
                $chunkEnd = $end;
            }
            $chunks[] = [$current, $chunkEnd];
            $current = date('Y-m-d', strtotime("{$chunkEnd} +1 day"));
        }

        return $chunks;
    }

    private function log(string $message): void
    {
        $ts = date('Y-m-d H:i:s');
        $line = "[Sync][{$ts}] {$message}";
        echo $line . PHP_EOL;
        error_log($line);
    }
}
