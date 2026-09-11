<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$cohortId = (int)($_GET['id'] ?? 0);
$classe = corsoCohort($cohortId);
if (!$classe) { http_response_code(404); exit('Classe non trovata.'); }

$avviso = '';
$error  = '';

// Password: prima qui si generava una password e la si mostrava a schermo, con
// tutto il rito di copiarla e mandarla a mano. Ora parte all'allieva lo stesso
// link della pagina "password dimenticata": se la sceglie lei, e nessun
// segreto passa da Valentina.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_id'])) {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'reset-pw')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $uid = (int)$_POST['reset_user_id'];
        try {
            $stmt = hdDb()->prepare('SELECT email, name, role FROM hd_users WHERE id = ?');
            $stmt->execute([$uid]);
            $target = $stmt->fetch();
            if (!$target) {
                $error = 'Allieva non trovata.';
            } elseif ($target['role'] === 'admin') {
                $error = "Da qui non si tocca l'accesso di un'amministratrice.";
            } else {
                $esito = corsoMandaLinkPassword((string)$target['email'], (string)$target['name']);
                $avviso = $esito
                    ? 'Link mandato a ' . $target['email'] . '. Vale due ore: la password la scegle lei.'
                    : "La mail non e' partita. Riprova fra un momento.";
                if (!$esito) { $error = $avviso; $avviso = ''; }
            }
        } catch (PDOException $e) {
            $error = 'Non sono riuscito a preparare il link della password.';
        }
    }
}

// Rimuove un'allieva da QUESTA classe (l'account resta, puo essere
// riscritta o iscritta a un'altra classe)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_user_id'])) {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'rimuovi')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        hdDb()->prepare('DELETE FROM course_enrollments WHERE user_id = ? AND cohort_id = ?')
              ->execute([(int)$_POST['remove_user_id'], $cohortId]);
        header('Location: classe.php?id=' . $cohortId);
        exit;
    }
}

$st = hdDb()->prepare('SELECT id, position, title, bunny_video_id FROM lessons
                       WHERE cohort_id = ? AND deleted_at IS NULL ORDER BY position');
$st->execute([$cohortId]);
$lessons = $st->fetchAll();

$st = hdDb()->prepare('SELECT u.id, u.name, u.email FROM course_enrollments e
                       JOIN hd_users u ON u.id = e.user_id
                       WHERE e.cohort_id = ? ORDER BY u.name, u.email');
$st->execute([$cohortId]);
$students = $st->fetchAll();

$daFare = corsoPendingHomework($cohortId);

corsoHtmlHead($classe['name']);
corsoNav($admin, true, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow"><a href="index.php" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($classe['course_title']) ?></a></p>
    <h1 class="hero"><?= htmlspecialchars($classe['name']) ?></h1>
    <p class="hero-sub">
        <?= count($lessons) ?> <?= count($lessons) === 1 ? 'lezione' : 'lezioni' ?> ·
        <?= count($students) ?> <?= count($students) === 1 ? 'iscritta' : 'iscritte' ?>
    </p>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($avviso): ?><div class="msg ok"><?= htmlspecialchars($avviso) ?></div><?php endif; ?>

    <p style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn" href="lezione-edit.php?cohort_id=<?= $cohortId ?>">Aggiungi lezione</a>
        <a class="btn ghost" href="iscrivi.php?cohort_id=<?= $cohortId ?>">Iscrivi allieva</a>
        <a class="btn ghost" href="classe-edit.php?id=<?= $cohortId ?>">Rinomina classe</a>
    </p>

    <?php if ($daFare): ?>
        <h2 class="sect">Da correggere</h2>
        <p class="meta" style="margin-top:-.5rem">Compiti a cui non hai ancora risposto. In cima chi aspetta da pi&ugrave; tempo.</p>
        <?php foreach (array_slice($daFare, 0, 5) as $t): ?>
            <?php
            $pezzo = trim(preg_replace('/\s+/', ' ', (string)$t['body']));
            if (mb_strlen($pezzo) > 110) { $pezzo = mb_substr($pezzo, 0, 110) . '…'; }
            ?>
            <a class="card task <?= corsoUrgency($t['created_at']) ?>"
               href="../discussione.php?id=<?= (int)$t['id'] ?>&from=compiti&classe=<?= $cohortId ?>">
                <span class="grow">
                    <span class="who"><?= htmlspecialchars($t['student_name'] ?: $t['student_email']) ?></span>
                    <?php if ($t['lesson_position']): ?><span class="badge" style="margin-left:.5rem">Lezione <?= (int)$t['lesson_position'] ?></span><?php endif; ?>
                    <div class="snippet"><?= htmlspecialchars($t['title'] ? $t['title'] . ' — ' . $pezzo : $pezzo) ?></div>
                    <span class="meta"><?= htmlspecialchars(corsoRelativeTime($t['created_at'])) ?></span>
                </span>
                <span class="dot" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
        <?php if (count($daFare) > 5): ?>
            <p><a class="btn ghost" href="compiti.php?classe=<?= $cohortId ?>">Vedi tutti i <?= count($daFare) ?></a></p>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="sect">Lezioni</h2>
    <?php if (empty($lessons)): ?>
        <div class="card"><p class="meta">Nessuna lezione ancora.</p></div>
    <?php else: ?>
        <div class="card">
        <?php foreach ($lessons as $l): ?>
            <div class="card-row" style="padding:.6rem 0;border-top:1px solid var(--surface)">
                <?= corsoLessonThumb($l['bunny_video_id'], (int)$l['position']) ?>
                <span class="grow">
                    <?= htmlspecialchars($l['title']) ?>
                    <?php if (!$l['bunny_video_id']): ?><div class="meta">Registrazione non ancora caricata</div><?php endif; ?>
                </span>
                <a class="meta" href="lezione-edit.php?id=<?= (int)$l['id'] ?>">Modifica</a>
                <a class="meta" href="../lezione.php?id=<?= (int)$l['id'] ?>">Apri</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="sect">Allieve</h2>
    <?php if (empty($students)): ?>
        <div class="card"><p class="meta">Nessuna iscritta ancora.</p></div>
    <?php else: ?>
        <div class="card">
        <?php foreach ($students as $s): ?>
            <div class="card-row" style="padding:.65rem 0;border-top:1px solid var(--surface)">
                <span class="grow">
                    <?= htmlspecialchars($s['name'] ?: $s['email']) ?>
                    <div class="meta"><?= htmlspecialchars($s['email']) ?></div>
                </span>
                <form method="post" data-conferma="Mandare a <?= htmlspecialchars($s['email']) ?> il link per scegliere una password nuova?">
                    <?= corsoCsrfField('reset-pw') ?>
                    <input type="hidden" name="reset_user_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn ghost" style="min-height:38px;padding:.4rem .8rem;font-size:.8125rem">Manda link password</button>
                </form>
                <form method="post" data-conferma="Togliere <?= htmlspecialchars($s['email']) ?> da questa classe? L'account resta, e i compiti già scritti restano.">
                    <?= corsoCsrfField('rimuovi') ?>
                    <input type="hidden" name="remove_user_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn ghost" style="min-height:38px;padding:.4rem .8rem;font-size:.8125rem">Togli</button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
