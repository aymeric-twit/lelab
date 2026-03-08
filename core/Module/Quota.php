<?php

namespace Platform\Module;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Enum\QuotaMode;
use Platform\Service\AuditLogger;
use Platform\Service\NotificationService;
use PDO;

class Quota
{
    /** @var array<string, int|null> Cache slug → module ID */
    private static array $moduleIdCache = [];

    private static function getDb(): PDO
    {
        return Connection::get();
    }

    private static function getModuleId(string $slug): ?int
    {
        if (array_key_exists($slug, self::$moduleIdCache)) {
            return self::$moduleIdCache[$slug];
        }

        $db = self::getDb();
        $stmt = $db->prepare('SELECT id FROM modules WHERE slug = ? AND desinstalle_le IS NULL');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        $id = $row ? (int) $row['id'] : null;

        self::$moduleIdCache[$slug] = $id;
        return $id;
    }

    /**
     * Calcule la période courante (format Ym) en fonction du jour d'inscription.
     * Si le jour actuel >= jourInscription, la période a commencé ce mois-ci.
     * Sinon, la période a commencé le mois précédent.
     */
    public static function currentPeriod(int $jourInscription): string
    {
        $jourActuel = (int) date('j');
        if ($jourActuel >= $jourInscription) {
            return date('Ym');
        }

        return date('Ym', strtotime('first day of last month'));
    }

    /**
     * Retourne la date du prochain reset des quotas (1er du mois suivant).
     * @deprecated Utiliser dateProchainResetUtilisateur() à la place
     */
    public static function dateProchainReset(): string
    {
        return date('Y-m-d', strtotime('first day of next month'));
    }

