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
        $alertesActives = true;
        try {
            $prefRepo = new NotificationPreferenceRepository();
            $prefsUtilisateur = $prefRepo->obtenirPreferences($user['id']);
            foreach (TypeNotification::cases() as $type) {
                if ($type->estDesabonnable() && isset($prefsUtilisateur[$type->value]) && !$prefsUtilisateur[$type->value]) {
                    $alertesActives = false;
                    break;
                }
            }
        } catch (\PDOException) {
            // Table user_notification_preferences pas encore créée (migration 024)
        }

        // Données d'usage pour les graphiques (6 derniers mois)
        $usageParMois = $this->usageMensuel($user['id'], $estAdmin);

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
            'usageParMois'      => $usageParMois,
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

    /**
     * Récupère l'usage mensuel agrégé (6 derniers mois) pour les graphiques.
     *
     * @return array{labels: string[], series: array<string, array{name: string, data: int[]}>}
     */
    private function usageMensuel(int $userId, bool $estAdmin): array
    {
        $db = Connection::get();
        $moisCount = 6;

        // Générer les labels des 6 derniers mois
        $labels = [];
        $moisFr = [1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin',
                    7 => 'Juil', 8 => 'Août', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];
        $yearMonths = [];
        for ($i = $moisCount - 1; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-{$i} months");
            $yearMonths[] = $date->format('Ym');
            $labels[] = $moisFr[(int) $date->format('n')] . ' ' . $date->format('y');
        }

        $seuilYearMonth = $yearMonths[0];
        $placeholders = implode(',', array_fill(0, count($yearMonths), '?'));

        if ($estAdmin) {
            // Admin : usage global par module
            $stmt = $db->prepare(
                "SELECT m.slug, m.name, mu.year_month, SUM(mu.usage_count) AS total
                 FROM module_usage mu
                 JOIN modules m ON m.id = mu.module_id
                 WHERE mu.year_month IN ({$placeholders})
                 GROUP BY m.slug, m.name, mu.year_month
                 ORDER BY m.slug, mu.year_month"
            );
            $stmt->execute($yearMonths);
        } else {
            // User : usage personnel
            $params = array_merge($yearMonths, [$userId]);
            $stmt = $db->prepare(
                "SELECT m.slug, m.name, mu.year_month, mu.usage_count AS total
                 FROM module_usage mu
                 JOIN modules m ON m.id = mu.module_id
                 WHERE mu.year_month IN ({$placeholders}) AND mu.user_id = ?
                 ORDER BY m.slug, mu.year_month"
            );
            $stmt->execute($params);
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Structurer par module
        $series = [];
        foreach ($rows as $row) {
            $slug = $row['slug'];
            if (!isset($series[$slug])) {
                $series[$slug] = ['name' => $row['name'], 'data' => array_fill(0, $moisCount, 0)];
            }
            $idx = array_search($row['year_month'], $yearMonths, true);
            if ($idx !== false) {
                $series[$slug]['data'][$idx] += (int) $row['total'];
            }
        }

        // Trier par total décroissant, garder les 5 premiers
        uasort($series, fn($a, $b) => array_sum($b['data']) <=> array_sum($a['data']));
        $series = array_slice($series, 0, 5, true);

        return ['labels' => $labels, 'series' => $series];
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
