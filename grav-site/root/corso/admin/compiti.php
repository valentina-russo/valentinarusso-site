<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

// Centro operativo settimanale: chi sta aspettando una correzione, e da quanto.
$admin = corsoRequireAdmin();

$filtro = isset($_GET['classe']) ? (int)$_GET['classe'] : null;
$classeSel = $filtro ? corsoCohort($filtro) : null;
if ($filtro && !$classeSel) $filtro = null;

$tasks = corsoPendingHomework($filtro);

// tutte le classi attive, per il selettore
$classi = hdDb()->query(
    'SELECT co.id, co.name, c.title AS course_title
     FROM cohorts co JOIN courses c ON c.id = co.course_id
     WHERE co.archived_at IS NULL
     ORDER BY c.created_at DESC, co.position ASC'
)->fetchAll();

corsoHtmlHead('Da correggere');
corsoNav($admin, true, 'compiti');
?>
<div class="wrap">
    <p class="eyebrow">Il tuo lavoro di questa settimana</p>
    <h1 class="hero"><?= count($tasks) === 0 ? 'Tutto corretto' : count($tasks) . ' ' . (count($tasks) === 1 ? 'compito da correggere' : 'compiti da correggere') ?></h1>
    <?php if ($tasks): ?><p class="hero-sub">In cima chi aspetta da più tempo.</p><?php endif; ?>

    <?php if (count($classi) > 1): ?>
        <p style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.75rem">
            <a class="badge<?= $filtro === null ? ' oro' : '' ?>" style="text-decoration:none;padding:.4rem .85rem" href="compiti.php">Tutte le classi</a>
            <?php foreach ($classi as $cl): ?>
                <?php $n = corsoPendingCount((int)$cl['id']); ?>
                <a class="badge<?= $filtro === (int)$cl['id'] ? ' oro' : '' ?>" style="text-decoration:none;padding:.4rem .85rem"
                   href="compiti.php?classe=<?= (int)$cl['id'] ?>">
                    <?= htmlspecialchars($cl['course_title']) ?> · <?= htmlspecialchars($cl['name']) ?><?= $n > 0 ? ' (' . $n . ')' : '' ?>
                </a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?php if (empty($tasks)): ?>
        <div class="card empty">
            <?= corsoCheckMark(true) ?>
            <p>Non c'è nessun compito in attesa di risposta<?= $classeSel ? ' in questa classe' : '' ?>.</p>
            <p class="meta">Quando un'allieva posta un esercizio, lo trovi qui.</p>
        </div>
    <?php else: ?>
        <?php foreach ($tasks as $t): ?>
            <?php
            $urg = corsoUrgency($t['created_at']);
            $snippet = trim(preg_replace('/\s+/', ' ', $t['body']));
            if (mb_strlen($snippet) > 110) $snippet = mb_substr($snippet, 0, 110) . '…';
            ?>
            <a class="card task <?= $urg ?>" href="../lezione.php?id=<?= (int)$t['lesson_id'] ?>#forum">
                <span class="grow">
                    <span class="who"><?= htmlspecialchars($t['student_name'] ?: $t['student_email']) ?></span>
                    <span class="badge" style="margin-left:.5rem">Lezione <?= (int)$t['lesson_position'] ?></span>
                    <div class="snippet"><?= htmlspecialchars($snippet) ?></div>
                    <span class="meta">
                        <?= htmlspecialchars(corsoRelativeTime($t['created_at'])) ?>
                        <?php if ($filtro === null): ?> · <?= htmlspecialchars($t['course_title'] . ' · ' . $t['cohort_name']) ?><?php endif; ?>
                    </span>
                </span>
                <span class="dot" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
