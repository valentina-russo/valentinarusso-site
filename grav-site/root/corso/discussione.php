<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$isAdmin = corsoIsAdmin((int)$user['id']);
$uid     = (int)$user['id'];
$id      = (int)($_GET['id'] ?? 0);

$thread = corsoThread($id);
if (!$thread) {
    http_response_code(404);
    corsoHtmlHead('Post non trovato');
    corsoNav($user, $isAdmin, 'forum');
    echo '<div class="wrap"><div class="card empty"><p>Questo post non esiste.</p>'
       . '<p><a class="btn ghost" href="forum.php">Torna al forum</a></p></div></div>';
    corsoHtmlFoot();
    exit;
}

// R11: controllo server-side, anche indovinando l'id
corsoRequireEnrollment($uid, (int)$thread['cohort_id']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react_post'])) {
    if (hdCsrfVerify($_POST['csrf'] ?? '', 'reazione')) {
        corsoToggleReaction((int)$_POST['react_post'], $uid, (string)($_POST['emoji'] ?? ''));
    }
    header('Location: discussione.php?id=' . $id);
    exit;
}

// Fissa / togli dall'alto (solo docente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin']) && $isAdmin) {
    if (hdCsrfVerify($_POST['csrf'] ?? '', 'pin')) {
        hdDb()->prepare('UPDATE forum_posts SET pinned = 1 - pinned WHERE id = ?')->execute([$id]);
    }
    header('Location: discussione.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rispondi'])) {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'rispondi')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $body = $_POST['body'] ?? '';
        if ($e = corsoValidatePostBody($body)) {
            $error = $e;
        } else {
            hdDb()->prepare('INSERT INTO forum_posts (lesson_id, cohort_id, parent_id, user_id, body) VALUES (?,?,?,?,?)')
                  ->execute([$thread['lesson_id'], $thread['cohort_id'], $thread['id'], $uid, trim($body)]);
            corsoSaveAttachments((int)hdDb()->lastInsertId(), 'allegati', __DIR__ . '/private-uploads');
            header('Location: discussione.php?id=' . $id . '&inviato=1#fine');
            exit;
        }
    }
}

$replies  = corsoReplies($id);
$justSent = isset($_GET['inviato']);

// allegati e reazioni del post radice
$att   = corsoAttachments([$id])[$id] ?? [];
$mie   = corsoReactions([$id], $uid)[$id] ?? [];
$isCreator = $thread['author_role'] === 'admin';

