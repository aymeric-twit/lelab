<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Service\AuditLogger;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use Platform\View\Flash;

class AdminAccessController
{
    public function index(): void
    {
        Response::redirect('/admin/users?onglet=acces');
    }

    public function mettreAJour(Request $req): void
    {
        $ac = new AccessControl();
        $db = Connection::get();
        $repo = new UserRepository();

        $users = $repo->findAll();
        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
        $accessData = $req->post('access') ?? [];
        $expiresData = $req->post('access_expires') ?? [];

        foreach ($users as $u) {
            foreach ($modules as $mod) {
                $granted = !empty($accessData[$u['id']][$mod['id']]);
                $expiresAt = !empty($expiresData[$u['id']][$mod['id']])
                    ? $expiresData[$u['id']][$mod['id']] . ' 23:59:59'
                    : null;
                $ac->setAccess($u['id'], $mod['id'], $granted, Auth::id(), $expiresAt);
            }
        }

        AuditLogger::instance()->log(
            AuditAction::AccessUpdate, $req->ip(), Auth::id(), 'matrix', null, ['updated' => true]
        );

        Flash::success('Accès mis à jour.');
        Response::redirect('/admin/users?onglet=acces');
    }
}
