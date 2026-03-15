<?php

namespace Platform\Controller;

use Platform\Service\PlanService;
use Platform\Database\Connection;
use Platform\View\Layout;

/**
 * Contrôleur de la page publique de tarification.
 */
class TarifsController
{
    /**
     * GET /tarifs — Affiche la grille tarifaire publique.
     */
    public function index(): void
    {
        $db = Connection::get();
        $planService = new PlanService($db);

        $plans = [];
        try {
            $plans = $planService->listerPlansActifs();
        } catch (\PDOException) {
            // Table pas encore créée
        }

        $modules = [];
        try {
            $modules = $db->query(
                'SELECT slug, name, icon, credits_par_analyse
                 FROM modules
                 WHERE enabled = 1 AND desinstalle_le IS NULL AND credits_par_analyse > 0
                 ORDER BY sort_order'
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            // Table pas encore créée
        }

        Layout::renderStandalone('tarifs', [
            'plans'   => $plans,
            'modules' => $modules,
        ]);
    }
}
