<?php

namespace Platform\Auth;

use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Service\AuditLogger;
use Platform\Service\EmailTemplate;
use Platform\Service\Mailer;
use Platform\User\UserRepository;
use PDO;

class PasswordReset
{
    private const EXPIRATION_HEURES = 1;

    /**
     * Demande de réinitialisation de mot de passe.
     * Anti-énumération : retourne toujours true (même si l'email n'existe pas).
     */
    public static function demander(string $email, string $ip): bool
    {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if (!$user) {
            return true; // Anti-énumération
        }

        // Invalider les tokens précédents non utilisés
        $db = Connection::get();
        $stmt = $db->prepare('UPDATE password_resets SET utilise = 1 WHERE user_id = ? AND utilise = 0');
        $stmt->execute([$user['id']]);

        // Générer un nouveau token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expireLe = date('Y-m-d H:i:s', time() + self::EXPIRATION_HEURES * 3600);

        $stmt = $db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expire_le) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['id'], $tokenHash, $expireLe]);

        // Envoyer l'email
        $config = require __DIR__ . '/../../config/app.php';
        $lien = rtrim($config['url'], '/') . '/reinitialiser-mot-de-passe?token=' . $token;

        $contenu = EmailTemplate::rendre('password-reset', [
            'username' => $user['username'],
            'lien' => $lien,
            'expiration' => self::EXPIRATION_HEURES,
        ]);

        Mailer::instance()->envoyer($user['email'], 'Réinitialisation de votre mot de passe', $contenu);

        AuditLogger::instance()->log(AuditAction::PasswordResetRequest, $ip, $user['id'], 'user');

        return true;
    }

    /**
     * Valide un token de réinitialisation.
     * Retourne le user_id si valide, null sinon.
     */
    public static function validerToken(string $token): ?int
    {
        $tokenHash = hash('sha256', $token);

        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT * FROM password_resets WHERE token_hash = ? AND utilise = 0 AND expire_le > NOW() ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $reset = $stmt->fetch();

        if (!$reset) {
            return null;
        }

        return (int) $reset['user_id'];
    }

    /**
     * Réinitialise le mot de passe et invalide le token.
     */
    public static function reinitialiser(string $token, string $nouveauMotDePasse, string $ip): bool
    {
        $tokenHash = hash('sha256', $token);

        $db = Connection::get();
        $db->beginTransaction();

        try {
            // Récupérer et marquer le token comme utilisé
            $stmt = $db->prepare(
                'SELECT * FROM password_resets WHERE token_hash = ? AND utilise = 0 AND expire_le > NOW() LIMIT 1'
            );
            $stmt->execute([$tokenHash]);
            $reset = $stmt->fetch();

            if (!$reset) {
                $db->rollBack();
                return false;
            }

            $stmtUpdate = $db->prepare('UPDATE password_resets SET utilise = 1 WHERE id = ?');
            $stmtUpdate->execute([$reset['id']]);

            // Mettre à jour le mot de passe
            $userId = (int) $reset['user_id'];
            $repo = new UserRepository();
            $repo->update($userId, [
                'password_hash' => PasswordHasher::hash($nouveauMotDePasse),
            ]);

            // Invalider tous les tokens remember-me
            RememberMe::supprimerTousTokens($userId);

            $db->commit();

            AuditLogger::instance()->log(AuditAction::PasswordResetComplete, $ip, $userId, 'user');

            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Purge les tokens expirés ou utilisés (pour le cron).
     */
    public static function nettoyerExpires(): int
    {
        $db = Connection::get();
        $stmt = $db->prepare('DELETE FROM password_resets WHERE expire_le < NOW() OR utilise = 1');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
