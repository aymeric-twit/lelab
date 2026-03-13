<?php

namespace Platform\Module;

use Platform\Database\Connection;
use Platform\Repository\SettingsRepository;
use PDO;

class ApiCreditsTracker
{
    /** @var array<string, array{periode: string, jour_debut: int}> Cache config par clé */
    private static array $configCache = [];

    private static function getDb(): PDO
    {
        return Connection::get();
    }

    /**
     * Période ISO semaine courante : '2026W11' (lundi–dimanche, ISO 8601).
     */
    public static function currentWeekPeriod(): string
    {
        return date('o\WW');
    }

    /**
     * Charge la config crédits d'une clé API depuis settings (avec cache statique).
     *
     * @return array{periode: string, jour_debut: int}
     */
    private static function chargerConfig(string $cleApi): array
    {
        if (isset(self::$configCache[$cleApi])) {
            return self::$configCache[$cleApi];
        }

        $defaut = ['periode' => 'mensuel', 'jour_debut' => 1];

        try {
            $db = self::getDb();
            $settingsRepo = new SettingsRepository($db);
            $json = $settingsRepo->obtenir('api_credits', $cleApi);

            if ($json !== null) {
                $config = json_decode($json, true);
                if (is_array($config)) {
                    $dateDebut = $config['date_debut'] ?? null;
                    $defaut = [
                        'periode' => $config['periode'] ?? 'mensuel',
                        'jour_debut' => $dateDebut !== null ? (int) date('j', strtotime($dateDebut)) : 1,
                    ];
                }
            }
        } catch (\PDOException) {
            // Table settings pas encore créée
        }

        self::$configCache[$cleApi] = $defaut;
        return $defaut;
    }

    /**
     * Période courante pour une clé API selon sa config.
     */
    public static function currentPeriodForKey(string $cleApi): string
    {
        $config = self::chargerConfig($cleApi);

        if ($config['periode'] === 'hebdomadaire') {
            return self::currentWeekPeriod();
        }

        return Quota::currentPeriod($config['jour_debut']);
    }

    /**
     * Incrémente le compteur de crédits API (upsert atomique MySQL).
     */
    public static function tracker(string $cleApi, int $amount = 1): void
    {
        if ($amount <= 0) {
            return;
        }

        try {
            $db = self::getDb();
            $periodeId = self::currentPeriodForKey($cleApi);

            $stmt = $db->prepare('
                INSERT INTO api_credits_usage (cle_api, periode_id, usage_count, last_tracked_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    usage_count = usage_count + VALUES(usage_count),
                    last_tracked_at = NOW()
            ');
            $stmt->execute([$cleApi, $periodeId, $amount]);
        } catch (\PDOException) {
            // Table pas encore créée — silencieux pour ne pas bloquer les plugins
        }
    }

    /**
     * Retourne l'usage de la période courante pour une clé API.
     */
    public static function getUsage(string $cleApi): int
    {
        try {
            $db = self::getDb();
            $periodeId = self::currentPeriodForKey($cleApi);

            $stmt = $db->prepare('SELECT usage_count FROM api_credits_usage WHERE cle_api = ? AND periode_id = ?');
            $stmt->execute([$cleApi, $periodeId]);
            $row = $stmt->fetch();

            return $row !== false ? (int) $row['usage_count'] : 0;
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * Retourne l'usage pour une clé API et une période spécifique.
     */
    public static function getUsagePourPeriode(string $cleApi, string $periodeId): int
    {
        try {
            $db = self::getDb();
            $stmt = $db->prepare('SELECT usage_count FROM api_credits_usage WHERE cle_api = ? AND periode_id = ?');
            $stmt->execute([$cleApi, $periodeId]);
            $row = $stmt->fetch();

            return $row !== false ? (int) $row['usage_count'] : 0;
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * Supprime les entrées antérieures à N mois.
     * Retourne le nombre de lignes supprimées.
     */
    public static function purger(int $moisAConserver = 6): int
    {
        try {
            $db = self::getDb();

            // Supprimer les périodes mensuelles anciennes
            $seuilMensuel = date('Ym', strtotime("-{$moisAConserver} months"));

            // Supprimer les périodes hebdomadaires anciennes
            $seuilDate = date('Y-m-d', strtotime("-{$moisAConserver} months"));
            $seuilHebdo = date('o\WW', strtotime($seuilDate));

            $stmt = $db->prepare('
                DELETE FROM api_credits_usage
                WHERE (periode_id NOT LIKE ? AND periode_id < ?)
                   OR (periode_id LIKE ? AND periode_id < ?)
            ');
            $stmt->execute(['%W%', $seuilMensuel, '%W%', $seuilHebdo]);

            return $stmt->rowCount();
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * Réinitialise le cache statique (utile pour les tests).
     */
    public static function resetCache(): void
    {
        self::$configCache = [];
    }
}
