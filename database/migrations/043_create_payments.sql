-- @up
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    stripe_session_id VARCHAR(255) DEFAULT NULL,
    stripe_payment_intent VARCHAR(255) DEFAULT NULL,
    montant DECIMAL(8,2) NOT NULL,
    devise VARCHAR(3) NOT NULL DEFAULT 'EUR',
    statut VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);

-- @down
DROP TABLE IF EXISTS payments;
