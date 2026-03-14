<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Module\Quota;
use Platform\User\AccessControl;
use Platform\View\Layout;

class MarketplaceController
{
    /**
     * GET /marketplace — Page publique listant tous les modules disponibles.
     */
    public function index(Request $req): void
    {
        $user = Auth::user();
        $db = Connection::get();
        $ac = new AccessControl();

        // Tous les modules actifs (non désinstallés)
        $modules = $db->query(
            'SELECT m.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.sort_order AS categorie_sort_order
             FROM modules m
             LEFT JOIN categories c ON c.id = m.categorie_id
             WHERE m.enabled = 1 AND m.desinstalle_le IS NULL
             ORDER BY COALESCE(c.sort_order, 9999), c.nom, m.sort_order'
        )->fetchAll();

        // Modules accessibles par l'utilisateur
        $accessibles = $ac->getAccessibleModules($user['id']);
        $slugsAccessibles = array_column($accessibles, 'slug');

        // Quotas
        $jourInscription = (int) date('j', strtotime($user['created_at'] ?? 'now'));
        $quotaSummary = Quota::getUserQuotaSummary($user['id'], $jourInscription);

        // Grouper par catégorie
        $modulesParCategorie = [];
        foreach ($modules as $mod) {
            $catKey = $mod['categorie_id'] ?? 0;
            if (!isset($modulesParCategorie[$catKey])) {
                $modulesParCategorie[$catKey] = [
                    'nom'        => $mod['categorie_nom'] ?? 'Autres',
                    'icone'      => $mod['categorie_icone'] ?? 'bi-folder',
                    'sort_order' => $mod['categorie_sort_order'] ?? 9999,
                    'modules'    => [],
                ];
            }
            $mod['_accessible'] = in_array($mod['slug'], $slugsAccessibles, true);
            $modulesParCategorie[$catKey]['modules'][] = $mod;
        }

        uksort($modulesParCategorie, function ($a, $b) use ($modulesParCategorie) {
            if ($a === 0) return 1;
            if ($b === 0) return -1;
            return $modulesParCategorie[$a]['sort_order'] <=> $modulesParCategorie[$b]['sort_order'];
        });

        // Compteurs
        $totalModules = count($modules);
        $totalAccessibles = count($slugsAccessibles);

        Layout::render('layout', [
            'template'              => 'marketplace',
            'pageTitle'             => 'Marketplace',
            'currentUser'           => $user,
            'accessibleModules'     => $accessibles,
            'quotaSummary'          => $quotaSummary,
            'modulesParCategorie'   => $modulesParCategorie,
            'totalModules'          => $totalModules,
            'totalAccessibles'      => $totalAccessibles,
        ]);
    }
}
