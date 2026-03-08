ALTER TABLE modules MODIFY COLUMN quota_mode
    ENUM('request','form_submit','api_call','url','none') NOT NULL DEFAULT 'none';
