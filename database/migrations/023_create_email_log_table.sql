CREATE TABLE IF NOT EXISTS email_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    destinataire    VARCHAR(255) NOT NULL,
    sujet           VARCHAR(500) NOT NULL,
    type_email      VARCHAR(50) DEFAULT NULL,
    statut          VARCHAR(20) NOT NULL DEFAULT 'envoye',
    erreur          TEXT DEFAULT NULL,
    user_id         INT UNSIGNED DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_type (type_email),
    INDEX idx_statut (statut)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
