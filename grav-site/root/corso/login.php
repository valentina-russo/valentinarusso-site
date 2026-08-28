<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

hdSessionStart();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hdCsrfVerify($csrf, 'login')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = hdGetIp();

        if (hdIsRateLimited($email, $ip)) {
            $error = 'Troppi tentativi. Riprova tra qualche minuto.';
        } else {
            $stmt = hdDb()->prepare('SELECT id, email, password_hash, name, session_ver FROM hd_users WHERE email = ?');
            $stmt->execute([strtolower($email)]);
            $user = $stmt->fetch();

            // R20: stesso messaggio generico, email inesistente o password errata
            if (!$user || !hdVerifyPassword($password, $user['password_hash'])) {
                hdRecordFailedAttempt($email, $ip);
                $error = 'Credenziali non valide.';
            } else {
                hdLoginSession($user);
                header('Location: index.php');
                exit;
            }
        }
    }
}

corsoHtmlHead('Accedi');
?>
<div class="container" style="max-width:420px; margin-top: 4rem;">
    <div class="card">
        <h1 style="text-align:center;">Corso Base Human Design</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= corsoCsrfField('login') ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <button type="submit" class="btn" style="width:100%;">Accedi</button>
        </form>
        <p class="muted" style="text-align:center; margin-top:1rem;">Non hai le credenziali? Contatta Valentina.</p>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
