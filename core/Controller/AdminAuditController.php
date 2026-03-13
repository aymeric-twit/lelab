<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Enum\AuditAction;
use Platform\Http\Csrf;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Repository\AuditRepository;
use Platform\User\AccessControl;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminAuditController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $repo = new AuditRepository();

        $page = max(1, (int) $req->get('page', 1));
        $filtres = $this->extraireFiltres($req);
        $pagination = $repo->rechercher($filtres, $page, 50);

        Layout::render('layout', [
            'template'          => 'admin/audit',
            'pageTitle'         => 'Journal d\'audit',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'audit',
            'logs'              => $pagination['donnees'],
            'pagination'        => $pagination,
            'filtres'           => $filtres,
            'actions'           => AuditAction::cases(),
        ]);
    }

    public function exporterCsv(Request $req): never
    {
        $repo = new AuditRepository();
        $filtres = $this->extraireFiltres($req);
        $logs = $repo->rechercherTout($filtres);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Action', 'Utilisateur', 'IP', 'Détails']);

        foreach ($logs as $log) {
            $action = AuditAction::tryFrom($log['action']);
            $details = $log['details'] ? json_decode($log['details'], true) : [];

            fputcsv($out, [
                $log['created_at'],
                $action?->label() ?? $log['action'],
                $log['username'] ?? '-',
                $log['ip_address'] ?? '-',
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : '',
            ]);
        }

        fclose($out);
        exit;
    }

    public function purger(Request $req): void
    {
        Csrf::validateOrAbort();

        $dateDebut = trim((string) $req->post('date_debut', ''));
        $dateFin = trim((string) $req->post('date_fin', ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
            Flash::error('Dates invalides. Veuillez sélectionner une plage de dates valide.');
            Response::redirect('/admin/audit');
        }

        if ($dateDebut > $dateFin) {
            Flash::error('La date de début doit être antérieure à la date de fin.');
            Response::redirect('/admin/audit');
        }

        $repo = new AuditRepository();
        $supprimees = $repo->purgerParPlage($dateDebut, $dateFin);

        if ($supprimees > 0) {
            Flash::success("{$supprimees} entrée(s) d'audit supprimée(s) du {$dateDebut} au {$dateFin}.");
        } else {
            Flash::set('info', 'Aucune entrée à supprimer dans cette plage.');
        }

        Response::redirect('/admin/audit');
    }

    /**
     * @return array{date_debut: string, date_fin: string, action: string, utilisateur: string, ip: string}
     */
    private function extraireFiltres(Request $req): array
    {
        return [
            'date_debut'  => trim((string) $req->get('date_debut', '')),
            'date_fin'    => trim((string) $req->get('date_fin', '')),
            'action'      => trim((string) $req->get('action', '')),
            'utilisateur' => trim((string) $req->get('utilisateur', '')),
            'ip'          => trim((string) $req->get('ip', '')),
        ];
    }
}
