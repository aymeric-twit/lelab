<?php

namespace Platform\Service;

use Platform\Auth\Auth;
use Platform\Auth\PasswordHasher;
use Platform\Auth\RememberMe;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\User\UserRepository;

/**
 * Service métier d'authentification.
 * Extrait la logique de login/2FA/historique hors du contrôleur.
 */
class AuthService
{
    private UserRepository $repo;
    private AuditLogger $audit;

    public function __construct(?UserRepository $repo = null, ?AuditLogger $audit = null)
    {
        $this->repo = $repo ?? new UserRepository();
        $this->audit = $audit ?? AuditLogger::instance();
    }

    /**
     * Tente une authentification par identifiants.
     *
     * @return array{succes: bool, necessite2fa: bool, userId: ?int, raison: ?string}
     */
    public function authentifier(string $username, string $password, string $ipAnonyme): array
    {
        $utilisateur = $this->repo->findByUsername($username);

        if (!$utilisateur) {
            $this->audit->log(AuditAction::LoginFailed, $ipAnonyme, null, 'user', null, ['username' => $username]);
            return ['succes' => false, 'necessite2fa' => false, 'userId' => null, 'raison' => 'identifiants'];
        }

        if (!$utilisateur['active'] && PasswordHasher::verify($password, $utilisateur['password_hash'])) {
            $this->audit->log(AuditAction::LoginFailed, $ipAnonyme, (int) $utilisateur['id'], 'user', null, [
                'username' => $username,
                'raison' => 'compte_inactif',
            ]);
            return ['succes' => false, 'necessite2fa' => false, 'userId' => (int) $utilisateur['id'], 'raison' => 'inactif'];
        }

        if (!$utilisateur['active'] || !PasswordHasher::verify($password, $utilisateur['password_hash'])) {
            $this->audit->log(AuditAction::LoginFailed, $ipAnonyme, null, 'user', null, ['username' => $username]);
            return ['succes' => false, 'necessite2fa' => false, 'userId' => null, 'raison' => 'identifiants'];
        }

        // Vérifier si la 2FA est activée
        if (!empty($utilisateur['totp_enabled'])) {
            return ['succes' => true, 'necessite2fa' => true, 'userId' => (int) $utilisateur['id'], 'raison' => null];
        }

        // Connexion complète
        $this->finaliserConnexion($utilisateur, $ipAnonyme);
        return ['succes' => true, 'necessite2fa' => false, 'userId' => (int) $utilisateur['id'], 'raison' => null];
    }

    /**
     * Finalise une connexion après validation des identifiants (et éventuellement de la 2FA).
     */
    public function finaliserConnexion(array $utilisateur, string $ipAnonyme, bool $sesouvenir = false, bool $via2fa = false): void
    {
        $userId = (int) $utilisateur['id'];

        Auth::loginParId($userId);

        $this->audit->log(AuditAction::LoginSuccess, $ipAnonyme, $userId, 'user', null, [
            'username' => $utilisateur['username'],
            '2fa' => $via2fa,
        ]);

        $this->enregistrerHistorique($userId, $ipAnonyme);

        if ($sesouvenir) {
            RememberMe::creerToken($userId);
        }
    }

    /**
     * Enregistre l'entrée dans la table login_history.
     */
    public function enregistrerHistorique(int $userId, string $ipAnonyme): void
    {
        $db = Connection::get();
        $stmt = $db->prepare('INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $ipAnonyme, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
    }
}
