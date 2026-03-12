ALTER TABLE users ADD COLUMN pending_email VARCHAR(255) DEFAULT NULL AFTER email;
ALTER TABLE users ADD COLUMN pending_email_token VARCHAR(64) DEFAULT NULL AFTER pending_email;
ALTER TABLE users ADD COLUMN pending_email_expires DATETIME DEFAULT NULL AFTER pending_email_token;
