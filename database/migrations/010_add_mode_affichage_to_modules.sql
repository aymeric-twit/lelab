-- Ajout du mode d'affichage (embedded, iframe, passthrough) aux modules
ALTER TABLE modules
    ADD COLUMN mode_affichage VARCHAR(20) NOT NULL DEFAULT 'embedded' AFTER passthrough_all;

-- Backfill : les modules passthrough_all deviennent mode passthrough
UPDATE modules SET mode_affichage = 'passthrough' WHERE passthrough_all = 1;
