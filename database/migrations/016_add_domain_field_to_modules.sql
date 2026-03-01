-- Identifiant du champ HTML (id) à pré-remplir avec le domaine utilisateur
ALTER TABLE modules ADD COLUMN domain_field VARCHAR(100) DEFAULT NULL;
