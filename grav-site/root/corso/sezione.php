<?php
/**
 * Una sezione del forum: l'elenco delle discussioni di una classe, nella forma
 * classica. Una riga per discussione, con quante risposte ha, quante volte e'
 * stata aperta e chi ha scritto per ultimo. In cima quelle fissate.
 *
 * L'indice delle sezioni sta in forum.php, i messaggi in discussione.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user      = corsoRequireStudent();
$isAdmin   = corsoIsAdmin((int)$user['id']);
$uid       = (int)$user['id'];
$cohortIds = array_map('intval', corsoVisibleCohortIds($user, $isAdmin));
corsoEnsureForumSchema();

$classeId = (int)($_GET['classe'] ?? 0);
if (!in_array($classeId, $cohortIds, true)) {
    http_response_code(404);
    corsoHtmlHead('Sezione non trovata');
    corsoNav($user, $isAdmin, 'forum');
    echo '<div class="wrap"><div class="card empty"><p>Questa sezione del forum non esiste.</p>'
       . '<p><a class="btn ghost" href="forum.php">Torna al forum</a></p></div></div>';
    corsoHtmlFoot();
    exit;
}
$classe = corsoCohort($classeId);

$scope = in_array($_GET['f'] ?? '', ['mine', 'joined'], true) ? $_GET['f'] : 'all';
$q     = trim((string)($_GET['q'] ?? ''));
$per   = 20;
$page  = max(1, (int)($_GET['p'] ?? 1));
$error = '';

// ── Nuova discussione ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuovo'])) {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'nuova-discussione')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $body     = $_POST['body'] ?? '';

        if ($e = (corsoValidateTitle($title) ?? corsoValidatePostBody($body))) {
            $error = $e;
        } else {
            $lessonOk = null;
            if ($lessonId) {
                $st = hdDb()->prepare('SELECT id FROM lessons WHERE id = ? AND cohort_id = ? AND deleted_at IS NULL');
                $st->execute([$lessonId, $classeId]);
                if ($st->fetch()) { $lessonOk = $lessonId; }
            }
            $pinned = ($isAdmin && !empty($_POST['pinned'])) ? 1 : 0;
            hdDb()->prepare('INSERT INTO forum_posts (lesson_id, cohort_id, parent_id, title, pinned, user_id, body) VALUES (?,?,NULL,?,?,?,?)')
                  ->execute([$lessonOk, $classeId, $title, $pinned, $uid, trim($body)]);
            $newId = (int)hdDb()->lastInsertId();
            corsoSaveAttachments($newId, 'allegati', __DIR__ . '/private-uploads');
            header('Location: discussione.php?id=' . $newId);
            exit;
        }
    }
}

$threads = corsoThreads($cohortIds, [
    'cohort' => $classeId, 'scope' => $scope, 'q' => $q,
    'limit' => $per + 1, 'offset' => ($page - 1) * $per, 'user_id' => $uid,
]);
$hasMore = count($threads) > $per;
if ($hasMore) { array_pop($threads); }

$ultime  = corsoLastReplies(array_map(fn($t) => (int)$t['id'], $threads));
$lezioni = hdDb()->prepare('SELECT id, position, title FROM lessons WHERE cohort_id = ? AND deleted_at IS NULL ORDER BY position');
$lezioni->execute([$classeId]);
$lezioni = $lezioni->fetchAll();

// tiene i filtri quando si cambia una cosa sola
$qs = function (array $over = []) use ($classeId, $scope, $q) {
    $a = array_filter([
        'classe' => $classeId,
        'f'      => $over['f'] ?? ($scope !== 'all' ? $scope : null),
        'q'      => $over['q'] ?? ($q !== '' ? $q : null),
        'p'      => $over['p'] ?? null,
    ], fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($a);
};

corsoHtmlHead($classe['course_title'] . ' - Forum');
corsoNav($user, $isAdmin, 'forum');
?>
<div class="wrap larga">
    <div class="aula-testa">
        <a class="briciola" href="forum.php">&larr; Forum</a>
        <h1><?= htmlspecialchars($classe['course_title']) ?></h1>
        <p class="sotto"><?= htmlspecialchars($classe['name']) ?> &middot; domande, compiti consegnati e risposte di Valentina.</p>
    </div>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin:0 0 1.25rem">
        <a class="btn" href="#nuova" data-apri="composer">Nuova discussione</a>
        <div class="chips" style="margin:0">
            <a class="chip<?= $scope === 'all' ? ' on' : '' ?>" href="sezione.php<?= $qs(['f' => null]) ?>">Tutte</a>
            <a class="chip<?= $scope === 'joined' ? ' on' : '' ?>" href="sezione.php<?= $qs(['f' => 'joined']) ?>">Dove ho scritto</a>
            <a class="chip<?= $scope === 'mine' ? ' on' : '' ?>" href="sezione.php<?= $qs(['f' => 'mine']) ?>">Aperte da me</a>
        </div>
        <form class="searchbar" method="get" action="sezione.php" style="margin:0;flex:1;min-width:220px">
            <input type="hidden" name="classe" value="<?= $classeId ?>">
            <?php if ($scope !== 'all'): ?><input type="hidden" name="f" value="<?= htmlspecialchars($scope) ?>"><?php endif; ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cerca nelle discussioni...">
        </form>
    </div>

    <details class="card" id="composer" <?= $error ? 'open' : '' ?> style="margin-bottom:1.25rem">
        <summary style="cursor:pointer;font-weight:600;color:var(--navy)" id="nuova">Apri una nuova discussione</summary>
        <form method="post" enctype="multipart/form-data" style="margin-top:1rem">
            <?= corsoCsrfField('nuova-discussione') ?>
            <input type="hidden" name="nuovo" value="1">

            <label for="title">Titolo</label>
            <input type="text" id="title" name="title" maxlength="200" required
                   placeholder="Di cosa si tratta in poche parole">

            <label for="lesson_id">Lezione di riferimento</label>
            <select id="lesson_id" name="lesson_id">
                <option value="0">Nessuna in particolare</option>
                <?php foreach ($lezioni as $lz): ?>
                    <option value="<?= (int)$lz['id'] ?>">Lezione <?= (int)$lz['position'] ?> &middot; <?= htmlspecialchars($lz['title']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="body">Messaggio</label>
            <textarea id="body" name="body" rows="7" maxlength="10000" required
                      placeholder="Scrivi la domanda, o incolla qui il compito..."></textarea>

            <label for="allegati">Allegati (immagini o PDF)</label>
            <input type="file" id="allegati" name="allegati[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">

            <?php if ($isAdmin): ?>
                <p style="margin:.75rem 0 0"><label style="font-weight:400"><input type="checkbox" name="pinned" value="1"> Fissa in alto</label></p>
            <?php endif; ?>
            <button class="btn dark" type="submit" style="margin-top:1rem">Pubblica</button>
        </form>
    </details>

    <div class="fo-sez">
        <div class="fo-riga intesta">
            <div class="tit">Discussione</div>
            <div class="fo-num">Risposte</div>
            <div class="fo-num letture">Letture</div>
            <div class="ultimo">Ultimo messaggio</div>
        </div>

        <?php if (empty($threads)): ?>
            <p class="fo-vuoto">
                <?= $q !== '' ? 'Nessuna discussione trovata per questa ricerca.' : 'Non c&rsquo;&egrave; ancora nessuna discussione.' ?>
                <?php if ($q === ''): ?><br>Aprila tu: consegna un compito o fai una domanda.<?php endif; ?>
            </p>
        <?php else: ?>
            <?php foreach ($threads as $t): $ult = $ultime[(int)$t['id']] ?? null; ?>
                <div class="fo-riga">
                    <div class="tit">
                        <a href="discussione.php?id=<?= (int)$t['id'] ?>">
                            <?php if ((int)$t['pinned'] === 1): ?><span class="fo-pin" title="Fissata in alto">&#9733;</span> <?php endif; ?>
                            <?= htmlspecialchars($t['title'] ?: 'Senza titolo') ?>
                        </a>
                        <div class="sotto">
                            <?= htmlspecialchars($t['author_name'] ?: $t['author_email']) ?>
                            <?php if ($t['author_role'] === 'admin'): ?><span class="badge">Docente</span><?php endif; ?>
                            &middot; <?= htmlspecialchars(corsoRelativeTime($t['created_at'])) ?>
                            <?php if ($t['lesson_position']): ?><span class="badge">Lezione <?= (int)$t['lesson_position'] ?></span><?php endif; ?>
                            <?php if ($t['author_role'] === 'student' && !$t['answered']): ?><span class="badge oro">In attesa di risposta</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="fo-num"><?= (int)$t['replies'] ?></div>
                    <div class="fo-num letture"><?= (int)$t['views'] ?></div>
                    <div class="ultimo">
                        <?php if ($ult): ?>
                            <?= corsoAvatar($ult['name'], $ult['email'], $ult['role'] === 'admin', 34, null) ?>
                            <span style="min-width:0">
                                <span class="chi"><?= htmlspecialchars($ult['name'] ?: $ult['email']) ?></span>
                                <?= htmlspecialchars(corsoRelativeTime($ult['created_at'])) ?>
                            </span>
                        <?php else: ?>
                            <span>Nessuna risposta</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($hasMore || $page > 1): ?>
        <div class="fo-pagine">
            <?php if ($page > 1): ?><a href="sezione.php<?= $qs(['p' => $page - 1]) ?>">&larr;</a><?php endif; ?>
            <span class="qui"><?= $page ?></span>
            <?php if ($hasMore): ?><a href="sezione.php<?= $qs(['p' => $page + 1]) ?>">&rarr;</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
