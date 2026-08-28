<?php
/**
 * Corso Base Human Design - Forum: allegati, reazioni, post fissati
 * Access: https://valentinarussobg5.com/corso/migra-forum2.php?token=corso_forum2_2026_a11eg
 * Idempotente.
 */

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('corso_forum2_2026_a11eg', $_GET['token'] ?? '')) {
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

    if (!$hasCol('forum_posts', 'pinned')) {
        $pdo->exec('ALTER TABLE forum_posts ADD COLUMN pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER title');
        $log[] = 'forum_posts.pinned aggiunta (post fissati in alto)';
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_attachments (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id    INT UNSIGNED NOT NULL,
        path       VARCHAR(255) NOT NULL,
        orig_name  VARCHAR(180) NOT NULL,
        mime       VARCHAR(80)  NOT NULL,
        bytes      INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
        INDEX idx_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Tabella forum_attachments: OK';

    // una reazione per persona per emoji su un dato post
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_reactions (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id    INT UNSIGNED NOT NULL,
        user_id    INT UNSIGNED NOT NULL,
        emoji      VARCHAR(16)  NOT NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_post_user_emoji (post_id, user_id, emoji),
        FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES hd_users(id) ON DELETE CASCADE,
        INDEX idx_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = 'Tabella forum_reactions: OK';

    $idx = array_column($pdo->query('SHOW INDEX FROM forum_posts')->fetchAll(), 'Key_name');
    if (!in_array('idx_pinned', $idx, true)) {
        $pdo->exec('ALTER TABLE forum_posts ADD INDEX idx_pinned (pinned)');
    }

} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
