<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\Quota;
use Platform\Service\AuditLogger;
use Platform\User\UserRepository;
use Platform\View\Flash;

class AdminQuotaController
{
    public function index(): void
    {
        Response::redirect('/admin/users?onglet=quotas');
    }

    public function mettreAJour(Request $req): void
    {
        $db = Connection::get();
        $repo = new UserRepository();

        $users = $repo->findAll();
        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
        $quotaData = $req->post('quotas') ?? [];

        foreach ($users as $u) {
            foreach ($modules as $mod) {
                $value = $quotaData[$u['id']][$mod['id']] ?? '';
                if ($value !== '') {
                    Quota::setLimit((int) $u['id'], (int) $mod['id'], (int) $value, Auth::id());
                }
            }
        }

        AuditLogger::instance()->log(
            AuditAction::QuotasUpdate, $req->ip(), Auth::id(), 'matrix', null, ['updated' => true]
        );

        Flash::success('Quotas mis à jour.');
        Response::redirect('/admin/users?onglet=quotas');
    }

    public function mettreAJourDefauts(Request $req): void
    {
        $db = Connection::get();
        $defauts = $req->post('defauts') ?? [];

        $stmt = $db->prepare('UPDATE modules SET default_quota = ? WHERE id = ?');
        foreach ($defauts as $moduleId => $valeur) {
            $stmt->execute([(int) $valeur, (int) $moduleId]);
        }

        AuditLogger::instance()->log(
            AuditAction::QuotasUpdate, $req->ip(), Auth::id(), 'defaults', null, ['updated' => array_keys($defauts)]
        );

        Flash::success('Quotas par défaut mis à jour.');
        Response::redirect('/admin/users?onglet=defauts');
    }
}
