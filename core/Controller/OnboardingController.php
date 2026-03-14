<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Service\PlanService;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use Platform\View\Flash;
use Platform\View\Layout;

class OnboardingController
{
    /**
     * GET /onboarding — Wizard de première connexion.
     */
    public function index(): void
    {
        $user = Auth::user();

        // Si l'utilisateur a déjà complété l'onboarding, rediriger
        if (!empty($user['domaine']) || !empty($_SESSION['onboarding_complete'])) {
            Response::redirect('/');
        }

        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);

        $planService = new PlanService();
        $plans = [];
        try {
            $plans = $planService->listerPlansActifs();
        } catch (\PDOException) {
            // Table plans pas encore créée
        }

        Layout::renderStandalone('onboarding', [
            'currentUser' => $user,
            'modules'     => $modules,
            'plans'       => $plans,
        ]);
    }

    /**
     * POST /onboarding — Sauvegarder les préférences d'onboarding.
     */
    public function sauvegarder(Request $req): void
    {
        $user = Auth::user();
        $repo = new UserRepository();

        $domaine = trim($req->post('domaine', ''));

        $data = [];
        if ($domaine !== '') {
            $data['domaine'] = $domaine;
        }

        if (!empty($data)) {
            $repo->update($user['id'], $data);
        }

        // Assigner un plan si sélectionné
        $planId = $req->post('plan_id', '');
        if ($planId !== '') {
            try {
                $planService = new PlanService();
                $plan = $planService->parId((int) $planId);
                if ($plan && $plan['actif']) {
                    $planService->assignerPlan($user['id'], (int) $planId);
                }
            } catch (\PDOException) {
                // Table plans pas encore créée
            }
        }

        $_SESSION['onboarding_complete'] = true;

        Flash::success('Bienvenue sur la plateforme ! Votre profil a été configuré.');
        Response::redirect('/');
    }
}
