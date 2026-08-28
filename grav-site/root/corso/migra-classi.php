<?php
/**
 * Corso Base Human Design - Migrazione: introduce le CLASSI (coorti)
 * Access: https://valentinarussobg5.com/corso/migra-classi.php?token=corso_migra_2026_c1a55i
 *
 * Modello prima:  corso -> lezioni, corso -> iscritte
 * Modello dopo:   corso -> classi (max 3 attive) -> lezioni, classe -> iscritte
 *
 * Una "classe" e un gruppo di allieve che segue lo stesso corso in un dato
 * periodo (Classe 1 di gennaio, Classe 2 di marzo...), ognuna con le proprie
 * registrazioni e le proprie iscritte.
 *
 * Idempotente. Tutto cio che esiste finisce dentro una "Classe 1" creata
 * automaticamente, quindi nessun dato viene perso.
 */

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('corso_migra_2026_c1a55i', $_GET['token'] ?? '')) {
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS cohorts (
        id          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
        course_id   INT UNSIGNED     NOT NULL,
        name        VARCHAR(100)     NOT NULL,
        position    TINYINT UNSIGNED NOT NULL DEFAULT 1,
        created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        archived_at DATETIME         NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        INDEX idx_course_pos (course_id, position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Tabella classi (cohorts): OK';

    $hasCol = fn(string $t, string $c) => (bool)$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->fetch();

    if (!$hasCol('lessons', 'cohort_id')) {
        $pdo->exec('ALTER TABLE lessons ADD COLUMN cohort_id INT UNSIGNED NULL AFTER course_id');
        $pdo->exec('ALTER TABLE lessons ADD INDEX idx_cohort_pos (cohort_id, position)');
        $log[] = 'lessons.cohort_id aggiunta';
    } else {
        $log[] = 'lessons.cohort_id gia presente';
    }

    if (!$hasCol('course_enrollments', 'cohort_id')) {
        $pdo->exec('ALTER TABLE course_enrollments ADD COLUMN cohort_id INT UNSIGNED NULL AFTER course_id');
        $log[] = 'course_enrollments.cohort_id aggiunta';
    } else {
        $log[] = 'course_enrollments.cohort_id gia presente';
    }

    // La vecchia UNIQUE(user_id, course_id) impedisce di iscrivere la stessa
    // persona a due classi DELLO STESSO corso: va sostituita con
    // UNIQUE(user_id, cohort_id). Verificato sugli indici reali, non assunto.
    $idx = $pdo->query('SHOW INDEX FROM course_enrollments')->fetchAll();
    $names = array_unique(array_column($idx, 'Key_name'));
    if (in_array('uniq_user_course', $names, true)) {
        $pdo->exec('ALTER TABLE course_enrollments DROP INDEX uniq_user_course');
        $log[] = 'Vecchio indice uniq_user_course rimosso';
    }
    if (!in_array('uniq_user_cohort', $names, true)) {
        $pdo->exec('ALTER TABLE course_enrollments ADD UNIQUE KEY uniq_user_cohort (user_id, cohort_id)');
        $log[] = 'Indice uniq_user_cohort creato';
    }
    $log[] = 'Indici su course_enrollments: ' . implode(', ', array_unique(array_column($pdo->query('SHOW INDEX FROM course_enrollments')->fetchAll(), 'Key_name')));

    // Ogni corso esistente riceve la sua "Classe 1", che raccoglie tutto
    $courses = $pdo->query('SELECT id, title FROM courses')->fetchAll();
    $migrated = 0;
    foreach ($courses as $c) {
        $s = $pdo->prepare('SELECT id FROM cohorts WHERE course_id = ? ORDER BY position LIMIT 1');
        $s->execute([$c['id']]);
        $row = $s->fetch();
        if ($row) {
            $cohortId = (int)$row['id'];
        } else {
            $pdo->prepare('INSERT INTO cohorts (course_id, name, position) VALUES (?,?,1)')
                ->execute([$c['id'], 'Classe 1']);
            $cohortId = (int)$pdo->lastInsertId();
            $migrated++;
        }
        $pdo->prepare('UPDATE lessons SET cohort_id = ? WHERE course_id = ? AND cohort_id IS NULL')
            ->execute([$cohortId, $c['id']]);
        $pdo->prepare('UPDATE course_enrollments SET cohort_id = ? WHERE course_id = ? AND cohort_id IS NULL')
            ->execute([$cohortId, $c['id']]);
    }
    $log[] = $migrated . ' classi create, lezioni e iscritte esistenti collegate';

} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
