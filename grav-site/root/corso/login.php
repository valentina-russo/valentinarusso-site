<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

hdSessionStart();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'login')) {
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
<div class="wrap" style="max-width:420px;padding-top:3.5rem">
    <p class="eyebrow" style="text-align:center">Area riservata</p>
    <h1 class="hero" style="text-align:center;font-size:clamp(1.6rem,5vw,2rem)">Corso Base<br>Human Design</h1>
    <div class="card" style="margin-top:1.75rem">
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= corsoCsrfField('login') ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button type="submit" class="btn full">Entra</button>
        </form>
    </div>
    <p class="meta" style="text-align:center">Non hai le credenziali? Scrivi a Valentina.</p>
</div>
<?php corsoHtmlFoot(); ?>
