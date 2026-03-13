-- Anonymisation RGPD : tronquer le dernier octet des adresses IPv4 existantes
UPDATE audit_log
SET ip_address = CONCAT(SUBSTRING_INDEX(ip_address, '.', 3), '.0')
WHERE ip_address IS NOT NULL
  AND ip_address != ''
  AND ip_address LIKE '%.%.%.%'
  AND ip_address NOT LIKE '%.0';

UPDATE login_history
SET ip_address = CONCAT(SUBSTRING_INDEX(ip_address, '.', 3), '.0')
WHERE ip_address IS NOT NULL
  AND ip_address != ''
  AND ip_address LIKE '%.%.%.%'
  AND ip_address NOT LIKE '%.0';
