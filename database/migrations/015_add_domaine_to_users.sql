-- Ajout du domaine associé à chaque utilisateur (pré-remplissage automatique dans les plugins)
ALTER TABLE users ADD COLUMN domaine VARCHAR(255) DEFAULT NULL AFTER email;
