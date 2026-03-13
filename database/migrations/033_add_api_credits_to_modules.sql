ALTER TABLE modules
    ADD COLUMN api_credits_period VARCHAR(20) NOT NULL DEFAULT 'mensuel' AFTER default_quota,
    ADD COLUMN api_credits_default INT UNSIGNED NOT NULL DEFAULT 0 AFTER api_credits_period;
