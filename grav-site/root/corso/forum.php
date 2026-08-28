<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user      = corsoRequireStudent();
$isAdmin   = corsoIsAdmin((int)$user['id']);
$uid       = (int)$user['id'];
$cohortIds = array_map('intval', corsoVisibleCohortIds($user, $isAdmin));

$scope  = in_array($_GET['f'] ?? '', ['mine', 'joined'], true) ? $_GET['f'] : 'all';
$filtro = isset($_GET['classe']) ? (int)$_GET['classe'] : null;
if ($filtro !== null && !in_array($filtro, $cohortIds, true)) $filtro = null;
$q      = trim((string)($_GET['q'] ?? ''));
$per    = 15;
$page   = max(1, (int)($_GET['p'] ?? 1));

$error = '';

// ── Reazione ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react_post'])) {
    if (hdCsrfVerify($_POST['csrf'] ?? '', 'reazione')) {
        $pid = (int)$_POST['react_post'];
        $st = hdDb()->prepare('SELECT cohort_id FROM forum_posts WHERE id = ?');
        $st->execute([$pid]);
        $co = (int)$st->fetchColumn();
        // R11: si reagisce solo dentro una classe a cui si ha accesso
        if ($co && in_array($co, $cohortIds, true)) {
            corsoToggleReaction($pid, $uid, (string)($_POST['emoji'] ?? ''));
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// ── Nuova discussione ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuovo'])) {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'nuova-discussione')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $cohortId = (int)($_POST['cohort_id'] ?? 0);
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $body     = $_POST['body'] ?? '';

        if (!in_array($cohortId, $cohortIds, true)) {
            $error = 'Classe non valida.';
        } elseif ($e = (corsoValidateTitle($title) ?? corsoValidatePostBody($body))) {
            $error = $e;
        } else {
            $lessonOk = null;
            if ($lessonId) {
                $st = hdDb()->prepare('SELECT id FROM lessons WHERE id = ? AND cohort_id = ? AND deleted_at IS NULL');
                $st->execute([$lessonId, $cohortId]);
                if ($st->fetch()) $lessonOk = $lessonId;
            }
            $pinned = ($isAdmin && !empty($_POST['pinned'])) ? 1 : 0;
            hdDb()->prepare('INSERT INTO forum_posts (lesson_id, cohort_id, parent_id, title, pinned, user_id, body) VALUES (?,?,NULL,?,?,?,?)')
                  ->execute([$lessonOk, $cohortId, $title, $pinned, $uid, trim($body)]);
            $newId = (int)hdDb()->lastInsertId();
            corsoSaveAttachments($newId, 'allegati', __DIR__ . '/private-uploads');
            header('Location: discussione.php?id=' . $newId);
            exit;
        }
    }
}

$threads = corsoThreads($cohortIds, [
    'cohort' => $filtro, 'scope' => $scope, 'q' => $q,
    'limit' => $per + 1, 'offset' => ($page - 1) * $per, 'user_id' => $uid,
]);
$hasMore = count($threads) > $per;
if ($hasMore) array_pop($threads);

$ids        = array_map(fn($t) => (int)$t['id'], $threads);
$allegati   = corsoAttachments($ids);
$reazioni   = corsoReactions($ids, $uid);
$ultime     = corsoLastReplies($ids);

