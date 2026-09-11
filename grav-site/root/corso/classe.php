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
<div class="wrap larga">
    <div class="aula-testa">
        <a class="briciola" href="index.php">&larr; I miei corsi</a>
        <h1><?= htmlspecialchars($classe['course_title']) ?></h1>
        <?php $pronte = count(array_filter($lessons, fn($l) => corsoVideoSorgente($l['bunny_video_id']) !== null)); ?>
        <p class="sotto">
            <?= count($lessons) ?> <?= count($lessons) === 1 ? 'lezione' : 'lezioni' ?><?php if ($pronte): ?> &middot; <?= $pronte ?> con la registrazione<?php endif; ?> &middot; <?= htmlspecialchars($classe['name']) ?>
        </p>
    </div>

    <?php if (empty($lessons)): ?>
        <div class="card empty"><p>Nessuna lezione pubblicata per ora.</p>
        <p class="meta">La trovi qui appena Valentina carica la prima registrazione.</p></div>
    <?php else: ?>
        <div class="lez-griglia">
        <?php foreach ($lessons as $l): ?>
            <?php $v = corsoVideoSorgente($l['bunny_video_id']);
                  $cop = $v ? match ($v['tipo']) {
                      'youtube' => 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg',
                      'drive'   => 'https://drive.google.com/thumbnail?id=' . $v['id'] . '&sz=w640',
                      'bunny'   => bunnyThumbUrl($v['id']),
                      default   => null,
                  } : null; ?>
            <a class="lez-card" href="lezione.php?id=<?= (int)$l['id'] ?>">
                <span class="lez-cover">
                    <span class="senza" aria-hidden="true"><?= (int)$l['position'] ?></span>
                    <?php if ($cop): ?><img src="<?= htmlspecialchars($cop) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                    <span class="n"><?= (int)$l['position'] ?></span>
                </span>
                <span class="lez-corpo">
                    <h3><?= htmlspecialchars($l['title']) ?></h3>
                    <span class="stato<?= $v ? '' : ' arrivo' ?>"><?= $v ? 'Registrazione disponibile' : 'Registrazione in arrivo' ?></span>
                </span>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
