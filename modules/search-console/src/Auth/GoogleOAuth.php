<?php

namespace App\Auth;

use App\Database\Connection;
use Google\Client as GoogleClient;

/**
 * Gestion de l'authentification OAuth2 Google.
 *
 * - Génère l'URL d'autorisation
 * - Échange le code contre un token
 * - Stocke / rafraîchit les tokens en base
 */
class GoogleOAuth
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $this->client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
        $this->client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
        $this->client->addScope('https://www.googleapis.com/auth/webmasters.readonly');
        $this->client->setAccessType('offline');    // pour obtenir un refresh_token
        $this->client->setPrompt('consent');        // force le consent pour garantir le refresh_token
        $this->client->setIncludeGrantedScopes(true);
    }

    /** Retourne l'URL Google pour démarrer le flux OAuth. */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Échange le code d'autorisation contre un access + refresh token
     * et les stocke en base de données.
     */
    public function handleCallback(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('OAuth error: ' . ($token['error_description'] ?? $token['error']));
        }

        $this->saveToken($token);

        return $token;
    }

    /**
     * Retourne un GoogleClient authentifié, prêt à appeler l'API.
     * Rafraîchit le token automatiquement si expiré.
     */
    public function getAuthenticatedClient(): GoogleClient
    {
        $token = $this->loadToken();

        if (!$token) {
            throw new \RuntimeException('Aucun token OAuth enregistré. Veuillez vous authentifier via /auth.');
        }

        $this->client->setAccessToken([
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_type'    => $token['token_type'],
            'expires_in'    => max(0, strtotime($token['expires_at']) - time()),
            'created'       => strtotime($token['created_at']),
        ]);

        // Rafraîchissement automatique si le token est expiré
        if ($this->client->isAccessTokenExpired()) {
            if (empty($token['refresh_token'])) {
                throw new \RuntimeException('Token expiré et pas de refresh_token disponible.');
            }

            $newToken = $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

            if (isset($newToken['error'])) {
                throw new \RuntimeException('Refresh token error: ' . ($newToken['error_description'] ?? $newToken['error']));
            }

            // Conserver le refresh_token s'il n'est pas renvoyé
            if (empty($newToken['refresh_token'])) {
                $newToken['refresh_token'] = $token['refresh_token'];
            }

            $this->saveToken($newToken);
        }

        return $this->client;
    }

    /** Vérifie si un token est stocké en base. */
    public function hasToken(): bool
    {
        return $this->loadToken() !== null;
    }

    /** Supprime tous les tokens (déconnexion). */
    public function revokeToken(): void
    {
        $token = $this->loadToken();
        if ($token) {
            $this->client->revokeToken($token['access_token']);
        }
        Connection::get()->exec('DELETE FROM oauth_tokens');
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    private function saveToken(array $token): void
    {
        $db = Connection::get();

        $expiresAt = date('Y-m-d H:i:s', time() + ($token['expires_in'] ?? 3600));
        $scope = $token['scope'] ?? '';

        // On ne garde qu'un seul enregistrement (single-user)
        $db->exec('DELETE FROM oauth_tokens');

        $stmt = $db->prepare(
            'INSERT INTO oauth_tokens (access_token, refresh_token, token_type, expires_at, scope)
             VALUES (:access_token, :refresh_token, :token_type, :expires_at, :scope)'
        );

        $stmt->execute([
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_type'    => $token['token_type'] ?? 'Bearer',
            'expires_at'    => $expiresAt,
            'scope'         => $scope,
        ]);
    }

    private function loadToken(): ?array
    {
        $stmt = Connection::get()->query('SELECT * FROM oauth_tokens ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
