<?php

namespace Platform\Http\Middleware;

use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Http\Response;

/**
 * Middleware d'authentification par clé API (header Authorization: Bearer <key>).
 * Stocke l'utilisateur authentifié dans $_SERVER['API_USER_ID'].
 */
class RequireApiKey
{
    public function __invoke(Request $req, callable $next): mixed
    {
        $header = $req->header('Authorization') ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            Response::json([
                'erreur'  => 'Clé API manquante. Utilisez le header Authorization: Bearer <votre-clé>.',
                'message' => 'Unauthorized',
            ], 401);
        }

        $key = substr($header, 7);
        if (strlen($key) < 32) {
            Response::json([
                'erreur'  => 'Format de clé API invalide.',
                'message' => 'Unauthorized',
            ], 401);
        }

        $keyHash = hash('sha256', $key);
        $db = Connection::get();

        $stmt = $db->prepare(
            'SELECT ak.id, ak.user_id, ak.expires_at, u.role, u.active
             FROM api_keys ak
             JOIN users u ON u.id = ak.user_id
             WHERE ak.key_hash = ? AND u.deleted_at IS NULL'
        );
        $stmt->execute([$keyHash]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::json([
                'erreur'  => 'Clé API invalide.',
                'message' => 'Unauthorized',
            ], 401);
        }

        if (!$row['active']) {
            Response::json([
                'erreur'  => 'Compte utilisateur désactivé.',
                'message' => 'Forbidden',
            ], 403);
        }

        if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
            Response::json([
                'erreur'  => 'Clé API expirée.',
                'message' => 'Unauthorized',
            ], 401);
        }

        // Mettre à jour last_used_at
        $db->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')
            ->execute([$row['id']]);

        // Rendre disponible l'utilisateur pour les contrôleurs API
        $_SERVER['API_USER_ID'] = (int) $row['user_id'];
        $_SERVER['API_USER_ROLE'] = $row['role'];

        return $next($req);
    }
}
