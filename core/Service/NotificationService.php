<?php

namespace Platform\Service;

use Platform\Database\Connection;
use Platform\Enum\TypeNotification;
use Platform\Log\Logger;
use Platform\Module\Quota;
use Platform\Repository\NotificationPreferenceRepository;
use Platform\Repository\SettingsRepository;
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
        $type = TypeNotification::Bienvenue;
        if (!$this->estActive($type) || $this->estDesactiveParUtilisateur($userId, $type)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $unsubData = $this->donneesDesabonnement($user, $type);
        $contenu = EmailTemplate::rendre('bienvenue', array_merge($unsubData['template'], [
            'username' => $user['username'],
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/',
        ]));

        $sujet = $this->sujetPour($type);
        return $this->envoyerAvecProtection($user['email'], $sujet, $contenu, $type->value, $userId, $unsubData['url']);
    }

    public function envoyerConfirmationSuppression(string $email, string $username): bool
    {
        $type = TypeNotification::SuppressionCompte;
        if (!$this->estActive($type)) {
            return false;
        }

        // Pas de désabonnement pour les emails transactionnels
        $contenu = EmailTemplate::rendre('suppression-compte', [
            'username' => $username,
            'dateEffective' => date('d/m/Y à H:i'),
        ]);

        $sujet = $this->sujetPour($type);
        return $this->envoyerAvecProtection($email, $sujet, $contenu, $type->value);
    }

    public function envoyerConfirmationChangementMdp(int $userId): bool
    {
        $type = TypeNotification::ChangementMdp;
        if (!$this->estActive($type)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        // Transactionnel : pas de désabonnement
        $contenu = EmailTemplate::rendre('changement-mot-de-passe', [
            'username' => $user['username'],
            'dateChangement' => date('d/m/Y à H:i'),
        ]);

        $sujet = $this->sujetPour($type);
        return $this->envoyerAvecProtection($user['email'], $sujet, $contenu, $type->value, $userId);
    }

    // -----------------------------------------------
    // Emails quota
    // -----------------------------------------------

    public function envoyerAlerteQuota80(int $userId, string $slug, int $usage, int $limite): bool
    {
        $type = TypeNotification::AlerteQuota80;
        if (!$this->estActive($type) || $this->estDesactiveParUtilisateur($userId, $type)) {
            return false;
        }

        if ($this->dejaEnvoye($userId, 'quota_80', $slug)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $unsubData = $this->donneesDesabonnement($user, $type);
        $nomModule = $this->getNomModule($slug);
        $contenu = EmailTemplate::rendre('alerte-quota-80', array_merge($unsubData['template'], [
            'username' => $user['username'],
            'nomModule' => $nomModule,
            'usage' => $usage,
            'limite' => $limite,
            'pourcentage' => round(($usage / $limite) * 100),
            'dateReset' => Quota::dateProchainResetUtilisateur(
                (int) date('j', strtotime($user['created_at'] ?? 'now'))
            ),
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/mon-compte',
        ]));

        $sujet = $this->sujetPour($type);
        $envoye = $this->envoyerAvecProtection($user['email'], $sujet, $contenu, $type->value, $userId, $unsubData['url']);
        if ($envoye) {
            $this->marquerEnvoye($userId, 'quota_80', $slug);
        }
        return $envoye;
    }

    public function envoyerAlerteQuota100(int $userId, string $slug, int $usage, int $limite): bool
    {
        $type = TypeNotification::AlerteQuota100;
        if (!$this->estActive($type) || $this->estDesactiveParUtilisateur($userId, $type)) {
            return false;
        }

        if ($this->dejaEnvoye($userId, 'quota_100', $slug)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $unsubData = $this->donneesDesabonnement($user, $type);
        $nomModule = $this->getNomModule($slug);
        $contenu = EmailTemplate::rendre('alerte-quota-100', array_merge($unsubData['template'], [
            'username' => $user['username'],
            'nomModule' => $nomModule,
            'usage' => $usage,
            'limite' => $limite,
            'dateReset' => Quota::dateProchainResetUtilisateur(
                (int) date('j', strtotime($user['created_at'] ?? 'now'))
            ),
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/mon-compte',
        ]));

        $sujet = $this->sujetPour($type);
        $envoye = $this->envoyerAvecProtection($user['email'], $sujet, $contenu, $type->value, $userId, $unsubData['url']);
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
        $type = TypeNotification::ResetQuotas;
        if (!$this->estActive($type) || $this->estDesactiveParUtilisateur($userId, $type)) {
            return false;
        }

        $moisPrecedent = date('Ym', strtotime('first day of last month'));
        if ($this->dejaEnvoye($userId, 'reset_quotas', null, $moisPrecedent)) {
            return false;
        }

        $user = (new UserRepository())->findById($userId);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $unsubData = $this->donneesDesabonnement($user, $type);
        $contenu = EmailTemplate::rendre('reset-quotas', array_merge($unsubData['template'], [
            'username' => $user['username'],
            'moisPrecedent' => $this->formaterMois($moisPrecedent),
            'resumeModules' => $resumeModules,
            'lienPlateforme' => rtrim($this->config['url'], '/') . '/',
        ]));

        $sujet = $this->sujetPour($type);
        $envoye = $this->envoyerAvecProtection($user['email'], $sujet, $contenu, $type->value, $userId, $unsubData['url']);
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
        $type = TypeNotification::AdminNouvelInscrit;
        if (!$this->estActive($type)) {
            return false;
        }

        $admins = $this->getAdminEmails();
        if ($admins === []) {
            return false;
        }

        // Les admins reçoivent avec le lien de désabonnement du premier admin trouvé
        $contenu = EmailTemplate::rendre('admin-nouvel-inscrit', [
            'username' => $username,
            'email' => $email,
            'dateInscription' => date('d/m/Y à H:i'),
            'lienAdmin' => rtrim($this->config['url'], '/') . '/admin/users',
            '_estDesabonnable' => true,
        ]);

        $sujet = $this->sujetPour($type);
        $succes = true;
        foreach ($admins as $adminEmail) {
            if (!$this->envoyerAvecProtection($adminEmail, $sujet . ' : ' . $username, $contenu, $type->value)) {
                $succes = false;
            }
        }
        return $succes;
    }

    // -----------------------------------------------
    // Méthodes internes
    // -----------------------------------------------

    private function envoyerAvecProtection(string $destinataire, string $sujet, string $contenuHtml, ?string $typeEmail = null, ?int $userId = null, ?string $unsubscribeUrl = null): bool
    {
        try {
            return Mailer::instance()->envoyer($destinataire, $sujet, $contenuHtml, $typeEmail, $userId, $unsubscribeUrl);
        } catch (\Throwable $e) {
            Logger::error('Échec envoi notification', [
                'destinataire' => $destinataire,
                'sujet' => $sujet,
                'erreur' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Vérifie si un utilisateur a désactivé ce type de notification.
     * Ne s'applique qu'aux types désabonnables.
     */
    private function estDesactiveParUtilisateur(int $userId, TypeNotification $type): bool
    {
        if (!$type->estDesabonnable()) {
            return false;
        }

        try {
            $repo = new NotificationPreferenceRepository($this->db);
            return $repo->estDesactive($userId, $type->value);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Construit les données de désabonnement pour un utilisateur et un type.
     *
     * @param array<string, mixed> $user
     * @return array{template: array<string, mixed>, url: string|null}
     */
    private function donneesDesabonnement(array $user, TypeNotification $type): array
    {
        $baseUrl = rtrim($this->config['url'] ?? '', '/');
        $token = $user['unsubscribe_token'] ?? null;
        $url = ($token && $type->estDesabonnable())
            ? $baseUrl . '/desabonnement?token=' . $token
            : null;

        return [
            'template' => [
                '_lienDesabonnement' => $url,
                '_estDesabonnable' => $type->estDesabonnable(),
            ],
            'url' => $url,
        ];
    }

    /**
     * Vérifie si un type de notification est activé (DB override).
     * Par défaut (pas de valeur en DB), toutes les notifications sont actives.
     */
    private function estActive(TypeNotification $type): bool
    {
        try {
            $repo = new SettingsRepository($this->db);
            $valeur = $repo->obtenir('notifications', $type->value . '_active');
            return $valeur !== '0';
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Retourne le sujet personnalisé depuis la DB, ou le sujet par défaut.
     */
    private function sujetPour(TypeNotification $type): string
    {
        try {
            $repo = new SettingsRepository($this->db);
            $sujetCustom = $repo->obtenir('email_sujets', $type->value);
            if ($sujetCustom !== null && $sujetCustom !== '') {
                return $sujetCustom;
            }
        } catch (\Throwable) {
            // Fallback silencieux
        }

        return $type->sujetParDefaut();
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
        // Priorité : DB → config → BDD users admin
        try {
            $repo = new SettingsRepository($this->db);
            $dbEmail = $repo->obtenir('notifications', 'admin_email');
            if ($dbEmail !== null && $dbEmail !== '') {
                return array_map('trim', explode(',', $dbEmail));
            }
        } catch (\Throwable) {
            // Fallback
        }

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
