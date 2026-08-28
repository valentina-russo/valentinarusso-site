<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$isAdmin = corsoIsAdmin((int)$user['id']);
$lessonId = (int)($_GET['id'] ?? 0);

$stmt = hdDb()->prepare('SELECT l.*, c.title AS course_title, c.slug AS course_slug
    FROM lessons l JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND l.deleted_at IS NULL');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    corsoHtmlHead('Classe non trovata');
    corsoNav($user, $isAdmin);
    echo '<div class="wrap"><div class="card empty"><p>Questa classe non esiste.</p></div></div>';
    corsoHtmlFoot();
    exit;
}

// R11: controllo server-side anche indovinando l'id della classe
corsoRequireEnrollment((int)$user['id'], (int)$lesson['course_id']);

$postError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'forum-post')) {
        $postError = 'Sessione scaduta, riprova.';
    } else {
        $body = $_POST['body'] ?? '';
        $validationError = corsoValidatePostBody($body);
        if ($validationError) {
            $postError = $validationError;
        } else {
            $stmt = hdDb()->prepare('INSERT INTO forum_posts (lesson_id, user_id, body) VALUES (?, ?, ?)');
            $stmt->execute([$lessonId, $user['id'], trim($body)]);
            header('Location: lezione.php?id=' . $lessonId . '&inviato=1#forum');
            exit;
        }
    }
}

$stmt = hdDb()->prepare('SELECT p.body, p.created_at, u.name, u.email, u.role
    FROM forum_posts p JOIN hd_users u ON u.id = p.user_id
    WHERE p.lesson_id = ? ORDER BY p.created_at ASC');
$stmt->execute([$lessonId]);
$posts = $stmt->fetchAll();

$justSent = isset($_GET['inviato']);

corsoHtmlHead($lesson['title']);
corsoNav($user, $isAdmin, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow"><a href="corso.php?slug=<?= urlencode($lesson['course_slug']) ?>" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($lesson['course_title']) ?></a></p>
    <h1 class="page">Classe <?= (int)$lesson['position'] ?> &middot; <?= htmlspecialchars($lesson['title']) ?></h1>

    <?php if ($lesson['bunny_video_id']): ?>
        <iframe class="video" src="<?= htmlspecialchars(bunnySignedEmbedUrl($lesson['bunny_video_id'])) ?>"
                allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"
                allowfullscreen loading="lazy" title="Registrazione della lezione"></iframe>
    <?php else: ?>
        <div class="card empty"><p>La registrazione di questa lezione non è ancora disponibile.</p></div>
    <?php endif; ?>

    <?php if ($lesson['pdf_slide_path'] || $lesson['pdf_exercise_path']): ?>
        <h2 class="sect">Materiali</h2>
        <?php if ($lesson['pdf_slide_path']): ?>
            <a class="card card-row" href="materiale.php?lesson=<?= $lessonId ?>&type=slide" target="_blank" rel="noopener">
                <span class="grow"><h3>Slide della lezione</h3><span class="meta">PDF</span></span>
                <span class="badge">Apri</span>
            </a>
        <?php endif; ?>
        <?php if ($lesson['pdf_exercise_path']): ?>
            <a class="card card-row" href="materiale.php?lesson=<?= $lessonId ?>&type=exercise" target="_blank" rel="noopener">
                <span class="grow"><h3>Esercizio pratico</h3><span class="meta">PDF</span></span>
                <span class="badge">Apri</span>
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="sect" id="forum">Il tuo compito</h2>

    <?php if ($justSent): ?>
        <div class="card" style="display:flex;align-items:center;gap:1rem">
            <?= corsoCheckMark(true) ?>
            <div><strong>Compito inviato.</strong>
            <div class="meta">Valentina lo legge e ti risponde qui sotto.</div></div>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if ($postError): ?><div class="msg err"><?= htmlspecialchars($postError) ?></div><?php endif; ?>

        <?php if (empty($posts)): ?>
            <p class="meta">Nessun messaggio ancora. Scrivi qui sotto il tuo esercizio, oppure una domanda.</p>
        <?php else: ?>
            <?php foreach ($posts as $p): ?>
                <div class="post<?= $p['role'] === 'admin' ? ' from-admin' : '' ?>">
                    <span class="author<?= $p['role'] === 'admin' ? ' is-admin' : '' ?>"><?= htmlspecialchars($p['name'] ?: $p['email']) ?></span>
                    <span class="meta"> &middot; <?= htmlspecialchars(corsoRelativeTime($p['created_at'])) ?></span>
                    <div class="body"><?= htmlspecialchars($p['body']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" style="margin-top:1.75rem">
            <?= corsoCsrfField('forum-post') ?>
            <label for="body"><?= $isAdmin ? 'Rispondi' : 'Scrivi il tuo compito o una domanda' ?></label>
            <textarea id="body" name="body" rows="5" maxlength="10000" required></textarea>
            <button type="submit" class="btn"><?= $isAdmin ? 'Invia risposta' : 'Invia' ?></button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
