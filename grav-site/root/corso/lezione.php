<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$uid     = (int)$user['id'];
$isAdmin = corsoIsAdmin($uid);
$lessonId = (int)($_GET['id'] ?? 0);

$stmt = hdDb()->prepare('SELECT l.*, c.title AS course_title, c.slug AS course_slug,
        co.name AS cohort_name, co.id AS cohort_id
    FROM lessons l
    JOIN cohorts co ON co.id = l.cohort_id
    JOIN courses c ON c.id = co.course_id
    WHERE l.id = ? AND l.deleted_at IS NULL');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    corsoHtmlHead('Lezione non trovata');
    corsoNav($user, $isAdmin);
    echo '<div class="wrap"><div class="card empty"><p>Questa lezione non esiste.</p></div></div>';
    corsoHtmlFoot();
    exit;
}

// R11: controllo server-side anche indovinando l'id della lezione
corsoRequireEnrollment($uid, (int)$lesson['cohort_id']);

// Appunti personali: privati, mai condivisi, salvati con un piccolo submit
$noteMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nota'])) {
    if (hdCsrfVerify($_POST['csrf'] ?? '', 'appunti')) {
        corsoSaveNote($lessonId, $uid, (string)$_POST['nota']);
        $noteMsg = 'Appunti salvati.';
    }
}
$noteBody = corsoGetNote($lessonId, $uid);

[$prevLesson, $nextLesson] = corsoLessonNav((int)$lesson['cohort_id'], (int)$lesson['position']);

