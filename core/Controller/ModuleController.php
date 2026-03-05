<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Enum\Role;
use Platform\Enum\RouteType;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Log\Logger;
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
            Response::abortAvecPage(403);
        }

        $module = ModuleRegistry::get($slug);
        if (!$module) {
            Response::abortAvecPage(404);
        }

        // Mode passthrough : le module gère tout, pas de layout
        if ($module->modeAffichage->estPassthrough()) {
            ModuleRenderer::passthrough($module, $module->entryPoint);
            return;
        }

        // Redirection trailing slash pour embedded (GET uniquement)
        // /m/suggest → 301 → /m/suggest/
        // Filet de sécurité : fetch('process.php') se résout vers /m/suggest/process.php
        if ($module->modeAffichage->estEmbedded() && $req->method() === 'GET') {
            $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
            if (!str_ends_with($rawPath, '/')) {
                $queryString = $_SERVER['QUERY_STRING'] ?? '';
                $destination = $rawPath . '/' . ($queryString !== '' ? '?' . $queryString : '');
                Response::redirect($destination, 301);
            }
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

        // Langue active : query param ?lg= > fallback 'fr' (localStorage géré côté JS)
        $langueActive = $_GET['lg'] ?? 'fr';
        if (!in_array($langueActive, $module->langues, true)) {
            $langueActive = $module->langues[0] ?? 'fr';
        }

        $baseUrlJs = json_encode('/m/' . $slug, JSON_UNESCAPED_SLASHES);
        $scriptLang = "<script>window.MODULE_BASE_URL={$baseUrlJs};";
        if (!empty($module->langues)) {
            $languesJson = json_encode($module->langues);
            $langueActiveJs = htmlspecialchars($langueActive, ENT_QUOTES, 'UTF-8');
            $scriptLang .= "window.PLATFORM_LANG='{$langueActiveJs}';window.MODULE_LANGUAGES={$languesJson};";
        }
        $scriptLang .= '</script>';

        $scriptLang .= self::genererScriptDomaine($user, $module);

        Layout::render('layout', [
            'content'           => $result['content'],
            'headExtra'         => $result['headExtra'] . "\n" . $scriptLang,
            'footExtra'         => $result['footExtra'],
            'pageTitle'         => $module->name,
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => $slug,
            'quotaSummary'      => $quotaSummary,
            'moduleLangages'    => $module->langues,
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
            Response::abortAvecPage(403);
        }

        $module = ModuleRegistry::get($slug);
        if (!$module) {
            Response::abortAvecPage(404);
        }

        // Mode passthrough : tout passe directement
        if ($module->modeAffichage->estPassthrough()) {
            ModuleRenderer::passthrough($module, $module->entryPoint);
            return;
        }

        // Mode iframe : assets statiques, _app, ou sous-routes PHP
        if ($module->modeAffichage->estIframe()) {
            // Servir les fichiers statiques (js, css, images) avec le bon Content-Type
            if (ModuleRenderer::servirAssetStatique($module, $sub)) {
                return;
            }

            if ($sub === '_app') {
                ModuleRenderer::servirApp($module);
            } else {
                ModuleRenderer::servirApp($module, $sub);
            }
            return;
        }

        // Mode embedded : sous-routes ajax/stream en passthrough, pages en layout
        $routeType = $module->getRouteType($sub);

        // Fallback : si la route n'est pas déclarée, détecter automatiquement
        // les requêtes AJAX/SSE pour éviter de wrapper du JSON dans le layout HTML
        if (!$module->hasSubRoute($sub)) {
            if ($req->estRequeteAjax()) {
                $routeType = RouteType::Ajax;
                Logger::warning('Route non déclarée traitée comme AJAX', [
                    'module' => $module->slug,
                    'sousRoute' => $sub,
                    'signal' => $this->detecterSignalAjax($req),
                ]);
            } elseif ($req->estRequeteSSE()) {
                $routeType = RouteType::Stream;
                Logger::warning('Route non déclarée traitée comme SSE', [
                    'module' => $module->slug,
                    'sousRoute' => $sub,
                ]);
            }
        }

        if ($routeType->estPassthrough()) {
            ModuleRenderer::passthrough($module, $sub);
            return;
        }

        $modules = $ac->getAccessibleModules($user['id']);
        $quotaSummary = Quota::getUserQuotaSummary($user['id']);
        $result = ModuleRenderer::render($module, $sub);

        $langueActive = $_GET['lg'] ?? 'fr';
        if (!in_array($langueActive, $module->langues, true)) {
            $langueActive = $module->langues[0] ?? 'fr';
        }

        $baseUrlJs = json_encode('/m/' . $slug, JSON_UNESCAPED_SLASHES);
        $scriptLang = "<script>window.MODULE_BASE_URL={$baseUrlJs};";
        if (!empty($module->langues)) {
            $languesJson = json_encode($module->langues);
            $langueActiveJs = htmlspecialchars($langueActive, ENT_QUOTES, 'UTF-8');
            $scriptLang .= "window.PLATFORM_LANG='{$langueActiveJs}';window.MODULE_LANGUAGES={$languesJson};";
        }
        $scriptLang .= '</script>';

        $scriptLang .= self::genererScriptDomaine($user, $module);

        Layout::render('layout', [
            'content'           => $result['content'],
            'headExtra'         => $result['headExtra'] . "\n" . $scriptLang,
            'footExtra'         => $result['footExtra'],
            'pageTitle'         => $module->name,
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => $slug,
            'quotaSummary'      => $quotaSummary,
            'moduleLangages'    => $module->langues,
        ]);
    }

    /**
     * Identifie le signal HTTP ayant déclenché la détection AJAX (pour les logs).
     */
    private function detecterSignalAjax(Request $req): string
    {
        if ($req->isAjax()) {
            return 'X-Requested-With: XMLHttpRequest';
        }

        $accept = $req->header('Accept') ?? '';
        if (str_contains($accept, 'application/json')) {
            return 'Accept: application/json';
        }

        $contentType = $req->header('Content-Type') ?? '';
        if (str_contains($contentType, 'application/json')) {
            return 'Content-Type: application/json';
        }

        return 'inconnu';
    }

    /**
     * Génère le script JS d'injection du domaine utilisateur et d'auto-remplissage
     * du champ déclaré dans module.json (domain_field).
     */
    private static function genererScriptDomaine(array $user, \Platform\Module\ModuleDescriptor $module): string
    {
        $userDomain = $user['domaine'] ?? '';
        if ($userDomain === '') {
            return '';
        }

        $domainJs = htmlspecialchars($userDomain, ENT_QUOTES, 'UTF-8');
        $script = "<script>window.USER_DOMAIN='{$domainJs}';</script>";

        if ($module->domainField !== null) {
            $fieldId = htmlspecialchars($module->domainField, ENT_QUOTES, 'UTF-8');
            $script .= "<script>document.addEventListener('DOMContentLoaded',function(){var f=document.getElementById('{$fieldId}');if(f&&!f.value)f.value=window.USER_DOMAIN;});</script>";
        }

        return $script;
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
