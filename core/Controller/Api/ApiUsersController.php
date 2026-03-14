<?php

namespace Platform\Controller\Api;

use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\Quota;
use Platform\User\AccessControl;
use Platform\User\UserRepository;

class ApiUsersController
{
    private function requireAdmin(): void
    {
        if (($_SERVER['API_USER_ROLE'] ?? '') !== 'admin') {
            Response::json(['erreur' => 'Accès réservé aux administrateurs.', 'message' => 'Forbidden'], 403);
        }
    }

    /**
     * GET /api/v1/users — Liste paginée des utilisateurs.
     */
    public function index(Request $req): void
    {
        $this->requireAdmin();

        $repo = new UserRepository();
        $page = max(1, (int) $req->get('page', 1));
        $parPage = min(100, max(1, (int) $req->get('par_page', 20)));
        $filtres = [
            'q'     => trim((string) $req->get('q', '')),
            'role'  => (string) $req->get('role', ''),
            'actif' => (string) $req->get('actif', ''),
        ];

        $pagination = $repo->findAllPagine($page, $parPage, $filtres);

        $donnees = array_map(fn(array $u) => $this->formaterUtilisateur($u), $pagination['donnees']);

        Response::json([
            'donnees'    => $donnees,
            'pagination' => [
                'page'       => $pagination['page'],
                'par_page'   => $pagination['parPage'],
                'total'      => $pagination['total'],
                'total_pages' => $pagination['totalPages'],
            ],
            'message'    => 'OK',
        ]);
    }

    /**
     * GET /api/v1/users/{id} — Détails d'un utilisateur.
     */
    public function show(Request $req, array $params): void
    {
        $this->requireAdmin();

        $repo = new UserRepository();
        $user = $repo->findById((int) $params['id']);

        if (!$user) {
            Response::json(['erreur' => 'Utilisateur introuvable.', 'message' => 'Not Found'], 404);
        }

        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules((int) $user['id']);
        $quotas = Quota::getUserQuotaSummary((int) $user['id']);

        Response::json([
            'donnees' => array_merge($this->formaterUtilisateur($user), [
                'modules' => array_map(fn($m) => ['slug' => $m['slug'], 'name' => $m['name']], $modules),
                'quotas'  => $quotas,
            ]),
            'message' => 'OK',
        ]);
    }

    private function formaterUtilisateur(array $u): array
    {
        return [
            'id'         => (int) $u['id'],
            'username'   => $u['username'],
            'email'      => $u['email'] ?? null,
            'domaine'    => $u['domaine'] ?? null,
            'role'       => $u['role'],
            'active'     => (bool) $u['active'],
            'last_login' => $u['last_login'] ?? null,
            'created_at' => $u['created_at'] ?? null,
        ];
    }
}
