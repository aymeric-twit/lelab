<?php

namespace Platform\Service;

use Platform\Database\Connection;
use Platform\Log\Logger;
use Platform\Module\Quota;
use Platform\User\UserRepository;
use PDO;

class NotificationService
{
    private static ?self $instance = null;
    private PDO $db;
    private array $config;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
        $this->config = require __DIR__ . '/../../config/app.php';
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    // -----------------------------------------------
    // Emails lifecycle
    // -----------------------------------------------

    public function envoyerBienvenue(int $userId): bool
    {
        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $contenu = EmailTemplate::rendre('bienvenue', [
            'username' => $user['username'],
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/',
        ]);

        return $this->envoyerAvecProtection($user['email'], 'Bienvenue sur Le lab !', $contenu);
    }

    public function envoyerConfirmationSuppression(string $email, string $username): bool
    {
        $contenu = EmailTemplate::rendre('suppression-compte', [
            'username' => $username,
            'dateEffective' => date('d/m/Y à H:i'),
        ]);

        return $this->envoyerAvecProtection($email, 'Votre compte a été supprimé', $contenu);
    }

    public function envoyerConfirmationChangementMdp(int $userId): bool
    {
        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $contenu = EmailTemplate::rendre('changement-mot-de-passe', [
            'username' => $user['username'],
            'dateChangement' => date('d/m/Y à H:i'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Inconnue',
        ]);

        return $this->envoyerAvecProtection($user['email'], 'Mot de passe modifié', $contenu);
    }

    // -----------------------------------------------
    // Emails quota
    // -----------------------------------------------

    public function envoyerAlerteQuota80(int $userId, string $slug, int $usage, int $limite): bool
    {
        if ($this->dejaEnvoye($userId, 'quota_80', $slug)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $nomModule = $this->getNomModule($slug);
        $contenu = EmailTemplate::rendre('alerte-quota-80', [
            'username' => $user['username'],
            'nomModule' => $nomModule,
            'usage' => $usage,
            'limite' => $limite,
            'pourcentage' => round(($usage / $limite) * 100),
            'dateReset' => Quota::dateProchainReset(),
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/mon-compte',
        ]);

        $envoye = $this->envoyerAvecProtection($user['email'], "Alerte : quota {$nomModule} bientôt atteint", $contenu);
        if ($envoye) {
            $this->marquerEnvoye($userId, 'quota_80', $slug);
        }
        return $envoye;
    }

    public function envoyerAlerteQuota100(int $userId, string $slug, int $usage, int $limite): bool
    {
        if ($this->dejaEnvoye($userId, 'quota_100', $slug)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $nomModule = $this->getNomModule($slug);
        $contenu = EmailTemplate::rendre('alerte-quota-100', [
            'username' => $user['username'],
            'nomModule' => $nomModule,
            'usage' => $usage,
            'limite' => $limite,
            'dateReset' => Quota::dateProchainReset(),
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/mon-compte',
        ]);

        $envoye = $this->envoyerAvecProtection($user['email'], "Quota {$nomModule} épuisé", $contenu);
        if ($envoye) {
            $this->marquerEnvoye($userId, 'quota_100', $slug);
        }
        return $envoye;
    }

    /**
     * @param array<array{nom: string, usage: int, limite: int}> $resumeModules
     */
    public function envoyerResumeResetQuotas(int $userId, array $resumeModules): bool
    {
        $moisPrecedent = date('Ym', strtotime('first day of last month'));
        if ($this->dejaEnvoye($userId, 'reset_quotas', null, $moisPrecedent)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $contenu = EmailTemplate::rendre('reset-quotas', [
            'username' => $user['username'],
            'moisPrecedent' => $this->formaterMois($moisPrecedent),
            'resumeModules' => $resumeModules,
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/',
        ]);

        $envoye = $this->envoyerAvecProtection($user['email'], 'Vos quotas ont été réinitialisés', $contenu);
        if ($envoye) {
            $this->marquerEnvoye($userId, 'reset_quotas', null, $moisPrecedent);
        }
        return $envoye;
    }

    // -----------------------------------------------
    // Notification admin
    // -----------------------------------------------

    public function notifierAdminNouvelInscrit(int $userId, string $username, string $email): bool
    {
        $admins = $this->getAdminEmails();
        if ($admins === []) {
            return false;
        }

        $contenu = EmailTemplate::rendre('admin-nouvel-inscrit', [
            'username' => $username,
            'email' => $email,
            'dateInscription' => date('d/m/Y à H:i'),
            'lienAdmin' => rtrim($this->config['url'], '/') . '/admin/users',
        ]);

        $succes = true;
        foreach ($admins as $adminEmail) {
            if (!$this->envoyerAvecProtection($adminEmail, 'Nouvel utilisateur inscrit : ' . $username, $contenu)) {
                $succes = false;
            }
        }
        return $succes;
    }

    // -----------------------------------------------
    // Méthodes internes
    // -----------------------------------------------

    private function envoyerAvecProtection(string $destinataire, string $sujet, string $contenuHtml): bool
    {
        try {
            return Mailer::instance()->envoyer($destinataire, $sujet, $contenuHtml);
        } catch (\Throwable $e) {
            Logger::error('Échec envoi notification', [
                'destinataire' => $destinataire,
                'sujet' => $sujet,
                'erreur' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function dejaEnvoye(int $userId, string $type, ?string $slug = null, ?string $yearMonth = null): bool
    {
        $yearMonth ??= date('Ym');
        $slug ??= '';

        $stmt = $this->db->prepare(
            'SELECT 1 FROM email_notifications_log
             WHERE user_id = ? AND type_notification = ? AND module_slug = ? AND year_month = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $type, $slug, $yearMonth]);
        return (bool) $stmt->fetch();
    }

    private function marquerEnvoye(int $userId, string $type, ?string $slug = null, ?string $yearMonth = null): void
    {
        $yearMonth ??= date('Ym');
        $slug ??= '';

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ignore = $driver === 'sqlite' ? 'OR IGNORE' : 'IGNORE';

        $this->db->prepare(
            "INSERT {$ignore} INTO email_notifications_log (user_id, type_notification, module_slug, year_month)
             VALUES (?, ?, ?, ?)"
        )->execute([$userId, $type, $slug, $yearMonth]);
    }

    /**
     * @return string[]
     */
    private function getAdminEmails(): array
    {
        $configEmail = $this->config['notifications']['admin_email'] ?? null;
        if ($configEmail !== null && $configEmail !== '') {
            return array_map('trim', explode(',', $configEmail));
        }

        $stmt = $this->db->query(
            "SELECT email FROM users WHERE role = 'admin' AND active = 1 AND deleted_at IS NULL AND email IS NOT NULL"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getNomModule(string $slug): string
    {
        $stmt = $this->db->prepare('SELECT name FROM modules WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() ?: $slug;
    }

    private function formaterMois(string $yearMonth): string
    {
        $moisNoms = [
            '01' => 'janvier', '02' => 'février', '03' => 'mars',
            '04' => 'avril', '05' => 'mai', '06' => 'juin',
            '07' => 'juillet', '08' => 'août', '09' => 'septembre',
            '10' => 'octobre', '11' => 'novembre', '12' => 'décembre',
        ];
        $annee = substr($yearMonth, 0, 4);
        $mois = substr($yearMonth, 4, 2);
        return ($moisNoms[$mois] ?? $mois) . ' ' . $annee;
    }
}
