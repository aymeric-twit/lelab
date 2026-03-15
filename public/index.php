<?php

/**
 * SEO Platform - Front Controller
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\App;
use Platform\Router;
use Platform\Http\Request;
use Platform\Http\Middleware\RequireAuth;
use Platform\Http\Middleware\RequireAdmin;
use Platform\Http\Middleware\VerifyCsrf;
use Platform\Http\Middleware\CheckModuleQuota;
use Platform\Http\Middleware\CheckPasswordReset;
use Platform\Module\ModuleRegistry;
use Platform\Controller\AuthController;
use Platform\Controller\DashboardController;
use Platform\Controller\ModuleController;
use Platform\Controller\AdminUserController;
use Platform\Controller\AdminAccessController;
use Platform\Controller\AdminQuotaController;
use Platform\Controller\AdminCategorieController;
use Platform\Controller\AdminPluginController;
use Platform\Controller\AdminMaintenanceController;
use Platform\Controller\AdminAuditController;
use Platform\Controller\AdminEmailController;
use Platform\Controller\AdminGroupController;
use Platform\Controller\AdminMigrationController;
use Platform\Controller\DesabonnementController;
use Platform\Controller\LegalController;
use Platform\Controller\WebhookGithubController;
use Platform\Controller\HealthController;
use Platform\Controller\Api\ApiUsersController;
use Platform\Controller\Api\ApiModulesController;
use Platform\Controller\Api\ApiStatsController;
use Platform\Http\Middleware\RequireApiKey;
use Platform\Http\Middleware\RateLimitApi;
use Platform\Database\Connection;

// Bootstrap
App::boot();

// Discover modules (embedded + external plugins from DB)
ModuleRegistry::discover(__DIR__ . '/../modules');
ModuleRegistry::chargerDepuisBase(Connection::get());

$router = new Router();
$request = new Request();

$auth = new AuthController();
$dashboard = new DashboardController();
$module = new ModuleController();
$adminUser = new AdminUserController();
$adminAccess = new AdminAccessController();
$adminQuota = new AdminQuotaController();
$adminCategorie = new AdminCategorieController();
$adminPlugin = new AdminPluginController();
$adminMaintenance = new AdminMaintenanceController();
$adminAudit = new AdminAuditController();
$adminEmail = new AdminEmailController();
$adminGroup = new AdminGroupController();
$compte = new \Platform\Controller\CompteController();
$notifController = new \Platform\Controller\NotificationController();
$onboarding = new \Platform\Controller\OnboardingController();
$marketplace = new \Platform\Controller\MarketplaceController();

// -----------------------------------------------
// Public routes
// -----------------------------------------------

// Health check (public, sans auth)
$health = new HealthController();
$router->get('/api/health', [$health, 'index']);

$router->get('/login', [$auth, 'formulaireLogin']);
$router->post('/login', [$auth, 'login']);
$router->get('/module-assets/{slug}/{file*}', [$module, 'assets']);

// Inscription
$router->get('/inscription', [$auth, 'formulaireInscription']);
$router->post('/inscription', [$auth, 'inscrire']);

// Mot de passe oublié
$router->get('/mot-de-passe-oublie', [$auth, 'formulaireMotDePasseOublie']);
$router->post('/mot-de-passe-oublie', [$auth, 'demanderReinitialisation']);
$router->get('/reinitialiser-mot-de-passe', [$auth, 'formulaireReinitialisation']);
$router->post('/reinitialiser-mot-de-passe', [$auth, 'reinitialiserMotDePasse']);

// Vérification email
$router->get('/verifier-email', [$auth, 'verifierEmail']);
$router->post('/renvoyer-verification', [$auth, 'renvoyerVerification']);

// Confirmation changement d'email (public, lien depuis l'email)
$router->get('/confirmer-email', [$compte, 'confirmerEmail']);

// Authentification à deux facteurs (public, sans auth — utilisateur en attente de 2FA)
$router->get('/2fa', [$auth, 'formulaire2fa']);
$router->post('/2fa', [$auth, 'verifier2fa']);

// Page tarifs (publique)
$tarifs = new \Platform\Controller\TarifsController();
$router->get('/tarifs', [$tarifs, 'index']);

// Pages légales (publiques)
$legal = new LegalController();
$router->get('/politique-de-confidentialite', [$legal, 'politiqueConfidentialite']);
$router->get('/mentions-legales', [$legal, 'mentionsLegales']);

// Désabonnement emails (public, sans auth)
$desabonnement = new DesabonnementController();
$router->get('/desabonnement', [$desabonnement, 'afficher']);
$router->post('/desabonnement', [$desabonnement, 'mettreAJour']);
$router->post('/desabonnement/tout', [$desabonnement, 'desabonnerTout']);

// -----------------------------------------------
// Authenticated routes
// -----------------------------------------------

$quotaApi = new \Platform\Controller\QuotaApiController();

$stripeAuth = new \Platform\Controller\StripeController();

$router->group([new RequireAuth(), new CheckPasswordReset()], function (Router $r) use ($auth, $dashboard, $module, $compte, $quotaApi, $notifController, $onboarding, $marketplace, $stripeAuth) {
    $r->get('/logout', [$auth, 'logout']);
    $r->get('/', [$dashboard, 'index']);

    // Paiement Stripe
    $r->get('/paiement/succes', [$stripeAuth, 'succes']);
    $r->get('/paiement/annulation', [$stripeAuth, 'annulation']);

    // Onboarding
    $r->get('/onboarding', [$onboarding, 'index']);

    // Marketplace
    $r->get('/marketplace', [$marketplace, 'index']);

    // API quota pour plugins JS
    $r->get('/api/quota/{slug}', [$quotaApi, 'afficher']);

    // Mon compte (avec CSRF pour les POST)
    $r->get('/mon-compte', [$compte, 'afficher']);
    $r->get('/mon-compte/2fa/activer', [$compte, 'activer2fa']);
    // Notifications in-app (AJAX)
    $r->get('/api/notifications', [$notifController, 'nonLues']);

    $r->group([new VerifyCsrf()], function (Router $r) use ($compte, $dashboard, $notifController, $onboarding, $stripeAuth) {
        $r->post('/mon-compte', [$compte, 'mettreAJour']);
        $r->post('/mon-compte/mot-de-passe', [$compte, 'changerMotDePasse']);
        $r->post('/mon-compte/supprimer', [$compte, 'supprimerCompte']);
        $r->post('/mon-compte/2fa/confirmer', [$compte, 'confirmer2fa']);
        $r->post('/mon-compte/2fa/desactiver', [$compte, 'desactiver2fa']);
        $r->post('/api/notifications/toggle', [$dashboard, 'toggleNotifications']);
        $r->post('/api/notifications/lire', [$notifController, 'marquerLue']);
        $r->post('/api/notifications/lire-tout', [$notifController, 'marquerToutesLues']);
        $r->post('/onboarding', [$onboarding, 'sauvegarder']);
        $r->post('/paiement/checkout', [$stripeAuth, 'checkout']);
    });

    // Demande d'accès module (CSRF, sans quota check)
    $r->group([new VerifyCsrf()], function (Router $r) use ($module) {
        $r->post('/m/{slug}/demander-acces', [$module, 'demanderAcces']);
    });

    // Module routes (with CSRF + quota check)
    $r->group([new VerifyCsrf(), new CheckModuleQuota()], function (Router $r) use ($module) {
        $r->any('/m/{slug}', [$module, 'afficher']);
        $r->any('/m/{slug}/{sub*}', [$module, 'sousRoute']);
    });
});

// -----------------------------------------------
// Admin routes
// -----------------------------------------------

$adminConfig = new \Platform\Controller\AdminConfigController();

$router->group([new RequireAdmin(), new VerifyCsrf()], function (Router $r) use ($adminUser, $adminAccess, $adminQuota, $adminCategorie, $adminPlugin, $adminMaintenance, $adminAudit, $adminEmail, $adminGroup, $adminConfig) {
    $r->get('/admin/users', [$adminUser, 'index']);
    $r->get('/admin/users/create', [$adminUser, 'formulaireCreation']);
    $r->post('/admin/users/create', [$adminUser, 'creer']);
    $r->get('/admin/users/{id}/edit', [$adminUser, 'formulaireEdition']);
    $r->post('/admin/users/{id}/edit', [$adminUser, 'mettreAJour']);
    $r->post('/admin/users/bulk-action', [$adminUser, 'actionGroupee']);
    $r->post('/admin/users/{id}/renvoyer-bienvenue', [$adminUser, 'renvoyerBienvenue']);
    $r->post('/admin/users/{id}/renvoyer-verification', [$adminUser, 'renvoyerVerification']);
    $r->get('/admin/users/{id}/details', [$adminUser, 'details']);

    $r->get('/admin/access', [$adminAccess, 'index']);
    $r->post('/admin/access', [$adminAccess, 'mettreAJour']);

    $r->get('/admin/quotas', [$adminQuota, 'index']);
    $r->post('/admin/quotas', [$adminQuota, 'mettreAJour']);
    $r->post('/admin/quotas/defauts', [$adminQuota, 'mettreAJourDefauts']);

    $r->get('/admin/categories', [$adminCategorie, 'index']);
    $r->get('/admin/categories/creer', [$adminCategorie, 'formulaireCreation']);
    $r->post('/admin/categories/creer', [$adminCategorie, 'creer']);
    $r->get('/admin/categories/{id}/editer', [$adminCategorie, 'formulaireEdition']);
    $r->post('/admin/categories/{id}/editer', [$adminCategorie, 'mettreAJour']);
    $r->post('/admin/categories/{id}/supprimer', [$adminCategorie, 'supprimer']);
    $r->post('/admin/categories/reordonner', [$adminCategorie, 'reordonner']);

    $r->get('/admin/plugins', [$adminPlugin, 'index']);
    $r->get('/admin/plugins/installer', [$adminPlugin, 'formulaireInstallation']);
    $r->post('/admin/plugins/detecter', [$adminPlugin, 'detecter']);
    $r->post('/admin/plugins/analyser-zip', [$adminPlugin, 'analyserZip']);
    $r->post('/admin/plugins/detecter-git', [$adminPlugin, 'detecterGit']);
    $r->post('/admin/plugins/branches-git', [$adminPlugin, 'listerBranchesGit']);
    $r->post('/admin/plugins/installer', [$adminPlugin, 'installer']);
    $r->get('/admin/plugins/{id}/editer', [$adminPlugin, 'formulaireEdition']);
    $r->post('/admin/plugins/{id}/editer', [$adminPlugin, 'mettreAJour']);
    $r->post('/admin/plugins/reordonner', [$adminPlugin, 'reordonnerPlugins']);
    $r->post('/admin/plugins/maj-git-tous', [$adminPlugin, 'mettreAJourTousGit']);
    $r->post('/admin/plugins/{id}/maj-git', [$adminPlugin, 'mettreAJourGit']);
    $r->post('/admin/plugins/{id}/basculer', [$adminPlugin, 'basculer']);
    $r->post('/admin/plugins/{id}/desinstaller', [$adminPlugin, 'desinstaller']);
    $r->post('/admin/plugins/cles-env', [$adminPlugin, 'mettreAJourCleEnv']);
    $r->post('/admin/plugins/api-credits', [$adminPlugin, 'apiCredits']);
    $r->post('/admin/plugins/api-credits-config', [$adminPlugin, 'sauvegarderCreditsApi']);

    // Configuration centralisée
    $r->get('/admin/configuration', [$adminConfig, 'index']);
    $r->post('/admin/configuration/smtp', [$adminConfig, 'sauvegarderSmtp']);
    $r->post('/admin/configuration/smtp/test', [$adminConfig, 'envoyerTestSmtp']);
    $r->post('/admin/configuration/google', [$adminConfig, 'sauvegarderGoogle']);
    $r->post('/admin/configuration/notifications', [$adminConfig, 'sauvegarderNotifications']);
    $r->post('/admin/configuration/securite', [$adminConfig, 'sauvegarderSecurite']);
    $r->post('/admin/configuration/general', [$adminConfig, 'sauvegarderGeneral']);
    $r->post('/admin/configuration/webhooks', [$adminConfig, 'creerWebhook']);
    $r->post('/admin/configuration/webhooks/{id}/editer', [$adminConfig, 'editerWebhook']);
    $r->post('/admin/configuration/webhooks/{id}/supprimer', [$adminConfig, 'supprimerWebhook']);
    $r->post('/admin/configuration/webhooks/{id}/tester', [$adminConfig, 'testerWebhook']);
    $r->post('/admin/configuration/api-keys', [$adminConfig, 'creerApiKey']);
    $r->post('/admin/configuration/api-keys/{id}/revoquer', [$adminConfig, 'revoquerApiKey']);
    $r->post('/admin/configuration/plans/module-credits', [$adminConfig, 'sauvegarderCreditsModules']);
    $r->post('/admin/configuration/plans/creer', [$adminConfig, 'creerPlan']);
    $r->post('/admin/configuration/plans/{id}/editer', [$adminConfig, 'editerPlan']);
    $r->post('/admin/configuration/plans/assigner', [$adminConfig, 'assignerPlan']);

    $r->get('/admin/maintenance', [$adminMaintenance, 'index']);
    $r->post('/admin/maintenance/dependances', [$adminMaintenance, 'installerDependances']);
    $r->post('/admin/maintenance/purge-usage', [$adminMaintenance, 'purgerUsage']);
    $r->post('/admin/maintenance/backup', [$adminMaintenance, 'creerBackup']);
    $r->get('/admin/maintenance/backup/{fichier}', [$adminMaintenance, 'telechargerBackup']);
    $r->post('/admin/maintenance/purge-logs', [$adminMaintenance, 'purgerLogs']);
    $r->post('/admin/maintenance/purge-credits', [$adminMaintenance, 'purgerCredits']);

    // Export utilisateurs
    $r->get('/admin/users/export-csv', [$adminUser, 'exporterCsv']);

    $r->get('/admin/audit', [$adminAudit, 'index']);
    $r->get('/admin/audit/export-csv', [$adminAudit, 'exporterCsv']);
    $r->post('/admin/audit/purge', [$adminAudit, 'purger']);

    $r->get('/admin/emails', [$adminEmail, 'index']);
    $r->post('/admin/emails/smtp', [$adminEmail, 'sauvegarderSmtp']);
    $r->post('/admin/emails/test', [$adminEmail, 'envoyerTest']);
    $r->post('/admin/emails/notifications', [$adminEmail, 'sauvegarderNotifications']);
    $r->get('/admin/emails/apercu/{type}', [$adminEmail, 'apercuTemplate']);
    $r->get('/admin/emails/historique/export', [$adminEmail, 'exporterCsv']);

    $r->get('/admin/groups', [$adminGroup, 'index']);
    $r->get('/admin/groups/create', [$adminGroup, 'formulaireCreation']);
    $r->post('/admin/groups/create', [$adminGroup, 'creer']);
    $r->get('/admin/groups/{id}/edit', [$adminGroup, 'formulaireEdition']);
    $r->post('/admin/groups/{id}/edit', [$adminGroup, 'mettreAJour']);
    $r->post('/admin/groups/{id}/supprimer', [$adminGroup, 'supprimer']);

    $adminMigration = new AdminMigrationController();
    $r->get('/admin/migrations', [$adminMigration, 'index']);
    $r->post('/admin/migrations', [$adminMigration, 'index']);
});

// -----------------------------------------------
// API v1 (auth par clé API, rate limited)
// -----------------------------------------------

$apiUsers = new ApiUsersController();
$apiModules = new ApiModulesController();
$apiStats = new ApiStatsController();

$router->group([new RateLimitApi(60, 60), new RequireApiKey()], function (Router $r) use ($apiUsers, $apiModules, $apiStats) {
    // Users (admin only)
    $r->get('/api/v1/users', [$apiUsers, 'index']);
    $r->get('/api/v1/users/{id}', [$apiUsers, 'show']);

    // Modules
    $r->get('/api/v1/modules', [$apiModules, 'index']);
    $r->get('/api/v1/modules/{slug}', [$apiModules, 'show']);

    // Stats
    $r->get('/api/v1/stats/usage', [$apiStats, 'usage']);
    $r->get('/api/v1/stats/quotas', [$apiStats, 'quotas']);
});

// -----------------------------------------------
// Webhook (public, sans CSRF ni auth — protégé par HMAC)
// -----------------------------------------------

$webhook = new WebhookGithubController();
$router->post('/webhook/github', [$webhook, 'handle']);

// Webhook Stripe (public, sans CSRF ni auth — protégé par signature Stripe)
$stripeController = new \Platform\Controller\StripeController();
$router->post('/webhook/stripe', [$stripeController, 'webhook']);

// -----------------------------------------------
// Dispatch
// -----------------------------------------------

$router->dispatch($request);
