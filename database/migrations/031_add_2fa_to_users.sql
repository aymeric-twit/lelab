ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER force_password_reset;
ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret;
