<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\Quota;

class QuotaApiController
{
    public function afficher(Request $req, array $params): void
    {
        $user = Auth::user();
        $slug = $params['slug'] ?? '';

        $estAdmin = ($user['role'] ?? '') === 'admin';
        $usage = Quota::getUsage($user['id'], $slug);
        $limit = Quota::getLimit($user['id'], $slug);

        $restant = ($estAdmin || $limit === 0)
            ? null
            : max(0, $limit - $usage);

        Response::json([
            'slug'       => $slug,
            'usage'      => $usage,
            'limit'      => $limit,
            'restant'    => $restant,
            'illimite'   => $estAdmin || $limit === 0,
            'reset_date' => Quota::dateProchainReset(),
        ]);
    }
}