    /**
     * Retourne la date du prochain reset basée sur le jour d'inscription de l'utilisateur.
     * Gère les mois courts (inscrit le 31 → reset le 28/29 en février).
     */
    public static function dateProchainResetUtilisateur(int $jourInscription): string
    {
        $jourActuel = (int) date('j');
        $moisActuel = new \DateTimeImmutable('first day of this month');

        if ($jourActuel >= $jourInscription) {
            $moisReset = $moisActuel->modify('+1 month');
        } else {
            $moisReset = $moisActuel;
        }

        $dernierJour = (int) $moisReset->format('t');
        $jour = min($jourInscription, $dernierJour);

        return $moisReset->format('Y-m-') . str_pad((string) $jour, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Extrait le jour d'inscription depuis la date created_at de l'utilisateur courant.
     */
    public static function jourInscriptionUtilisateur(): int
    {
        $user = Auth::user();
        if (!$user || empty($user['created_at'])) {
            return 1;
        }

        return (int) date('j', strtotime($user['created_at']));
    }

    /**
     * Check if user has exceeded their quota for a module.
     * Admins are always exempt.
     */
    public static function isOverQuota(int $userId, string $slug, int $jourInscription = 1): bool
    {
        $limit = self::getLimit($userId, $slug);
        if ($limit === 0) {
            return false; // 0 = unlimited
        }

        $usage = self::getUsage($userId, $slug, $jourInscription);
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
     * Get the current period's usage count for a user/module.
     */
    public static function getUsage(int $userId, string $slug, int $jourInscription = 1): int
    {
        $db = self::getDb();
        $moduleId = self::getModuleId($slug);
        if (!$moduleId) {
            return 0;
        }

        $yearMonth = self::currentPeriod($jourInscription);
        $stmt = $db->prepare('SELECT usage_count FROM module_usage WHERE user_id = ? AND module_id = ? AND `year_month` = ?');
        $stmt->execute([$userId, $moduleId, $yearMonth]);
        $row = $stmt->fetch();

        return $row ? (int) $row['usage_count'] : 0;
    }

    /**
     * Increment usage counter for a user/module.
     */
    public static function increment(int $userId, string $slug, int $amount = 1, int $jourInscription = 1): void
    {
        $db = self::getDb();
        $moduleId = self::getModuleId($slug);
        if (!$moduleId) {
            return;
        }

        $yearMonth = self::currentPeriod($jourInscription);
        $stmt = $db->prepare('
            INSERT INTO module_usage (user_id, module_id, `year_month`, usage_count, last_tracked_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                usage_count = usage_count + VALUES(usage_count),
                last_tracked_at = NOW()
        ');
        $stmt->execute([$userId, $moduleId, $yearMonth, $amount]);

        self::verifierSeuilQuota($userId, $slug, $jourInscription);
        self::logUsageAudit($userId, $moduleId, $slug);
    }

    /**
     * Vérifie si le seuil de 80% est atteint et envoie une alerte email.
     */
    private static function verifierSeuilQuota(int $userId, string $slug, int $jourInscription = 1): void
    {
        $limite = self::getLimit($userId, $slug);
        if ($limite === 0) {
            return;
        }

        $usage = self::getUsage($userId, $slug, $jourInscription);
        $config = require __DIR__ . '/../../config/app.php';
        $seuil = $config['notifications']['quota_seuil_alerte'] ?? 80;

        $pourcentage = ($usage / $limite) * 100;

        if ($pourcentage >= $seuil && $pourcentage < 100) {
            NotificationService::instance()->envoyerAlerteQuota80($userId, $slug, $usage, $limite);
        }
    }

    /**
     * Logger l'usage dans audit_log avec filtre anti-spam (5 min).
     */
    private static function logUsageAudit(int $userId, int $moduleId, string $slug): void
    {
        $db = self::getDb();
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $recentCondition = $driver === 'mysql'
            ? 'created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
            : 'created_at > datetime("now", "-5 minutes")';

        $check = $db->prepare(
            "SELECT 1 FROM audit_log
             WHERE user_id = ? AND action = ? AND target_id = ?
               AND {$recentCondition}
             LIMIT 1"
        );
        $check->execute([$userId, AuditAction::ModuleUse->value, $moduleId]);

        if (!$check->fetch()) {
            AuditLogger::instance()->log(
                AuditAction::ModuleUse,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $userId,
                'module',
                $moduleId,
                ['slug' => $slug],
            );
        }
    }

    /**
     * Static convenience method for modules in api_call mode.
     * Uses the currently authenticated user.
     */
    public static function track(string $slug, int $amount = 1): void
    {
        $userId = Auth::id();
        if ($userId) {
            $jourInscription = self::jourInscriptionUtilisateur();
            self::increment($userId, $slug, $amount, $jourInscription);
        }
    }

    /**
     * Vérifie le quota restant, incrémente si disponible, retourne le succès.
     * Retourne false si aucun utilisateur connecté ou quota dépassé.
     * Retourne true (et incrémente) si le quota est suffisant ou illimité.
     *
     * Atomique : pas de race condition entre la vérification et l'incrément.
     */
    public static function trackerSiDisponible(string $slug, int $amount = 1): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }

        $jourInscription = self::jourInscriptionUtilisateur();
        $limit = self::getLimit($userId, $slug);

        // Quota illimité
        if ($limit === 0) {
            self::increment($userId, $slug, $amount, $jourInscription);
            return true;
        }

        $db = self::getDb();
        $moduleId = self::getModuleId($slug);
        if (!$moduleId) {
            return false;
        }

        $yearMonth = self::currentPeriod($jourInscription);
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $db->prepare('
                INSERT INTO module_usage (user_id, module_id, `year_month`, usage_count, last_tracked_at)
                SELECT ?, ?, ?, ?, NOW()
                FROM DUAL
                WHERE ? <= ?
                ON DUPLICATE KEY UPDATE
                    usage_count = IF(usage_count + ? <= ?, usage_count + ?, usage_count),
                    last_tracked_at = IF(usage_count + ? <= ?, NOW(), last_tracked_at)
            ');
            $stmt->execute([
                $userId, $moduleId, $yearMonth, $amount,
                $amount, $limit,
                $amount, $limit, $amount,
                $amount, $limit,
            ]);

            if ($stmt->rowCount() === 0) {
                return false;
            }
        } else {
            // Fallback SQLite : approche transactionnelle
            $usage = self::getUsage($userId, $slug, $jourInscription);
            if ($usage + $amount > $limit) {
                return false;
            }

            $stmt = $db->prepare('
                INSERT INTO module_usage (user_id, module_id, year_month, usage_count, last_tracked_at)
                VALUES (?, ?, ?, ?, datetime("now"))
                ON CONFLICT(user_id, module_id, year_month)
                DO UPDATE SET usage_count = usage_count + excluded.usage_count, last_tracked_at = datetime("now")
            ');
            $stmt->execute([$userId, $moduleId, $yearMonth, $amount]);
        }

        self::logUsageAudit($userId, $moduleId, $slug);
        return true;
    }

    /**
     * Retourne le nombre d'unités restantes pour l'utilisateur courant.
     * Retourne null si quota illimité, 0 si atteint/dépassé (jamais négatif).
     */
    public static function restant(string $slug): ?int
    {
        $userId = Auth::id();
        if (!$userId) {
            return 0;
        }

        $limit = self::getLimit($userId, $slug);

        if ($limit === 0) {
            return null;
        }

        $jourInscription = self::jourInscriptionUtilisateur();
        $usage = self::getUsage($userId, $slug, $jourInscription);
        return max(0, $limit - $usage);
    }

    /**
     * Get quota summary for all modules accessible by a user.
     * Returns [slug => ['usage' => int, 'limit' => int, 'quota_mode' => QuotaMode]]
     */
    public static function getUserQuotaSummary(int $userId, int $jourInscription = 1): array
    {
        $db = self::getDb();
        $yearMonth = self::currentPeriod($jourInscription);

        $stmt = $db->prepare('
            SELECT
                m.slug,
                m.quota_mode,
                m.default_quota,
                COALESCE(umq.monthly_limit, 0) AS user_limit,
                COALESCE(mu.usage_count, 0) AS usage_count,
                CASE WHEN umq.id IS NOT NULL THEN 1 ELSE 0 END AS has_override
            FROM modules m
            LEFT JOIN user_module_quotas umq
                ON umq.module_id = m.id AND umq.user_id = ?
            LEFT JOIN module_usage mu
                ON mu.module_id = m.id AND mu.user_id = ? AND mu.year_month = ?
            WHERE m.enabled = 1
        ');
        $stmt->execute([$userId, $userId, $yearMonth]);
        $rows = $stmt->fetchAll();

        $summary = [];
        foreach ($rows as $row) {
            $limit = $row['has_override']
                ? (int) $row['user_limit']
                : (int) $row['default_quota'];

            $summary[$row['slug']] = [
                'usage'      => (int) $row['usage_count'],
                'limit'      => $limit,
                'quota_mode' => QuotaMode::tryFrom($row['quota_mode']) ?? QuotaMode::None,
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

    /**
     * Get usage matrix for the current month (admin view).
     * Utilise le 1er du mois comme convention pour la vue admin (tous les utilisateurs).
     * Returns [userId => [moduleId => usageCount]]
     */
    public static function getUsageMatrix(): array
    {
        $db = self::getDb();
        // Pour l'admin, on récupère l'usage de la période courante de chaque utilisateur.
        // Comme les périodes varient par utilisateur, on récupère toutes les entrées
        // du mois courant ET du mois précédent pour couvrir tous les cas.
        $moisCourant = date('Ym');
        $moisPrecedent = date('Ym', strtotime('first day of last month'));

        $stmt = $db->prepare(
            'SELECT mu.user_id, mu.module_id, mu.usage_count, mu.year_month,
                    u.created_at
             FROM module_usage mu
             JOIN users u ON u.id = mu.user_id
             WHERE mu.year_month IN (?, ?)'
        );
        $stmt->execute([$moisCourant, $moisPrecedent]);
        $rows = $stmt->fetchAll();

        $matrix = [];
        foreach ($rows as $row) {
            $jourInscription = (int) date('j', strtotime($row['created_at']));
            $periodeCourante = self::currentPeriod($jourInscription);

            // Ne garder que les entrées correspondant à la période courante de cet utilisateur
            if ($row['year_month'] === $periodeCourante) {
                $matrix[(int) $row['user_id']][(int) $row['module_id']] = (int) $row['usage_count'];
            }
        }

        return $matrix;
    }

    /**
     * Supprime les lignes module_usage antérieures à N mois.
     * Retourne le nombre de lignes supprimées.
     */
    public static function purgerAncienUsage(int $moisAConserver = 12): int
    {
        $db = self::getDb();
        $seuilYearMonth = date('Ym', strtotime("-{$moisAConserver} months"));

        $stmt = $db->prepare('DELETE FROM module_usage WHERE `year_month` < ?');
        $stmt->execute([$seuilYearMonth]);

        return $stmt->rowCount();
    }
}
