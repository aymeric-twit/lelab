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
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function findAll(): array
    {
        return $this->db->query('SELECT * FROM users ORDER BY id')->fetchAll();
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
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
}
