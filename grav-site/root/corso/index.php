<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user = corsoRequireStudent();
if (corsoIsAdmin((int)$user['id'])) {
    header('Location: admin/index.php');
    exit;
}

// Un'allieva e iscritta a una CLASSE, non genericamente a un corso
$stmt = hdDb()->prepare(
    'SELECT co.id AS cohort_id, co.name AS cohort_name, c.title AS course_title
     FROM course_enrollments e
     JOIN cohorts co ON co.id = e.cohort_id
     JOIN courses c  ON c.id = co.course_id
     WHERE e.user_id = ? AND co.archived_at IS NULL
     ORDER BY c.created_at DESC, co.position ASC'
);
$stmt->execute([$user['id']]);
$classi = $stmt->fetchAll();

$firstName = trim(explode(' ', trim((string)$user['name']))[0] ?? '');

corsoHtmlHead('Il mio corso');
corsoNav($user, false, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow">Area riservata</p>
    <h1 class="hero"><?= $firstName ? 'Ciao ' . htmlspecialchars($firstName) : 'Bentornata' ?></h1>
    <p class="hero-sub">Qui trovi le lezioni, i materiali e lo spazio per i compiti.</p>

    <?php if (empty($classi)): ?>
        <div class="card empty">
            <?= corsoCheckMark(false) ?>
            <p>Non risulti ancora iscritta a nessun corso.</p>
            <p class="meta">Se pensi sia un errore, scrivi a Valentina e sistemiamo subito.</p>
        </div>
    <?php else: ?>
        <?php foreach ($classi as $cl): ?>
            <?php
            $st = hdDb()->prepare('SELECT COUNT(*) FROM lessons WHERE cohort_id = ? AND deleted_at IS NULL');
            $st->execute([$cl['cohort_id']]);
            $n = (int)$st->fetchColumn();
            // il nome della classe si mostra solo se serve a distinguere
            $st = hdDb()->prepare('SELECT COUNT(*) FROM course_enrollments e
                                   JOIN cohorts co ON co.id = e.cohort_id
                                   WHERE e.user_id = ? AND co.course_id = (SELECT course_id FROM cohorts WHERE id = ?)');
            $st->execute([$user['id'], $cl['cohort_id']]);
            $sameCourse = (int)$st->fetchColumn();
            ?>
            <a class="card card-row" href="classe.php?id=<?= (int)$cl['cohort_id'] ?>">
                <span class="grow">
                    <h3><?= htmlspecialchars($cl['course_title']) ?></h3>
                    <span class="meta">
                        <?php if ($sameCourse > 1): ?><?= htmlspecialchars($cl['cohort_name']) ?> · <?php endif; ?>
                        <?= $n ?> <?= $n === 1 ? 'lezione' : 'lezioni' ?>
                    </span>
                </span>
                <span class="badge teal">Apri</span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
