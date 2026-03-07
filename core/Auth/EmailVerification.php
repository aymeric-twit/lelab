<?php

namespace Platform\Auth;

use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Service\AuditLogger;
use Platform\Service\EmailTemplate;
use Platform\Service\Mailer;
use Platform\Service\NotificationService;
use Platform\User\UserRepository;
use PDO;

class EmailVerification
{
    private const EXPIRATION_HEURES = 24;

    /**
     * Envoie un email de vérification.
     */
    public static function envoyer(int $userId, string $email, string $username): bool
    {
        $db = Connection::get();

        // Supprimer les tokens précédents
        $stmt = $db->prepare('DELETE FROM email_verifications WHERE user_id = ?');
        $stmt->execute([$userId]);

        // Générer un nouveau token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expireLe = date('Y-m-d H:i:s', time() + self::EXPIRATION_HEURES * 3600);

        $stmt = $db->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expire_le) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expireLe]);

        // Envoyer l'email
        $config = require __DIR__ . '/../../config/app.php';
        $lien = rtrim($config['url'], '/') . '/verifier-email?token=' . $token;

        $contenu = EmailTemplate::rendre('verification-email', [
            'username' => $username,
            'lien' => $lien,
            'expiration' => self::EXPIRATION_HEURES,
        ]);

        return Mailer::instance()->envoyer($email, 'Vérifiez votre adresse email', $contenu);
    }

    /**
     * Valide le token de vérification email et active le compte.
     */
    public static function verifier(string $token, string $ip): bool
    {
        $tokenHash = hash('sha256', $token);

        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT * FROM email_verifications WHERE token_hash = ? AND expire_le > NOW() LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $verification = $stmt->fetch();

        if (!$verification) {
            return false;
        }

        $userId = (int) $verification['user_id'];

        $db->beginTransaction();
        try {
            // Supprimer le token
            $stmtDel = $db->prepare('DELETE FROM email_verifications WHERE id = ?');
            $stmtDel->execute([$verification['id']]);

            // Activer le compte
            $repo = new UserRepository();
            $repo->update($userId, ['active' => 1]);

            $db->commit();

            AuditLogger::instance()->log(AuditAction::EmailVerified, $ip, $userId, 'user');

            NotificationService::instance()->envoyerBienvenue($userId);

            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Purge les tokens expirés (pour le cron).
     */
    public static function nettoyerExpires(): int
    {
        $db = Connection::get();
        $stmt = $db->prepare('DELETE FROM email_verifications WHERE expire_le < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
