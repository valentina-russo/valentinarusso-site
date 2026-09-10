CREATE TABLE IF NOT EXISTS hd_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(100) NOT NULL DEFAULT '',
    role          ENUM('student','admin') NOT NULL DEFAULT 'student',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at   DATETIME     NULL,
    verify_token  VARCHAR(64)  NULL,
    reset_token   VARCHAR(64)  NULL,
    reset_expires DATETIME     NULL,
    session_ver   INT UNSIGNED NOT NULL DEFAULT 0,
    gdpr_consent  TINYINT(1)   NOT NULL DEFAULT 0,
    gdpr_date     DATETIME     NULL,
    INDEX idx_email (email),
    INDEX idx_reset_token (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS courses (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cohorts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    position    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS course_enrollments (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id   INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    cohort_id INT UNSIGNED NULL,
    UNIQUE KEY uniq_iscrizione (user_id, cohort_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Come in produzione: una sola colonna identifier (impronta di email o IP).
CREATE TABLE IF NOT EXISTS hd_login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier   CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_id_time (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO courses (id, title) VALUES (1, 'Corso Base Human Design')
    ON DUPLICATE KEY UPDATE title = VALUES(title);
INSERT INTO cohorts (id, course_id, name, position) VALUES
    (1, 1, 'Classe 1 - Semestre 1', 1),
    (2, 1, 'Classe 1 - Semestre 2', 2)
    ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Colonne e tabelle che il pannello si aspetta oltre al percorso di iscrizione.
ALTER TABLE courses  ADD COLUMN IF NOT EXISTS slug        VARCHAR(80)  NOT NULL DEFAULT 'corso-base';
ALTER TABLE hd_users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL;
CREATE TABLE IF NOT EXISTS forum_posts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cohort_id INT UNSIGNED NULL, user_id INT UNSIGNED NULL, parent_id INT UNSIGNED NULL, title VARCHAR(200) NULL, body TEXT NULL, pinned TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS forum_attachments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT UNSIGNED NULL, filename VARCHAR(255) NULL, mime VARCHAR(100) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS forum_reactions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT UNSIGNED NULL, user_id INT UNSIGNED NULL, emoji VARCHAR(16) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS lessons (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, course_id INT UNSIGNED NULL, cohort_id INT UNSIGNED NULL, title VARCHAR(200) NULL, position INT NOT NULL DEFAULT 1, video_guid VARCHAR(64) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS lesson_notes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lesson_id INT UNSIGNED NULL, user_id INT UNSIGNED NULL, body TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
