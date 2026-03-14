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
use Platform\Service\NotificationService;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use Platform\Validation\Validator;
use Platform\Log\Logger;
use Platform\Service\UserExportService;
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
        $filtres = [
            'q'     => trim((string) $req->get('q', '')),
            'role'  => (string) $req->get('role', ''),
            'actif' => (string) $req->get('actif', ''),
        ];
        $pagination = $repo->findAllPagine($page, 20, $filtres);

        // Chargement paresseux : matrices accès/quotas seulement pour les onglets concernés
        $tousLesUtilisateurs = [];
        $modulesActifs = [];
        $matriceAcces = [];
        $matriceQuotas = [];
        $matriceUsage = [];

        if ($onglet !== 'utilisateurs') {
            $tousLesUtilisateurs = $repo->findAll();
            $modulesActifs = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
            $matriceAcces = $ac->getAccessMatrix();
            $matriceQuotas = Quota::getQuotaMatrix();
            $matriceUsage = Quota::getUsageMatrix();
        }

        Layout::render('layout', [
            'template'              => 'admin/users',
            'pageTitle'             => 'Utilisateurs',
            'currentUser'           => $user,
            'accessibleModules'     => $ac->getAccessibleModules($user['id']),
            'adminPage'             => 'users',
            'onglet'                => $onglet,
            'users'                 => $pagination['donnees'],
            'pagination'            => $pagination,
            'filtres'               => $filtres,
            'tousLesUtilisateurs'   => $tousLesUtilisateurs,
            'modulesActifs'         => $modulesActifs,
            'matriceAcces'          => $matriceAcces,
            'matriceQuotas'         => $matriceQuotas,
            'matriceUsage'          => $matriceUsage,
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
            'userAccess'        => [],
            'userQuotas'        => [],
            'userExpires'       => [],
        ]);
    }

    public function creer(Request $req): void
    {
        $repo = new UserRepository();
        $username = trim($req->post('username', ''));
        $email = trim($req->post('email', ''));
        $domaine = trim($req->post('domaine', ''));
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
                'domaine'       => $domaine !== '' ? $domaine : null,
                'password_hash' => PasswordHasher::hash($password),
                'role'          => $role->value,
                'active'        => $active,
            ]);

            $this->sauvegarderAccesEtQuotas($req, $db, $userId);

            AuditLogger::instance()->log(
                AuditAction::UserCreate, $req->ipAnonymisee(), Auth::id(), 'user', $userId
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Logger::error('Échec création utilisateur', [
                'message'  => $e->getMessage(),
                'fichier'  => $e->getFile(),
                'ligne'    => $e->getLine(),
                'trace'    => $e->getTraceAsString(),
                'username' => $username,
            ]);
            Flash::error('Erreur lors de la création de l\'utilisateur : ' . $e->getMessage());
            Response::redirect('/admin/users/create');
        }

        // Envoi de l'email de bienvenue hors transaction : un échec SMTP
        // ne doit pas faire croire que la création a échoué
        if ($req->post('envoyer_bienvenue') && !empty($email)) {
            try {
                NotificationService::instance()->envoyerBienvenue($userId);
            } catch (\Throwable $e) {
                Logger::warning('Email de bienvenue non envoyé', ['userId' => $userId, 'erreur' => $e->getMessage()]);
                Flash::warning('Utilisateur créé mais l\'email de bienvenue n\'a pas pu être envoyé. Vérifiez les paramètres SMTP.');
                Response::redirect('/admin/users');
            }
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
            'userExpires'       => array_map(
                fn(array $a) => $a['expires_at'] ?? null,
                $accessMatrix[$editUserId] ?? []
            ),
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

        $domaine = trim($req->post('domaine', ''));
        $data = [
            'username'             => trim($req->post('username', '')),
            'email'                => trim($req->post('email', '')) ?: null,
            'domaine'              => $domaine !== '' ? $domaine : null,
            'role'                 => $role->value,
            'active'               => $req->post('active') ? 1 : 0,
            'force_password_reset' => $req->post('force_password_reset') ? 1 : 0,
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
                AuditAction::UserUpdate, $req->ipAnonymisee(), Auth::id(), 'user', $id
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

    public function actionGroupee(Request $req): void
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        $action = $payload['action'] ?? '';
        $ids = array_filter(array_map('intval', $payload['ids'] ?? []), fn(int $id) => $id > 0);

        $actionsValides = ['supprimer', 'activer', 'desactiver'];
        if (!in_array($action, $actionsValides, true) || empty($ids)) {
            Response::json(['erreur' => 'Action invalide ou aucun utilisateur sélectionné.'], 400);
        }

        $adminId = Auth::id();
        // Empêcher l'auto-modification pour les actions destructives
        if ($action === 'supprimer' || $action === 'desactiver') {
            $ids = array_filter($ids, fn(int $id) => $id !== $adminId);
            if (empty($ids)) {
                Response::json(['erreur' => 'Vous ne pouvez pas ' . $action . ' votre propre compte.'], 400);
            }
        }

        $repo = new UserRepository();
        $traites = 0;

        foreach ($ids as $id) {
            match ($action) {
                'supprimer' => $repo->delete($id),
                'activer' => $repo->update($id, ['active' => 1]),
                'desactiver' => $repo->update($id, ['active' => 0]),
            };

            $auditAction = match ($action) {
                'supprimer' => AuditAction::UserDelete,
                default => AuditAction::UserUpdate,
            };

            AuditLogger::instance()->log($auditAction, $req->ipAnonymisee(), $adminId, 'user', $id);
            $traites++;
        }

        $labels = ['supprimer' => 'supprimé', 'activer' => 'activé', 'desactiver' => 'désactivé'];
        Response::json([
            'message' => $traites . ' utilisateur(s) ' . $labels[$action] . '(s).',
            'traites' => $traites,
        ]);
    }

    /**
     * A2 : Renvoyer l'email de bienvenue à un utilisateur existant.
     */
    public function renvoyerBienvenue(Request $req, array $params): void
    {
        $repo = new UserRepository();
        $id = (int) $params['id'];
        $editUser = $repo->findById($id);

        if (!$editUser) {
            Response::abort(404);
        }

        if (empty($editUser['email'])) {
            Flash::error('Cet utilisateur n\'a pas d\'adresse email.');
            Response::redirect('/admin/users/' . $id . '/edit');
        }

        try {
            NotificationService::instance()->envoyerBienvenue($id);
            Flash::success('Email de bienvenue renvoyé à ' . $editUser['email'] . '.');
        } catch (\Throwable $e) {
            Logger::warning('Renvoi email bienvenue échoué', ['userId' => $id, 'erreur' => $e->getMessage()]);
            Flash::error('Échec de l\'envoi : ' . $e->getMessage());
        }

        Response::redirect('/admin/users/' . $id . '/edit');
    }

    /**
     * Renvoyer l'email de vérification à un utilisateur existant.
     */
    public function renvoyerVerification(Request $req, array $params): void
    {
        $repo = new UserRepository();
        $id = (int) $params['id'];
        $editUser = $repo->findById($id);

        if (!$editUser) {
            Response::abort(404);
        }

        if (empty($editUser['email'])) {
            Flash::error('Cet utilisateur n\'a pas d\'adresse email.');
            Response::redirect('/admin/users/' . $id . '/edit');
        }

        try {
            \Platform\Auth\EmailVerification::envoyer($id, $editUser['email'], $editUser['username']);
            Flash::success('Email de vérification renvoyé à ' . $editUser['email'] . '.');
        } catch (\Throwable $e) {
            Logger::warning('Renvoi email vérification échoué', ['userId' => $id, 'erreur' => $e->getMessage()]);
            Flash::error('Échec de l\'envoi : ' . $e->getMessage());
        }

        Response::redirect('/admin/users/' . $id . '/edit');
    }

    /**
     * A5 : Détails d'un utilisateur en JSON (pour la modale).
     */
    public function details(Request $req, array $params): void
    {
        $repo = new UserRepository();
        $ac = new AccessControl();
        $id = (int) $params['id'];
        $u = $repo->findById($id);

        if (!$u) {
            Response::json(['erreur' => 'Utilisateur introuvable.'], 404);
        }

        $modules = $ac->getAccessibleModules($id);
        $quotaSummary = Quota::getUserQuotaSummary($id);

        Response::json([
            'id'             => (int) $u['id'],
            'username'       => $u['username'],
            'email'          => $u['email'] ?? '-',
            'domaine'        => $u['domaine'] ?? '-',
            'role'           => $u['role'],
            'active'         => (bool) $u['active'],
            'lastLogin'      => $u['last_login'] ?? null,
            'createdAt'      => $u['created_at'] ?? null,
            'modules'        => array_map(fn($m) => $m['name'], $modules),
            'quotas'         => $quotaSummary,
        ]);
    }

    /**
     * GET /admin/users/export-csv — Export CSV des utilisateurs avec accès et quotas.
     */
    public function exporterCsv(Request $req): void
    {
        $filtres = [
            'role'  => (string) $req->get('role', ''),
            'actif' => (string) $req->get('actif', ''),
        ];

        $service = new UserExportService();
        $service->exporterCsv($filtres);
        exit;
    }

    private function sauvegarderAccesEtQuotas(Request $req, \PDO $db, int $userId): void
    {
        $ac = new AccessControl();
        $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
        $accessData = $req->post('access') ?? [];
        $quotaData = $req->post('quotas') ?? [];
        $expiresData = $req->post('access_expires') ?? [];

        foreach ($modules as $mod) {
            $modId = (int) $mod['id'];
            $granted = !empty($accessData[$modId]);
            $expiresAt = !empty($expiresData[$modId])
                ? $expiresData[$modId] . ' 23:59:59'
                : null;
            $ac->setAccess($userId, $modId, $granted, Auth::id(), $expiresAt);

            $quotaVal = $quotaData[$modId] ?? '';
            if ($quotaVal !== '') {
                Quota::setLimit($userId, $modId, (int) $quotaVal, Auth::id());
            }
        }
    }
}
