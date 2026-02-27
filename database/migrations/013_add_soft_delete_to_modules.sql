-- Soft delete des modules : conservation des réglages utilisateurs lors de la désinstallation
ALTER TABLE modules
    ADD COLUMN desinstalle_le DATETIME DEFAULT NULL,
    ADD COLUMN desinstalle_par INT UNSIGNED DEFAULT NULL;
