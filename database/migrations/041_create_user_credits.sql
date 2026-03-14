-- @up
CREATE TABLE IF NOT EXISTS user_credits (
    user_id INT UNSIGNED PRIMARY KEY,
    credits_utilises INT UNSIGNED NOT NULL DEFAULT 0,
    credits_limite INT UNSIGNED NOT NULL DEFAULT 50,
    periode_debut DATE NOT NULL,
    periode_fin DATE NOT NULL,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE plans ADD COLUMN credits_mensuels INT UNSIGNED NOT NULL DEFAULT 50;

-- Mettre à jour les plans existants
UPDATE plans SET credits_mensuels = 50 WHERE slug = 'gratuit';
UPDATE plans SET credits_mensuels = 500 WHERE slug = 'pro';
UPDATE plans SET credits_mensuels = 2000 WHERE slug = 'enterprise';

-- @down
ALTER TABLE plans DROP COLUMN credits_mensuels;
DROP TABLE IF EXISTS user_credits;
