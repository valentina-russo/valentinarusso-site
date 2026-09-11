<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$isAdmin = corsoIsAdmin((int)$user['id']);
$uid     = (int)$user['id'];
$id      = (int)($_GET['id'] ?? 0);

// Se si arriva da "Da correggere", si deve poter tornare li invece che al forum
$fromCompiti = $isAdmin && ($_GET['from'] ?? '') === 'compiti';
$fromClasse  = (int)($_GET['classe'] ?? 0);
$backQuery   = $fromCompiti ? ('&from=compiti' . ($fromClasse ? '&classe=' . $fromClasse : '')) : '';
$backHref    = $fromCompiti ? ('admin/compiti.php' . ($fromClasse ? '?classe=' . $fromClasse : '')) : null;
$backLabel   = $fromCompiti ? 'Da correggere' : null;

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

if ($backHref === null) {           // si torna alla sezione della propria classe
    $backHref  = 'sezione.php?classe=' . (int)$thread['cohort_id'];
    $backLabel = $thread['course_title'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react_post'])) {
    if (hdCsrfVerify($_POST['csrf'] ?? '', 'reazione')) {
        corsoToggleReaction((int)$_POST['react_post'], $uid, (string)($_POST['emoji'] ?? ''));
    }
    header('Location: discussione.php?id=' . $id . $backQuery);
    exit;
}

// Fissa / togli dall'alto (solo docente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin']) && $isAdmin) {
    if (hdCsrfVerify($_POST['csrf'] ?? '', 'pin')) {
        hdDb()->prepare('UPDATE forum_posts SET pinned = 1 - pinned WHERE id = ?')->execute([$id]);
    }
    header('Location: discussione.php?id=' . $id . $backQuery);
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
            // Se ha risposto Valentina, l'allieva lo viene a sapere per mail:
            // altrimenti la risposta resta qui e la trova solo se torna a guardare.
            if ($isAdmin && (int)$thread['user_id'] !== $uid) {
                corsoAvvisaRisposta((int)$thread['id'], $uid);
            }
            header('Location: discussione.php?id=' . $id . $backQuery . '&inviato=1#fine');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    corsoEnsureForumSchema();
    hdDb()->prepare('UPDATE forum_posts SET views = COALESCE(views, 0) + 1 WHERE id = ?')->execute([$id]);
    $thread['views'] = (int)($thread['views'] ?? 0) + 1;
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
<div class="wrap larga">
    <div class="aula-testa" style="margin-bottom:1.4rem">
        <a class="briciola" href="<?= htmlspecialchars($backHref) ?>">&larr; <?= htmlspecialchars($backLabel) ?></a>
        <h1 style="font-size:clamp(1.5rem,4vw,2.1rem)">
            <?php if ((int)$thread['pinned'] === 1): ?><span class="fo-pin" title="Fissata in alto">&#9733;</span> <?php endif; ?>
            <?= htmlspecialchars($thread['title'] ?: 'Discussione') ?>
        </h1>
        <p class="sotto" style="display:flex;gap:.45rem;flex-wrap:wrap;align-items:center;font-size:.9375rem">
            <?php if ($thread['lesson_position']): ?>
                <a class="badge teal" style="text-decoration:none" href="lezione.php?id=<?= (int)$thread['lesson_id'] ?>">Lezione <?= (int)$thread['lesson_position'] ?></a>
            <?php else: ?>
                <span class="badge">Domanda generale</span>
            <?php endif; ?>
            <span><?= count($replies) ?> <?= count($replies) === 1 ? 'risposta' : 'risposte' ?></span>
            <span>&middot;</span>
            <span><?= (int)($thread['views'] ?? 0) ?> letture</span>
            <?php if ($isAdmin): ?>
                <span>&middot;</span>
                <span><?= htmlspecialchars($thread['cohort_name']) ?></span>
                <form method="post" style="display:inline">
                    <?= corsoCsrfField('pin') ?>
                    <input type="hidden" name="toggle_pin" value="1">
                    <button type="submit" class="chip" style="cursor:pointer"><?= (int)$thread['pinned'] === 1 ? 'Togli dall&rsquo;alto' : 'Fissa in alto' ?></button>
                </form>
            <?php endif; ?>
        </p>
    </div>

    <?php
    // Il primo messaggio e' quello che ha aperto la discussione, poi le
    // risposte in ordine di arrivo: numerate, come in un forum.
    $messaggi = array_merge([[
        'id' => $id, 'name' => $thread['author_name'], 'email' => $thread['author_email'],
        'role' => $thread['author_role'], 'created_at' => $thread['created_at'],
        'body' => $thread['body'], 'user_id' => $thread['user_id'],
    ]], $replies);
    $attTutti = corsoAttachments(array_map(fn($m) => (int)$m['id'], $messaggi));
    ?>

    <?php foreach ($messaggi as $n => $m): ?>
        <?php $docente = $m['role'] === 'admin'; $attM = $attTutti[(int)$m['id']] ?? []; ?>
        <div class="fo-msg<?= $n === 0 ? ' prima' : '' ?>" id="m<?= $n + 1 ?>">
            <div class="autore">
                <?= corsoAvatar($m['name'], $m['email'], $docente, 64, (int)($m['user_id'] ?? 0)) ?>
                <span class="chi"><?= htmlspecialchars($m['name'] ?: $m['email']) ?></span>
                <?php if ($docente): ?><span class="ruolo">Docente</span><?php endif; ?>
            </div>
            <div class="testo">
                <p class="quando">
                    <a href="#m<?= $n + 1 ?>" style="color:inherit;text-decoration:none">#<?= $n + 1 ?></a>
                    <span><?= htmlspecialchars(corsoRelativeTime($m['created_at'])) ?></span>
                    <?php if ($n === 0): ?><span class="badge">Apre la discussione</span><?php endif; ?>
                </p>
                <p class="ptext" style="margin:0"><?= corsoBodyHtml($m['body']) ?></p>

                <?php foreach ($attM as $a): if (!corsoIsImage($a['mime'])) continue; ?>
                    <p style="margin:1rem 0 0"><img src="allegato.php?id=<?= (int)$a['id'] ?>" alt="<?= htmlspecialchars($a['orig_name']) ?>"
                         style="max-width:100%;border-radius:11px;display:block"></p>
                <?php endforeach; ?>
                <?php if ($attM): ?>
                    <p style="margin:.7rem 0 0">
                    <?php foreach ($attM as $a): ?>
                        <a class="attach" href="allegato.php?id=<?= (int)$a['id'] ?>" target="_blank" rel="noopener">
                            <?= corsoIsImage($a['mime']) ? '&#128247;' : '&#128196;' ?> <?= htmlspecialchars($a['orig_name']) ?>
                        </a>
                    <?php endforeach; ?>
                    </p>
                <?php endif; ?>

                <?php if ($n === 0): ?>
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
                            <button class="react react-add" type="submit" name="emoji" value="<?= htmlspecialchars($e) ?>" title="Reagisci"><?= $e ?></button>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($justSent): ?>
        <div class="card" style="display:flex;align-items:center;gap:1rem">
            <?= corsoCheckMark(true) ?>
            <div><strong><?= $isAdmin ? 'Risposta inviata.' : 'Messaggio inviato.' ?></strong>
            <div class="meta"><?= $isAdmin ? 'La discussione non risulta piu&rsquo; in attesa.' : 'Valentina lo legge e ti risponde qui.' ?></div></div>
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
