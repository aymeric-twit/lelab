<?php

namespace App\Service;

use App\Auth\GoogleOAuth;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

/**
 * Service d'interaction avec l'API Google Search Console.
 *
 * Gère :
 * - La liste des sites
 * - La récupération paginée des données de performance
 * - Le retry en cas d'erreur temporaire / quota
 */
class SearchConsoleAPI
{
    private SearchConsole $service;
    private int $rowLimit;
    private int $maxRetries;
    private int $retryDelay;

    public function __construct(GoogleOAuth $auth)
    {
        $client = $auth->getAuthenticatedClient();
        $this->service   = new SearchConsole($client);
        $this->rowLimit   = (int) ($_ENV['SYNC_ROW_LIMIT']   ?? 25000);
        $this->maxRetries = (int) ($_ENV['SYNC_MAX_RETRIES'] ?? 3);
        $this->retryDelay = (int) ($_ENV['SYNC_RETRY_DELAY'] ?? 5);
    }

    /**
     * Liste tous les sites accessibles dans Search Console.
     *
     * @return array<array{siteUrl: string, permissionLevel: string}>
     */
    public function listSites(): array
    {
        $response = $this->service->sites->listSites();
        $sites = [];

        foreach ($response->getSiteEntry() ?? [] as $entry) {
            $sites[] = [
                'siteUrl'         => $entry->getSiteUrl(),
                'permissionLevel' => $entry->getPermissionLevel(),
            ];
        }

        return $sites;
    }

    /**
     * Récupère toutes les données de performance pour un site,
     * une plage de dates et un type de recherche.
     *
     * Gère automatiquement la pagination (startRow) lorsque le
     * nombre de lignes atteint le rowLimit.
     *
     * @return array<array{
     *   date: string, page: string, query: string,
     *   country: string, device: string,
     *   clicks: int, impressions: int, ctr: float, position: float
     * }>
     */
    public function fetchPerformanceData(
        string $siteUrl,
        string $startDate,
        string $endDate,
        string $searchType = 'web',
        string $dataState = 'all'
    ): array {
        $allRows  = [];
        $startRow = 0;

        do {
            $response = $this->queryWithRetry(
                $siteUrl,
                $startDate,
                $endDate,
                $searchType,
                $dataState,
                $startRow
            );

            $rows = $response->getRows() ?? [];
            $count = count($rows);

            foreach ($rows as $row) {
                $keys = $row->getKeys();
                $allRows[] = [
                    'date'        => $keys[0] ?? '',
                    'page'        => $keys[1] ?? '',
                    'query'       => $keys[2] ?? '',
                    'country'     => $keys[3] ?? '',
                    'device'      => $keys[4] ?? '',
                    'clicks'      => (int) $row->getClicks(),
                    'impressions' => (int) $row->getImpressions(),
                    'ctr'         => (float) $row->getCtr(),
                    'position'    => (float) $row->getPosition(),
                ];
            }

            $startRow += $count;

        } while ($count >= $this->rowLimit);

        return $allRows;
    }

    /**
     * Exécute la requête API avec retry automatique en cas d'erreur 429/5xx.
     */
    private function queryWithRetry(
        string $siteUrl,
        string $startDate,
        string $endDate,
        string $searchType,
        string $dataState,
        int $startRow
    ): \Google\Service\SearchConsole\SearchAnalyticsQueryResponse {
        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($startDate);
        $request->setEndDate($endDate);
        $request->setDimensions(['date', 'page', 'query', 'country', 'device']);
        $request->setRowLimit($this->rowLimit);
        $request->setStartRow($startRow);
        $request->setType($searchType);
        $request->setDataState($dataState);

        $attempt = 0;

        while (true) {
            try {
                $attempt++;
                return $this->service->searchanalytics->query($siteUrl, $request);
            } catch (\Google\Service\Exception $e) {
                $code = $e->getCode();
                $isRetryable = in_array($code, [429, 500, 502, 503], true);

                if (!$isRetryable || $attempt >= $this->maxRetries) {
                    throw $e;
                }

                // Back-off exponentiel
                $delay = $this->retryDelay * pow(2, $attempt - 1);
                $this->log("API error {$code}, retry {$attempt}/{$this->maxRetries} dans {$delay}s...");
                sleep($delay);
            }
        }
    }

    private function log(string $message): void
    {
        $ts = date('Y-m-d H:i:s');
        error_log("[SearchConsoleAPI][{$ts}] {$message}");
    }
}
