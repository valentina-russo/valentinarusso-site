<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$isAdmin = corsoIsAdmin((int)$user['id']);
$lessonId = (int)($_GET['id'] ?? 0);

$stmt = hdDb()->prepare('SELECT l.*, c.title AS course_title, c.slug AS course_slug
    , co.name AS cohort_name, co.id AS cohort_id
    FROM lessons l
    JOIN cohorts co ON co.id = l.cohort_id
    JOIN courses c ON c.id = co.course_id
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
corsoRequireEnrollment((int)$user['id'], (int)$lesson['cohort_id']);

$postError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'forum-post')) {
        $postError = 'Sessione scaduta, riprova.';
    } else {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') $title = 'Compito · Lezione ' . (int)$lesson['position'];
        $body  = $_POST['body'] ?? '';
        $err = corsoValidateTitle($title) ?? corsoValidatePostBody($body);
        if ($err) {
            $postError = $err;
        } else {
            hdDb()->prepare('INSERT INTO forum_posts (lesson_id, cohort_id, parent_id, title, user_id, body) VALUES (?,?,NULL,?,?,?)')
                  ->execute([$lessonId, (int)$lesson['cohort_id'], $title, $user['id'], trim($body)]);
            $newId = (int)hdDb()->lastInsertId();
            corsoSaveAttachments($newId, 'allegati', __DIR__ . '/private-uploads');
            header('Location: discussione.php?id=' . $newId . '&inviato=1');
            exit;
        }
    }
}

// discussioni aperte su questa lezione
$stmt = hdDb()->prepare(
    "SELECT p.id, p.title, p.body, p.created_at,
            u.name, u.email, u.role,
            (SELECT COUNT(*) FROM forum_posts r WHERE r.parent_id = p.id) AS replies,
            EXISTS(SELECT 1 FROM forum_posts ra JOIN hd_users ua ON ua.id = ra.user_id
                   WHERE ra.parent_id = p.id AND ua.role = 'admin') AS answered
     FROM forum_posts p JOIN hd_users u ON u.id = p.user_id
     WHERE p.lesson_id = ? AND p.parent_id IS NULL
     ORDER BY p.created_at DESC"
);
$stmt->execute([$lessonId]);
$threads = $stmt->fetchAll();

corsoHtmlHead($lesson['title']);
corsoNav($user, $isAdmin, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow"><a href="classe.php?id=<?= (int)$lesson['cohort_id'] ?>" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($lesson['course_title']) ?></a></p>
    <h1 class="page">Lezione <?= (int)$lesson['position'] ?> &middot; <?= htmlspecialchars($lesson['title']) ?></h1>

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

    <h2 class="sect" id="forum">Compiti e domande</h2>

    <?php if ($threads): ?>
        <?php foreach ($threads as $t): ?>
            <?php $sn = trim(preg_replace('/\s+/', ' ', $t['body']));
                  if (mb_strlen($sn) > 110) $sn = mb_substr($sn, 0, 110) . '…'; ?>
            <a class="card card-row" href="discussione.php?id=<?= (int)$t['id'] ?>">
                <span class="grow">
                    <h3><?= htmlspecialchars($t['title'] ?: 'Senza titolo') ?></h3>
                    <div class="snippet"><?= htmlspecialchars($sn) ?></div>
                    <span class="meta"><?= htmlspecialchars($t['name'] ?: $t['email']) ?> · <?= htmlspecialchars(corsoRelativeTime($t['created_at'])) ?></span>
                </span>
                <span style="text-align:right;flex-shrink:0">
                    <?php if ((int)$t['replies'] > 0): ?><span class="badge"><?= (int)$t['replies'] ?></span><?php endif; ?>
                    <?php if (!$t['answered'] && $t['role'] === 'student'): ?><span class="badge oro">In attesa</span><?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="card">
        <?php if ($postError): ?><div class="msg err"><?= htmlspecialchars($postError) ?></div><?php endif; ?>
        <?php if (!$threads): ?>
            <p class="meta">Nessun messaggio su questa lezione. Consegna qui il tuo compito, oppure fai una domanda.</p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:1rem">
            <?= corsoCsrfField('forum-post') ?>
            <label for="title">Titolo</label>
            <input type="text" id="title" name="title" maxlength="180"
                   placeholder="Compito · Lezione <?= (int)$lesson['position'] ?>">
            <label for="body"><?= $isAdmin ? 'Scrivi' : 'Il tuo compito, o la tua domanda' ?></label>
            <textarea id="body" name="body" rows="5" maxlength="10000" required></textarea>
            <label for="allegati">Allegati</label>
            <input type="file" id="allegati" name="allegati[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp">
            <button type="submit" class="btn">Pubblica</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
