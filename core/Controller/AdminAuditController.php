<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Enum\AuditAction;
use Platform\Http\Request;
use Platform\Repository\AuditRepository;
use Platform\User\AccessControl;
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
