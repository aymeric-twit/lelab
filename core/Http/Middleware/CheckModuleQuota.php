<?php

namespace Platform\Http\Middleware;

use Platform\Auth\Auth;
use Platform\Enum\Role;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\ModuleRegistry;
use Platform\Module\Quota;
use Platform\Service\CreditService;
use Platform\Service\NotificationService;
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
        if (!$module) {
            $next($request);
            return;
        }

        // Module gratuit (poids 0) → toujours accessible
        if ($module->creditsParAnalyse === 0) {
            $next($request);
            return;
        }

        // Mode iframe : la requête parente (/m/{slug}) charge juste l'iframe,
        // les crédits seront comptés sur les sous-routes réelles
        if ($module->modeAffichage->estIframe() && !preg_match('#^/m/[^/]+/.+#', $path)) {
            $next($request);
            return;
        }

        // Déterminer si cette requête doit consommer des crédits
        $doitConsommer = true;

        // En mode form_submit, seuls les POST consomment
        if ($module->quotaMode === \Platform\Enum\QuotaMode::FormSubmit && $request->method() !== 'POST') {
            $doitConsommer = false;
        }

        // En mode api_call, le plugin gère lui-même via Quota::track() / CreditService
        // Le middleware vérifie juste qu'il reste des crédits
        if ($module->quotaMode === \Platform\Enum\QuotaMode::ApiCall) {
            $doitConsommer = false;
        }

        // En mode none, pas de vérification
        if ($module->quotaMode === \Platform\Enum\QuotaMode::None) {
            $next($request);
            return;
        }

        // Vérifier les crédits
        try {
            $creditService = new CreditService();

            if (!$creditService->peutConsommer($user['id'], $slug)) {
                $this->bloquer($request, $user, $slug, $module, $creditService);
            }

            // Auto-consommer pour request, form_submit (POST), url
            if ($doitConsommer) {
                $creditService->consommer($user['id'], $slug);

                // Incrémenter aussi module_usage pour le suivi par module
                $jourInscription = (int) date('j', strtotime($user['created_at'] ?? 'now'));
                Quota::increment($user['id'], $slug, 1, $jourInscription);
            }
        } catch (\PDOException) {
            // Table user_credits pas encore créée → fallback ancien système
            $this->fallbackAncienSysteme($request, $user, $slug, $module);
        }

        $next($request);
    }

    /**
     * Fallback vers l'ancien système de quotas par module.
     */
    private function fallbackAncienSysteme(Request $request, array $user, string $slug, \Platform\Module\ModuleDescriptor $module): void
    {
        if ($module->quotaMode === \Platform\Enum\QuotaMode::None) {
            return;
        }

        $jourInscription = (int) date('j', strtotime($user['created_at'] ?? 'now'));

        if (Quota::isOverQuota($user['id'], $slug, $jourInscription)) {
            $this->bloquerAncien($request, $user, $slug, $module, $jourInscription);
        }

        // Auto-incrémenter pour request et form_submit
        if ($module->quotaMode === \Platform\Enum\QuotaMode::Request
            || ($module->quotaMode === \Platform\Enum\QuotaMode::FormSubmit && $request->method() === 'POST')) {
            Quota::increment($user['id'], $slug, 1, $jourInscription);
        }
    }

    private function bloquer(Request $request, array $user, string $slug, \Platform\Module\ModuleDescriptor $module, CreditService $creditService): never
    {
        $resume = $creditService->resumePourDashboard($user['id']);

        if ($request->isAjax()) {
            Response::json([
                'error'            => 'Crédits épuisés',
                'credits_exceeded' => true,
                'utilises'         => $resume['utilises'],
                'limite'           => $resume['limite'],
                'periode_fin'      => $resume['periode_fin'],
            ], 429);
        }

        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);

        Layout::render('layout', [
            'template'          => 'quota-exceeded',
            'pageTitle'         => 'Crédits épuisés',
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => $slug,
            'quotaSummary'      => Quota::getUserQuotaSummary($user['id']),
            'moduleSlug'        => $slug,
            'moduleName'        => $module->name,
            'quotaUsage'        => $resume['utilises'],
            'quotaLimit'        => $resume['limite'],
            'dateResetQuota'    => $resume['periode_fin'],
            'creditsMode'       => true,
        ]);
        exit;
    }

    private function bloquerAncien(Request $request, array $user, string $slug, \Platform\Module\ModuleDescriptor $module, int $jourInscription): never
    {
        NotificationService::instance()->envoyerAlerteQuota100(
            $user['id'], $slug,
            Quota::getUsage($user['id'], $slug, $jourInscription),
            Quota::getLimit($user['id'], $slug)
        );

        if ($request->isAjax()) {
            Response::json([
                'error'          => 'Quota dépassé',
                'quota_exceeded' => true,
                'usage'          => Quota::getUsage($user['id'], $slug, $jourInscription),
                'limit'          => Quota::getLimit($user['id'], $slug),
                'reset_date'     => Quota::dateProchainResetUtilisateur($jourInscription),
            ], 429);
        }

        $ac = new AccessControl();
        Layout::render('layout', [
            'template'          => 'quota-exceeded',
            'pageTitle'         => 'Quota dépassé',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'activeModule'      => $slug,
            'quotaSummary'      => Quota::getUserQuotaSummary($user['id'], $jourInscription),
            'moduleSlug'        => $slug,
            'moduleName'        => $module->name,
            'quotaUsage'        => Quota::getUsage($user['id'], $slug, $jourInscription),
            'quotaLimit'        => Quota::getLimit($user['id'], $slug),
            'dateResetQuota'    => Quota::dateProchainResetUtilisateur($jourInscription),
        ]);
        exit;
    }
}