corsoHtmlHead($thread['title'] ?: 'Post');
corsoNav($user, $isAdmin, 'forum');
?>
<div class="wrap">
    <p class="eyebrow"><a href="forum.php" style="color:inherit;text-decoration:none">&larr; Forum</a></p>

    <?php if ((int)$thread['pinned'] === 1): ?><p class="pin">&#9733; Fissato in alto</p><?php endif; ?>
    <h1 class="page"><?= htmlspecialchars($thread['title'] ?: 'Post') ?></h1>

    <p class="meta" style="margin:0 0 1.5rem;display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
        <?php if ($thread['lesson_position']): ?>
            <a class="badge teal" style="text-decoration:none" href="lezione.php?id=<?= (int)$thread['lesson_id'] ?>">Lezione <?= (int)$thread['lesson_position'] ?></a>
        <?php else: ?>
            <span class="badge">Domanda generale</span>
        <?php endif; ?>
        <span class="badge"><?= htmlspecialchars($thread['course_title'] . ($isAdmin ? ' · ' . $thread['cohort_name'] : '')) ?></span>
        <?php if ($isAdmin): ?>
            <form method="post" style="display:inline">
                <?= corsoCsrfField('pin') ?>
                <input type="hidden" name="toggle_pin" value="1">
                <button type="submit" class="chip" style="cursor:pointer"><?= (int)$thread['pinned'] === 1 ? 'Togli dall\'alto' : 'Fissa in alto' ?></button>
            </form>
        <?php endif; ?>
    </p>

    <div class="card">
        <div class="pmeta" style="margin-top:0">
            <?= corsoAvatar($thread['author_name'], $thread['author_email'], $isCreator, 44) ?>
            <span>
                <span class="who"><?= htmlspecialchars($thread['author_name'] ?: $thread['author_email']) ?></span>
                <?php if ($isCreator): ?><span class="role">Docente</span><?php endif; ?>
                <div class="when"><?= htmlspecialchars(corsoRelativeTime($thread['created_at'])) ?></div>
            </span>
        </div>
        <p class="ptext"><?= corsoBodyHtml($thread['body']) ?></p>

        <?php foreach ($att as $a): ?>
            <?php if (corsoIsImage($a['mime'])): ?>
                <p style="margin:1rem 0 0"><img src="allegato.php?id=<?= (int)$a['id'] ?>" alt="<?= htmlspecialchars($a['orig_name']) ?>"
                     style="max-width:100%;border-radius:11px;display:block"></p>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($att): ?>
            <p style="margin:.7rem 0 0">
            <?php foreach ($att as $a): ?>
                <a class="attach" href="allegato.php?id=<?= (int)$a['id'] ?>" target="_blank" rel="noopener">
                    <?= corsoIsImage($a['mime']) ? '&#128247;' : '&#128196;' ?> <?= htmlspecialchars($a['orig_name']) ?>
                </a>
            <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <form method="post" class="reacts">
            <?= corsoCsrfField('reazione') ?>
            <input type="hidden" name="react_post" value="<?= $id ?>">
            <?php foreach ($mie as $r): ?>
                <button class="react<?= $r['mine'] ? ' mine' : '' ?>" type="submit" name="emoji" value="<?= htmlspecialchars($r['emoji']) ?>">
                    <?= $r['emoji'] ?> <span class="n"><?= (int)$r['n'] ?></span>
                </button>
            <?php endforeach; ?>
            <?php foreach (corsoEmoji() as $e):
                $already = false; foreach ($mie as $r) if ($r['emoji'] === $e) $already = true;
                if ($already) continue; ?>
                <button class="react react-add" type="submit" name="emoji" value="<?= $e ?>" title="Reagisci"><?= $e ?></button>
            <?php endforeach; ?>
        </form>
    </div>

    <?php if ($replies): ?>
        <h2 class="sect"><?= count($replies) ?> <?= count($replies) === 1 ? 'risposta' : 'risposte' ?></h2>
        <?php foreach ($replies as $r): ?>
            <div class="card"<?= $r['role'] === 'admin' ? ' style="border-left:3px solid var(--rosa)"' : '' ?>>
                <div class="pmeta" style="margin-top:0">
                    <?= corsoAvatar($r['name'], $r['email'], $r['role'] === 'admin', 38) ?>
                    <span>
                        <span class="who"><?= htmlspecialchars($r['name'] ?: $r['email']) ?></span>
                        <?php if ($r['role'] === 'admin'): ?><span class="role">Docente</span><?php endif; ?>
                        <div class="when"><?= htmlspecialchars(corsoRelativeTime($r['created_at'])) ?></div>
                    </span>
                </div>
                <p class="ptext"><?= corsoBodyHtml($r['body']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($justSent): ?>
        <div class="card" style="display:flex;align-items:center;gap:1rem">
            <?= corsoCheckMark(true) ?>
            <div><strong><?= $isAdmin ? 'Risposta inviata.' : 'Messaggio inviato.' ?></strong>
            <div class="meta"><?= $isAdmin ? 'Il post non risulta più in attesa.' : 'Valentina lo legge e ti risponde qui.' ?></div></div>
        </div>
    <?php endif; ?>

    <div class="card" id="fine">
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= corsoCsrfField('rispondi') ?>
            <input type="hidden" name="rispondi" value="1">
            <label for="body"><?= $isAdmin ? 'Rispondi' : 'Scrivi una risposta' ?></label>
            <textarea id="body" name="body" rows="5" maxlength="10000" required></textarea>
            <label for="allegati">Allegati</label>
            <input type="file" id="allegati" name="allegati[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp">
            <button type="submit" class="btn">Invia</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
