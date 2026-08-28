<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

$stmt = hdDb()->prepare('SELECT id, title FROM courses WHERE id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) { http_response_code(404); exit('Corso non trovato.'); }

$error = '';
$generatedPassword = '';
$enrolledEmail = '';
$wasExisting = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'iscrivi')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $name  = trim($_POST['name'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Questa email non sembra valida.';
        } else {
            $stmt = hdDb()->prepare('SELECT id FROM hd_users WHERE email = ?');
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            try {
                if ($existing) {
                    $userId = (int)$existing['id'];
                    $wasExisting = true;
                } else {
                    // R7: creazione manuale, nessun self-signup pubblico.
                    // GDPR: base contrattuale (corso a pagamento), consenso
                    // registrato al momento della creazione da parte dell'admin.
                    $generatedPassword = bin2hex(random_bytes(8));
                    $stmt = hdDb()->prepare('INSERT INTO hd_users (email, password_hash, name, role, verified_at, gdpr_consent, gdpr_date) VALUES (?, ?, ?, ?, NOW(), 1, NOW())');
                    $stmt->execute([$email, hdHashPassword($generatedPassword), $name, 'student']);
                    $userId = (int)hdDb()->lastInsertId();
                }

                $stmt = hdDb()->prepare('INSERT IGNORE INTO course_enrollments (user_id, course_id) VALUES (?, ?)');
                $stmt->execute([$userId, $courseId]);
                $enrolledEmail = $email;
            } catch (PDOException $e) {
                // SEC-CORSO-003: mai esporre il messaggio DB grezzo
                $error = 'Non è stato possibile completare l\'iscrizione.';
            }
        }
    }
}

corsoHtmlHead('Iscrivi corsista');
corsoNav($admin, true, 'corsi');
?>
<div class="wrap" style="max-width:560px">
    <p class="eyebrow"><a href="index.php" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($course['title']) ?></a></p>
    <h1 class="page">Iscrivi una corsista</h1>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($enrolledEmail && $generatedPassword): ?>
        <div class="reveal">
            <p class="eyebrow" style="color:#E4C4D0">Password per <?= htmlspecialchars($enrolledEmail) ?></p>
            <p class="pw" id="pw"><?= htmlspecialchars($generatedPassword) ?></p>
            <button type="button" class="btn ghost" onclick="navigator.clipboard.writeText(document.getElementById('pw').textContent.trim()).then(()=>{this.textContent='Copiata';setTimeout(()=>this.textContent='Copia password',2000)})">Copia password</button>
            <p class="warn">Questa password non sarà più visibile dopo aver lasciato questa pagina. Copiala e mandala adesso.</p>
        </div>
    <?php elseif ($enrolledEmail && $wasExisting): ?>
        <div class="msg ok">
            <?= htmlspecialchars($enrolledEmail) ?> è ora iscritta a questo corso.
            Aveva già un account, quindi entra con la password che usa di solito.
            Se l'ha persa, puoi generarne una nuova dalla <a href="index.php">pagina dei corsi</a>.
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <?= corsoCsrfField('iscrivi') ?>
            <input type="hidden" name="course_id" value="<?= (int)$courseId ?>">

            <label for="email">Email della corsista</label>
            <input type="email" id="email" name="email" required autofocus>

            <label for="name">Nome</label>
            <input type="text" id="name" name="name" placeholder="Come vuoi che appaia nel forum">

            <button type="submit" class="btn">Iscrivi al corso</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
