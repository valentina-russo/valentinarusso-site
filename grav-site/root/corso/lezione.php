<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user = corsoRequireStudent();
$lessonId = (int)($_GET['id'] ?? 0);

$stmt = hdDb()->prepare('SELECT l.*, c.title AS course_title, c.slug AS course_slug
    FROM lessons l JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND l.deleted_at IS NULL');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    corsoHtmlHead('Classe non trovata');
    echo '<div class="container"><div class="card"><p>Classe non trovata.</p></div></div>';
    corsoHtmlFoot();
    exit;
}

// R11: controllo server-side, anche indovinando l'id della classe
corsoRequireEnrollment((int)$user['id'], (int)$lesson['course_id']);

$postError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gestito qui stesso per semplicita (redirect PRG dopo il submit)
    $csrf = $_POST['csrf'] ?? '';
    if (!hdCsrfVerify($csrf, 'forum-post')) {
        $postError = 'Sessione scaduta, riprova.';
    } else {
        $body = $_POST['body'] ?? '';
        $validationError = corsoValidatePostBody($body);
        if ($validationError) {
            $postError = $validationError;
        } else {
            $stmt = hdDb()->prepare('INSERT INTO forum_posts (lesson_id, user_id, body) VALUES (?, ?, ?)');
            $stmt->execute([$lessonId, $user['id'], trim($body)]);
            header('Location: lezione.php?id=' . $lessonId . '#forum');
            exit;
        }
    }
}

$stmt = hdDb()->prepare('SELECT p.body, p.created_at, u.name, u.email, u.role
    FROM forum_posts p JOIN hd_users u ON u.id = p.user_id
    WHERE p.lesson_id = ? ORDER BY p.created_at ASC');
$stmt->execute([$lessonId]);
$posts = $stmt->fetchAll();

corsoHtmlHead($lesson['title']);
?>
<div class="top-nav">
    <a href="corso.php?slug=<?= urlencode($lesson['course_slug']) ?>">&larr; <?= htmlspecialchars($lesson['course_title']) ?></a>
    <a href="logout.php">Esci</a>
</div>
<div class="container">
    <h1><?= htmlspecialchars($lesson['title']) ?></h1>

    <?php if ($lesson['bunny_video_id']): ?>
        <iframe class="video-embed" src="<?= htmlspecialchars(bunnySignedEmbedUrl($lesson['bunny_video_id'])) ?>" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen loading="lazy"></iframe>
    <?php else: ?>
        <div class="card empty-state">Video non ancora caricato per questa classe.</div>
    <?php endif; ?>

    <div class="card">
        <h3>Materiali</h3>
        <?php if ($lesson['pdf_slide_path']): ?>
            <p><a href="materiale.php?lesson=<?= $lessonId ?>&type=slide" target="_blank">Slide (PDF)</a></p>
        <?php endif; ?>
        <?php if ($lesson['pdf_exercise_path']): ?>
            <p><a href="materiale.php?lesson=<?= $lessonId ?>&type=exercise" target="_blank">Esercizio pratico (PDF)</a></p>
        <?php endif; ?>
        <?php if (!$lesson['pdf_slide_path'] && !$lesson['pdf_exercise_path']): ?>
            <p class="muted">Nessun materiale caricato ancora.</p>
        <?php endif; ?>
    </div>

    <div class="card" id="forum">
        <h3>Compiti e domande</h3>
        <?php if ($postError): ?><div class="error"><?= htmlspecialchars($postError) ?></div><?php endif; ?>
        <?php if (empty($posts)): ?>
            <p class="muted">Nessun messaggio ancora. Scrivi qui il tuo compito.</p>
        <?php else: ?>
            <?php foreach ($posts as $p): ?>
                <div class="post">
                    <div class="author<?= $p['role'] === 'admin' ? ' admin-post' : '' ?>"><?= htmlspecialchars($p['name'] ?: $p['email']) ?></div>
                    <div class="muted"><?= htmlspecialchars($p['created_at']) ?></div>
                    <div><?= nl2br(htmlspecialchars($p['body'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" style="margin-top:1.5rem;">
            <?= corsoCsrfField('forum-post') ?>
            <label for="body">Scrivi un messaggio</label>
            <textarea id="body" name="body" rows="4" maxlength="10000" required></textarea>
            <button type="submit" class="btn">Invia</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
