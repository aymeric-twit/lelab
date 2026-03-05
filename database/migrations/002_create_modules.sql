CREATE TABLE IF NOT EXISTS modules (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(50) NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    version         VARCHAR(20) DEFAULT '1.0.0',
    icon            VARCHAR(50) DEFAULT 'bi-tools',
    enabled         TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT UNSIGNED DEFAULT 100,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
