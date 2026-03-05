<?php

namespace Platform\Auth;

use Platform\Database\Connection;
use PDO;

class RememberMe
{
    private const COOKIE_NAME = 'remember_me';
    private const SEPARATOR = ':';

    /**
     * Crée un token remember-me et envoie le cookie.
     */
    public static function creerToken(int $userId): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $lifetime = $config['remember']['lifetime'];

        $selecteur = bin2hex(random_bytes(16));
        $validateur = bin2hex(random_bytes(32));
        $hashValidateur = hash('sha256', $validateur);
        $expireLe = date('Y-m-d H:i:s', time() + $lifetime);

        $db = Connection::get();
        $stmt = $db->prepare(
            'INSERT INTO remember_tokens (user_id, selecteur, hash_validateur, expire_le) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $selecteur, $hashValidateur, $expireLe]);

        $cookieValue = $selecteur . self::SEPARATOR . $validateur;
        $secure = ($_ENV['APP_ENV'] ?? 'production') !== 'development';

        setcookie(self::COOKIE_NAME, $cookieValue, [
            'expires'  => time() + $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Tente un auto-login via le cookie remember-me.
     * Retourne le user_id si valide, null sinon.
     * Effectue une rotation du token à chaque validation réussie.
     */
    public static function tenterAutoLogin(): ?int
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($cookie === null || !str_contains($cookie, self::SEPARATOR)) {
            return null;
        }

        [$selecteur, $validateur] = explode(self::SEPARATOR, $cookie, 2);

        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT * FROM remember_tokens WHERE selecteur = ? AND expire_le > NOW()'
        );
        $stmt->execute([$selecteur]);
        $token = $stmt->fetch();

        if (!$token) {
            self::supprimerCookie();
            return null;
        }

        $hashAttendu = $token['hash_validateur'];
        $hashFourni = hash('sha256', $validateur);

        if (!hash_equals($hashAttendu, $hashFourni)) {
            // Possible vol de token : supprimer TOUS les tokens de cet utilisateur
            self::supprimerTousTokens($token['user_id']);
            self::supprimerCookie();
            return null;
        }

        $userId = (int) $token['user_id'];

        // Rotation : supprimer l'ancien, créer un nouveau
        $stmtDel = $db->prepare('DELETE FROM remember_tokens WHERE id = ?');
        $stmtDel->execute([$token['id']]);

        self::creerToken($userId);

        return $userId;
    }

    /**
     * Supprime tous les tokens remember-me d'un utilisateur.
     */
    public static function supprimerTousTokens(int $userId): void
    {
        $db = Connection::get();
        $stmt = $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Supprime le cookie remember-me.
     */
    public static function supprimerCookie(): void
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            setcookie(self::COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => ($_ENV['APP_ENV'] ?? 'production') !== 'development',
                'httponly'  => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE[self::COOKIE_NAME]);
        }
    }

    /**
     * Purge les tokens expirés (pour le cron).
     */
    public static function nettoyerExpires(): int
    {
        $db = Connection::get();
        $stmt = $db->prepare('DELETE FROM remember_tokens WHERE expire_le < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