corsoHtmlHead($lesson['title']);
corsoNav($user, $isAdmin, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow"><a href="classe.php?id=<?= (int)$lesson['cohort_id'] ?>" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($lesson['course_title']) ?></a></p>
    <h1 class="page">Lezione <?= (int)$lesson['position'] ?> &middot; <?= htmlspecialchars($lesson['title']) ?></h1>

    <?php if ($lesson['bunny_video_id']):
        // R22: il token dura 4h, ma se l'allieva lascia la pagina aperta piu a
        // lungo (pausa, distrazione) il player deve rinnovarlo da solo invece
        // di interrompersi. Il tempo di scadenza e' calcolato qui e passato
        // al JS, che rinfresca poco prima con lo stesso punto di ripresa.
        $videoTtl = 14400;
        $videoExpiresAt = time() + $videoTtl;
    ?>
        <iframe id="corso-video" class="video" src="<?= htmlspecialchars(bunnySignedEmbedUrl($lesson['bunny_video_id'], $videoTtl)) ?>"
                allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"
                allowfullscreen loading="lazy" title="Registrazione della lezione"></iframe>
        <script src="//assets.mediadelivery.net/playerjs/playerjs-latest.min.js"></script>
        <script>
        (function () {
            var iframe = document.getElementById('corso-video');
            var lessonId = <?= (int)$lessonId ?>;
            var expiresAt = <?= (int)$videoExpiresAt ?> * 1000;
            var refreshMarginMs = 5 * 60 * 1000;
            var lastKnownTime = 0;
            var wasPlaying = false;
            var player = null;

            function bind(iframeEl) {
                player = new playerjs.Player(iframeEl);
                player.on('ready', function () {
                    player.on('timeupdate', function (data) {
                        if (data && typeof data.seconds === 'number') lastKnownTime = data.seconds;
                    });
                    player.on('play', function () { wasPlaying = true; });
                    player.on('pause', function () { wasPlaying = false; });
                    if (wasPlaying) {
                        player.setCurrentTime(lastKnownTime);
                        player.play();
                    }
                });
            }
            bind(iframe);

            function scheduleRefresh() {
                var delay = Math.max(0, expiresAt - Date.now() - refreshMarginMs);
                setTimeout(refresh, delay);
            }

            function refresh() {
                fetch('video-token.php?lesson=' + lessonId, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) return;
                        expiresAt = Date.now() + <?= (int)$videoTtl ?> * 1000;
                        iframe.src = data.embed_url;
                        bind(iframe);
                        scheduleRefresh();
                    })
                    .catch(function () { setTimeout(refresh, 30000); });
            }

            scheduleRefresh();
        })();
        </script>
    <?php else: ?>
        <div class="card empty"><p>La registrazione di questa lezione non è ancora disponibile.</p></div>
    <?php endif; ?>

    <p style="display:flex;justify-content:space-between;gap:1rem;margin:1rem 0 1.5rem">
        <?php if ($prevLesson): ?>
            <a class="btn ghost" href="lezione.php?id=<?= (int)$prevLesson['id'] ?>">&larr; Lezione <?= (int)$prevLesson['position'] ?></a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($nextLesson): ?>
            <a class="btn ghost" href="lezione.php?id=<?= (int)$nextLesson['id'] ?>">Lezione <?= (int)$nextLesson['position'] ?> &rarr;</a>
        <?php endif; ?>
    </p>

    <?php if (!empty($lesson['description'])): ?>
        <p class="ptext" style="margin-bottom:1.5rem"><?= corsoBodyHtml($lesson['description']) ?></p>
    <?php endif; ?>

    <?php $hasAudio = (bool)$lesson['audio_path'];
          $pdfList = array_filter([
              ['slide', 'Slide della lezione', $lesson['pdf_slide_path']],
              ['exercise', 'Esercizio pratico', $lesson['pdf_exercise_path']],
          ], fn($m) => !empty($m[2])); ?>
    <?php if ($hasAudio || $pdfList): ?>
        <h2 class="sect">Materiali</h2>
        <div class="card">
            <?php $isFirst = true; ?>
            <?php if ($hasAudio): $isFirst = false; ?>
                <audio controls preload="none" style="width:100%">
                    <source src="materiale.php?lesson=<?= $lessonId ?>&type=audio">
                </audio>
                <p style="margin:.75rem 0 0"><a class="attach" href="materiale.php?lesson=<?= $lessonId ?>&type=audio&scarica=1">Scarica l'audio</a></p>
            <?php endif; ?>

            <?php foreach ($pdfList as [$mtype, $mlabel, $mpath]): ?>
                <div class="card-row"<?= !$isFirst ? ' style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--surface)"' : '' ?>>
                    <span class="grow"><h3><?= $mlabel ?></h3><span class="meta">PDF</span></span>
                    <a class="btn ghost" style="min-height:38px;padding:.4rem .8rem;font-size:.8125rem" href="materiale.php?lesson=<?= $lessonId ?>&type=<?= $mtype ?>" target="_blank" rel="noopener">Visualizza</a>
                    <a class="btn ghost" style="min-height:38px;padding:.4rem .8rem;font-size:.8125rem" href="materiale.php?lesson=<?= $lessonId ?>&type=<?= $mtype ?>&scarica=1">Scarica</a>
                </div>
                <?php $isFirst = false; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="sect">I miei appunti</h2>
    <div class="card">
        <?php if ($noteMsg): ?><div class="msg ok"><?= htmlspecialchars($noteMsg) ?></div><?php endif; ?>
        <p class="meta" style="margin:0 0 .75rem">Solo tu li vedi. Non sono condivisi con nessuno, nemmeno con Valentina.</p>
        <form method="post">
            <?= corsoCsrfField('appunti') ?>
            <input type="hidden" name="nota" value="1">
            <textarea name="nota" rows="6" maxlength="20000" placeholder="Scrivi qui le tue osservazioni su questa lezione..."><?= htmlspecialchars($noteBody) ?></textarea>
            <button type="submit" class="btn">Salva appunti</button>
        </form>
    </div>

    <p class="meta" style="margin-top:1.5rem">Hai un compito da consegnare o una domanda? Vai al <a href="forum.php?lesson=<?= $lessonId ?>">forum di questa lezione</a>.</p>
</div>
<?php corsoHtmlFoot(); ?>
