CREATE TABLE IF NOT EXISTS api_credits_usage (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cle_api         VARCHAR(100) NOT NULL,
    periode_id      VARCHAR(10) NOT NULL,
    usage_count     INT UNSIGNED NOT NULL DEFAULT 0,
    last_tracked_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_cle_periode (cle_api, periode_id),
    INDEX idx_periode_id (periode_id)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
