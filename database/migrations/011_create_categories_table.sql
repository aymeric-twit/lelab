-- Création de la table des catégories de plugins
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL UNIQUE,
    icone       VARCHAR(50) DEFAULT 'bi-folder',
    sort_order  INT UNSIGNED DEFAULT 100,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout de la colonne categorie_id sur modules
ALTER TABLE modules ADD COLUMN categorie_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE modules ADD CONSTRAINT fk_modules_categorie
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL;
