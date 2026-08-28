<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();

$courses = hdDb()->query('SELECT id, slug, title FROM courses ORDER BY created_at DESC')->fetchAll();

foreach ($courses as &$c) {
    $stmt = hdDb()->prepare(
        'SELECT u.id, u.name, u.email FROM course_enrollments e
         JOIN hd_users u ON u.id = e.user_id
         WHERE e.course_id = ? ORDER BY u.name'
    );
    $stmt->execute([$c['id']]);
    $c['students'] = $stmt->fetchAll();

    $stmt2 = hdDb()->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ? AND deleted_at IS NULL');
    $stmt2->execute([$c['id']]);
    $c['lesson_count'] = (int)$stmt2->fetchColumn();
}
unset($c);

corsoHtmlHead('Admin - Corsi');
?>
<div class="top-nav">
    <span>Admin: <?= htmlspecialchars($admin['name'] ?: $admin['email']) ?></span>
    <a href="../logout.php">Esci</a>
</div>
<div class="container">
    <h1>Corsi</h1>
    <p><a class="btn" href="corso-edit.php">+ Nuovo corso</a></p>

    <?php foreach ($courses as $c): ?>
        <div class="card">
            <h2><?= htmlspecialchars($c['title']) ?></h2>
            <p class="muted"><?= $c['lesson_count'] ?> classi &middot; <?= count($c['students']) ?> iscritti</p>
            <p>
                <a class="btn secondary" href="corso-edit.php?id=<?= (int)$c['id'] ?>">Modifica corso</a>
                <a class="btn secondary" href="lezione-edit.php?course_id=<?= (int)$c['id'] ?>">+ Aggiungi classe</a>
                <a class="btn secondary" href="iscrivi.php?course_id=<?= (int)$c['id'] ?>">+ Iscrivi corsista</a>
            </p>

            <h3>Corsisti</h3>
            <?php if (empty($c['students'])): ?>
                <p class="muted">Nessun iscritto ancora.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($c['students'] as $s): ?>
                        <li><?= htmlspecialchars($s['name'] ?: $s['email']) ?> (<?= htmlspecialchars($s['email']) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>Classi</h3>
            <?php
            $stmt = hdDb()->prepare('SELECT id, position, title FROM lessons WHERE course_id = ? AND deleted_at IS NULL ORDER BY position');
            $stmt->execute([$c['id']]);
            $lessons = $stmt->fetchAll();
            ?>
            <?php if (empty($lessons)): ?>
                <p class="muted">Nessuna classe ancora.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($lessons as $l): ?>
                        <li>
                            Classe <?= (int)$l['position'] ?> &mdash; <?= htmlspecialchars($l['title']) ?>
                            &nbsp;<a href="lezione-edit.php?id=<?= (int)$l['id'] ?>">Modifica</a>
                            &nbsp;<a href="../lezione.php?id=<?= (int)$l['id'] ?>">Vedi come corsista</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php corsoHtmlFoot(); ?>
