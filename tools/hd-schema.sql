-- ============================================================
-- HD Account System — Schema MySQL
-- Valentina Russo BG5® — valentinarussobg5.com
-- Versione: 1.0.0 — 2026-03-27
-- ============================================================
-- Eseguire manualmente su Aruba: pannello → MySQL → phpMyAdmin
-- Charset: utf8mb4 (emoji + caratteri speciali italiani)
-- ============================================================

CREATE TABLE IF NOT EXISTS hd_users (
    id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(254)     NOT NULL UNIQUE,
    password_hash VARCHAR(255)     NOT NULL,
    name          VARCHAR(100)     NOT NULL DEFAULT '',
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at   DATETIME         NULL,
    verify_token  VARCHAR(64)      NULL,
    reset_token   VARCHAR(64)      NULL,
    reset_expires DATETIME         NULL,
    session_ver   INT UNSIGNED     NOT NULL DEFAULT 0,   -- incrementa ad ogni password change/reset
    gdpr_consent  TINYINT(1)       NOT NULL DEFAULT 0,
    gdpr_date     DATETIME         NULL,
    INDEX idx_email         (email),
    INDEX idx_verify_token  (verify_token),
    INDEX idx_reset_token   (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hd_charts (
    id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED     NOT NULL,
    chart_name   VARCHAR(100)     NOT NULL DEFAULT 'Carta senza nome',
    birth_day    TINYINT UNSIGNED NOT NULL,
    birth_month  TINYINT UNSIGNED NOT NULL,
    birth_year   SMALLINT         NOT NULL,
    birth_hour   TINYINT UNSIGNED NOT NULL,
    birth_min    TINYINT UNSIGNED NOT NULL,
    birth_city   VARCHAR(150)     NOT NULL DEFAULT '',
    birth_tz     VARCHAR(60)      NOT NULL DEFAULT 'Europe/Rome',
    chart_json   MEDIUMTEXT       NOT NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES hd_users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hd_login_attempts (
    id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(64)      NOT NULL,   -- hash SHA-256 di email o IP
    attempted_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_time (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
