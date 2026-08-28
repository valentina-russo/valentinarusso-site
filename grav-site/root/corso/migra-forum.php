<?php
/**
 * Corso Base Human Design - Migrazione: il FORUM come luogo a se
 * Access: https://valentinarussobg5.com/corso/migra-forum.php?token=corso_forum_2026_f0r7m
 *
 * Prima: i messaggi erano solo commenti appesi a una lezione.
 * Dopo: discussioni con titolo, che possono essere legate a una lezione
 * oppure generali della classe, con risposte in thread.
 *
 * Idempotente, nessun messaggio esistente viene perso: ogni post diventa
 * una discussione radice della sua lezione.
 */

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('corso_forum_2026_f0r7m', $_GET['token'] ?? '')) {
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

    if (!$hasCol('forum_posts', 'cohort_id')) {
        $pdo->exec('ALTER TABLE forum_posts ADD COLUMN cohort_id INT UNSIGNED NULL AFTER lesson_id');
        $log[] = 'forum_posts.cohort_id aggiunta';
    }
    if (!$hasCol('forum_posts', 'parent_id')) {
        $pdo->exec('ALTER TABLE forum_posts ADD COLUMN parent_id INT UNSIGNED NULL AFTER cohort_id');
        $log[] = 'forum_posts.parent_id aggiunta (risposte in thread)';
    }
    if (!$hasCol('forum_posts', 'title')) {
        $pdo->exec('ALTER TABLE forum_posts ADD COLUMN title VARCHAR(180) NULL AFTER parent_id');
        $log[] = 'forum_posts.title aggiunta';
    }

    // una discussione generale della classe non ha lezione
    $col = $pdo->query("SHOW COLUMNS FROM forum_posts LIKE 'lesson_id'")->fetch();
    if ($col && strtoupper($col['Null']) === 'NO') {
        // la FK va tolta prima di rendere la colonna nullable
        foreach ($pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts'
                                AND COLUMN_NAME = 'lesson_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll() as $fk) {
            $pdo->exec('ALTER TABLE forum_posts DROP FOREIGN KEY `' . $fk['CONSTRAINT_NAME'] . '`');
        }
        $pdo->exec('ALTER TABLE forum_posts MODIFY lesson_id INT UNSIGNED NULL');
        $pdo->exec('ALTER TABLE forum_posts ADD CONSTRAINT fk_forum_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE RESTRICT');
        $log[] = 'lesson_id ora facoltativo (discussioni generali di classe)';
    }

    $idx = array_column($pdo->query('SHOW INDEX FROM forum_posts')->fetchAll(), 'Key_name');
    if (!in_array('idx_cohort_created', $idx, true)) {
        $pdo->exec('ALTER TABLE forum_posts ADD INDEX idx_cohort_created (cohort_id, created_at)');
    }
    if (!in_array('idx_parent', $idx, true)) {
        $pdo->exec('ALTER TABLE forum_posts ADD INDEX idx_parent (parent_id)');
    }

    // I messaggi esistenti diventano discussioni radice della loro lezione
    $n = $pdo->exec('UPDATE forum_posts p JOIN lessons l ON l.id = p.lesson_id
                     SET p.cohort_id = l.cohort_id WHERE p.cohort_id IS NULL');
    $log[] = $n . ' messaggi collegati alla loro classe';

    $n = $pdo->exec("UPDATE forum_posts p JOIN lessons l ON l.id = p.lesson_id
                     SET p.title = CONCAT('Compito · Lezione ', l.position)
                     WHERE p.title IS NULL AND p.parent_id IS NULL");
    $log[] = $n . ' discussioni esistenti hanno ricevuto un titolo';

} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
