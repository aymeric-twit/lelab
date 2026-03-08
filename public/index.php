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
use Platform\Controller\DesabonnementController;
use Platform\Controller\LegalController;
use Platform\Controller\WebhookGithubController;
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
$compte = new \Platform\Controller\CompteController();

// -----------------------------------------------
// Public routes
// -----------------------------------------------

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

$router->group([new RequireAuth()], function (Router $r) use ($auth, $dashboard, $module, $compte, $quotaApi) {
    $r->get('/logout', [$auth, 'logout']);
    $r->get('/', [$dashboard, 'index']);

    // API quota pour plugins JS
    $r->get('/api/quota/{slug}', [$quotaApi, 'afficher']);

    // Mon compte (avec CSRF pour les POST)
    $r->get('/mon-compte', [$compte, 'afficher']);
    $r->group([new VerifyCsrf()], function (Router $r) use ($compte, $dashboard) {
        $r->post('/mon-compte', [$compte, 'mettreAJour']);
        $r->post('/mon-compte/mot-de-passe', [$compte, 'changerMotDePasse']);
        $r->post('/mon-compte/supprimer', [$compte, 'supprimerCompte']);
        $r->post('/api/notifications/toggle', [$dashboard, 'toggleNotifications']);
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

$router->group([new RequireAdmin(), new VerifyCsrf()], function (Router $r) use ($adminUser, $adminAccess, $adminQuota, $adminCategorie, $adminPlugin, $adminMaintenance, $adminAudit, $adminEmail) {
    $r->get('/admin/users', [$adminUser, 'index']);
    $r->get('/admin/users/create', [$adminUser, 'formulaireCreation']);
    $r->post('/admin/users/create', [$adminUser, 'creer']);
    $r->get('/admin/users/{id}/edit', [$adminUser, 'formulaireEdition']);
    $r->post('/admin/users/{id}/edit', [$adminUser, 'mettreAJour']);

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

    $r->get('/admin/maintenance', [$adminMaintenance, 'index']);
    $r->post('/admin/maintenance/dependances', [$adminMaintenance, 'installerDependances']);
    $r->post('/admin/maintenance/purge-usage', [$adminMaintenance, 'purgerUsage']);

    $r->get('/admin/audit', [$adminAudit, 'index']);
    $r->get('/admin/audit/export-csv', [$adminAudit, 'exporterCsv']);

    $r->get('/admin/emails', [$adminEmail, 'index']);
    $r->post('/admin/emails/smtp', [$adminEmail, 'sauvegarderSmtp']);
    $r->post('/admin/emails/test', [$adminEmail, 'envoyerTest']);
    $r->post('/admin/emails/notifications', [$adminEmail, 'sauvegarderNotifications']);
    $r->get('/admin/emails/apercu/{type}', [$adminEmail, 'apercuTemplate']);
    $r->get('/admin/emails/historique/export', [$adminEmail, 'exporterCsv']);
});

// -----------------------------------------------
// Webhook (public, sans CSRF ni auth — protégé par HMAC)
// -----------------------------------------------

$webhook = new WebhookGithubController();
$router->post('/webhook/github', [$webhook, 'handle']);

// -----------------------------------------------
// Dispatch
// -----------------------------------------------

$router->dispatch($request);
