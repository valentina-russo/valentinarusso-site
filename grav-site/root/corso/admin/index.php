<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$courses = hdDb()->query('SELECT id, slug, title FROM courses ORDER BY created_at DESC')->fetchAll();

$resetPassword = '';
$resetEmail    = '';
$error         = '';

// Reset password per un corsista che ha perso l'accesso.
// Prima non esisteva: iscrivi.php genera la password solo alla CREAZIONE
// dell'account, quindi un utente gia esistente restava senza via di rientro.
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
                $error = 'Corsista non trovata.';
            } elseif ($target['role'] === 'admin') {
                $error = 'Non puoi resettare da qui la password di un amministratore.';
            } else {
                $newPw = bin2hex(random_bytes(8));
                // session_ver++ chiude le sessioni ancora aperte altrove
                $stmt = hdDb()->prepare('UPDATE hd_users SET password_hash = ?, session_ver = session_ver + 1 WHERE id = ?');
                $stmt->execute([hdHashPassword($newPw), $uid]);
                $resetPassword = $newPw;
                $resetEmail    = $target['email'];
            }
        } catch (PDOException $e) {
            $error = 'Errore durante il reset della password.';
        }
    }
}

corsoHtmlHead('Corsi');
corsoNav($admin, true, 'corsi');
?>
<div class="wrap">
    <p class="eyebrow">Pannello di Valentina</p>
    <h1 class="hero">I tuoi corsi</h1>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($resetPassword): ?>
        <div class="reveal">
            <p class="eyebrow" style="color:#E4C4D0">Nuova password per <?= htmlspecialchars($resetEmail) ?></p>
            <p class="pw" id="pw"><?= htmlspecialchars($resetPassword) ?></p>
            <button type="button" class="btn ghost" onclick="navigator.clipboard.writeText(document.getElementById('pw').textContent.trim()).then(()=>{this.textContent='Copiata';setTimeout(()=>this.textContent='Copia password',2000)})">Copia password</button>
            <p class="warn">Questa password non sarà più visibile dopo aver lasciato questa pagina. Copiala e mandala adesso.</p>
        </div>
    <?php endif; ?>

    <p><a class="btn" href="corso-edit.php">Nuovo corso</a></p>

    <?php if (empty($courses)): ?>
        <div class="card empty">
            <p>Non hai ancora creato nessun corso.</p>
            <p class="meta">Crea il primo corso, poi aggiungi le classi una alla volta.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($courses as $c): ?>
        <?php
        $st = hdDb()->prepare('SELECT u.id, u.name, u.email FROM course_enrollments e
                               JOIN hd_users u ON u.id = e.user_id
                               WHERE e.course_id = ? ORDER BY u.name, u.email');
        $st->execute([$c['id']]);
        $students = $st->fetchAll();

        $st = hdDb()->prepare('SELECT id, position, title FROM lessons
                               WHERE course_id = ? AND deleted_at IS NULL ORDER BY position');
        $st->execute([$c['id']]);
        $lessons = $st->fetchAll();
        ?>
        <div class="card">
            <h3 style="font-family:var(--f-head);font-size:1.375rem"><?= htmlspecialchars($c['title']) ?></h3>
            <p class="meta" style="margin:0 0 1rem">
                <span class="badge"><?= count($lessons) ?> <?= count($lessons) === 1 ? 'classe' : 'classi' ?></span>
                <span class="badge teal"><?= count($students) ?> <?= count($students) === 1 ? 'iscritta' : 'iscritte' ?></span>
            </p>
            <p style="display:flex;gap:.5rem;flex-wrap:wrap;margin:0 0 1.5rem">
                <a class="btn" href="lezione-edit.php?course_id=<?= (int)$c['id'] ?>">Aggiungi classe</a>
                <a class="btn ghost" href="iscrivi.php?course_id=<?= (int)$c['id'] ?>">Iscrivi corsista</a>
                <a class="btn ghost" href="corso-edit.php?id=<?= (int)$c['id'] ?>">Modifica</a>
            </p>

            <h2 class="sect" style="margin-top:0">Classi</h2>
            <?php if (empty($lessons)): ?>
                <p class="meta">Nessuna classe ancora.</p>
            <?php else: ?>
                <?php foreach ($lessons as $l): ?>
                    <div class="card-row" style="padding:.55rem 0;border-top:1px solid var(--surface)">
                        <span class="num" style="width:34px;height:34px;font-size:.9375rem"><?= (int)$l['position'] ?></span>
                        <span class="grow"><?= htmlspecialchars($l['title']) ?></span>
                        <a class="meta" href="lezione-edit.php?id=<?= (int)$l['id'] ?>">Modifica</a>
                        <a class="meta" href="../lezione.php?id=<?= (int)$l['id'] ?>">Apri</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2 class="sect">Corsiste</h2>
            <?php if (empty($students)): ?>
                <p class="meta">Nessuna iscritta ancora.</p>
            <?php else: ?>
                <?php foreach ($students as $s): ?>
                    <div class="card-row" style="padding:.55rem 0;border-top:1px solid var(--surface)">
                        <span class="grow">
                            <?= htmlspecialchars($s['name'] ?: $s['email']) ?>
                            <div class="meta"><?= htmlspecialchars($s['email']) ?></div>
                        </span>
                        <form method="post" onsubmit="return confirm('Generare una nuova password per <?= htmlspecialchars(addslashes($s['email'])) ?>? Quella attuale smetterà di funzionare.');">
                            <?= corsoCsrfField('reset-pw') ?>
                            <input type="hidden" name="reset_user_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn ghost" style="min-height:38px;padding:.4rem .8rem;font-size:.8125rem">Reset password</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php corsoHtmlFoot(); ?>
