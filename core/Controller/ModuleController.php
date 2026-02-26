<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Enum\Role;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\ModuleRegistry;
use Platform\Module\ModuleRenderer;
use Platform\Module\Quota;
use Platform\User\AccessControl;
use Platform\View\Layout;

class ModuleController
{
    public function afficher(Request $req, array $params): void
    {
        $user = Auth::user();
        $slug = $params['slug'];
        $role = Role::tryFrom($user['role']) ?? Role::User;

        $ac = new AccessControl();
        if ($role !== Role::Admin && !$ac->hasAccess($user['id'], $slug)) {
            Response::abort(403);
        }

        $module = ModuleRegistry::get($slug);
        if (!$module) {
            Response::abort(404);
        }

        // Mode passthrough : le module gère tout, pas de layout
        if ($module->modeAffichage->estPassthrough()) {
            ModuleRenderer::passthrough($module, $module->entryPoint);
            return;
        }

        $modules = $ac->getAccessibleModules($user['id']);
        $quotaSummary = Quota::getUserQuotaSummary($user['id']);

        // Mode iframe : le contenu est un iframe, le layout parent reste
        if ($module->modeAffichage->estIframe()) {
            $result = ModuleRenderer::renderIframe($module);
            Layout::render('layout', [
                'content'           => $result['content'],
                'headExtra'         => $result['headExtra'],
                'footExtra'         => $result['footExtra'],
                'pageTitle'         => $module->name,
                'currentUser'       => $user,
                'accessibleModules' => $modules,
                'activeModule'      => $slug,
                'quotaSummary'      => $quotaSummary,
                'modeIframe'        => true,
            ]);
            return;
        }

        // Mode embedded : extractParts classique
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
    }

    public function sousRoute(Request $req, array $params): void
    {
        $user = Auth::user();
        $slug = $params['slug'];
        $sub = $params['sub'];
        $role = Role::tryFrom($user['role']) ?? Role::User;

        $ac = new AccessControl();
        if ($role !== Role::Admin && !$ac->hasAccess($user['id'], $slug)) {
            Response::abort(403);
        }

        $module = ModuleRegistry::get($slug);
        if (!$module) {
            Response::abort(404);
        }

        // Mode passthrough : tout passe directement
        if ($module->modeAffichage->estPassthrough()) {
            ModuleRenderer::passthrough($module, $module->entryPoint);
            return;
        }

        // Mode iframe : _app sert l'app complète, les autres sous-routes aussi
        if ($module->modeAffichage->estIframe()) {
            if ($sub === '_app') {
                ModuleRenderer::servirApp($module);
            } else {
                ModuleRenderer::servirApp($module, $sub);
            }
            return;
        }

        // Mode embedded : sous-routes ajax/stream en passthrough, pages en layout
        $routeType = $module->getRouteType($sub);
        if ($routeType->estPassthrough()) {
            ModuleRenderer::passthrough($module, $sub);
            return;
        }

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

    public function assets(Request $req, array $params): void
    {
        $slug = $params['slug'];
        $file = $params['file'];

        $module = ModuleRegistry::get($slug);
        if (!$module) {
            Response::abort(404);
        }

        $filePath = realpath($module->path . '/' . $file);
        $modulePath = realpath($module->path);

        if (!$filePath || !$modulePath || !str_starts_with($filePath, $modulePath)) {
            Response::abort(404);
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'map'   => 'application/json',
        ];

        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }
}
