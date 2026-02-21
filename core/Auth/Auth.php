<?php

namespace Platform\Auth;

use Platform\User\UserRepository;
use Platform\Database\Connection;
use PDO;

class Auth
{
    private static ?array $currentUser = null;

    public static function attempt(string $username, string $password): bool
    {
        $repo = new UserRepository();
        $user = $repo->findByUsername($username);

        if (!$user || !$user['active']) {
            return false;
        }

        if (!PasswordHasher::verify($password, $user['password_hash'])) {
            return false;
        }

        // Rehash if needed
        if (PasswordHasher::needsRehash($user['password_hash'])) {
            $repo->update($user['id'], [
                'password_hash' => PasswordHasher::hash($password),
            ]);
        }

        // Login
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
        $_SESSION['user_id'] = $user['id'];
        $repo->updateLastLogin($user['id']);

        self::$currentUser = $user;
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$currentUser === null || self::$currentUser['id'] !== $_SESSION['user_id']) {
            $repo = new UserRepository();
            self::$currentUser = $repo->findById($_SESSION['user_id']);
        }

        return self::$currentUser;
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user && $user['role'] === 'admin';
    }

    public static function logout(): void
    {
        self::$currentUser = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    /**
     * Rate limiting for login attempts
     */
    public static function isRateLimited(string $ip, int $maxAttempts = 5, int $window = 900): bool
    {
        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT COUNT(*) as cnt FROM audit_log
             WHERE action = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute(['login.failed', $ip, $window]);
        $row = $stmt->fetch();
        return ($row['cnt'] ?? 0) >= $maxAttempts;
    }

    public static function logAttempt(string $action, string $ip, ?int $userId = null, ?string $username = null): void
    {
        $db = Connection::get();
        $stmt = $db->prepare(
            'INSERT INTO audit_log (user_id, action, target_type, details, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $action,
            'user',
            json_encode(['username' => $username]),
            $ip,
        ]);
    }
}
