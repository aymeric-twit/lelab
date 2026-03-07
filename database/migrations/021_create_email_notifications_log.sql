CREATE TABLE IF NOT EXISTS email_notifications_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    type_notification VARCHAR(50) NOT NULL,
    module_slug     VARCHAR(100) DEFAULT NULL,
    `year_month`    CHAR(6) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dedup (user_id, type_notification, module_slug, `year_month`),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
