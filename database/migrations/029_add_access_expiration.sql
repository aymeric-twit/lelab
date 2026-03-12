ALTER TABLE user_module_access ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER granted_at;
