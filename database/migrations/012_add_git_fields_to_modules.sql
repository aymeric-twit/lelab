-- Migration 012 : Ajout des champs Git pour l'installation de plugins depuis GitHub

ALTER TABLE modules
    ADD COLUMN git_url VARCHAR(500) DEFAULT NULL,
    ADD COLUMN git_branche VARCHAR(100) DEFAULT 'main',
    ADD COLUMN git_dernier_pull DATETIME DEFAULT NULL,
    ADD COLUMN git_dernier_commit VARCHAR(40) DEFAULT NULL;
