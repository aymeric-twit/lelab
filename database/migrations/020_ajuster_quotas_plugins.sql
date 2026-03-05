-- Ajustement des quotas plugins après audit détaillé des fonctionnements

-- Facettes : 500 → 3000 (1 analyse = 200–1000+ appels SEMrush, 500 insuffisant pour une seule analyse)
UPDATE modules SET default_quota = 3000 WHERE slug = 'facettes';

-- Suggest Checker : 500 → 2000 (1 appel/keyword, un SEO teste 200-500 keywords/session)
UPDATE modules SET default_quota = 2000 WHERE slug = 'suggest';

-- URL Organizer : api_call → form_submit (aucune API externe, traitement 100% local)
UPDATE modules SET quota_mode = 'form_submit' WHERE slug = 'url-organizer';
