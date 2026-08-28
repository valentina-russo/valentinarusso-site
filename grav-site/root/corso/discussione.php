<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$isAdmin = corsoIsAdmin((int)$user['id']);
$id      = (int)($_GET['id'] ?? 0);

$thread = corsoThread($id);
if (!$thread) {
    http_response_code(404);
    corsoHtmlHead('Discussione non trovata');
    corsoNav($user, $isAdmin, 'forum');
    echo '<div class="wrap"><div class="card empty"><p>Questa discussione non esiste.</p>'
       . '<p><a class="btn ghost" href="forum.php">Torna al forum</a></p></div></div>';
    corsoHtmlFoot();
    exit;
}

// R11: controllo server-side, anche indovinando l'id della discussione
corsoRequireEnrollment((int)$user['id'], (int)$thread['cohort_id']);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'rispondi')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $body = $_POST['body'] ?? '';
        if ($e = corsoValidatePostBody($body)) {
            $error = $e;
        } else {
            hdDb()->prepare('INSERT INTO forum_posts (lesson_id, cohort_id, parent_id, user_id, body) VALUES (?,?,?,?,?)')
                  ->execute([$thread['lesson_id'], $thread['cohort_id'], $thread['id'], $user['id'], trim($body)]);
            header('Location: discussione.php?id=' . $id . '&inviato=1#fine');
            exit;
        }
    }
}

$replies   = corsoReplies($id);
$justSent  = isset($_GET['inviato']);

corsoHtmlHead($thread['title'] ?: 'Discussione');
corsoNav($user, $isAdmin, 'forum');
?>
<div class="wrap">
    <p class="eyebrow"><a href="forum.php" style="color:inherit;text-decoration:none">&larr; Forum</a></p>
    <h1 class="page"><?= htmlspecialchars($thread['title'] ?: 'Discussione') ?></h1>
    <p class="meta" style="margin:0 0 1.5rem">
        <?php if ($thread['lesson_position']): ?>
            <a class="badge teal" style="text-decoration:none" href="lezione.php?id=<?= (int)$thread['lesson_id'] ?>">Lezione <?= (int)$thread['lesson_position'] ?></a>
        <?php else: ?>
            <span class="badge">Domanda generale</span>
        <?php endif; ?>
        <?php if ($isAdmin): ?><span class="badge"><?= htmlspecialchars($thread['course_title'] . ' · ' . $thread['cohort_name']) ?></span><?php endif; ?>
    </p>

    <div class="card">
        <div class="post<?= $thread['author_role'] === 'admin' ? ' from-admin' : '' ?>">
            <span class="author<?= $thread['author_role'] === 'admin' ? ' is-admin' : '' ?>"><?= htmlspecialchars($thread['author_name'] ?: $thread['author_email']) ?></span>
            <span class="meta"> · <?= htmlspecialchars(corsoRelativeTime($thread['created_at'])) ?></span>
            <div class="body"><?= nl2br(htmlspecialchars($thread['body'])) ?></div>
        </div>

        <?php foreach ($replies as $r): ?>
            <div class="post<?= $r['role'] === 'admin' ? ' from-admin' : '' ?>">
                <span class="author<?= $r['role'] === 'admin' ? ' is-admin' : '' ?>"><?= htmlspecialchars($r['name'] ?: $r['email']) ?></span>
                <span class="meta"> · <?= htmlspecialchars(corsoRelativeTime($r['created_at'])) ?></span>
                <div class="body"><?= nl2br(htmlspecialchars($r['body'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($justSent): ?>
        <div class="card" style="display:flex;align-items:center;gap:1rem">
            <?= corsoCheckMark(true) ?>
            <div><strong><?= $isAdmin ? 'Risposta inviata.' : 'Messaggio inviato.' ?></strong>
            <div class="meta"><?= $isAdmin ? 'La discussione non risulta più in attesa.' : 'Valentina lo legge e ti risponde qui.' ?></div></div>
        </div>
    <?php endif; ?>

    <div class="card" id="fine">
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= corsoCsrfField('rispondi') ?>
            <label for="body"><?= $isAdmin ? 'Rispondi' : 'Scrivi un messaggio' ?></label>
            <textarea id="body" name="body" rows="5" maxlength="10000" required></textarea>
            <button type="submit" class="btn">Invia</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
