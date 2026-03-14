<?php

namespace Platform\Controller\Api;

use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\Quota;

class ApiStatsController
{
    /**
     * GET /api/v1/stats/usage — Statistiques d'usage par module (admin only).
     */
    public function usage(Request $req): void
    {
        if (($_SERVER['API_USER_ROLE'] ?? '') !== 'admin') {
            Response::json(['erreur' => 'Accès réservé aux administrateurs.', 'message' => 'Forbidden'], 403);
        }

        $db = Connection::get();
        $mois = $req->get('mois', 6);
        $mois = min(24, max(1, (int) $mois));

        // Usage agrégé par module sur les N derniers mois
        $stmt = $db->prepare(
            'SELECT m.slug, m.name, mu.year_month, SUM(mu.usage_count) AS total
             FROM module_usage mu
             JOIN modules m ON m.id = mu.module_id
             WHERE mu.year_month >= ?
             GROUP BY m.slug, m.name, mu.year_month
             ORDER BY mu.year_month, m.slug'
        );
        $seuilYearMonth = date('Ym', strtotime("-{$mois} months"));
        $stmt->execute([$seuilYearMonth]);
        $rows = $stmt->fetchAll();

        // Structurer par module
        $parModule = [];
        foreach ($rows as $row) {
            $slug = $row['slug'];
            if (!isset($parModule[$slug])) {
                $parModule[$slug] = [
                    'slug'   => $slug,
                    'name'   => $row['name'],
                    'series' => [],
                    'total'  => 0,
                ];
            }
            $parModule[$slug]['series'][$row['year_month']] = (int) $row['total'];
            $parModule[$slug]['total'] += (int) $row['total'];
        }

        // Top modules
        usort($parModule, fn($a, $b) => $b['total'] <=> $a['total']);

        Response::json([
            'donnees' => [
                'periode'      => $seuilYearMonth . ' → ' . date('Ym'),
                'par_module'   => $parModule,
                'total_global' => array_sum(array_column($parModule, 'total')),
            ],
            'message' => 'OK',
        ]);
    }

    /**
     * GET /api/v1/stats/quotas — Résumé quotas de l'utilisateur courant.
     */
    public function quotas(Request $req): void
    {
        $userId = (int) $_SERVER['API_USER_ID'];
        $quotas = Quota::getUserQuotaSummary($userId);

        $donnees = [];
        foreach ($quotas as $slug => $q) {
            $donnees[] = [
                'slug'       => $slug,
                'mode'       => $q['quota_mode']->value,
                'usage'      => $q['usage'],
                'limit'      => $q['limit'],
                'restant'    => $q['limit'] > 0 ? max(0, $q['limit'] - $q['usage']) : null,
                'illimite'   => $q['limit'] === 0,
            ];
        }

        Response::json([
            'donnees' => $donnees,
            'message' => 'OK',
        ]);
    }
}
