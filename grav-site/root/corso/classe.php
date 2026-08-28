<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$isAdmin = corsoIsAdmin((int)$user['id']);
$cohortId = (int)($_GET['id'] ?? 0);

$classe = corsoCohort($cohortId);
if (!$classe || $classe['archived_at']) {
    http_response_code(404);
    corsoHtmlHead('Non trovato');
    corsoNav($user, $isAdmin);
    echo '<div class="wrap"><div class="card empty"><p>Questo corso non esiste.</p>'
       . '<p><a class="btn ghost" href="index.php">Torna ai miei corsi</a></p></div></div>';
    corsoHtmlFoot();
    exit;
}

// R11: controllo server-side ad ogni richiesta, non solo nascosto nell'UI
corsoRequireEnrollment((int)$user['id'], $cohortId);

$stmt = hdDb()->prepare('SELECT id, position, title, bunny_video_id FROM lessons
    WHERE cohort_id = ? AND deleted_at IS NULL ORDER BY position ASC');
$stmt->execute([$cohortId]);
$lessons = $stmt->fetchAll();

corsoHtmlHead($classe['course_title']);
corsoNav($user, $isAdmin, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow"><a href="index.php" style="color:inherit;text-decoration:none">&larr; I miei corsi</a></p>
    <h1 class="page"><?= htmlspecialchars($classe['course_title']) ?></h1>

    <h2 class="sect">Le lezioni</h2>
    <?php if (empty($lessons)): ?>
        <div class="card empty"><p>Nessuna lezione pubblicata per ora.</p>
        <p class="meta">La trovi qui appena Valentina carica la prima registrazione.</p></div>
    <?php else: ?>
        <?php foreach ($lessons as $l): ?>
            <a class="card card-row" href="lezione.php?id=<?= (int)$l['id'] ?>">
                <?= corsoLessonThumb($l['bunny_video_id'], (int)$l['position']) ?>
                <span class="grow">
                    <h3><?= htmlspecialchars($l['title']) ?></h3>
                    <span class="meta"><?= $l['bunny_video_id'] ? 'Registrazione disponibile' : 'Registrazione in arrivo' ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
