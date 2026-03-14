-- @up
ALTER TABLE modules ADD COLUMN credits_par_analyse INT UNSIGNED NOT NULL DEFAULT 1;

-- Poids par module selon le coût réel
UPDATE modules SET credits_par_analyse = 5 WHERE slug = 'kwcible';
UPDATE modules SET credits_par_analyse = 5 WHERE slug = 'keywords-forge';
UPDATE modules SET credits_par_analyse = 3 WHERE slug = 'cannibalization-checker';
UPDATE modules SET credits_par_analyse = 2 WHERE slug = 'tfidf-analyzer';
UPDATE modules SET credits_par_analyse = 2 WHERE slug = 'sitemap-killer';
UPDATE modules SET credits_par_analyse = 1 WHERE slug = 'crux-history';
UPDATE modules SET credits_par_analyse = 1 WHERE slug = 'suggest';
UPDATE modules SET credits_par_analyse = 1 WHERE slug = 'kg-entities';
UPDATE modules SET credits_par_analyse = 1 WHERE slug = 'url-organizer';
UPDATE modules SET credits_par_analyse = 1 WHERE slug = 'htaccess-cleaner';
UPDATE modules SET credits_par_analyse = 1 WHERE slug = 'robotstxt-checker';
UPDATE modules SET credits_par_analyse = 0 WHERE slug = 'cache-warmer';

-- @down
ALTER TABLE modules DROP COLUMN credits_par_analyse;
