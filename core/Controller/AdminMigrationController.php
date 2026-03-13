<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\View\Layout;
use PDO;

class AdminMigrationController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $db = Connection::get();

        $resultats = null;

        if ($req->method() === 'POST') {
            $resultats = $this->executerMigrations($db);
        }

        Layout::render('layout', [
            'template'          => 'admin/migrations',
            'pageTitle'         => 'Migrations',
            'currentUser'       => $user,
            'accessibleModules' => [],
            'adminPage'         => 'migrations',
            'resultats'         => $resultats,
        ]);
    }

    /**
     * @return array{ok: int, skip: int, err: int, details: list<array{nom: string, statut: string, message?: string}>}
     */
    private function executerMigrations(PDO $db): array
    {
        $migrations = $this->definirMigrations($db);

        $ok = 0;
        $skip = 0;
        $err = 0;
        $details = [];

        foreach ($migrations as $m) {
            $label = $m['nom'];

            if (!($m['test'])()) {
                $details[] = ['nom' => $label, 'statut' => 'skip'];
                $skip++;
                continue;
            }

            try {
                $db->exec($m['sql']);
                $details[] = ['nom' => $label, 'statut' => 'ok'];
                $ok++;
            } catch (\Throwable $e) {
                $details[] = ['nom' => $label, 'statut' => 'fail', 'message' => $e->getMessage()];
                $err++;
            }
        }

        return ['ok' => $ok, 'skip' => $skip, 'err' => $err, 'details' => $details];
    }

    private function colonneExiste(PDO $db, string $table, string $colonne): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $colonne]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableExiste(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return list<array{nom: string, test: callable, sql: string}>
     */
    private function definirMigrations(PDO $db): array
    {
        return [
            // 024
            [
                'nom' => '024 — unsubscribe_token sur users',
                'test' => fn() => !$this->colonneExiste($db, 'users', 'unsubscribe_token'),
                'sql' => 'ALTER TABLE users ADD COLUMN unsubscribe_token VARCHAR(64) DEFAULT NULL',
            ],
            [
                'nom' => '024 — table user_notification_preferences',
                'test' => fn() => !$this->tableExiste($db, 'user_notification_preferences'),
                'sql' => "CREATE TABLE user_notification_preferences (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    type_notification VARCHAR(50) NOT NULL,
                    actif TINYINT(1) NOT NULL DEFAULT 1,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_user_type (user_id, type_notification),
                    CONSTRAINT fk_unp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],

            // 026
            [
                'nom' => '026 — table login_history',
                'test' => fn() => !$this->tableExiste($db, 'login_history'),
                'sql' => "CREATE TABLE login_history (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent VARCHAR(500) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_lh_user (user_id),
                    CONSTRAINT fk_lh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],

            // 027
            [
                'nom' => '027 — pending_email sur users',
                'test' => fn() => !$this->colonneExiste($db, 'users', 'pending_email'),
                'sql' => 'ALTER TABLE users ADD COLUMN pending_email VARCHAR(255) DEFAULT NULL',
            ],
            [
                'nom' => '027 — pending_email_token sur users',
                'test' => fn() => !$this->colonneExiste($db, 'users', 'pending_email_token'),
                'sql' => 'ALTER TABLE users ADD COLUMN pending_email_token VARCHAR(64) DEFAULT NULL',
            ],
            [
                'nom' => '027 — pending_email_expires sur users',
                'test' => fn() => !$this->colonneExiste($db, 'users', 'pending_email_expires'),
                'sql' => 'ALTER TABLE users ADD COLUMN pending_email_expires DATETIME DEFAULT NULL',
            ],

            // 028
            [
                'nom' => '028 — force_password_reset sur users',
                'test' => fn() => !$this->colonneExiste($db, 'users', 'force_password_reset'),
                'sql' => 'ALTER TABLE users ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0',
            ],

            // 029
            [
                'nom' => '029 — expires_at sur user_module_access',
                'test' => fn() => !$this->colonneExiste($db, 'user_module_access', 'expires_at'),
                'sql' => 'ALTER TABLE user_module_access ADD COLUMN expires_at DATETIME DEFAULT NULL',
            ],

            // 030
            [
                'nom' => '030 — table user_groups',
                'test' => fn() => !$this->tableExiste($db, 'user_groups'),
                'sql' => "CREATE TABLE user_groups (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            [
                'nom' => '030 — table user_group_members',
                'test' => fn() => !$this->tableExiste($db, 'user_group_members'),
                'sql' => "CREATE TABLE user_group_members (
                    group_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    PRIMARY KEY (group_id, user_id),
                    CONSTRAINT fk_ugm_group FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
                    CONSTRAINT fk_ugm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            [
                'nom' => '030 — table group_module_access',
                'test' => fn() => !$this->tableExiste($db, 'group_module_access'),
                'sql' => "CREATE TABLE group_module_access (
                    group_id INT UNSIGNED NOT NULL,
                    module_id INT UNSIGNED NOT NULL,
                    granted TINYINT(1) NOT NULL DEFAULT 1,
                    PRIMARY KEY (group_id, module_id),
                    CONSTRAINT fk_gma_group FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
                    CONSTRAINT fk_gma_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],

            // 031
            [
                'nom' => '031 — totp_secret sur users',
                'test' => fn() => !$this->colonneExiste($db, 'users', 'totp_secret'),
                'sql' => 'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL',
            ],
            [
                'nom' => '031 — totp_enabled sur users',
                'test' => fn() => !$this->colonneExiste($db, 'users', 'totp_enabled'),
                'sql' => 'ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0',
            ],

            // 032
            [
                'nom' => '032 — table api_credits_usage',
                'test' => fn() => !$this->tableExiste($db, 'api_credits_usage'),
                'sql' => "CREATE TABLE api_credits_usage (
                    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    cle_api         VARCHAR(100) NOT NULL,
                    periode_id      VARCHAR(10) NOT NULL,
                    usage_count     INT UNSIGNED NOT NULL DEFAULT 0,
                    last_tracked_at DATETIME DEFAULT NULL,
                    UNIQUE KEY uk_cle_periode (cle_api, periode_id),
                    INDEX idx_periode_id (periode_id)
                ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],

            // 033
            [
                'nom' => '033 — api_credits_period sur modules',
                'test' => fn() => !$this->colonneExiste($db, 'modules', 'api_credits_period'),
                'sql' => "ALTER TABLE modules ADD COLUMN api_credits_period VARCHAR(20) NOT NULL DEFAULT 'mensuel' AFTER default_quota",
            ],
            [
                'nom' => '033 — api_credits_default sur modules',
                'test' => fn() => !$this->colonneExiste($db, 'modules', 'api_credits_default'),
                'sql' => 'ALTER TABLE modules ADD COLUMN api_credits_default INT UNSIGNED NOT NULL DEFAULT 0 AFTER api_credits_period',
            ],

            // 034
            [
                'nom' => '034 — anonymiser IPs audit_log (RGPD)',
                'test' => fn() => (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE ip_address IS NOT NULL AND ip_address != '' AND ip_address LIKE '%.%.%.%' AND ip_address NOT LIKE '%.0'")->fetchColumn() > 0,
                'sql' => "UPDATE audit_log SET ip_address = CONCAT(SUBSTRING_INDEX(ip_address, '.', 3), '.0') WHERE ip_address IS NOT NULL AND ip_address != '' AND ip_address LIKE '%.%.%.%' AND ip_address NOT LIKE '%.0'",
            ],
            [
                'nom' => '034 — anonymiser IPs login_history (RGPD)',
                'test' => fn() => $this->tableExiste($db, 'login_history') && (int) $db->query("SELECT COUNT(*) FROM login_history WHERE ip_address IS NOT NULL AND ip_address != '' AND ip_address LIKE '%.%.%.%' AND ip_address NOT LIKE '%.0'")->fetchColumn() > 0,
                'sql' => "UPDATE login_history SET ip_address = CONCAT(SUBSTRING_INDEX(ip_address, '.', 3), '.0') WHERE ip_address IS NOT NULL AND ip_address != '' AND ip_address LIKE '%.%.%.%' AND ip_address NOT LIKE '%.0'",
            ],
        ];
    }
}
