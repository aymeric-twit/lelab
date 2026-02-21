CREATE TABLE IF NOT EXISTS user_module_quotas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    module_id       INT UNSIGNED NOT NULL,
    monthly_limit   INT UNSIGNED NOT NULL DEFAULT 0,
    updated_by      INT UNSIGNED DEFAULT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_module_quota (user_id, module_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (module_id)  REFERENCES modules(id)  ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
