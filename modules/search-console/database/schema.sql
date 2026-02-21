-- ============================================================
-- Google Search Console - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS search_console
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE search_console;

-- ------------------------------------------------------------
-- Sites enregistrés dans Search Console
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sites (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_url    VARCHAR(500)  NOT NULL COMMENT 'URL du site (ex: sc-domain:example.com)',
    label       VARCHAR(255)  DEFAULT NULL COMMENT 'Label lisible',
    active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_url (site_url)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tokens OAuth2 (un par utilisateur / compte Google)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    access_token    TEXT          NOT NULL,
    refresh_token   TEXT          DEFAULT NULL,
    token_type      VARCHAR(50)   DEFAULT 'Bearer',
    expires_at      DATETIME      NOT NULL,
    scope           TEXT          DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Données de performance Search Console
-- Clé unique sur (site_id, data_date, page, query, country, device, search_type)
-- pour éviter les doublons lors des re-synchronisations.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS performance_data (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED    NOT NULL,
    data_date       DATE            NOT NULL COMMENT 'Date de la donnée',
    page            VARCHAR(2048)   NOT NULL DEFAULT '' COMMENT 'URL de la page',
    query           VARCHAR(500)    NOT NULL DEFAULT '' COMMENT 'Requête de recherche',
    country         CHAR(3)         NOT NULL DEFAULT '' COMMENT 'Code pays ISO 3166-1 alpha-3',
    device          VARCHAR(20)     NOT NULL DEFAULT '' COMMENT 'DESKTOP, MOBILE, TABLET',
    search_type     VARCHAR(20)     NOT NULL DEFAULT 'web' COMMENT 'web, image, video',
    clicks          INT UNSIGNED    NOT NULL DEFAULT 0,
    impressions     INT UNSIGNED    NOT NULL DEFAULT 0,
    ctr             DECIMAL(8,6)    NOT NULL DEFAULT 0 COMMENT 'Click-through rate (0.0 - 1.0)',
    position        DECIMAL(8,2)    NOT NULL DEFAULT 0 COMMENT 'Position moyenne',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_perf_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,

    -- Index unique pour dédupliquer les données
    -- On utilise un hash pour contourner la limite de taille de clé sur page
    UNIQUE KEY uq_perf_row (site_id, data_date, search_type, country, device, query, page(255))
) ENGINE=InnoDB;

-- Index pour les requêtes dashboard fréquentes
CREATE INDEX idx_perf_date        ON performance_data (data_date);
CREATE INDEX idx_perf_site_date   ON performance_data (site_id, data_date);
CREATE INDEX idx_perf_query       ON performance_data (query(100));
CREATE INDEX idx_perf_page        ON performance_data (page(255));

-- ------------------------------------------------------------
-- Journal de synchronisation
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED    NOT NULL,
    search_type     VARCHAR(20)     NOT NULL DEFAULT 'web',
    date_from       DATE            NOT NULL,
    date_to         DATE            NOT NULL,
    rows_fetched    INT UNSIGNED    NOT NULL DEFAULT 0,
    rows_inserted   INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('running','success','error') NOT NULL DEFAULT 'running',
    error_message   TEXT            DEFAULT NULL,
    duration_sec    DECIMAL(8,2)    DEFAULT NULL,
    started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at     DATETIME        DEFAULT NULL,

    CONSTRAINT fk_sync_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_sync_site_date ON sync_logs (site_id, date_from, date_to);

-- ============================================================
-- Exemples de requêtes pour le dashboard
-- ============================================================

-- 1) Tendance quotidienne (clicks & impressions) pour un site
-- SELECT data_date,
--        SUM(clicks) AS total_clicks,
--        SUM(impressions) AS total_impressions,
--        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS avg_ctr,
--        AVG(position) AS avg_position
-- FROM performance_data
-- WHERE site_id = 1 AND data_date BETWEEN '2025-01-01' AND '2025-01-31'
-- GROUP BY data_date
-- ORDER BY data_date;

-- 2) Top requêtes par clicks sur une période
-- SELECT query,
--        SUM(clicks) AS total_clicks,
--        SUM(impressions) AS total_impressions,
--        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS avg_ctr,
--        AVG(position) AS avg_position
-- FROM performance_data
-- WHERE site_id = 1 AND data_date BETWEEN '2025-01-01' AND '2025-01-31' AND query != ''
-- GROUP BY query
-- ORDER BY total_clicks DESC
-- LIMIT 50;

-- 3) Top pages par impressions
-- SELECT page,
--        SUM(clicks) AS total_clicks,
--        SUM(impressions) AS total_impressions,
--        AVG(position) AS avg_position
-- FROM performance_data
-- WHERE site_id = 1 AND data_date BETWEEN '2025-01-01' AND '2025-01-31' AND page != ''
-- GROUP BY page
-- ORDER BY total_impressions DESC
-- LIMIT 50;

-- 4) Comparaison entre deux périodes
-- SELECT 'current' AS period, SUM(clicks), SUM(impressions), AVG(position)
-- FROM performance_data WHERE site_id = 1 AND data_date BETWEEN '2025-02-01' AND '2025-02-28'
-- UNION ALL
-- SELECT 'previous', SUM(clicks), SUM(impressions), AVG(position)
-- FROM performance_data WHERE site_id = 1 AND data_date BETWEEN '2025-01-01' AND '2025-01-31';

-- 5) Répartition par appareil
-- SELECT device, SUM(clicks) AS total_clicks, SUM(impressions) AS total_impressions
-- FROM performance_data
-- WHERE site_id = 1 AND data_date BETWEEN '2025-01-01' AND '2025-01-31'
-- GROUP BY device;

-- 6) Répartition par pays
-- SELECT country, SUM(clicks) AS total_clicks, SUM(impressions) AS total_impressions
-- FROM performance_data
-- WHERE site_id = 1 AND data_date BETWEEN '2025-01-01' AND '2025-01-31' AND country != ''
-- GROUP BY country
-- ORDER BY total_clicks DESC
-- LIMIT 20;
