<?php

namespace Platform\Service;

use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use PDO;

class AuditLogger
{
    private static ?self $instance = null;
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log(
        AuditAction $action,
        string $ip,
        ?int $userId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $details = null,
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_log (user_id, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $action->value,
            $targetType,
            $targetId,
            $details !== null ? json_encode($details) : null,
            $ip,
        ]);
    }
}
