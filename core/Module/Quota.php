<?php

namespace Platform\Module;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use PDO;

class Quota
{
    private static function getDb(): PDO
    {
        return Connection::get();
    }

    private static function getModuleId(string $slug): ?int
    {
        $db = self::getDb();
        $stmt = $db->prepare('SELECT id FROM modules WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    private static function currentYearMonth(): string
    {
        return date('Ym');
    }

    /**
     * Check if user has exceeded their quota for a module.
     * Admins are always exempt.
     */
    public static function isOverQuota(int $userId, string $slug): bool
    {
        $limit = self::getLimit($userId, $slug);
        if ($limit === 0) {
            return false; // 0 = unlimited
        }

        $usage = self::getUsage($userId, $slug);
        return $usage >= $limit;
    }

    /**
     * Get the quota limit for a user/module.
     * Checks user_module_quotas first, falls back to module default_quota.
     * Returns 0 for unlimited.
     */
    public static function getLimit(int $userId, string $slug): int
    {
        $db = self::getDb();
        $moduleId = self::getModuleId($slug);
        if (!$moduleId) {
            return 0;
        }

        // Check per-user override first
        $stmt = $db->prepare('SELECT monthly_limit FROM user_module_quotas WHERE user_id = ? AND module_id = ?');
        $stmt->execute([$userId, $moduleId]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['monthly_limit'];
        }

        // Fall back to module default
        $stmt = $db->prepare('SELECT default_quota FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $row = $stmt->fetch();

        return $row ? (int) $row['default_quota'] : 0;
    }

    /**
     * Get the current month's usage count for a user/module.
     */
    public static function getUsage(int $userId, string $slug): int
    {
        $db = self::getDb();
        $moduleId = self::getModuleId($slug);
        if (!$moduleId) {
            return 0;
        }

        $yearMonth = self::currentYearMonth();
        $stmt = $db->prepare('SELECT usage_count FROM module_usage WHERE user_id = ? AND module_id = ? AND `year_month` = ?');
        $stmt->execute([$userId, $moduleId, $yearMonth]);
        $row = $stmt->fetch();

        return $row ? (int) $row['usage_count'] : 0;
    }

    /**
     * Increment usage counter for a user/module.
     */
    public static function increment(int $userId, string $slug, int $amount = 1): void
    {
        $db = self::getDb();
        $moduleId = self::getModuleId($slug);
        if (!$moduleId) {
            return;
        }

        $yearMonth = self::currentYearMonth();
        $stmt = $db->prepare('
            INSERT INTO module_usage (user_id, module_id, `year_month`, usage_count, last_tracked_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                usage_count = usage_count + VALUES(usage_count),
                last_tracked_at = NOW()
        ');
        $stmt->execute([$userId, $moduleId, $yearMonth, $amount]);
    }

    /**
     * Static convenience method for modules in api_call mode.
     * Uses the currently authenticated user.
     */
    public static function track(string $slug, int $amount = 1): void
    {
        $userId = Auth::id();
        if ($userId) {
            self::increment($userId, $slug, $amount);
        }
    }

    /**
     * Get quota summary for all modules accessible by a user.
     * Returns [slug => ['usage' => int, 'limit' => int, 'quota_mode' => string]]
     */
    public static function getUserQuotaSummary(int $userId): array
    {
        $db = self::getDb();
        $yearMonth = self::currentYearMonth();

        $modules = $db->query('SELECT id, slug, quota_mode, default_quota FROM modules WHERE enabled = 1')->fetchAll();
        $summary = [];

        foreach ($modules as $mod) {
            $moduleId = (int) $mod['id'];
            $slug = $mod['slug'];

            // Get limit: per-user override or default
            $stmt = $db->prepare('SELECT monthly_limit FROM user_module_quotas WHERE user_id = ? AND module_id = ?');
            $stmt->execute([$userId, $moduleId]);
            $row = $stmt->fetch();
            $limit = $row ? (int) $row['monthly_limit'] : (int) $mod['default_quota'];

            // Get usage
            $stmt = $db->prepare('SELECT usage_count FROM module_usage WHERE user_id = ? AND module_id = ? AND `year_month` = ?');
            $stmt->execute([$userId, $moduleId, $yearMonth]);
            $row = $stmt->fetch();
            $usage = $row ? (int) $row['usage_count'] : 0;

            $summary[$slug] = [
                'usage'      => $usage,
                'limit'      => $limit,
                'quota_mode' => $mod['quota_mode'],
            ];
        }

        return $summary;
    }

    /**
     * Set a per-user quota limit (admin action).
     */
    public static function setLimit(int $userId, int $moduleId, int $limit, ?int $updatedBy = null): void
    {
        $db = self::getDb();
        $stmt = $db->prepare('
            INSERT INTO user_module_quotas (user_id, module_id, monthly_limit, updated_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                monthly_limit = VALUES(monthly_limit),
                updated_by = VALUES(updated_by)
        ');
        $stmt->execute([$userId, $moduleId, $limit, $updatedBy]);
    }

    /**
     * Get full quota matrix for admin view.
     * Returns [userId => [moduleId => limit]]
     */
    public static function getQuotaMatrix(): array
    {
        $db = self::getDb();
        $rows = $db->query('SELECT user_id, module_id, monthly_limit FROM user_module_quotas')->fetchAll();
        $matrix = [];

        foreach ($rows as $row) {
            $matrix[(int) $row['user_id']][(int) $row['module_id']] = (int) $row['monthly_limit'];
        }

        return $matrix;
    }
}
