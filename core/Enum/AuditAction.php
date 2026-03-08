<?php

namespace Platform\Enum;

enum AuditAction: string
{
    case LoginSuccess = 'login.success';
    case LoginFailed = 'login.failed';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';
    case AccessUpdate = 'access.update';
    case QuotasUpdate = 'quotas.update';
    case PluginInstall = 'plugin.install';
    case PluginUpdate = 'plugin.update';
    case PluginUninstall = 'plugin.uninstall';
    case ModuleUse = 'module.use';
    case Inscription = 'inscription';
    case PasswordResetRequest = 'password_reset.request';
    case PasswordResetComplete = 'password_reset.complete';
    case EmailVerified = 'email.verified';
    case AccountDelete = 'account.delete';
    case EmailConfigUpdate = 'email.config.update';

    public function label(): string
    {
        return match ($this) {
            self::LoginSuccess         => 'Connexion',
            self::LoginFailed          => 'Tentative de connexion échouée',
            self::UserCreate           => 'Utilisateur créé',
            self::UserUpdate           => 'Utilisateur modifié',
            self::UserDelete           => 'Utilisateur supprimé',
            self::AccessUpdate         => 'Accès mis à jour',
            self::QuotasUpdate         => 'Quotas mis à jour',
            self::PluginInstall        => 'Plugin installé',
            self::PluginUpdate         => 'Plugin mis à jour',
            self::PluginUninstall      => 'Plugin désinstallé',
            self::ModuleUse            => 'Utilisation plugin',
            self::Inscription          => 'Inscription',
            self::PasswordResetRequest => 'Demande de réinitialisation',
            self::PasswordResetComplete => 'Mot de passe réinitialisé',
            self::EmailVerified        => 'Email vérifié',
            self::AccountDelete        => 'Suppression de compte',
            self::EmailConfigUpdate    => 'Configuration email modifiée',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::LoginSuccess         => 'bi-box-arrow-in-right text-success',
            self::LoginFailed          => 'bi-x-circle text-danger',
            self::UserCreate           => 'bi-person-plus text-primary',
            self::UserUpdate           => 'bi-pencil text-info',
            self::UserDelete           => 'bi-person-dash text-danger',
            self::AccessUpdate         => 'bi-shield-check text-warning',
            self::QuotasUpdate         => 'bi-speedometer2 text-warning',
            self::PluginInstall        => 'bi-download text-success',
            self::PluginUpdate         => 'bi-arrow-repeat text-info',
            self::PluginUninstall      => 'bi-trash text-danger',
            self::ModuleUse            => 'bi-play-circle text-primary',
            self::Inscription          => 'bi-person-plus-fill text-success',
            self::PasswordResetRequest => 'bi-key text-warning',
            self::PasswordResetComplete => 'bi-key-fill text-success',
            self::EmailVerified        => 'bi-envelope-check text-success',
            self::AccountDelete        => 'bi-person-x text-danger',
            self::EmailConfigUpdate    => 'bi-envelope-gear text-info',
        };
    }
}
