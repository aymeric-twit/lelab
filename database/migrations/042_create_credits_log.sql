-- @up
CREATE TABLE IF NOT EXISTS credits_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    module_slug VARCHAR(100) NOT NULL,
    module_name VARCHAR(100) NOT NULL,
    credits_deduits INT NOT NULL,
    credits_restants INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_credits_log_user (user_id, created_at)
);

-- @down
DROP TABLE IF EXISTS credits_log;
