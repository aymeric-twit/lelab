<?php

namespace Platform\Http\Middleware;

use Platform\Auth\Auth;
use Platform\Enum\QuotaMode;
use Platform\Enum\Role;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\ModuleRegistry;
use Platform\Module\Quota;
use Platform\User\AccessControl;
use Platform\View\Layout;

class CheckModuleQuota implements Middleware
{
    public function handle(Request $request, \Closure $next): void
    {
        $path = $request->path();

        // Extraire le slug du module depuis le path /m/{slug}...
        if (!preg_match('#^/m/([^/]+)#', $path, $matches)) {
            $next($request);
            return;
        }

        $slug = $matches[1];
        $user = Auth::user();
        $role = Role::tryFrom($user['role']) ?? Role::User;

        // Les admins sont exemptés
        if ($role === Role::Admin) {
            $next($request);
            return;
        }

        $module = ModuleRegistry::get($slug);
        if (!$module || $module->quotaMode === QuotaMode::None) {
            $next($request);
            return;
        }

        // Mode iframe : la requête parente (/m/{slug}) charge juste l'iframe,
        // le quota sera compté sur les sous-routes réelles (_app, etc.)
        if ($module->modeAffichage->estIframe() && !preg_match('#^/m/[^/]+/.+#', $path)) {
            $next($request);
            return;
        }

        // 1. Déterminer si le quota doit être vérifié pour ce mode
        $doitVerifier = $module->quotaMode->estSuivi(); // true pour request, form_submit, api_call

        // En mode form_submit, seuls les POST sont vérifiés
        if ($module->quotaMode === QuotaMode::FormSubmit && $request->method() !== 'POST') {
            $doitVerifier = false;
        }

        if (!$doitVerifier) {
            $next($request);
            return;
        }

        // 2. Bloquer si quota dépassé — s'applique à TOUS les modes suivis (y compris api_call)
        if (Quota::isOverQuota($user['id'], $slug)) {
            if ($request->isAjax()) {
                Response::json([
                    'error'          => 'Quota dépassé',
                    'quota_exceeded' => true,
                    'usage'          => Quota::getUsage($user['id'], $slug),
                    'limit'          => Quota::getLimit($user['id'], $slug),
                    'reset_date'     => Quota::dateProchainReset(),
                ], 429);
            }

            $ac = new AccessControl();
            $quotaSummary = Quota::getUserQuotaSummary($user['id']);
            $modules = $ac->getAccessibleModules($user['id']);
            Layout::render('layout', [
                'template'          => 'quota-exceeded',
                'pageTitle'         => 'Quota dépassé',
                'currentUser'       => $user,
                'accessibleModules' => $modules,
                'activeModule'      => $slug,
                'quotaSummary'      => $quotaSummary,
                'moduleSlug'        => $slug,
                'moduleName'        => $module->name,
                'quotaUsage'        => Quota::getUsage($user['id'], $slug),
                'quotaLimit'        => Quota::getLimit($user['id'], $slug),
                'dateResetQuota'    => Quota::dateProchainReset(),
            ]);
            exit;
        }

        // 3. Auto-incrémenter uniquement pour request et form_submit
        //    Le mode api_call est incrémenté manuellement par le plugin via Quota::track()
        if ($module->quotaMode === QuotaMode::Request
            || ($module->quotaMode === QuotaMode::FormSubmit && $request->method() === 'POST')) {
            Quota::increment($user['id'], $slug);
        }

        $next($request);
    }
}
