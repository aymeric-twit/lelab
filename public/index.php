<?php

/**
 * SEO Platform - Front Controller
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\App;
use Platform\Router;
use Platform\Auth\Auth;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Http\Csrf;
use Platform\View\Flash;
use Platform\View\Layout;
use Platform\User\UserRepository;
use Platform\User\AccessControl;
use Platform\Module\ModuleRegistry;
use Platform\Module\ModuleRenderer;
use Platform\Module\Quota;
use Platform\Auth\PasswordHasher;
use Platform\Database\Connection;

// Bootstrap
App::boot();

// Discover modules
ModuleRegistry::discover(__DIR__ . '/../modules');

$router = new Router();
$request = new Request();

// -----------------------------------------------
// Public routes
// -----------------------------------------------

$router->get('/login', function () {
    if (Auth::check()) {
        Response::redirect('/');
    }
    Layout::renderStandalone('login');
});

$router->post('/login', function (Request $req) {
    Csrf::validateOrAbort();

    $ip = $req->ip();
    $username = trim($req->post('username', ''));
    $password = $req->post('password', '');

    // Rate limiting
    if (Auth::isRateLimited($ip)) {
        Flash::error('Trop de tentatives. Reessayez dans 15 minutes.');
        Response::redirect('/login');
    }

    if (Auth::attempt($username, $password)) {
        Auth::logAttempt('login.success', $ip, Auth::id(), $username);
        Response::redirect('/');
    }

    Auth::logAttempt('login.failed', $ip, null, $username);
    Flash::error('Identifiants incorrects.');
    Response::redirect('/login');
});

// -----------------------------------------------
// Module assets (public, no auth)
// -----------------------------------------------

$router->get('/module-assets/{slug}/{file*}', function (Request $req, array $params) {
    $slug = $params['slug'];
    $file = $params['file'];

    $module = ModuleRegistry::get($slug);
    if (!$module) {
        Response::abort(404);
    }

    $filePath = realpath($module->path . '/' . $file);
    $modulePath = realpath($module->path);

    // Security: prevent directory traversal
    if (!$filePath || !$modulePath || !str_starts_with($filePath, $modulePath)) {
        Response::abort(404);
    }

    // Determine MIME type
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
        'map'  => 'application/json',
    ];

    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($filePath);
    exit;
});

// -----------------------------------------------
// Authenticated routes
// -----------------------------------------------

$router->get('/logout', function () {
    Auth::logout();
    Response::redirect('/login');
});

// Dashboard
$router->get('/', function () {
    Auth::requireAuth();
    $user = Auth::user();
    $ac = new AccessControl();
    $modules = $ac->getAccessibleModules($user['id']);
    $quotaSummary = Quota::getUserQuotaSummary($user['id']);

    Layout::render('layout', [
        'template'          => 'dashboard',
        'pageTitle'         => 'Dashboard',
        'currentUser'       => $user,
        'accessibleModules' => $modules,
        'activeModule'      => '',
        'quotaSummary'      => $quotaSummary,
    ]);
});

// Module main page
$router->any('/m/{slug}', function (Request $req, array $params) {
    Auth::requireAuth();
    $user = Auth::user();
    $slug = $params['slug'];

    $ac = new AccessControl();
    if ($user['role'] !== 'admin' && !$ac->hasAccess($user['id'], $slug)) {
        Response::abort(403);
    }

    $module = ModuleRegistry::get($slug);
    if (!$module) {
        Response::abort(404);
    }

    // Passthrough modules handle their own rendering (e.g. search-console)
    if ($module->passthroughAll) {
        ModuleRenderer::passthrough($module, $module->entryPoint);
        return;
    }

    // Quota check (non-admin only)
    $quotaMode = $module->quotaMode;
    if ($user['role'] !== 'admin' && $quotaMode !== 'none') {
        $shouldCheck = ($quotaMode === 'request')
            || ($quotaMode === 'form_submit' && $req->method() === 'POST');

        if ($shouldCheck && Quota::isOverQuota($user['id'], $slug)) {
            $quotaSummary = Quota::getUserQuotaSummary($user['id']);
            $modules = $ac->getAccessibleModules($user['id']);
            Layout::render('layout', [
                'template'          => 'quota-exceeded',
                'pageTitle'         => 'Quota depasse',
                'currentUser'       => $user,
                'accessibleModules' => $modules,
                'activeModule'      => $slug,
                'quotaSummary'      => $quotaSummary,
                'moduleSlug'        => $slug,
                'moduleName'        => $module->name,
                'quotaUsage'        => Quota::getUsage($user['id'], $slug),
                'quotaLimit'        => Quota::getLimit($user['id'], $slug),
            ]);
            return;
        }

        // Auto-increment for request and form_submit modes
        if ($shouldCheck) {
            Quota::increment($user['id'], $slug);
        }
    }

    $modules = $ac->getAccessibleModules($user['id']);
    $quotaSummary = Quota::getUserQuotaSummary($user['id']);
    $result = ModuleRenderer::render($module);

    Layout::render('layout', [
        'content'           => $result['content'],
        'headExtra'         => $result['headExtra'] . "\n<script>window.MODULE_BASE_URL='/m/{$slug}';</script>",
        'footExtra'         => $result['footExtra'],
        'pageTitle'         => $module->name,
        'currentUser'       => $user,
        'accessibleModules' => $modules,
        'activeModule'      => $slug,
        'quotaSummary'      => $quotaSummary,
    ]);
});

// Module sub-routes (AJAX, SSE, etc.)
$router->any('/m/{slug}/{sub*}', function (Request $req, array $params) {
    Auth::requireAuth();
    $user = Auth::user();
    $slug = $params['slug'];
    $sub = $params['sub'];

    $ac = new AccessControl();
    if ($user['role'] !== 'admin' && !$ac->hasAccess($user['id'], $slug)) {
        Response::abort(403);
    }

    $module = ModuleRegistry::get($slug);
    if (!$module) {
        Response::abort(404);
    }

    // Passthrough modules handle their own routing (e.g. search-console)
    if ($module->passthroughAll) {
        ModuleRenderer::passthrough($module, $module->entryPoint);
        return;
    }

    // Quota check on sub-routes (non-admin only)
    $quotaMode = $module->quotaMode;
    if ($user['role'] !== 'admin' && $quotaMode !== 'none') {
        $shouldCheck = ($quotaMode === 'request')
            || ($quotaMode === 'form_submit' && $req->method() === 'POST');

        if ($shouldCheck && Quota::isOverQuota($user['id'], $slug)) {
            if ($req->isAjax()) {
                Response::json(['error' => 'Quota depasse', 'quota_exceeded' => true], 429);
            }
            $quotaSummary = Quota::getUserQuotaSummary($user['id']);
            $modules = $ac->getAccessibleModules($user['id']);
            Layout::render('layout', [
                'template'          => 'quota-exceeded',
                'pageTitle'         => 'Quota depasse',
                'currentUser'       => $user,
                'accessibleModules' => $modules,
                'activeModule'      => $slug,
                'quotaSummary'      => $quotaSummary,
                'moduleSlug'        => $slug,
                'moduleName'        => $module->name,
                'quotaUsage'        => Quota::getUsage($user['id'], $slug),
                'quotaLimit'        => Quota::getLimit($user['id'], $slug),
            ]);
            return;
        }

        if ($shouldCheck) {
            Quota::increment($user['id'], $slug);
        }
    }

    // Determine if this is a passthrough route (AJAX/SSE)
    $routeType = $module->getRouteType($sub);
    if ($routeType === 'ajax' || $routeType === 'stream') {
        ModuleRenderer::passthrough($module, $sub);
    } else {
        // Render within the platform layout
        $modules = $ac->getAccessibleModules($user['id']);
        $quotaSummary = Quota::getUserQuotaSummary($user['id']);
        $result = ModuleRenderer::render($module, $sub);

        Layout::render('layout', [
            'content'           => $result['content'],
            'headExtra'         => $result['headExtra'] . "\n<script>window.MODULE_BASE_URL='/m/{$slug}';</script>",
            'footExtra'         => $result['footExtra'],
            'pageTitle'         => $module->name,
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => $slug,
            'quotaSummary'      => $quotaSummary,
        ]);
    }
});

// -----------------------------------------------
// Admin routes
// -----------------------------------------------

$router->get('/admin/users', function () {
    Auth::requireAdmin();
    $user = Auth::user();
    $repo = new UserRepository();
    $ac = new AccessControl();

    Layout::render('layout', [
        'template'          => 'admin/users',
        'pageTitle'         => 'Utilisateurs',
        'currentUser'       => $user,
        'accessibleModules' => $ac->getAccessibleModules($user['id']),
        'adminPage'         => 'users',
        'users'             => $repo->findAll(),
    ]);
});

$router->get('/admin/users/create', function () {
    Auth::requireAdmin();
    $user = Auth::user();
    $ac = new AccessControl();
    $db = Connection::get();
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();

    Layout::render('layout', [
        'template'          => 'admin/user-form',
        'pageTitle'         => 'Nouvel utilisateur',
        'currentUser'       => $user,
        'accessibleModules' => $ac->getAccessibleModules($user['id']),
        'adminPage'         => 'users',
        'modules'           => $modules,
    ]);
});

$router->post('/admin/users/create', function (Request $req) {
    Auth::requireAdmin();
    Csrf::validateOrAbort();

    $repo = new UserRepository();
    $username = trim($req->post('username', ''));
    $email = trim($req->post('email', ''));
    $password = $req->post('password', '');
    $role = $req->post('role', 'user');
    $active = $req->post('active') ? 1 : 0;

    if (empty($username) || empty($password)) {
        Flash::error('Nom d\'utilisateur et mot de passe requis.');
        Response::redirect('/admin/users/create');
    }

    if ($repo->findByUsername($username)) {
        Flash::error('Ce nom d\'utilisateur existe deja.');
        Response::redirect('/admin/users/create');
    }

    $userId = $repo->create([
        'username'      => $username,
        'email'         => $email ?: null,
        'password_hash' => PasswordHasher::hash($password),
        'role'          => in_array($role, ['admin', 'user']) ? $role : 'user',
        'active'        => $active,
    ]);

    // Access & Quotas
    $db = Connection::get();
    $ac = new AccessControl();
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
    $accessData = $req->post('access') ?? [];
    $quotaData = $req->post('quotas') ?? [];

    foreach ($modules as $mod) {
        $modId = (int) $mod['id'];
        $granted = !empty($accessData[$modId]);
        $ac->setAccess($userId, $modId, $granted, Auth::id());

        $quotaVal = $quotaData[$modId] ?? '';
        if ($quotaVal !== '') {
            Quota::setLimit($userId, $modId, (int) $quotaVal, Auth::id());
        }
    }

    // Log
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, target_type, target_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([Auth::id(), 'user.create', 'user', $userId, $_SERVER['REMOTE_ADDR'] ?? '']);

    Flash::success('Utilisateur cree avec succes.');
    Response::redirect('/admin/users');
});

$router->get('/admin/users/{id}/edit', function (Request $req, array $params) {
    Auth::requireAdmin();
    $user = Auth::user();
    $ac = new AccessControl();
    $repo = new UserRepository();
    $db = Connection::get();
    $editUser = $repo->findById((int) $params['id']);

    if (!$editUser) {
        Response::abort(404);
    }

    $editUserId = (int) $editUser['id'];
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
    $accessMatrix = $ac->getAccessMatrix();
    $quotaMatrix = Quota::getQuotaMatrix();

    $userAccess = $accessMatrix[$editUserId] ?? [];
    $userQuotas = $quotaMatrix[$editUserId] ?? [];

    Layout::render('layout', [
        'template'          => 'admin/user-form',
        'pageTitle'         => 'Modifier ' . $editUser['username'],
        'currentUser'       => $user,
        'accessibleModules' => $ac->getAccessibleModules($user['id']),
        'adminPage'         => 'users',
        'editUser'          => $editUser,
        'modules'           => $modules,
        'userAccess'        => $userAccess,
        'userQuotas'        => $userQuotas,
    ]);
});

$router->post('/admin/users/{id}/edit', function (Request $req, array $params) {
    Auth::requireAdmin();
    Csrf::validateOrAbort();

    $repo = new UserRepository();
    $id = (int) $params['id'];
    $editUser = $repo->findById($id);

    if (!$editUser) {
        Response::abort(404);
    }

    $data = [
        'username' => trim($req->post('username', '')),
        'email'    => trim($req->post('email', '')) ?: null,
        'role'     => in_array($req->post('role'), ['admin', 'user']) ? $req->post('role') : 'user',
        'active'   => $req->post('active') ? 1 : 0,
    ];

    $password = $req->post('password', '');
    if (!empty($password)) {
        $data['password_hash'] = PasswordHasher::hash($password);
    }

    $repo->update($id, $data);

    // Access & Quotas
    $db = Connection::get();
    $ac = new AccessControl();
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
    $accessData = $req->post('access') ?? [];
    $quotaData = $req->post('quotas') ?? [];

    foreach ($modules as $mod) {
        $modId = (int) $mod['id'];
        $granted = !empty($accessData[$modId]);
        $ac->setAccess($id, $modId, $granted, Auth::id());

        $quotaVal = $quotaData[$modId] ?? '';
        if ($quotaVal !== '') {
            Quota::setLimit($id, $modId, (int) $quotaVal, Auth::id());
        }
    }

    // Log
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, target_type, target_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([Auth::id(), 'user.update', 'user', $id, $_SERVER['REMOTE_ADDR'] ?? '']);

    Flash::success('Utilisateur mis a jour.');
    Response::redirect('/admin/users');
});

$router->get('/admin/access', function () {
    Auth::requireAdmin();
    $user = Auth::user();
    $ac = new AccessControl();
    $repo = new UserRepository();
    $db = Connection::get();

    $users = $repo->findAll();
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
    $matrix = $ac->getAccessMatrix();

    Layout::render('layout', [
        'template'          => 'admin/user-access',
        'pageTitle'         => 'Matrice d\'acces',
        'currentUser'       => $user,
        'accessibleModules' => $ac->getAccessibleModules($user['id']),
        'adminPage'         => 'access',
        'users'             => $users,
        'modules'           => $modules,
        'matrix'            => $matrix,
    ]);
});

$router->post('/admin/access', function (Request $req) {
    Auth::requireAdmin();
    Csrf::validateOrAbort();

    $ac = new AccessControl();
    $db = Connection::get();
    $repo = new UserRepository();

    $users = $repo->findAll();
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
    $accessData = $req->post('access') ?? [];

    foreach ($users as $u) {
        foreach ($modules as $mod) {
            $granted = !empty($accessData[$u['id']][$mod['id']]);
            $ac->setAccess($u['id'], $mod['id'], $granted, Auth::id());
        }
    }

    // Log
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, target_type, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([Auth::id(), 'access.update', 'matrix', json_encode(['updated' => true]), $_SERVER['REMOTE_ADDR'] ?? '']);

    Flash::success('Acces mis a jour.');
    Response::redirect('/admin/access');
});

// Admin - Quotas matrix
$router->get('/admin/quotas', function () {
    Auth::requireAdmin();
    $user = Auth::user();
    $ac = new AccessControl();
    $repo = new UserRepository();
    $db = Connection::get();

    $users = $repo->findAll();
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
    $matrix = Quota::getQuotaMatrix();

    Layout::render('layout', [
        'template'          => 'admin/quotas',
        'pageTitle'         => 'Quotas',
        'currentUser'       => $user,
        'accessibleModules' => $ac->getAccessibleModules($user['id']),
        'adminPage'         => 'quotas',
        'users'             => $users,
        'modules'           => $modules,
        'matrix'            => $matrix,
    ]);
});

$router->post('/admin/quotas', function (Request $req) {
    Auth::requireAdmin();
    Csrf::validateOrAbort();

    $db = Connection::get();
    $repo = new UserRepository();

    $users = $repo->findAll();
    $modules = $db->query('SELECT * FROM modules WHERE enabled = 1')->fetchAll();
    $quotaData = $req->post('quotas') ?? [];

    foreach ($users as $u) {
        foreach ($modules as $mod) {
            $value = $quotaData[$u['id']][$mod['id']] ?? '';
            if ($value !== '') {
                Quota::setLimit((int) $u['id'], (int) $mod['id'], (int) $value, Auth::id());
            }
        }
    }

    // Log
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, target_type, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([Auth::id(), 'quotas.update', 'matrix', json_encode(['updated' => true]), $_SERVER['REMOTE_ADDR'] ?? '']);

    Flash::success('Quotas mis a jour.');
    Response::redirect('/admin/quotas');
});

// -----------------------------------------------
// Dispatch
// -----------------------------------------------

$router->dispatch($request);
