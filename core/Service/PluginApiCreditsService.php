<?php

namespace Platform\Service;

use PDO;
use Platform\Module\ApiCreditsTracker;
use Platform\Module\Quota;
use Platform\Repository\SettingsRepository;

/**
 * Service de gestion des crédits API des plugins.
 * Extrait de AdminPluginController.
 */
class PluginApiCreditsService
{
    private PDO $db;
    private SettingsRepository $settings;

    public function __construct(PDO $db, ?SettingsRepository $settings = null)
    {
        $this->db = $db;
        $this->settings = $settings ?? new SettingsRepository($db);
    }

    /**
     * Vérifie les crédits restants pour une clé API.
     *
     * @return array{ok: bool, credits: ?int, source?: string, credits_mensuels?: int, usage_mois?: int, prochain_reset?: string, periode?: string, fournisseur?: string, erreur?: string}
     */
    public function verifierCredits(string $cle): array
    {
        if ($cle === '') {
            return ['ok' => false, 'credits' => null, 'erreur' => 'Clé manquante.'];
        }

        // SEMrush : vérification live
        if ($cle === 'SEMRUSH_API_KEY') {
            $resultat = $this->verifierCreditsSemrush($cle);
            if ($resultat !== null) {
                return $resultat;
            }
        }

        // Calcul local : config manuelle - usage de la période
        return $this->calculerCreditsLocaux($cle);
    }

    /**
     * Sauvegarde la configuration des crédits pour une clé API.
     */
    public function sauvegarderConfig(string $cle, ?int $creditsMensuels, string $dateDebut, string $periode, string $commentaire): void
    {
        $configExistante = [];
        $existant = $this->settings->obtenir('api_credits', $cle);
        if ($existant !== null) {
            $configExistante = json_decode($existant, true) ?: [];
        }

        if ($creditsMensuels !== null) {
            $configExistante['credits_mensuels'] = $creditsMensuels;
        } elseif (array_key_exists('credits_mensuels', $configExistante)) {
            unset($configExistante['credits_mensuels']);
        }

        if ($dateDebut !== '') {
            $configExistante['date_debut'] = $dateDebut;
        }

        if (in_array($periode, ['mensuel', 'hebdomadaire'], true)) {
            $configExistante['periode'] = $periode;
        }

        $configExistante['commentaire'] = $commentaire;

        $this->settings->definir('api_credits', $cle, json_encode($configExistante, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Calcule la date du prochain reset pour un jour de début donné.
     */
    public static function calculerProchainReset(int $jourDebut): string
    {
        $jourActuel = (int) date('j');
        if ($jourActuel < $jourDebut) {
            $mois = (int) date('n');
            $annee = (int) date('Y');
        } else {
            $mois = (int) date('n') + 1;
            $annee = (int) date('Y');
            if ($mois > 12) {
                $mois = 1;
                $annee++;
            }
        }
        $dernierJourMois = (int) date('t', mktime(0, 0, 0, $mois, 1, $annee));
        $jour = min($jourDebut, $dernierJourMois);
        return sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
    }

    /**
     * Prochain lundi (reset hebdomadaire, lundi–dimanche).
     */
    public static function calculerProchainResetHebdo(): string
    {
        return date('Y-m-d', strtotime('next monday'));
    }

    /**
     * Calcule l'usage total de la période en cours pour une clé API.
     */
    public function calculerUsageParCle(string $cle, int $jourDebut = 1, string $periode = 'mensuel'): int
    {
        try {
            $periodeId = $periode === 'hebdomadaire'
                ? ApiCreditsTracker::currentWeekPeriod()
                : Quota::currentPeriod($jourDebut);

            $stmt = $this->db->prepare('SELECT usage_count FROM api_credits_usage WHERE cle_api = ? AND periode_id = ?');
            $stmt->execute([$cle, $periodeId]);
            $row = $stmt->fetch();

            if ($row !== false) {
                return (int) $row['usage_count'];
            }
        } catch (\PDOException) {
            // Table pas encore créée
        }

        return $this->calculerUsageDepuisModuleUsage($cle, $jourDebut);
    }

    private function verifierCreditsSemrush(string $cle): ?array
    {
        $envService = new PluginEnvService($this->db);
        $valeur = $envService->resoudreCle($cle);

        if ($valeur === '') {
            return null;
        }

        try {
            $url = 'https://www.semrush.com/users/countapiunits.html?key=' . urlencode($valeur);
            $contexte = stream_context_create([
                'http' => ['timeout' => 10, 'method' => 'GET'],
            ]);
            $reponse = @file_get_contents($url, false, $contexte);

            if ($reponse !== false) {
                $credits = (int) trim($reponse);
                return ['ok' => true, 'credits' => $credits, 'fournisseur' => 'SEMrush', 'source' => 'live'];
            }
        } catch (\Throwable) {
            // Fallback vers le calcul local
        }

        return null;
    }

    private function calculerCreditsLocaux(string $cle): array
    {
        $configJson = $this->settings->obtenir('api_credits', $cle);

        if ($configJson !== null) {
            $config = json_decode($configJson, true);
            $creditsMensuels = $config['credits_mensuels'] ?? null;

            if ($creditsMensuels !== null) {
                $dateDebut = $config['date_debut'] ?? null;
                $jourDebut = $dateDebut !== null ? (int) date('j', strtotime($dateDebut)) : 1;
                $periode = $config['periode'] ?? 'mensuel';
                $usageMois = $this->calculerUsageParCle($cle, $jourDebut, $periode);
                $restants = max(0, (int) $creditsMensuels - $usageMois);
                $prochainReset = $periode === 'hebdomadaire'
                    ? self::calculerProchainResetHebdo()
                    : self::calculerProchainReset($jourDebut);
                return [
                    'ok'               => true,
                    'credits'          => $restants,
                    'credits_mensuels' => (int) $creditsMensuels,
                    'usage_mois'       => $usageMois,
                    'prochain_reset'   => $prochainReset,
                    'periode'          => $periode,
                    'source'           => 'config',
                ];
            }
        }

        return ['ok' => true, 'credits' => null];
    }

    private function calculerUsageDepuisModuleUsage(string $cle, int $jourDebut): int
    {
        $modules = $this->db->query('SELECT id, cles_env FROM modules WHERE cles_env IS NOT NULL AND desinstalle_le IS NULL')->fetchAll();
        $moduleIds = [];
        foreach ($modules as $mod) {
            $envKeys = json_decode($mod['cles_env'], true);
            if (is_array($envKeys) && in_array($cle, $envKeys, true)) {
                $moduleIds[] = (int) $mod['id'];
            }
        }

        if ($moduleIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
        $periodeActive = Quota::currentPeriod($jourDebut);
        $params = array_merge($moduleIds, [$periodeActive]);

        $stmt = $this->db->prepare(
            "SELECT SUM(usage_count) AS total FROM module_usage WHERE module_id IN ({$placeholders}) AND year_month = ?"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0);
    }
}
