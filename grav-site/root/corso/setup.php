<?php
/**
 * Corso Base Human Design - Setup DB (one-time, self-deletes)
 * Access: https://valentinarussobg5.com/corso/setup.php?token=corso_setup_2026_v2r9k3
 *
 * Idempotente: crea hd_users/hd_login_attempts se mancanti (stesso schema di
 * hd-setup.php), aggiunge la colonna role, crea courses/lessons/enrollments/
 * forum_posts, crea il primo account admin.
 *
 * SICUREZZA: richiede che grav-site/root/hd-db-config.php esista GIA sul
 * server (creato a mano via FTP/cPanel, mai in git). Non genera piu il file
 * con credenziali hardcoded nel sorgente — quel pattern (ereditato da
 * hd-setup.php) finiva in git history in chiaro (SEC-CORSO-001, review
 * 28/08/2026). Se manca, questo script si ferma e basta.
 */

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!hash_equals('corso_setup_2026_v2r9k3', $token)) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'error' => 'Forbidden']));
}

$log = [];
$errors = [];

$configPath = __DIR__ . '/../hd-db-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die(json_encode([
        'ok' => false,
        'error' => 'hd-db-config.php mancante. Va creato a mano sul server (via FTP/cPanel File Manager), MAI committato in git. Contenuto atteso: define(HD_DB_HOST/HD_DB_NAME/HD_DB_USER/HD_DB_PASS/HD_DB_PORT/HD_PEPPER/HD_BASE_URL/HD_FROM_EMAIL/HD_FROM_NAME) - vedi grav-site/root/hd-db.php per i nomi esatti delle costanti.',
    ], JSON_PRETTY_PRINT));
}
$log[] = 'hd-db-config.php trovato, riusato';

require_once $configPath;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', HD_DB_HOST, HD_DB_NAME),
        HD_DB_USER, HD_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $log[] = 'DB connection OK';

    $pdo->exec("CREATE TABLE IF NOT EXISTS hd_users (
        id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        email         VARCHAR(254)     NOT NULL UNIQUE,
        password_hash VARCHAR(255)     NOT NULL,
        name          VARCHAR(100)     NOT NULL DEFAULT '',
        role          ENUM('student','admin') NOT NULL DEFAULT 'student',
        created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        verified_at   DATETIME         NULL,
        verify_token  VARCHAR(64)      NULL,
        reset_token   VARCHAR(64)      NULL,
        reset_expires DATETIME         NULL,
        session_ver   INT UNSIGNED     NOT NULL DEFAULT 0,
        gdpr_consent  TINYINT(1)       NOT NULL DEFAULT 0,
        gdpr_date     DATETIME         NULL,
        INDEX idx_email         (email),
        INDEX idx_verify_token  (verify_token),
        INDEX idx_reset_token   (reset_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Table hd_users: OK';

    $col = $pdo->query("SHOW COLUMNS FROM hd_users LIKE 'role'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE hd_users ADD COLUMN role ENUM('student','admin') NOT NULL DEFAULT 'student' AFTER name");
        $log[] = 'Colonna hd_users.role aggiunta';
    } else {
        $log[] = 'Colonna hd_users.role gia presente';
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS hd_login_attempts (
        id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        identifier   VARCHAR(64)      NOT NULL,
        attempted_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_identifier_time (identifier, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Table hd_login_attempts: OK';

    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        slug         VARCHAR(100)     NOT NULL UNIQUE,
        title        VARCHAR(200)     NOT NULL,
        created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Table courses: OK';

    $pdo->exec("CREATE TABLE IF NOT EXISTS lessons (
        id              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        course_id       INT UNSIGNED     NOT NULL,
        position        INT UNSIGNED     NOT NULL DEFAULT 0,
        title           VARCHAR(200)     NOT NULL,
        bunny_video_id  VARCHAR(64)      NULL,
        pdf_slide_path  VARCHAR(255)     NULL,
        pdf_exercise_path VARCHAR(255)   NULL,
        created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at      DATETIME         NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        INDEX idx_course_position (course_id, position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Table lessons: OK';

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_enrollments (
        id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        user_id      INT UNSIGNED     NOT NULL,
        course_id    INT UNSIGNED     NOT NULL,
        created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_course (user_id, course_id),
        FOREIGN KEY (user_id) REFERENCES hd_users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Table course_enrollments: OK';

    // ON DELETE RESTRICT: una lezione con post non puo essere cancellata a
    // cascata (R19) - il codice applicativo usa soft-delete (lessons.deleted_at)
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_posts (
        id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        lesson_id    INT UNSIGNED     NOT NULL,
        user_id      INT UNSIGNED     NOT NULL,
        body         TEXT             NOT NULL,
        created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE RESTRICT,
        FOREIGN KEY (user_id) REFERENCES hd_users(id) ON DELETE CASCADE,
        INDEX idx_lesson_created (lesson_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Table forum_posts: OK';

    // ── Primo account admin (Valentina) ──────────────────────────────────────
    $adminEmail = 'valentinebers@gmail.com';
    $stmt = $pdo->prepare('SELECT id, role FROM hd_users WHERE email = ?');
    $stmt->execute([$adminEmail]);
    $existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAdmin) {
        if ($existingAdmin['role'] !== 'admin') {
            $pdo->prepare('UPDATE hd_users SET role = ? WHERE id = ?')->execute(['admin', $existingAdmin['id']]);
        }
        $log[] = 'Account admin gia esistente (' . $adminEmail . '), promosso ad admin se necessario. Password NON cambiata.';
    } else {
        $adminPassword = bin2hex(random_bytes(8));
        $peppered = hash_hmac('sha256', $adminPassword, HD_PEPPER);
        $passwordHash = defined('PASSWORD_ARGON2ID')
            ? password_hash($peppered, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 2])
            : password_hash($peppered, PASSWORD_BCRYPT, ['cost' => 12]);
        // GDPR: account creato manualmente dall'admin per un corso a pagamento,
        // non self-signup - consenso su base contrattuale, da confermare con
        // legal-guardian (vedi spec R18/Acceptance Criteria)
        $pdo->prepare('INSERT INTO hd_users (email, password_hash, name, role, verified_at, gdpr_consent, gdpr_date) VALUES (?, ?, ?, ?, NOW(), 1, NOW())')
            ->execute([$adminEmail, $passwordHash, 'Valentina Russo', 'admin']);
        $log[] = 'Account admin creato: ' . $adminEmail;
        $log[] = 'PASSWORD (mostrata solo ora, salvala subito): ' . $adminPassword;
    }

} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

if (empty($errors)) {
    $log[] = 'Setup completo. Autoeliminazione...';
    unlink(__FILE__);
    $log[] = 'Autoeliminato OK';
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
