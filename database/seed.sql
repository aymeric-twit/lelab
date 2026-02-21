-- Admin par defaut (password: changeme)
INSERT IGNORE INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@seo-platform.local', '$2y$12$DepWNo/3kj0dzyeQaSEQ/.OoTC7sEEnJAg.a4q7sb0oA.bDx3KSAC', 'admin');

-- Modules
INSERT IGNORE INTO modules (slug, name, description, version, icon, sort_order) VALUES
('kwcible', 'KWCible', 'Analyse sémantique SEO de pages web', '1.0.0', 'bi-search', 10),
('crux', 'CrUX History Explorer', 'Visualisation historique des Core Web Vitals', '1.0.0', 'bi-speedometer2', 20),
('suggest', 'Suggest Checker', 'Vérification des suggestions Google', '1.0.0', 'bi-chat-left-text', 30),
('kg-entities', 'KG Entities', 'Audit Knowledge Graph et Schema.org', '1.0.0', 'bi-diagram-3', 40),
('search-console', 'Search Console', 'Dashboard Google Search Console', '1.0.0', 'bi-graph-up', 50);

-- Admin a acces a tous les modules
INSERT IGNORE INTO user_module_access (user_id, module_id, granted, granted_by)
SELECT 1, id, 1, 1 FROM modules;

-- Quota modes par module
UPDATE modules SET quota_mode='form_submit', default_quota=100 WHERE slug='kwcible';
UPDATE modules SET quota_mode='form_submit', default_quota=100 WHERE slug='crux';
UPDATE modules SET quota_mode='api_call',    default_quota=500 WHERE slug='suggest';
UPDATE modules SET quota_mode='form_submit', default_quota=200 WHERE slug='kg-entities';
UPDATE modules SET quota_mode='none',        default_quota=0   WHERE slug='search-console';
