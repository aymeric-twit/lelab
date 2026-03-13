<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\DependencyInstaller;
use Platform\Module\ApiCreditsTracker;
use Platform\Module\Quota;
use Platform\User\AccessControl;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminMaintenanceController
{
    public function index(): void
    {
        $db = Connection::get();
        $modules = $db->query(
            'SELECT slug, name, chemin_source FROM modules WHERE enabled = 1 AND desinstalle_le IS NULL ORDER BY name'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $depInstaller = new DependencyInstaller();
        $etats = [];

        foreach ($modules as $module) {
            $chemin = $module['chemin_source'];
            if (empty($chemin) || !is_dir($chemin)) {
                continue;
            }

            $verif = $depInstaller->verifierDependances($chemin);

            // N'afficher que les plugins qui ont au moins une dépendance
            if ($verif['composer'] !== null || $verif['npm'] !== null) {
                $etats[] = [
                    'slug'     => $module['slug'],
                    'name'     => $module['name'],
                    'composer' => $verif['composer'],
                    'npm'      => $verif['npm'],
                ];
            }
        }

        $ac = new AccessControl();

        Layout::render('layout', [
            'template'          => 'admin/maintenance',
            'pageTitle'         => 'Maintenance',
            'currentUser'       => Auth::user(),
            'accessibleModules' => $ac->getAccessibleModules(Auth::id()),
            'adminPage'         => 'maintenance',
            'etats'             => $etats,
            'outils'            => $depInstaller->verifierOutils(),
        ]);
    }

    public function installerDependances(Request $req): void
    {
        $depInstaller = new DependencyInstaller();
        $resultats = $depInstaller->installerToutesLesDependances();

        if (empty($resultats)) {
            Flash::set('info', 'Aucun plugin avec des dépendances à installer.');
            Response::redirect('/admin/maintenance');
            return;
        }

        $succes = 0;
        $echecs = 0;
        $erreurs = [];

        foreach ($resultats as $slug => $res) {
            $aEchoue = false;

            if ($res['composer'] === false) {
                $aEchoue = true;
            }
            if ($res['npm'] === false) {
                $aEchoue = true;
            }

            if ($aEchoue) {
                $echecs++;
                foreach ($res['erreurs'] as $err) {
                    $erreurs[] = "{$slug} : {$err}";
                }
            } else {
                $succes++;
            }
        }

        if ($echecs === 0) {
            Flash::success("Dépendances installées pour {$succes} plugin(s).");
        } else {
            $message = "{$succes} plugin(s) OK, {$echecs} en erreur.";
            if (!empty($erreurs)) {
                $message .= ' ' . implode(' | ', array_slice($erreurs, 0, 3));
            }
            Flash::error($message);
        }

        Response::redirect('/admin/maintenance');
    }

    public function purgerUsage(Request $req): void
    {
        $supprimees = Quota::purgerAncienUsage(12);
        $supprimeesApi = ApiCreditsTracker::purger(6);
        $total = $supprimees + $supprimeesApi;

        if ($total > 0) {
            Flash::success("{$total} ligne(s) d'usage supprimée(s) ({$supprimees} quotas + {$supprimeesApi} crédits API).");
        } else {
            Flash::set('info', 'Aucune donnée à purger.');
        }

        Response::redirect('/admin/maintenance');
    }
}
