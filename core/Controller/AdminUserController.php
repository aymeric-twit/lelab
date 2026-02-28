<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Auth\PasswordHasher;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Enum\Role;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\Quota;
use Platform\Service\AuditLogger;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminUserController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $repo = new UserRepository();
        $ac = new AccessControl();
        $db = Connection::get();

        $onglet = $req->get('onglet', 'utilisateurs');

        $page = max(1, (int) $req->get('page', 1));
        $pagination = $repo->findAllPagine($page, 20);

        $tousLesUtilisateurs = $repo->findAll();
        $modulesActifs = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
        $matriceAcces = $ac->getAccessMatrix();
        $matriceQuotas = Quota::getQuotaMatrix();

        Layout::render('layout', [
            'template'              => 'admin/users',
            'pageTitle'             => 'Utilisateurs',
            'currentUser'           => $user,
            'accessibleModules'     => $ac->getAccessibleModules($user['id']),
            'adminPage'             => 'users',
            'onglet'                => $onglet,
            'users'                 => $pagination['donnees'],
            'pagination'            => $pagination,
            'tousLesUtilisateurs'   => $tousLesUtilisateurs,
            'modulesActifs'         => $modulesActifs,
            'matriceAcces'          => $matriceAcces,
            'matriceQuotas'         => $matriceQuotas,
        ]);
    }

    public function formulaireCreation(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();
        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();

        Layout::render('layout', [
            'template'          => 'admin/user-form',
            'pageTitle'         => 'Nouvel utilisateur',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'users',
            'modules'           => $modules,
        ]);
    }

    public function creer(Request $req): void
    {
        $repo = new UserRepository();
        $username = trim($req->post('username', ''));
        $email = trim($req->post('email', ''));
        $password = $req->post('password', '');
        $role = Role::tryFrom($req->post('role', 'user')) ?? Role::User;
        $active = $req->post('active') ? 1 : 0;

        $validateur = new Validator();
        $valide = $validateur->valider(
            ['username' => $username, 'email' => $email, 'password' => $password],
            ['username' => 'requis|min:3|max:50', 'email' => 'email', 'password' => 'requis|mot_de_passe']
        );

        if (!$valide) {
            Flash::error($validateur->premiereErreur() ?? 'Données invalides.');
            Response::redirect('/admin/users/create');
        }

        if ($repo->findByUsername($username)) {
            Flash::error('Ce nom d\'utilisateur existe déjà.');
            Response::redirect('/admin/users/create');
        }

        $db = Connection::get();
        $db->beginTransaction();
        try {
            $userId = $repo->create([
                'username'      => $username,
                'email'         => $email ?: null,
                'password_hash' => PasswordHasher::hash($password),
                'role'          => $role->value,
                'active'        => $active,
            ]);

            $this->sauvegarderAccesEtQuotas($req, $db, $userId);

            AuditLogger::instance()->log(
                AuditAction::UserCreate, $req->ip(), Auth::id(), 'user', $userId
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Flash::error('Erreur lors de la création de l\'utilisateur.');
            Response::redirect('/admin/users/create');
        }

        Flash::success('Utilisateur créé avec succès.');
        Response::redirect('/admin/users');
    }

    public function formulaireEdition(Request $req, array $params): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $repo = new UserRepository();
        $db = Connection::get();
        $editUser = $repo->findById((int) $params['id']);

        if (!$editUser) {
            Response::abort(404);
        }

        $editUserId = (int) $editUser['id'];
        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
        $accessMatrix = $ac->getAccessMatrix();
        $quotaMatrix = Quota::getQuotaMatrix();

        Layout::render('layout', [
            'template'          => 'admin/user-form',
            'pageTitle'         => 'Modifier ' . $editUser['username'],
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'users',
            'editUser'          => $editUser,
            'modules'           => $modules,
            'userAccess'        => $accessMatrix[$editUserId] ?? [],
            'userQuotas'        => $quotaMatrix[$editUserId] ?? [],
        ]);
    }

    public function mettreAJour(Request $req, array $params): void
    {
        $repo = new UserRepository();
        $id = (int) $params['id'];
        $editUser = $repo->findById($id);

        if (!$editUser) {
            Response::abort(404);
        }

        $role = Role::tryFrom($req->post('role', 'user')) ?? Role::User;

        $data = [
            'username' => trim($req->post('username', '')),
            'email'    => trim($req->post('email', '')) ?: null,
            'role'     => $role->value,
            'active'   => $req->post('active') ? 1 : 0,
        ];

        $password = $req->post('password', '');
        if (!empty($password)) {
            $data['password_hash'] = PasswordHasher::hash($password);
        }

        $db = Connection::get();
        $db->beginTransaction();
        try {
            $repo->update($id, $data);

            $this->sauvegarderAccesEtQuotas($req, $db, $id);

            AuditLogger::instance()->log(
                AuditAction::UserUpdate, $req->ip(), Auth::id(), 'user', $id
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Flash::error('Erreur lors de la mise à jour de l\'utilisateur.');
            Response::redirect('/admin/users/' . $id . '/edit');
        }

        Flash::success('Utilisateur mis à jour.');
        Response::redirect('/admin/users');
    }

    private function sauvegarderAccesEtQuotas(Request $req, \PDO $db, int $userId): void
    {
        $ac = new AccessControl();
        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
        $accessData = $req->post('access') ?? [];
        $quotaData = $req->post('quotas') ?? [];

        foreach ($modules as $mod) {
            $modId = (int) $mod['id'];
            $granted = !empty($accessData[$modId]);
            $ac->setAccess($userId, $modId, $granted, Auth::id());

            $quotaVal = $quotaData[$modId] ?? '';
            if ($quotaVal !== '') {
                Quota::setLimit($userId, $modId, (int) $quotaVal, Auth::id());
            }
        }
    }
}
