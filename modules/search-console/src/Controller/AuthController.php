<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;

/**
 * Gère les routes d'authentification OAuth2 Google.
 */
class AuthController
{
    private GoogleOAuth $oauth;

    public function __construct()
    {
        $this->oauth = new GoogleOAuth();
    }

    /** Page d'accueil auth : affiche le statut et le bouton de connexion. */
    public function index(): void
    {
        $hasToken = $this->oauth->hasToken();
        require __DIR__ . '/../../templates/auth.php';
    }

    /** Redirige vers Google pour lancer le flux OAuth. */
    public function login(): void
    {
        $url = $this->oauth->getAuthUrl();
        header('Location: ' . $url);
        exit;
    }

    /** Callback OAuth : échange le code et redirige vers le dashboard. */
    public function callback(): void
    {
        if (empty($_GET['code'])) {
            http_response_code(400);
            echo 'Paramètre "code" manquant.';
            return;
        }

        try {
            $this->oauth->handleCallback($_GET['code']);
            $prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
            header('Location: ' . $prefix . '/');
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Erreur OAuth : ' . htmlspecialchars($e->getMessage());
        }
    }

    /** Révoque le token et redirige. */
    public function logout(): void
    {
        try {
            $this->oauth->revokeToken();
        } catch (\Throwable $e) {
            // Ignore les erreurs de révocation
        }

        $prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
        header('Location: ' . $prefix . '/auth');
        exit;
    }
}
