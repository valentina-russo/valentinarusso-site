<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$cohortId = (int)($_GET['id'] ?? 0);
$classe = corsoCohort($cohortId);
if (!$classe) { http_response_code(404); exit('Classe non trovata.'); }

$resetPassword = '';
$resetEmail    = '';
$error         = '';

// Reset password per un'allieva che ha perso l'accesso.
// iscrivi.php genera la password solo alla CREAZIONE dell'account, quindi
// senza questo un'utente gia esistente non avrebbe modo di rientrare.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_id'])) {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'reset-pw')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $uid = (int)$_POST['reset_user_id'];
        try {
            $stmt = hdDb()->prepare('SELECT email, role FROM hd_users WHERE id = ?');
            $stmt->execute([$uid]);
            $target = $stmt->fetch();
            if (!$target) {
                $error = 'Allieva non trovata.';
            } elseif ($target['role'] === 'admin') {
                $error = 'Non puoi resettare da qui la password di un amministratore.';
            } else {
                $newPw = bin2hex(random_bytes(8));
                // session_ver++ chiude le sessioni ancora aperte altrove
                hdDb()->prepare('UPDATE hd_users SET password_hash = ?, session_ver = session_ver + 1 WHERE id = ?')
                      ->execute([hdHashPassword($newPw), $uid]);
                $resetPassword = $newPw;
                $resetEmail    = $target['email'];
            }
        } catch (PDOException $e) {
            $error = 'Errore durante il reset della password.';
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

$pend = corsoPendingCount($cohortId);

corsoHtmlHead($classe['name']);
corsoNav($admin, true, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow"><a href="index.php" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($classe['course_title']) ?></a></p>
    <h1 class="hero"><?= htmlspecialchars($classe['name']) ?></h1>
    <p class="hero-sub">
        <?= count($lessons) ?> <?= count($lessons) === 1 ? 'lezione' : 'lezioni' ?> ·
        <?= count($students) ?> <?= count($students) === 1 ? 'iscritta' : 'iscritte' ?>
        <?php if ($pend > 0): ?> · <a href="compiti.php?classe=<?= $cohortId ?>"><?= $pend ?> da correggere</a><?php endif; ?>
    </p>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($resetPassword): ?>
        <div class="reveal">
            <p class="eyebrow" style="color:#E4C4D0">Nuova password per <?= htmlspecialchars($resetEmail) ?></p>
            <p class="pw" id="pw"><?= htmlspecialchars($resetPassword) ?></p>
            <button type="button" class="btn ghost" onclick="navigator.clipboard.writeText(document.getElementById('pw').textContent.trim()).then(()=>{this.textContent='Copiata';setTimeout(()=>this.textContent='Copia password',2000)})">Copia password</button>
            <p class="warn">Questa password non sarà più visibile dopo aver lasciato questa pagina. Copiala e mandala adesso.</p>
        </div>
    <?php endif; ?>

    <p style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn" href="lezione-edit.php?cohort_id=<?= $cohortId ?>">Aggiungi lezione</a>
        <a class="btn ghost" href="iscrivi.php?cohort_id=<?= $cohortId ?>">Iscrivi allieva</a>
        <a class="btn ghost" href="classe-edit.php?id=<?= $cohortId ?>">Rinomina classe</a>
    </p>

    <h2 class="sect">Lezioni</h2>
    <?php if (empty($lessons)): ?>
        <div class="card"><p class="meta">Nessuna lezione ancora.</p></div>
    <?php else: ?>
        <div class="card">
        <?php foreach ($lessons as $l): ?>
            <div class="card-row" style="padding:.6rem 0;border-top:1px solid var(--surface)">
                <span class="num" style="width:34px;height:34px;font-size:.9375rem"><?= (int)$l['position'] ?></span>
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
                <form method="post" onsubmit="return confirm('Generare una nuova password per <?= htmlspecialchars(addslashes($s['email'])) ?>? Quella attuale smetterà di funzionare.');">
                    <?= corsoCsrfField('reset-pw') ?>
                    <input type="hidden" name="reset_user_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn ghost" style="min-height:38px;padding:.4rem .8rem;font-size:.8125rem">Reset password</button>
                </form>
                <form method="post" onsubmit="return confirm('Togliere <?= htmlspecialchars(addslashes($s['email'])) ?> da questa classe? L\'account resta, e i compiti già scritti restano.');">
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
