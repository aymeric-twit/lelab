<?php

namespace Platform\Controller\Api;

use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\Quota;
use Platform\User\AccessControl;

class ApiModulesController
{
    /**
     * GET /api/v1/modules — Liste des modules accessibles par l'utilisateur API.
     */
    public function index(Request $req): void
    {
        $userId = (int) $_SERVER['API_USER_ID'];
        $role = $_SERVER['API_USER_ROLE'] ?? 'user';

        $db = Connection::get();

        if ($role === 'admin') {
            $modules = $db->query(
                'SELECT id, slug, name, description, version, icon, enabled, quota_mode, default_quota, sort_order
                 FROM modules WHERE desinstalle_le IS NULL ORDER BY sort_order'
            )->fetchAll();
        } else {
            $ac = new AccessControl();
            $modules = $ac->getAccessibleModules($userId);
        }

        $quotas = Quota::getUserQuotaSummary($userId);

        $donnees = array_map(function (array $m) use ($quotas) {
            $slug = $m['slug'];
            return [
                'id'          => (int) $m['id'],
                'slug'        => $slug,
                'name'        => $m['name'],
                'description' => $m['description'] ?? '',
                'version'     => $m['version'] ?? '1.0.0',
                'icon'        => $m['icon'] ?? 'bi-tools',
                'enabled'     => (bool) ($m['enabled'] ?? true),
                'quota'       => isset($quotas[$slug]) ? [
                    'mode'  => $quotas[$slug]['quota_mode']->value,
                    'usage' => $quotas[$slug]['usage'],
                    'limit' => $quotas[$slug]['limit'],
                ] : null,
            ];
        }, $modules);

        Response::json([
            'donnees' => $donnees,
            'message' => 'OK',
        ]);
    }

    /**
     * GET /api/v1/modules/{slug} — Détails d'un module.
     */
    public function show(Request $req, array $params): void
    {
        $slug = $params['slug'];
        $userId = (int) $_SERVER['API_USER_ID'];
        $role = $_SERVER['API_USER_ROLE'] ?? 'user';

        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT id, slug, name, description, version, icon, enabled, quota_mode, default_quota, sort_order
             FROM modules WHERE slug = ? AND desinstalle_le IS NULL'
        );
        $stmt->execute([$slug]);
        $module = $stmt->fetch();

        if (!$module) {
            Response::json(['erreur' => 'Module introuvable.', 'message' => 'Not Found'], 404);
        }

        // Vérifier l'accès pour les non-admin
        if ($role !== 'admin') {
            $ac = new AccessControl();
            if (!$ac->hasAccess($userId, $slug)) {
                Response::json(['erreur' => 'Accès refusé à ce module.', 'message' => 'Forbidden'], 403);
            }
        }

        $quotas = Quota::getUserQuotaSummary($userId);

        Response::json([
            'donnees' => [
                'id'          => (int) $module['id'],
                'slug'        => $module['slug'],
                'name'        => $module['name'],
                'description' => $module['description'] ?? '',
                'version'     => $module['version'] ?? '1.0.0',
                'icon'        => $module['icon'] ?? 'bi-tools',
                'enabled'     => (bool) $module['enabled'],
                'quota'       => isset($quotas[$slug]) ? [
                    'mode'  => $quotas[$slug]['quota_mode']->value,
                    'usage' => $quotas[$slug]['usage'],
                    'limit' => $quotas[$slug]['limit'],
                ] : null,
            ],
            'message' => 'OK',
        ]);
    }
}
