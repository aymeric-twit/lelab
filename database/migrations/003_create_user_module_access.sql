CREATE TABLE IF NOT EXISTS user_module_access (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    module_id   INT UNSIGNED NOT NULL,
    granted     TINYINT(1) NOT NULL DEFAULT 1,
    granted_by  INT UNSIGNED DEFAULT NULL,
    granted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_module (user_id, module_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id)  ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
