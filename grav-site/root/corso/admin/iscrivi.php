<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

$stmt = hdDb()->prepare('SELECT id, title FROM courses WHERE id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('Corso non trovato.');
}

$error = '';
$generatedPassword = '';
$enrolledEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'iscrivi')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $name = trim($_POST['name'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email non valida.';
        } else {
            $stmt = hdDb()->prepare('SELECT id FROM hd_users WHERE email = ?');
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            try {
                if ($existing) {
                    $userId = (int)$existing['id'];
                } else {
                    // R7: creazione manuale account, nessun self-signup pubblico
                    // GDPR: base contrattuale (iscrizione a corso a pagamento),
                    // consenso registrato al momento della creazione admin -
                    // vedi legal-guardian in Acceptance Criteria della spec
                    $generatedPassword = bin2hex(random_bytes(8));
                    $hash = hdHashPassword($generatedPassword);
                    $stmt = hdDb()->prepare('INSERT INTO hd_users (email, password_hash, name, role, verified_at, gdpr_consent, gdpr_date) VALUES (?, ?, ?, ?, NOW(), 1, NOW())');
                    $stmt->execute([$email, $hash, $name, 'student']);
                    $userId = (int)hdDb()->lastInsertId();
                }

                $stmt = hdDb()->prepare('INSERT IGNORE INTO course_enrollments (user_id, course_id) VALUES (?, ?)');
                $stmt->execute([$userId, $courseId]);
                $enrolledEmail = $email;
            } catch (PDOException $e) {
                // SEC-CORSO-003: mai esporre il messaggio DB grezzo
                $error = 'Errore nella creazione/iscrizione dell utente.';
            }
        }
    }
}

corsoHtmlHead('Iscrivi corsista');
?>
<div class="top-nav">
    <a href="index.php">&larr; Corsi</a>
    <a href="../logout.php">Esci</a>
</div>
<div class="container">
    <h1>Iscrivi corsista &mdash; <?= htmlspecialchars($course['title']) ?></h1>
    <div class="card">
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($enrolledEmail): ?>
            <div class="success">
                <?= htmlspecialchars($enrolledEmail) ?> iscritta/o al corso.
                <?php if ($generatedPassword): ?>
                    <br>Password generata (comunicala tu alla persona, non verra piu mostrata):
                    <br><strong><?= htmlspecialchars($generatedPassword) ?></strong>
                <?php else: ?>
                    <br>Account gia esistente, solo iscrizione al corso aggiunta.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <?= corsoCsrfField('iscrivi') ?>
            <input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
            <label for="email">Email corsista</label>
            <input type="email" id="email" name="email" required>
            <label for="name">Nome</label>
            <input type="text" id="name" name="name">
            <button type="submit" class="btn">Iscrivi</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
