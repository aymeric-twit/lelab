<?php

namespace Platform\Auth;

use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Service\AuditLogger;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use PDO;

class Inscription
{
    /**
     * Vérifie si l'inscription est activée.
     */
    public static function estActive(): bool
    {
        $config = require __DIR__ . '/../../config/app.php';
        return (bool) ($config['inscription']['active'] ?? false);
    }

    /**
     * Vérifie si l'IP est limitée (rate limiting inscriptions).
     */
    public static function estLimitee(string $ip): bool
    {
        $config = require __DIR__ . '/../../config/app.php';
        $max = $config['inscription']['rate_limit'] ?? 5;
        $fenetre = $config['inscription']['rate_window'] ?? 3600;

        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM audit_log WHERE action = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([AuditAction::Inscription->value, $ip, $fenetre]);

        return (int) $stmt->fetchColumn() >= $max;
    }

    /**
     * Crée un nouvel utilisateur (inactif) et accorde l'accès à tous les plugins actifs.
     *
     * @return array{id: int, email: string, username: string}
     */
    public static function creerCompte(string $username, string $email, string $motDePasse, string $ip): array
    {
        $db = Connection::get();
        $db->beginTransaction();

        try {
            $repo = new UserRepository();

            $userId = $repo->create([
                'username' => $username,
                'email' => $email,
                'password_hash' => PasswordHasher::hash($motDePasse),
                'role' => 'user',
                'active' => 0, // Inactif jusqu'à vérification email
            ]);

            // Accorder l'accès à tous les modules actifs
            $ac = new AccessControl();
            $stmt = $db->query('SELECT id FROM modules WHERE enabled = 1 AND desinstalle_le IS NULL');
            $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($modules as $moduleId) {
                $ac->setAccess($userId, (int) $moduleId, true);
            }

            $db->commit();

            AuditLogger::instance()->log(AuditAction::Inscription, $ip, $userId, 'user', null, [
                'username' => $username,
                'email' => $email,
            ]);

            return ['id' => $userId, 'email' => $email, 'username' => $username];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
