<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user = corsoRequireStudent();
$slug = $_GET['slug'] ?? '';

$stmt = hdDb()->prepare('SELECT id, slug, title FROM courses WHERE slug = ?');
$stmt->execute([$slug]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    corsoHtmlHead('Corso non trovato');
    echo '<div class="container"><div class="card"><p>Corso non trovato.</p><a href="index.php">Torna ai miei corsi</a></div></div>';
    corsoHtmlFoot();
    exit;
}

// R11: controllo server-side ad ogni richiesta, non solo nascosto nell'UI
corsoRequireEnrollment((int)$user['id'], (int)$course['id']);

$stmt = hdDb()->prepare('SELECT id, position, title FROM lessons WHERE course_id = ? AND deleted_at IS NULL ORDER BY position ASC');
$stmt->execute([$course['id']]);
$lessons = $stmt->fetchAll();

corsoHtmlHead($course['title']);
?>
<div class="top-nav">
    <a href="index.php">&larr; I miei corsi</a>
    <a href="logout.php">Esci</a>
</div>
<div class="container">
    <h1><?= htmlspecialchars($course['title']) ?></h1>
    <?php if (empty($lessons)): ?>
        <div class="card empty-state"><p>Nessuna classe ancora pubblicata.</p></div>
    <?php else: ?>
        <?php foreach ($lessons as $l): ?>
            <div class="card">
                <h3>Classe <?= (int)$l['position'] ?> &mdash; <?= htmlspecialchars($l['title']) ?></h3>
                <a class="btn secondary" href="lezione.php?id=<?= (int)$l['id'] ?>">Apri</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
