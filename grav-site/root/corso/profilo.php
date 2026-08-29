<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user    = corsoRequireStudent();
$uid     = (int)$user['id'];
$isAdmin = corsoIsAdmin($uid);

$stmt = hdDb()->prepare('SELECT id, email, name, phone, avatar_path, email_notifications FROM hd_users WHERE id = ?');
$stmt->execute([$uid]);
$me = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'profilo')) {
        $error = 'Sessione scaduta, riprova.';
    } elseif ($form === 'dati') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $emailChanged = $email !== ($me['email'] ?? '');
        $confirmPw = $_POST['confirm_password'] ?? '';

        if ($name === '') {
            $error = 'Il nome non può essere vuoto.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Inserisci un indirizzo email valido.';
        } elseif ($emailChanged && $confirmPw === '') {
            $error = 'Per cambiare email conferma la tua password attuale.';
        } elseif ($emailChanged) {
            $stmtPw2 = hdDb()->prepare('SELECT password_hash FROM hd_users WHERE id = ?');
            $stmtPw2->execute([$uid]);
            $rowPw = $stmtPw2->fetch();
            if (!$rowPw || !hdVerifyPassword($confirmPw, $rowPw['password_hash'])) {
                sleep(1);
                $error = 'Password non corretta, email non modificata.';
            }
        }

        if (!$error) {
            $avatarPath = $me['avatar_path'];
            if (!empty($_FILES['avatar']['name'])) {
                $newPath = corsoValidatedAvatarUpload('avatar', __DIR__ . '/private-uploads', 'avatar-' . $uid . '-' . time());
                if ($newPath === null) {
                    $error = 'La foto deve essere jpg, png o webp, sotto i 5MB.';
                } else {
                    if ($avatarPath && is_file($avatarPath)) @unlink($avatarPath);
                    $avatarPath = $newPath;
                }
            }
            if (!$error) {
                try {
                    hdDb()->prepare('UPDATE hd_users SET name = ?, email = ?, phone = ?, avatar_path = ? WHERE id = ?')
                          ->execute([$name, $email, $phone ?: null, $avatarPath, $uid]);
                    $me['name'] = $name; $me['email'] = $email; $me['phone'] = $phone; $me['avatar_path'] = $avatarPath;
                    $user['name'] = $name;
                    $success = 'Dati aggiornati.';
                } catch (PDOException $e) {
                    // Indice UNIQUE su email
                    $error = 'Questa email è già usata da un altro account.';
                }
            }
        }
    } elseif ($form === 'preferenze') {
        $notify = isset($_POST['email_notifications']) ? 1 : 0;
        hdDb()->prepare('UPDATE hd_users SET email_notifications = ? WHERE id = ?')->execute([$notify, $uid]);
        $me['email_notifications'] = $notify;
        $success = 'Preferenze salvate.';
    } elseif ($form === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';

        if ($passErr = hdValidatePassword($new)) {
            $error = $passErr;
        } else {
            $stmtPw = hdDb()->prepare('SELECT password_hash FROM hd_users WHERE id = ?');
            $stmtPw->execute([$uid]);
            $row = $stmtPw->fetch();
            if (!$row || !hdVerifyPassword($old, $row['password_hash'])) {
                sleep(1);
                $error = 'La password attuale non è corretta.';
            } else {
                hdDb()->prepare('UPDATE hd_users SET password_hash = ?, session_ver = session_ver + 1 WHERE id = ?')
                      ->execute([hdHashPassword($new), $uid]);
                hdLogout();
                header('Location: login.php?password_cambiata=1');
                exit;
            }
        }
    }
}

corsoHtmlHead('Il mio profilo');
corsoNav($user, $isAdmin, 'profilo');
?>
<div class="wrap" style="max-width:560px">
    <h1 class="page">Il mio profilo</h1>
    <?php if ($success): ?><div class="msg ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <h2 class="sect">Dati personali</h2>
    <div class="card">
        <p style="display:flex;align-items:center;gap:1rem;margin:0 0 1rem">
            <?= corsoAvatar($me['name'], $me['email'], $isAdmin, 64, $uid) ?>
            <span class="meta">Formati accettati: JPG, PNG, WEBP. Max 5MB.</span>
        </p>
        <form method="post" enctype="multipart/form-data">
            <?= corsoCsrfField('profilo') ?>
            <input type="hidden" name="form" value="dati">

            <label for="avatar">Cambia foto</label>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">

            <label for="name">Nome</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($me['name'] ?? '') ?>" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($me['email'] ?? '') ?>" required>

            <label for="phone">Cellulare</label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($me['phone'] ?? '') ?>" placeholder="+39 ...">

            <label for="confirm_password">Password attuale (solo se cambi l'email)</label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="current-password">

            <button type="submit" class="btn">Salva dati</button>
        </form>
    </div>

    <h2 class="sect">Password</h2>
    <div class="card">
        <form method="post">
            <?= corsoCsrfField('profilo') ?>
            <input type="hidden" name="form" value="password">

            <label for="old_password">Password attuale</label>
            <input type="password" id="old_password" name="old_password" autocomplete="current-password" required>

            <label for="new_password">Nuova password</label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
            <p class="hint">Almeno 8 caratteri. Dopo il cambio dovrai effettuare di nuovo il login.</p>

            <button type="submit" class="btn">Cambia password</button>
        </form>
    </div>

    <h2 class="sect">Preferenze email</h2>
    <div class="card">
        <form method="post">
            <?= corsoCsrfField('profilo') ?>
            <input type="hidden" name="form" value="preferenze">
            <label style="display:flex;align-items:center;gap:.6rem;font-weight:normal">
                <input type="checkbox" name="email_notifications" value="1" style="width:auto"
                       <?= !empty($me['email_notifications']) ? 'checked' : '' ?>>
                Ricevi notifiche via email su nuove lezioni e risposte nel forum
            </label>
            <button type="submit" class="btn" style="margin-top:1rem">Salva preferenze</button>
        </form>
    </div>

    <h2 class="sect">Privacy</h2>
    <div class="card">
        <p class="ptext" style="margin:0">Consulta in qualsiasi momento la nostra <a href="https://valentinarussobg5.com/privacy" target="_blank" rel="noopener">informativa sulla privacy</a>.</p>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
