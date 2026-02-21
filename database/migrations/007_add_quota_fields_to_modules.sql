ALTER TABLE modules
    ADD COLUMN quota_mode ENUM('request','form_submit','api_call','none') NOT NULL DEFAULT 'none' AFTER sort_order,
    ADD COLUMN default_quota INT UNSIGNED NOT NULL DEFAULT 0 AFTER quota_mode;
