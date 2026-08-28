<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user = corsoRequireStudent();
if (corsoIsAdmin((int)$user['id'])) {
    header('Location: admin/index.php');
    exit;
}

$stmt = hdDb()->prepare(
    'SELECT c.id, c.slug, c.title FROM courses c
     JOIN course_enrollments e ON e.course_id = c.id
     WHERE e.user_id = ? ORDER BY c.created_at DESC'
);
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll();

$firstName = trim(explode(' ', trim((string)$user['name']))[0] ?? '');

corsoHtmlHead('Il mio corso');
corsoNav($user, false, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow">Area riservata</p>
    <h1 class="hero"><?= $firstName ? 'Ciao ' . htmlspecialchars($firstName) : 'Bentornata' ?></h1>
    <p class="hero-sub">Qui trovi le lezioni, i materiali e lo spazio per i compiti.</p>

    <?php if (empty($courses)): ?>
        <div class="card empty">
            <?= corsoCheckMark(false) ?>
            <p>Non risulti ancora iscritta a nessun corso.</p>
            <p class="meta">Se pensi sia un errore, scrivi a Valentina e sistemiamo subito.</p>
        </div>
    <?php else: ?>
        <?php foreach ($courses as $c): ?>
            <?php
            $st = hdDb()->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ? AND deleted_at IS NULL');
            $st->execute([$c['id']]);
            $n = (int)$st->fetchColumn();
            ?>
            <a class="card card-row" href="corso.php?slug=<?= urlencode($c['slug']) ?>">
                <span class="grow">
                    <h3><?= htmlspecialchars($c['title']) ?></h3>
                    <span class="meta"><?= $n ?> <?= $n === 1 ? 'classe' : 'classi' ?></span>
                </span>
                <span class="badge teal">Apri</span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
