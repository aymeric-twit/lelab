<?php

namespace Platform\Http\Middleware;

use Platform\Auth\Auth;
use Platform\Enum\QuotaMode;
use Platform\Enum\Role;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\ModuleRegistry;
use Platform\Module\Quota;
use Platform\Service\CreditService;
use Platform\User\AccessControl;
use Platform\View\Layout;

/**
 * Middleware de vérification des crédits/quotas.
 *
 * Flux unifié :
 * - request/form_submit/url → Quota::trackerSiDisponible() (vérifie + déduit crédits + module_usage)
 * - api_call → Quota::creditsDisponibles() (vérifie sans déduire, le plugin gère via Quota::track())
 * - none / poids 0 → passe directement
 */
class CheckModuleQuota implements Middleware
{
    public function handle(Request $request, \Closure $next): void
    {
        $path = $request->path();

        if (!preg_match('#^/m/([^/]+)#', $path, $matches)) {
            $next($request);
            return;
        }

        $slug = $matches[1];
        $user = Auth::user();
        $role = Role::tryFrom($user['role']) ?? Role::User;

        // Admin exempt
        if ($role === Role::Admin) {
            $next($request);
            return;
        }

        $module = ModuleRegistry::get($slug);
        if (!$module) {
            $next($request);
            return;
        }

        // Module gratuit (poids 0) ou mode none → passe
        if ($module->creditsParAnalyse === 0 || $module->quotaMode === QuotaMode::None) {
            $next($request);
            return;
        }

        // Mode iframe : la requête parente ne compte pas
        if ($module->modeAffichage->estIframe() && !preg_match('#^/m/[^/]+/.+#', $path)) {
            $next($request);
            return;
        }

        // Mode api_call : vérifier seulement, le plugin consommera via Quota::track()
        if ($module->quotaMode === QuotaMode::ApiCall) {
            if (!Quota::creditsDisponibles($slug)) {
                $this->bloquer($request, $user, $slug, $module);
            }
            $next($request);
            return;
        }

        // Mode form_submit : seuls les POST consomment
        if ($module->quotaMode === QuotaMode::FormSubmit && $request->method() !== 'POST') {
            $next($request);
            return;
        }

        // Modes request, form_submit (POST), url → vérifier + consommer
        if (!Quota::trackerSiDisponible($slug)) {
            $this->bloquer($request, $user, $slug, $module);
        }

        $next($request);
    }

    private function bloquer(Request $request, array $user, string $slug, \Platform\Module\ModuleDescriptor $module): never
    {
        // Récupérer les infos de crédits pour l'affichage
        $creditsMode = false;
        $utilises = 0;
        $limite = 0;
        $periodeFin = '';

        try {
            $creditService = new CreditService();
            $resume = $creditService->resumePourDashboard($user['id']);
            $creditsMode = true;
            $utilises = $resume['utilises'];
            $limite = $resume['limite'];
            $periodeFin = $resume['periode_fin'];
        } catch (\PDOException) {
            $jourInscription = (int) date('j', strtotime($user['created_at'] ?? 'now'));
            $utilises = Quota::getUsage($user['id'], $slug, $jourInscription);
            $limite = Quota::getLimit($user['id'], $slug);
            $periodeFin = Quota::dateProchainResetUtilisateur($jourInscription);
        }

        if ($request->isAjax()) {
            Response::json([
                'error'       => $creditsMode ? 'Crédits épuisés' : 'Quota dépassé',
                'utilises'    => $utilises,
                'limite'      => $limite,
                'periode_fin' => $periodeFin,
            ], 429);
        }

        $ac = new AccessControl();
        Layout::render('layout', [
            'template'          => 'quota-exceeded',
            'pageTitle'         => $creditsMode ? 'Crédits épuisés' : 'Quota dépassé',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'activeModule'      => $slug,
            'quotaSummary'      => Quota::getUserQuotaSummary($user['id']),
            'moduleSlug'        => $slug,
            'moduleName'        => $module->name,
            'quotaUsage'        => $utilises,
            'quotaLimit'        => $limite,
            'dateResetQuota'    => $periodeFin,
            'creditsMode'       => $creditsMode,
        ]);
        exit;
    }
}
