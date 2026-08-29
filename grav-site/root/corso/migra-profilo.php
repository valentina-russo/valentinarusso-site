<?php
/**
 * Corso Base Human Design - Vista lezione pulita + profilo studentessa
 * Access: https://valentinarussobg5.com/corso/migra-profilo.php?token=corso_prof_2026_pr0f1
 *
 * - lessons: description, audio_path (versione audio della lezione)
 * - lesson_notes: appunti personali per lezione, privati, un solo record per
 *   coppia (lezione, utente)
 * - hd_users: phone, avatar_path, email_notifications (preferenza, non
 *   ancora agganciata a un invio automatico reale)
 *
 * Idempotente.
 */

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('corso_prof_2026_pr0f1', $_GET['token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'error' => 'Forbidden']));
}

$configPath = __DIR__ . '/../hd-db-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'hd-db-config.php mancante']));
}
require_once $configPath;

$log = [];
$errors = [];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', HD_DB_HOST, HD_DB_NAME),
        HD_DB_USER, HD_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $hasCol = fn(string $t, string $c) => (bool)$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->fetch();

    foreach ([
        ['lessons', 'description', "ALTER TABLE lessons ADD COLUMN description TEXT NULL AFTER title"],
        ['lessons', 'audio_path', "ALTER TABLE lessons ADD COLUMN audio_path VARCHAR(255) NULL AFTER pdf_exercise_path"],
        ['hd_users', 'phone', "ALTER TABLE hd_users ADD COLUMN phone VARCHAR(30) NULL AFTER name"],
        ['hd_users', 'avatar_path', "ALTER TABLE hd_users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER phone"],
        ['hd_users', 'email_notifications', "ALTER TABLE hd_users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_path"],
    ] as [$t, $c, $sql]) {
        if (!$hasCol($t, $c)) {
            $pdo->exec($sql);
            $log[] = "$t.$c aggiunta";
        } else {
            $log[] = "$t.$c gia presente";
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_notes (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lesson_id   INT UNSIGNED NOT NULL,
        user_id     INT UNSIGNED NOT NULL,
        body        TEXT         NOT NULL,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_lesson_user (lesson_id, user_id),
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES hd_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Tabella lesson_notes: OK';

} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
