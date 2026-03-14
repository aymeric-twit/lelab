<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Service\NotificationInAppService;

class NotificationController
{
    /**
     * GET /api/notifications — Notifications non lues (AJAX).
     */
    public function nonLues(Request $req): void
    {
        $user = Auth::user();
        $service = new NotificationInAppService();

        $notifications = $service->nonLues($user['id']);
        $count = $service->compterNonLues($user['id']);

        Response::json([
            'donnees' => array_map(fn(array $n) => [
                'id'         => (int) $n['id'],
                'type'       => $n['type'],
                'titre'      => $n['titre'],
                'message'    => $n['message'],
                'lien'       => $n['lien'],
                'icone'      => $n['icone'],
                'created_at' => $n['created_at'],
            ], $notifications),
            'non_lues' => $count,
        ]);
    }

    /**
     * POST /api/notifications/lire — Marquer une notification comme lue.
     */
    public function marquerLue(Request $req): void
    {
        $user = Auth::user();
        $id = (int) $req->post('id', 0);

        if ($id > 0) {
            $service = new NotificationInAppService();
            $service->marquerLue($id, $user['id']);
        }

        Response::json(['ok' => true]);
    }

    /**
     * POST /api/notifications/lire-tout — Marquer toutes comme lues.
     */
    public function marquerToutesLues(Request $req): void
    {
        $user = Auth::user();
        $service = new NotificationInAppService();
        $service->marquerToutesLues($user['id']);

        Response::json(['ok' => true]);
    }
}
