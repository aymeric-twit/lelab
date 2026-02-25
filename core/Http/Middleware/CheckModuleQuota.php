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

        $shouldCheck = ($module->quotaMode === QuotaMode::Request)
            || ($module->quotaMode === QuotaMode::FormSubmit && $request->method() === 'POST');

        if (!$shouldCheck) {
            $next($request);
            return;
        }

        if (Quota::isOverQuota($user['id'], $slug)) {
            if ($request->isAjax()) {
                Response::json(['error' => 'Quota dépassé', 'quota_exceeded' => true], 429);
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
            ]);
            exit;
        }

        // Auto-increment
        Quota::increment($user['id'], $slug);

        $next($request);
    }
}
