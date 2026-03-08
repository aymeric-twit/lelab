<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Enum\TypeNotification;
use Platform\Http\Response;
use Platform\Module\Quota;
use Platform\Repository\NotificationPreferenceRepository;
use Platform\User\AccessControl;
use Platform\View\Layout;

class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);

        $jourInscription = (int) date('j', strtotime($user['created_at'] ?? 'now'));
        $quotaSummary = Quota::getUserQuotaSummary($user['id'], $jourInscription);

        $estAdmin = ($user['role'] ?? '') === 'admin';
        $journalActivite = $this->dernieresActivites($user['id'], $estAdmin);

        // Préférences notifications : vérifier si au moins un type désabonnable est actif
        $prefRepo = new NotificationPreferenceRepository();
        $prefsUtilisateur = $prefRepo->obtenirPreferences($user['id']);
        $alertesActives = true;
        foreach (TypeNotification::cases() as $type) {
            if ($type->estDesabonnable() && isset($prefsUtilisateur[$type->value]) && !$prefsUtilisateur[$type->value]) {
                $alertesActives = false;
                break;
            }
        }

        Layout::render('layout', [
            'template'          => 'dashboard',
            'pageTitle'         => 'Dashboard',
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => '',
            'quotaSummary'      => $quotaSummary,
            'journalActivite'   => $journalActivite,
            'jourInscription'   => $jourInscription,
            'dateResetQuota'    => Quota::dateProchainResetUtilisateur($jourInscription),
            'alertesActives'    => $alertesActives,
            'unsubscribeToken'  => $user['unsubscribe_token'] ?? '',
        ]);
    }

    public function toggleNotifications(): void
    {
        $user = Auth::user();
        $actif = ($_POST['actif'] ?? '1') === '1';

        $repo = new NotificationPreferenceRepository();
        $prefs = [];
        foreach (TypeNotification::cases() as $type) {
            if ($type->estDesabonnable()) {
                $prefs[$type->value] = $actif;
            }
        }
        $repo->mettreAJourMultiple($user['id'], $prefs);

        Response::json(['ok' => true]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dernieresActivites(int $userId, bool $estAdmin): array
    {
        $db = Connection::get();

        if ($estAdmin) {
            $stmt = $db->query(
                'SELECT a.action, a.target_type, a.details, a.created_at, u.username
                 FROM audit_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.created_at DESC
                 LIMIT 15'
            );
        } else {
            $stmt = $db->prepare(
                'SELECT a.action, a.target_type, a.details, a.created_at, u.username
                 FROM audit_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.user_id = ?
                 ORDER BY a.created_at DESC
                 LIMIT 15'
            );
            $stmt->execute([$userId]);
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function tempsRelatif(string $dateStr): string
    {
        $date = new \DateTimeImmutable($dateStr);
        $maintenant = new \DateTimeImmutable();
        $diff = $maintenant->diff($date);

        if ($diff->days === 0) {
            if ($diff->h === 0 && $diff->i < 1) {
                return "à l'instant";
            }
            if ($diff->h === 0) {
                return "il y a {$diff->i} min";
            }
            return "il y a {$diff->h}h" . ($diff->i > 0 ? sprintf('%02d', $diff->i) : '');
        }

        if ($diff->days === 1) {
            return 'hier à ' . $date->format('H:i');
        }

        if ($diff->days < 7) {
            return "il y a {$diff->days} jours";
        }

        return $date->format('d/m/Y H:i');
    }

    public static function tempsRelatifFutur(string $dateStr): string
    {
        $cible = new \DateTimeImmutable($dateStr);
        $maintenant = new \DateTimeImmutable('today');
        $diff = $maintenant->diff($cible);
        $jours = (int) $diff->format('%r%a');

        if ($jours <= 0) {
            return "aujourd'hui";
        }
        if ($jours === 1) {
            return 'demain';
        }

        return "dans {$jours} jours";
    }

    public static function dateFrancaise(string $dateStr): string
    {
        $mois = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        $date = new \DateTimeImmutable($dateStr);

        return $date->format('j') . ' ' . $mois[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
