-- Index sur audit_log.created_at pour accélérer les filtres par date et la purge
CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log (created_at);

-- Index sur modules.categorie_id pour le regroupement par catégorie dans la navbar
CREATE INDEX IF NOT EXISTS idx_modules_categorie_id ON modules (categorie_id);

-- Index sur module_usage.module_id pour les requêtes de résumé admin par module
CREATE INDEX IF NOT EXISTS idx_module_usage_module_id ON module_usage (module_id);

-- Index sur audit_log pour le rate limiting login (user_id + action + created_at)
CREATE INDEX IF NOT EXISTS idx_audit_log_action_created ON audit_log (action, created_at);
