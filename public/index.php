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

// -----------------------------------------------
// Public routes
// -----------------------------------------------

$router->get('/login', [$auth, 'formulaireLogin']);
$router->post('/login', [$auth, 'login']);
$router->get('/module-assets/{slug}/{file*}', [$module, 'assets']);

// -----------------------------------------------
// Authenticated routes
// -----------------------------------------------

$router->group([new RequireAuth()], function (Router $r) use ($auth, $dashboard, $module) {
    $r->get('/logout', [$auth, 'logout']);
    $r->get('/', [$dashboard, 'index']);

    // Module routes (with quota check)
    $r->group([new CheckModuleQuota()], function (Router $r) use ($module) {
        $r->any('/m/{slug}', [$module, 'afficher']);
        $r->any('/m/{slug}/{sub*}', [$module, 'sousRoute']);
    });
});

// -----------------------------------------------
// Admin routes
// -----------------------------------------------

$router->group([new RequireAdmin(), new VerifyCsrf()], function (Router $r) use ($adminUser, $adminAccess, $adminQuota, $adminCategorie, $adminPlugin) {
    $r->get('/admin/users', [$adminUser, 'index']);
    $r->get('/admin/users/create', [$adminUser, 'formulaireCreation']);
    $r->post('/admin/users/create', [$adminUser, 'creer']);
    $r->get('/admin/users/{id}/edit', [$adminUser, 'formulaireEdition']);
    $r->post('/admin/users/{id}/edit', [$adminUser, 'mettreAJour']);

    $r->get('/admin/access', [$adminAccess, 'index']);
    $r->post('/admin/access', [$adminAccess, 'mettreAJour']);

    $r->get('/admin/quotas', [$adminQuota, 'index']);
    $r->post('/admin/quotas', [$adminQuota, 'mettreAJour']);

    $r->get('/admin/categories', [$adminCategorie, 'index']);
    $r->get('/admin/categories/creer', [$adminCategorie, 'formulaireCreation']);
    $r->post('/admin/categories/creer', [$adminCategorie, 'creer']);
    $r->get('/admin/categories/{id}/editer', [$adminCategorie, 'formulaireEdition']);
    $r->post('/admin/categories/{id}/editer', [$adminCategorie, 'mettreAJour']);
    $r->post('/admin/categories/{id}/supprimer', [$adminCategorie, 'supprimer']);

    $r->get('/admin/plugins', [$adminPlugin, 'index']);
    $r->get('/admin/plugins/installer', [$adminPlugin, 'formulaireInstallation']);
    $r->post('/admin/plugins/detecter', [$adminPlugin, 'detecter']);
    $r->post('/admin/plugins/analyser-zip', [$adminPlugin, 'analyserZip']);
    $r->post('/admin/plugins/installer', [$adminPlugin, 'installer']);
    $r->get('/admin/plugins/{id}/editer', [$adminPlugin, 'formulaireEdition']);
    $r->post('/admin/plugins/{id}/editer', [$adminPlugin, 'mettreAJour']);
    $r->post('/admin/plugins/{id}/basculer', [$adminPlugin, 'basculer']);
    $r->post('/admin/plugins/{id}/desinstaller', [$adminPlugin, 'desinstaller']);
});

// -----------------------------------------------
// Dispatch
// -----------------------------------------------

$router->dispatch($request);
