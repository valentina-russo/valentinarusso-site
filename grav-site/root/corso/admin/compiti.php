<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

// Centro operativo settimanale: chi sta aspettando una correzione, e da quanto.
$admin = corsoRequireAdmin();
$tasks = corsoPendingHomework();

corsoHtmlHead('Da correggere');
corsoNav($admin, true, 'compiti');
?>
<div class="wrap">
    <p class="eyebrow">Il tuo lavoro di questa settimana</p>
    <h1 class="hero"><?= count($tasks) === 0 ? 'Tutto corretto' : count($tasks) . ' ' . (count($tasks) === 1 ? 'compito da correggere' : 'compiti da correggere') ?></h1>
    <?php if ($tasks): ?>
        <p class="hero-sub">In cima chi aspetta da più tempo.</p>
    <?php endif; ?>

    <?php if (empty($tasks)): ?>
        <div class="card empty">
            <?= corsoCheckMark(true) ?>
            <p>Non c'è nessun compito in attesa di risposta.</p>
            <p class="meta">Quando una corsista posta un esercizio, lo trovi qui.</p>
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
                    <span class="badge" style="margin-left:.5rem">Classe <?= (int)$t['lesson_position'] ?></span>
                    <div class="snippet"><?= htmlspecialchars($snippet) ?></div>
                    <span class="meta"><?= htmlspecialchars(corsoRelativeTime($t['created_at'])) ?></span>
                </span>
                <span class="dot" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
