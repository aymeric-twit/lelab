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

    /**
     * @return array{donnees: array, total: int, page: int, parPage: int, totalPages: int}
     */
    public function findAllPagine(int $page = 1, int $parPage = 20): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        $stmtCount = $this->db->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        $total = (int) $stmtCount->fetchColumn();

        $stmt = $this->db->prepare('SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $parPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
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
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, role, active) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['username'],
            $data['email'] ?: null,
            $data['password_hash'],
            $data['role'] ?? 'user',
            $data['active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $values = [];

        foreach (['username', 'email', 'password_hash', 'role', 'active'] as $field) {
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

    public function getDb(): PDO
    {
        return $this->db;
    }
}
