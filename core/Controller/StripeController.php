<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\User\AccessControl;
use Platform\View\Layout;

/**
 * Contrôleur Stripe — scaffolding pour l'intégration paiement.
 * Les méthodes sont des placeholders tant que STRIPE_SECRET_KEY n'est pas configuré.
 */
class StripeController
{
    /**
     * POST /paiement/checkout — Crée une session Stripe Checkout.
     */
    public function checkout(Request $req): void
    {
        $planId = (int) $req->post('plan_id', 0);

        if ($planId <= 0) {
            Response::json(['erreur' => 'Plan invalide.'], 400);
        }

        // Vérifier si Stripe est configuré
        $cleStripe = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        if ($cleStripe === '') {
            Response::json([
                'erreur'  => 'Stripe non configuré.',
                'message' => 'Configurez STRIPE_SECRET_KEY dans Configuration.',
            ], 503);
        }

        // TODO : Créer une session Stripe Checkout
        // $stripe = new \Stripe\StripeClient($cleStripe);
        // $session = $stripe->checkout->sessions->create([...]);
        // Response::json(['url' => $session->url]);

        Response::json([
            'message' => 'Stripe non configuré. Configurez STRIPE_SECRET_KEY dans Configuration.',
        ], 503);
    }

    /**
     * POST /webhook/stripe — Réception des événements Stripe.
     * Route publique sans CSRF ni auth, protégée par signature Stripe.
     */
    public function webhook(Request $req): void
    {
        // TODO : Vérifier la signature webhook Stripe
        // $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        // $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        // $payload = file_get_contents('php://input');

        // Répondre 200 pour que Stripe ne ré-essaie pas
        http_response_code(200);
        echo json_encode(['recu' => true]);
        exit;
    }

    /**
     * GET /paiement/succes — Page de succès après checkout.
     */
    public function succes(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();

        Layout::render('layout', [
            'template'          => 'paiement/succes',
            'pageTitle'         => 'Paiement réussi',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
        ]);
    }

    /**
     * GET /paiement/annulation — Page d'annulation après checkout.
     */
    public function annulation(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();

        Layout::render('layout', [
            'template'          => 'paiement/annulation',
            'pageTitle'         => 'Paiement annulé',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
        ]);
    }
}
