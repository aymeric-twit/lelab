<?php

namespace Platform\User;

use Platform\Database\Connection;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findByUnsubscribeToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE unsubscribe_token = ? AND deleted_at IS NULL');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function usernameExiste(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND deleted_at IS NULL');
        $stmt->execute([$username]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([$email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array{q?: string, role?: string, actif?: string} $filtres
     * @return array{donnees: array, total: int, page: int, parPage: int, totalPages: int}
     */
    public function findAllPagine(int $page = 1, int $parPage = 20, array $filtres = []): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        $conditions = ['deleted_at IS NULL'];
        $params = [];

        if (!empty($filtres['q'])) {
            $conditions[] = '(username LIKE ? OR email LIKE ?)';
            $terme = '%' . $filtres['q'] . '%';
            $params[] = $terme;
            $params[] = $terme;
        }

        if (!empty($filtres['role']) && in_array($filtres['role'], ['admin', 'user'], true)) {
            $conditions[] = 'role = ?';
            $params[] = $filtres['role'];
        }

        if (isset($filtres['actif']) && $filtres['actif'] !== '') {
            $conditions[] = 'active = ?';
            $params[] = (int) $filtres['actif'];
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM users {$where}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "SELECT * FROM users {$where} ORDER BY id LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }
        $stmt->bindValue($i++, $parPage, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'donnees'    => $stmt->fetchAll(),
            'total'      => $total,
            'page'       => $page,
            'parPage'    => $parPage,
            'totalPages' => (int) ceil($total / $parPage),
        ];
    }

    public function findAll(): array
    {
        return $this->db->query('SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id')->fetchAll();
    }

    public function create(array $data): int
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, domaine, password_hash, role, active, unsubscribe_token) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['username'],
            $data['email'] ?: null,
            $data['domaine'] ?? null,
            $data['password_hash'],
            $data['role'] ?? 'user',
            $data['active'] ?? 1,
            $token,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $values = [];

        foreach (['username', 'email', 'domaine', 'password_hash', 'role', 'active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $values[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Soft delete : marque l'utilisateur comme supprimé sans le retirer de la base.
     */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
    }

    /**
     * Supprime un compte utilisateur : soft-delete + purge des données associées.
     */
    public function supprimerCompte(int $userId): void
    {
        $this->db->beginTransaction();

        try {
            // Soft-delete de l'utilisateur
            $stmt = $this->db->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$userId]);

            // Purge des tokens et données associées
            foreach (['remember_tokens', 'password_resets', 'email_verifications', 'user_module_access', 'user_module_quotas', 'user_notification_preferences'] as $table) {
                $stmt = $this->db->prepare("DELETE FROM {$table} WHERE user_id = ?");
                $stmt->execute([$userId]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getDb(): PDO
    {
        return $this->db;
    }
}
