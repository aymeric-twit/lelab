<?php

namespace Platform\Enum;

enum TypeNotification: string
{
    case Bienvenue = 'bienvenue';
    case SuppressionCompte = 'suppression_compte';
    case ChangementMdp = 'changement_mdp';
    case AlerteQuota80 = 'quota_80';
    case AlerteQuota100 = 'quota_100';
    case ResetQuotas = 'reset_quotas';
    case AdminNouvelInscrit = 'admin_nouvel_inscrit';
    case VerificationEmail = 'verification_email';
    case PasswordReset = 'password_reset';

    public function label(): string
    {
        return match ($this) {
            self::Bienvenue          => 'Bienvenue',
            self::SuppressionCompte  => 'Suppression de compte',
            self::ChangementMdp      => 'Changement de mot de passe',
            self::AlerteQuota80      => 'Alerte quota 80%',
            self::AlerteQuota100     => 'Quota épuisé (100%)',
            self::ResetQuotas        => 'Résumé reset quotas mensuel',
            self::AdminNouvelInscrit => 'Notification admin : nouvel inscrit',
            self::VerificationEmail  => 'Vérification email',
            self::PasswordReset      => 'Réinitialisation mot de passe',
        };
    }

    public function sujetParDefaut(): string
    {
        return match ($this) {
            self::Bienvenue          => 'Bienvenue sur Le lab !',
            self::SuppressionCompte  => 'Votre compte a été supprimé',
            self::ChangementMdp      => 'Mot de passe modifié',
            self::AlerteQuota80      => 'Alerte : quota bientôt atteint',
            self::AlerteQuota100     => 'Quota épuisé',
            self::ResetQuotas        => 'Vos quotas ont été réinitialisés',
            self::AdminNouvelInscrit => 'Nouvel utilisateur inscrit',
            self::VerificationEmail  => 'Vérifiez votre adresse email',
            self::PasswordReset      => 'Réinitialisation de votre mot de passe',
        };
    }

    public function template(): string
    {
        return match ($this) {
            self::Bienvenue          => 'bienvenue',
            self::SuppressionCompte  => 'suppression-compte',
            self::ChangementMdp      => 'changement-mot-de-passe',
            self::AlerteQuota80      => 'alerte-quota-80',
            self::AlerteQuota100     => 'alerte-quota-100',
            self::ResetQuotas        => 'reset-quotas',
            self::AdminNouvelInscrit => 'admin-nouvel-inscrit',
            self::VerificationEmail  => 'verification-email',
            self::PasswordReset      => 'password-reset',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Bienvenue          => 'bi-hand-thumbs-up',
            self::SuppressionCompte  => 'bi-person-x',
            self::ChangementMdp      => 'bi-key',
            self::AlerteQuota80      => 'bi-exclamation-triangle',
            self::AlerteQuota100     => 'bi-x-octagon',
            self::ResetQuotas        => 'bi-arrow-counterclockwise',
            self::AdminNouvelInscrit => 'bi-person-plus',
            self::VerificationEmail  => 'bi-envelope-check',
            self::PasswordReset      => 'bi-unlock',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function donneesExemple(): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $url = rtrim($config['url'] ?? 'https://example.com', '/');

        return match ($this) {
            self::Bienvenue => [
                'username' => 'Jean Dupont',
                'lienPlateforme' => $url . '/',
            ],
            self::SuppressionCompte => [
                'username' => 'Jean Dupont',
                'dateEffective' => date('d/m/Y à H:i'),
            ],
            self::ChangementMdp => [
                'username' => 'Jean Dupont',
                'dateChangement' => date('d/m/Y à H:i'),
                'ip' => '192.168.1.42',
            ],
            self::AlerteQuota80 => [
                'username' => 'Jean Dupont',
                'nomModule' => 'Keywords Forge',
                'usage' => 80,
                'limite' => 100,
                'pourcentage' => 80,
                'dateReset' => date('Y-m-d', strtotime('first day of next month')),
                'lienPlateforme' => $url . '/mon-compte',
            ],
            self::AlerteQuota100 => [
                'username' => 'Jean Dupont',
                'nomModule' => 'Keywords Forge',
                'usage' => 100,
                'limite' => 100,
                'dateReset' => date('Y-m-d', strtotime('first day of next month')),
                'lienPlateforme' => $url . '/mon-compte',
            ],
            self::ResetQuotas => [
                'username' => 'Jean Dupont',
                'moisPrecedent' => 'février 2026',
                'resumeModules' => [
                    ['nom' => 'Keywords Forge', 'usage' => 42, 'limite' => 100],
                    ['nom' => 'Google Suggest', 'usage' => 280, 'limite' => 500],
                    ['nom' => 'KG Entities', 'usage' => 15, 'limite' => 200],
                ],
                'lienPlateforme' => $url . '/',
            ],
            self::AdminNouvelInscrit => [
                'username' => 'Marie Martin',
                'email' => 'marie@example.com',
                'dateInscription' => date('d/m/Y à H:i'),
                'lienAdmin' => $url . '/admin/users',
            ],
            self::VerificationEmail => [
                'username' => 'Jean Dupont',
                'lien' => $url . '/verifier-email?token=exemple',
                'expiration' => 24,
            ],
            self::PasswordReset => [
                'username' => 'Jean Dupont',
                'lien' => $url . '/reinitialiser-mot-de-passe?token=exemple',
                'expiration' => 1,
            ],
        };
    }
}
