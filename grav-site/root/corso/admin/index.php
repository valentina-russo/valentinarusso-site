<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$courses = hdDb()->query('SELECT id, slug, title FROM courses ORDER BY created_at DESC')->fetchAll();

corsoHtmlHead('Corsi');
corsoNav($admin, true, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow">Pannello di Valentina</p>
    <h1 class="hero">I tuoi corsi</h1>
    <p class="hero-sub">Ogni corso può avere fino a <?= CORSO_MAX_CLASSI ?> classi attive: gruppi diversi che seguono lo stesso programma in periodi diversi.</p>

    <p><a class="btn" href="corso-edit.php">Nuovo corso</a></p>

    <?php if (empty($courses)): ?>
        <div class="card empty">
            <p>Non hai ancora creato nessun corso.</p>
            <p class="meta">Crea il corso, poi aggiungi la prima classe.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($courses as $c): ?>
        <?php $classi = corsoCohorts((int)$c['id']); ?>
        <div class="card">
            <div class="card-row" style="align-items:flex-start">
                <span class="grow">
                    <h3 style="font-family:var(--f-head);font-size:1.375rem"><?= htmlspecialchars($c['title']) ?></h3>
                    <span class="meta"><?= count($classi) ?> di <?= CORSO_MAX_CLASSI ?> classi attive</span>
                </span>
                <a class="meta" href="corso-edit.php?id=<?= (int)$c['id'] ?>">Rinomina</a>
            </div>

            <div style="margin-top:1.25rem">
            <?php if (empty($classi)): ?>
                <p class="meta">Nessuna classe ancora. Creane una per iniziare ad aggiungere lezioni e iscritte.</p>
            <?php else: ?>
                <?php foreach ($classi as $cl): ?>
                    <?php
                    $st = hdDb()->prepare('SELECT COUNT(*) FROM lessons WHERE cohort_id = ? AND deleted_at IS NULL');
                    $st->execute([$cl['id']]);
                    $nLez = (int)$st->fetchColumn();
                    $st = hdDb()->prepare('SELECT COUNT(*) FROM course_enrollments WHERE cohort_id = ?');
                    $st->execute([$cl['id']]);
                    $nStud = (int)$st->fetchColumn();
                    $pend = corsoPendingCount((int)$cl['id']);
                    ?>
                    <a class="card card-row" href="classe.php?id=<?= (int)$cl['id'] ?>" style="margin-bottom:.6rem">
                        <span class="grow">
                            <h3><?= htmlspecialchars($cl['name']) ?></h3>
                            <span class="meta"><?= $nLez ?> <?= $nLez === 1 ? 'lezione' : 'lezioni' ?> · <?= $nStud ?> <?= $nStud === 1 ? 'iscritta' : 'iscritte' ?></span>
                        </span>
                        <?php if ($pend > 0): ?>
                            <span class="badge oro"><?= $pend ?> da correggere</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <p style="margin:1rem 0 0">
            <?php if (corsoCanAddCohort((int)$c['id'])): ?>
                <a class="btn ghost" href="classe-edit.php?course_id=<?= (int)$c['id'] ?>">Nuova classe</a>
            <?php else: ?>
                <span class="meta">Hai raggiunto il massimo di <?= CORSO_MAX_CLASSI ?> classi attive per questo corso. Archiviane una per farne spazio.</span>
            <?php endif; ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>
<?php corsoHtmlFoot(); ?>
