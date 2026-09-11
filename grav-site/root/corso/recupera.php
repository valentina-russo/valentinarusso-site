<?php
/**
 * Password dimenticata.
 *
 * Chi ha perso la password chiede da qui un link per rifarla. Il link e' lo
 * stesso della prima attivazione (attiva.php), cosi' la pagina dove si scrive
 * la password nuova e' una sola; il token pero' vale due ore invece di sette
 * giorni, perche' qui l'ha chiesto la persona un attimo prima.
 *
 * Due cose fanno la sicurezza di questa pagina:
 *  - la risposta e' sempre la stessa, esista o no l'indirizzo: altrimenti
 *    diventa un modo per sapere chi e' iscritta al corso;
 *  - il link non lo vede il browser di chi chiede, arriva solo nella casella
 *    di posta di quell'indirizzo.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

hdSessionStart();

const RISPOSTA = 'Se quell&rsquo;indirizzo &egrave; registrato al corso, tra pochi minuti '
               . 'ricevi una mail con il link per rifare la password. Il link vale due ore.';

$error = '';
$fatto = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $ip    = hdGetIp();

    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'recupera')) {
        $error = 'Sessione scaduta, riprova.';
    } elseif (hdIsRateLimited('recupera:' . strtolower($email), $ip)) {
        $error = 'Troppe richieste. Riprova tra qualche minuto.';
    } else {
        // Ogni richiesta conta come tentativo: e' l'unico modo di tenere a
        // freno chi prova indirizzi a raffica, visto che la risposta e' muta.
        hdRecordFailedAttempt('recupera:' . strtolower($email), $ip);

        $esito = corsoMandaLinkPassword($email);
        if ($esito === false) {
            error_log('[corso-recupera] mail non partita per ' . strtolower($email));
        }
        $fatto = true;   // la stessa risposta, indirizzo noto o no
    }
}

corsoHtmlHead('Password dimenticata');
?>
<div class="wrap" style="max-width:420px;padding-top:3.5rem">
    <p class="eyebrow" style="text-align:center">Area riservata</p>
    <h1 class="hero" style="text-align:center;font-size:clamp(1.6rem,5vw,2rem)">Corso Base<br>Human Design</h1>

    <?php if ($fatto): ?>
        <div class="card" style="margin-top:1.75rem">
            <div class="msg ok"><?= RISPOSTA ?></div>
            <p class="meta">Non la trovi? Guarda tra la posta indesiderata, oppure scrivi a
               <a href="mailto:info@valentinarussobg5.com">info@valentinarussobg5.com</a>.</p>
            <a class="btn full" href="login.php">Torna all&rsquo;accesso</a>
        </div>
    <?php else: ?>
        <div class="card" style="margin-top:1.75rem">
            <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <p class="meta" style="margin-top:0">Scrivi l&rsquo;indirizzo con cui ti sei iscritta: ti mandiamo
               un link per scegliere una password nuova.</p>
            <form method="post">
                <?= corsoCsrfField('recupera') ?>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="username">
                <button type="submit" class="btn full">Mandami il link</button>
            </form>
        </div>
        <p class="meta" style="text-align:center"><a href="login.php">Torna all&rsquo;accesso</a></p>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
