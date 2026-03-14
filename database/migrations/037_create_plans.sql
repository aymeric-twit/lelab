-- @up
CREATE TABLE IF NOT EXISTS plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    prix_mensuel DECIMAL(8,2) DEFAULT NULL,
    prix_annuel DECIMAL(8,2) DEFAULT NULL,
    quotas_defaut JSON DEFAULT NULL,
    modules_inclus JSON DEFAULT NULL,
    limites JSON DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Plan par défaut (gratuit)
INSERT IGNORE INTO plans (slug, nom, description, prix_mensuel, sort_order, quotas_defaut, modules_inclus, limites)
VALUES
    ('gratuit', 'Gratuit', 'Accès limité aux outils de base', 0, 1,
     '{}', '[]', '{"max_modules": 3}'),
    ('pro', 'Pro', 'Accès complet avec quotas élevés', 29.00, 2,
     '{}', '["*"]', '{"max_modules": null}'),
    ('enterprise', 'Enterprise', 'Usage illimité, support prioritaire', 99.00, 3,
     '{}', '["*"]', '{"max_modules": null}');

-- Ajouter la colonne plan_id aux utilisateurs
ALTER TABLE users ADD COLUMN plan_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE users ADD CONSTRAINT fk_users_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL;
CREATE INDEX idx_users_plan ON users (plan_id);

-- @down
ALTER TABLE users DROP FOREIGN KEY fk_users_plan;
ALTER TABLE users DROP INDEX idx_users_plan;
ALTER TABLE users DROP COLUMN plan_id;
DROP TABLE IF EXISTS plans;