$classi = [];
if ($cohortIds) {
    $in = implode(',', array_fill(0, count($cohortIds), '?'));
    $st = hdDb()->prepare("SELECT co.id, co.name, c.title AS course_title
                           FROM cohorts co JOIN courses c ON c.id = co.course_id
                           WHERE co.id IN ($in) ORDER BY c.created_at DESC, co.position");
    $st->execute($cohortIds);
    $classi = $st->fetchAll();
}

// mantiene i filtri attivi quando si cambia una sola cosa
$qs = function (array $over = []) use ($scope, $filtro, $q) {
    $a = array_filter([
        'f'      => $over['f']      ?? ($scope !== 'all' ? $scope : null),
        'classe' => array_key_exists('classe', $over) ? $over['classe'] : $filtro,
        'q'      => $over['q']      ?? ($q !== '' ? $q : null),
        'p'      => $over['p']      ?? null,
    ], fn($v) => $v !== null && $v !== '');
    return $a ? '?' . http_build_query($a) : '';
};

corsoHtmlHead('Forum');
corsoNav($user, $isAdmin, 'forum');
?>
<div class="wrap">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div>
            <p class="eyebrow">Lo spazio della classe</p>
            <h1 class="hero" style="margin-bottom:.2rem">Forum</h1>
        </div>
        <a class="btn" href="#scrivi" onclick="document.getElementById('composer').open=true">Scrivi un post</a>
    </div>
    <p class="hero-sub">Qui si consegnano i compiti e si fanno domande.</p>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (empty($classi)): ?>
        <div class="card empty"><p>Non risulti iscritta a nessuna classe.</p></div>
        <?php corsoHtmlFoot(); exit; ?>
    <?php endif; ?>

    <div class="chips">
        <a class="chip<?= $scope === 'all' ? ' on' : '' ?>" href="forum.php<?= $qs(['f' => null]) ?>">Tutti i post</a>
        <a class="chip<?= $scope === 'joined' ? ' on' : '' ?>" href="forum.php<?= $qs(['f' => 'joined']) ?>">Post partecipati</a>
        <a class="chip<?= $scope === 'mine' ? ' on' : '' ?>" href="forum.php<?= $qs(['f' => 'mine']) ?>">I miei post</a>
        <?php foreach ($classi as $cl): ?>
            <a class="chip<?= $filtro === (int)$cl['id'] ? ' on' : '' ?>"
               href="forum.php<?= $qs(['classe' => $filtro === (int)$cl['id'] ? null : (int)$cl['id']]) ?>">
                <?= htmlspecialchars($cl['course_title'] . ($isAdmin ? ' · ' . $cl['name'] : '')) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="searchbar" method="get" action="forum.php">
        <?php if ($scope !== 'all'): ?><input type="hidden" name="f" value="<?= htmlspecialchars($scope) ?>"><?php endif; ?>
        <?php if ($filtro): ?><input type="hidden" name="classe" value="<?= (int)$filtro ?>"><?php endif; ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cerca post o risposta per contenuto o autore...">
    </form>

    <details class="card" id="composer" <?= $error ? 'open' : '' ?>>
        <summary style="cursor:pointer;font-weight:600;color:var(--navy)" id="scrivi">Scrivi un post</summary>
        <form method="post" enctype="multipart/form-data" style="margin-top:1.25rem">
            <?= corsoCsrfField('nuova-discussione') ?>
            <input type="hidden" name="nuovo" value="1">

            <?php if (count($classi) > 1): ?>
                <label for="cohort_id">Classe</label>
                <select id="cohort_id" name="cohort_id" required>
                    <?php foreach ($classi as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>"<?= $filtro === (int)$cl['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($cl['course_title'] . ($isAdmin ? ' · ' . $cl['name'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="cohort_id" value="<?= (int)$classi[0]['id'] ?>">
            <?php endif; ?>

            <label for="lesson_id">Lezione di riferimento</label>
            <select id="lesson_id" name="lesson_id">
                <option value="0">Nessuna, è una domanda generale</option>
                <?php foreach ($classi as $cl): ?>
                    <?php $st = hdDb()->prepare('SELECT id, position, title FROM lessons WHERE cohort_id = ? AND deleted_at IS NULL ORDER BY position');
                          $st->execute([$cl['id']]);
                          foreach ($st->fetchAll() as $l): ?>
                        <option value="<?= (int)$l['id'] ?>">Lezione <?= (int)$l['position'] ?> · <?= htmlspecialchars($l['title']) ?></option>
                    <?php endforeach; endforeach; ?>
            </select>

            <label for="title">Titolo</label>
            <input type="text" id="title" name="title" maxlength="180" required placeholder="Es. Esercizio lezione 3">

            <label for="body">Messaggio</label>
            <textarea id="body" name="body" rows="6" maxlength="10000" required></textarea>

            <label for="allegati">Allegati</label>
            <input type="file" id="allegati" name="allegati[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp">
            <p class="hint">Immagini o PDF, fino a 20MB l'uno.</p>

            <?php if ($isAdmin): ?>
                <p><label style="font-weight:400"><input type="checkbox" name="pinned" value="1" style="width:auto;margin-right:.5rem"> Fissa in alto</label></p>
            <?php endif; ?>

            <button type="submit" class="btn">Pubblica</button>
        </form>
    </details>

    <?php if (empty($threads)): ?>
        <div class="card empty">
            <p><?= $q !== '' ? 'Nessun risultato per «' . htmlspecialchars($q) . '».' : 'Non c\'è ancora nessun post.' ?></p>
            <?php if ($q === ''): ?><p class="meta">Apri il primo: consegna un compito o fai una domanda.</p><?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($threads as $t): ?>
            <?php
            $att   = $allegati[(int)$t['id']] ?? [];
            $cover = null;
            foreach ($att as $a) { if (corsoIsImage($a['mime'])) { $cover = $a; break; } }
            $isCreator = $t['author_role'] === 'admin';
            ?>
            <div class="card pcard">
                <?php if ($cover): ?>
                    <a class="pcover" href="discussione.php?id=<?= (int)$t['id'] ?>">
                        <img src="allegato.php?id=<?= (int)$cover['id'] ?>" alt="" loading="lazy" decoding="async">
                    </a>
                <?php endif; ?>
                <div class="pbody">
                    <?php if ((int)$t['pinned'] === 1): ?>
                        <p class="pin">&#9733; Fissato in alto</p>
                    <?php endif; ?>

                    <span class="badge"><?= htmlspecialchars($t['course_title'] . ($isAdmin ? ' · ' . $t['cohort_name'] : '')) ?></span>

                    <div class="pmeta">
                        <?= corsoAvatar($t['author_name'], $t['author_email'], $isCreator, 40) ?>
                        <span>
                            <span class="who"><?= htmlspecialchars($t['author_name'] ?: $t['author_email']) ?></span>
                            <?php if ($isCreator): ?><span class="role">Docente</span><?php endif; ?>
                            <div class="when"><?= htmlspecialchars(corsoRelativeTime($t['created_at'])) ?></div>
                        </span>
                    </div>

                    <a href="discussione.php?id=<?= (int)$t['id'] ?>" style="text-decoration:none;color:inherit">
                        <p class="ptitle"><?= htmlspecialchars($t['title'] ?: 'Senza titolo') ?></p>
                        <p class="ptext"><?= corsoBodyHtml(mb_strimwidth($t['body'], 0, 320, '…')) ?></p>
                    </a>

                    <?php if ($att): ?>
                        <p style="margin:.6rem 0 0">
                        <?php foreach ($att as $a): ?>
                            <a class="attach" href="allegato.php?id=<?= (int)$a['id'] ?>" target="_blank" rel="noopener">
                                <?= corsoIsImage($a['mime']) ? '&#128247;' : '&#128196;' ?> <?= htmlspecialchars($a['orig_name']) ?>
                            </a>
                        <?php endforeach; ?>
                        </p>
                    <?php endif; ?>

                    <form method="post" class="reacts">
                        <?= corsoCsrfField('reazione') ?>
                        <input type="hidden" name="react_post" value="<?= (int)$t['id'] ?>">
                        <?php $mie = $reazioni[(int)$t['id']] ?? []; ?>
                        <?php foreach ($mie as $r): ?>
                            <button class="react<?= $r['mine'] ? ' mine' : '' ?>" type="submit" name="emoji" value="<?= htmlspecialchars($r['emoji']) ?>">
                                <?= $r['emoji'] ?> <span class="n"><?= (int)$r['n'] ?></span>
                            </button>
                        <?php endforeach; ?>
                        <?php foreach (corsoEmoji() as $e):
                            $already = false;
                            foreach ($mie as $r) if ($r['emoji'] === $e) $already = true;
                            if ($already) continue; ?>
                            <button class="react react-add" type="submit" name="emoji" value="<?= $e ?>" title="Reagisci"><?= $e ?></button>
                        <?php endforeach; ?>
                    </form>

                    <?php if ((int)$t['replies'] > 0): $last = $ultime[(int)$t['id']] ?? null; ?>
                        <div class="replybox">
                            <a class="more" href="discussione.php?id=<?= (int)$t['id'] ?>">
                                &#128172; <?= (int)$t['replies'] ?> <?= (int)$t['replies'] === 1 ? 'risposta' : 'risposte' ?>
                            </a>
                            <?php if ($last): ?>
                                <div class="pmeta" style="margin:.3rem 0 .4rem">
                                    <?= corsoAvatar($last['name'], $last['email'], $last['role'] === 'admin', 30) ?>
                                    <span>
                                        <span class="who" style="font-size:.875rem"><?= htmlspecialchars($last['name'] ?: $last['email']) ?></span>
                                        <?php if ($last['role'] === 'admin'): ?><span class="role">Docente</span><?php endif; ?>
                                        <div class="when"><?= htmlspecialchars(corsoRelativeTime($last['created_at'])) ?></div>
                                    </span>
                                </div>
                                <p class="ptext" style="font-size:.9375rem"><?= corsoBodyHtml(mb_strimwidth($last['body'], 0, 200, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($t['author_role'] === 'student' && !$t['answered']): ?>
                        <p style="margin:.9rem 0 0"><span class="badge oro">In attesa di risposta</span></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($hasMore || $page > 1): ?>
            <p style="display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem">
                <?php if ($page > 1): ?><a class="btn ghost" href="forum.php<?= $qs(['p' => $page - 1]) ?>">Indietro</a><?php endif; ?>
                <?php if ($hasMore): ?><a class="btn ghost" href="forum.php<?= $qs(['p' => $page + 1]) ?>">Carica di più</a><?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
