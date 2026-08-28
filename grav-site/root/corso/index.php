<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user = corsoRequireStudent();
$isAdmin = corsoIsAdmin((int)$user['id']);

if ($isAdmin) {
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

corsoHtmlHead('I miei corsi');
?>
<div class="top-nav">
    <span>Ciao, <?= htmlspecialchars($user['name'] ?: $user['email']) ?></span>
    <span><a href="letture.php">Prenota una lettura</a> &middot; <a href="logout.php">Esci</a></span>
</div>
<div class="container">
    <h1>I miei corsi</h1>
    <?php if (empty($courses)): ?>
        <div class="card empty-state">
            <p>Non risulti ancora iscritta/o a nessun corso.</p>
            <p class="muted">Se pensi sia un errore, scrivi a Valentina.</p>
        </div>
    <?php else: ?>
        <?php foreach ($courses as $c): ?>
            <div class="card">
                <h2><?= htmlspecialchars($c['title']) ?></h2>
                <a class="btn" href="corso.php?slug=<?= urlencode($c['slug']) ?>">Vai al corso</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
