<?php
/**
 * Attivazione dell'account dopo il pagamento.
 *
 * Il link arriva nella mail di conferma e vale una volta sola, per sette
 * giorni. Qui l'allieva sceglie la sua password: nessuna credenziale generata da
 * noi viaggia mai per email.
 *
 * Progetto: specs/corso-iscrizione-automatica.md (D3).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

hdSessionStart();

// Lo stesso messaggio per token inesistente, scaduto o gia' speso: la pagina
// non deve permettere di capire se un indirizzo esista.
const MUTO = 'Questo link non è più valido. Chiedine un altro da "Password dimenticata", oppure scrivi a Valentina.';

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$ip    = hdGetIp();
$error = '';
$fatto = false;

// Con r=1 il link arriva dal recupero password (recupera.php) invece che dal
// pagamento: la differenza e' solo che accetta anche l'account della docente.
$daRecupero = ($_GET['r'] ?? $_POST['r'] ?? '') === '1';
$utente = corsoUtenteDaTokenAttivazione($token, $daRecupero);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'attiva')) {
        $error = 'Sessione scaduta, riprova.';
    } elseif (hdIsRateLimited('attiva:' . substr(hash('sha256', $token), 0, 16), $ip)) {
        $error = 'Troppi tentativi. Riprova tra qualche minuto.';
    } elseif (!$utente) {
        hdRecordFailedAttempt('attiva', $ip);
        $error = MUTO;
    } else {
        $password = (string)($_POST['password'] ?? '');
        $ripeti   = (string)($_POST['ripeti'] ?? '');
        $problema = hdValidatePassword($password);

        if ($problema !== null) {
            $error = $problema;
        } elseif ($password !== $ripeti) {
            $error = 'Le due password non coincidono.';
        } else {
            corsoAttivaAccount((int)$utente['id'], $password);
            $fatto = true;
        }
    }
}

corsoHtmlHead($daRecupero ? 'Nuova password' : 'Attiva il tuo accesso');
?>
<div class="wrap" style="max-width:440px;padding-top:3.5rem">
    <p class="eyebrow" style="text-align:center">Area riservata</p>
    <h1 class="hero" style="text-align:center;font-size:clamp(1.6rem,5vw,2rem)">Corso Base<br>Human Design</h1>

    <?php if ($fatto): ?>
        <div class="card" style="margin-top:1.75rem">
            <div class="msg ok">Fatto. Da ora entri con la tua email e la password che hai appena scelto.</div>
            <a class="btn full" href="login.php">Vai all&rsquo;accesso</a>
        </div>
    <?php elseif (!$utente && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <div class="card" style="margin-top:1.75rem">
            <div class="msg err"><?= htmlspecialchars(MUTO) ?></div>
            <p class="meta">Scrivi a <a href="mailto:info@valentinarussobg5.com">info@valentinarussobg5.com</a>.</p>
        </div>
    <?php else: ?>
        <div class="card" style="margin-top:1.75rem">
            <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($utente): ?>
                <p class="meta" style="margin-top:0">Stai scegliendo la password di <strong><?= htmlspecialchars($utente['email']) ?></strong>.</p>
            <?php endif; ?>
            <form method="post">
                <?= corsoCsrfField('attiva') ?>
                <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
                <?php if ($daRecupero): ?><input type="hidden" name="r" value="1"><?php endif; ?>
                <label for="password">Scegli una password</label>
                <input type="password" id="password" name="password" required autofocus autocomplete="new-password">
                <label for="ripeti">Ripetila</label>
                <input type="password" id="ripeti" name="ripeti" required autocomplete="new-password">
                <button type="submit" class="btn full">Salva la password</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
