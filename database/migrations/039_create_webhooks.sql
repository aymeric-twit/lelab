-- @up
CREATE TABLE IF NOT EXISTS webhooks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(255) DEFAULT NULL,
    evenements JSON NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    dernier_envoi DATETIME DEFAULT NULL,
    dernier_statut INT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT UNSIGNED NOT NULL,
    evenement VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    statut_http INT DEFAULT NULL,
    reponse TEXT DEFAULT NULL,
    duree_ms INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook_logs_webhook (webhook_id),
    INDEX idx_webhook_logs_created (created_at)
);

-- @down
DROP TABLE IF EXISTS webhook_logs;
DROP TABLE IF EXISTS webhooks;
