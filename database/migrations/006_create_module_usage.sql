CREATE TABLE IF NOT EXISTS module_usage (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    module_id       INT UNSIGNED NOT NULL,
    `year_month`    CHAR(6) NOT NULL,
    usage_count     INT UNSIGNED NOT NULL DEFAULT 0,
    last_tracked_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_user_module_month (user_id, module_id, `year_month`),
    INDEX idx_year_month (`year_month`),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id)  ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
