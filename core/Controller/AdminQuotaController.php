<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\Quota;
use Platform\Service\AuditLogger;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminQuotaController
{
    public function index(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $repo = new UserRepository();
        $db = Connection::get();

        Layout::render('layout', [
            'template'          => 'admin/quotas',
            'pageTitle'         => 'Quotas',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'quotas',
            'users'             => $repo->findAll(),
            'modules'           => $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll(),
            'matrix'            => Quota::getQuotaMatrix(),
        ]);
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
        Response::redirect('/admin/quotas');
    }
}
