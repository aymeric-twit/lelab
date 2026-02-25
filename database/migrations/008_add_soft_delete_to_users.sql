-- Ajout du soft delete sur la table users
ALTER TABLE users ADD COLUMN deleted_at DATETIME DEFAULT NULL;
