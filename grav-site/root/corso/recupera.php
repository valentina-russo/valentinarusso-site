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

/** Il messaggio col link. Intestazioni separate da CRLF, come vuole la posta. */
function corsoMailRecupero(string $email, ?string $nome, string $token): bool {
    $ac = chr(13) . chr(10);
    $link = 'https://valentinarussobg5.com/corso/attiva.php?r=1&t=' . $token;
    $saluto = trim((string)$nome) !== '' ? 'Ciao ' . trim(explode(' ', trim((string)$nome))[0]) . ',' : 'Ciao,';

    $righe = [
        $saluto,
        '',
        'hai chiesto di rifare la password del corso. Scegline una nuova da qui:',
        '',
        $link,
        '',
        'Il link vale due ore e si usa una volta sola. Se scade, ne chiedi un altro',
        'dalla pagina di accesso.',
        '',
        'Se non sei stata tu a chiederlo, puoi ignorare questa mail: senza aprire il',
        'link la tua password resta quella di prima.',
        '',
        'Valentina Russo',
        'Corso Base di Human Design',
    ];

    $intestazioni = 'From: Valentina Russo <info@valentinarussobg5.com>' . $ac
        . 'Reply-To: info@valentinarussobg5.com' . $ac
        . 'Content-Type: text/plain; charset=UTF-8' . $ac
        . 'Content-Transfer-Encoding: 8bit' . $ac
        . 'MIME-Version: 1.0';
    $oggetto = '=?UTF-8?B?' . base64_encode('La tua password del corso') . '?=';

    return @mail($email, $oggetto, implode($ac, $righe) . $ac, $intestazioni);
}

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

        $token = corsoTokenRecuperoPerEmail($email);
        if ($token !== null) {
            $st = hdDb()->prepare('SELECT name FROM hd_users WHERE email = ?');
            $st->execute([strtolower($email)]);
            $nome = (string)$st->fetchColumn();
            if (!corsoMailRecupero(strtolower($email), $nome, $token)) {
                error_log('[corso-recupera] mail non partita per ' . strtolower($email));
            }
        }
        $fatto = true;   // la stessa risposta nei due casi
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
