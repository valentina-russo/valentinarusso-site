<?php
/**
 * Funzione digitale di recesso — art. 54-bis D.Lgs. 206/2005 (Codice del Consumo)
 * In vigore per contratti conclusi dal 19/06/2026.
 *
 * Flusso a 2 step (obbligatorio dalla norma):
 *   GET            -> Step 1: form dichiarazione di recesso
 *   POST step=1    -> Step 2: pagina di riepilogo + pulsante "Conferma recesso"
 *   POST step=2    -> elabora: invia ricevuta su supporto durevole (email), logga, schermata esito
 *
 * Token: ?token=XXXX nell'email di conferma ordine identifica l'ordine senza esporre PII
 * e senza dipendere dalla sessione PHP (la funzione resta accessibile 14+ giorni).
 * Se il token manca/non è valido, il consumatore inserisce i dati manualmente.
 */
declare(strict_types=1);

// ─── Config ──────────────────────────────────────────────────────────────────
$ADMIN_EMAIL = 'consulenza@marcomunich.com, consulenze@valentinarussobg5.com';
$FROM_EMAIL  = 'info@valentinarussobg5.com';
$FROM_NAME   = 'Valentina Russo — Libretto HD';
$TOKEN_LOG   = __DIR__ . '/logs/recesso-tokens.log';   // token|session|tier|YYYY-MM-DD|waiver scritto da invia.php
$REQ_LOG     = __DIR__ . '/logs/recesso-richieste.log'; // richieste di recesso ricevute

session_start();
if (empty($_SESSION['csrf_recesso'])) {
    $_SESSION['csrf_recesso'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_recesso'];

// ─── Helper ──────────────────────────────────────────────────────────────────
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function post(string $k): string { return trim((string)($_POST[$k] ?? '')); }

/** Cerca l'ordine nel token-log. Ritorna [tier,data,waiver] o null. */
function lookup_token(string $token, string $logFile): ?array {
    if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token) || !is_file($logFile)) return null;
    foreach (file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $p = explode('|', $line);
        if (count($p) >= 5 && hash_equals(trim($p[0]), $token)) {
            return ['tier' => trim($p[2]), 'data' => trim($p[3]), 'waiver' => trim($p[4]) === '1'];
        }
    }
    return null;
}

$token   = preg_match('/^[a-f0-9]{32}$/', $_GET['token'] ?? '') ? $_GET['token'] : '';
$order   = lookup_token($token, $TOKEN_LOG);
$tierLbl = $order ? ($order['tier'] === 'base' ? 'Base' : 'Avanzato') : '';
$prodPrefill = $order ? "Libretto d'Istruzioni Human Design — {$tierLbl}" : '';
$dataPrefill = $order['data'] ?? '';

$step = (int)($_POST['step'] ?? 0);
$csrfOk = isset($_POST['csrf']) && hash_equals($CSRF, (string)$_POST['csrf']);

// ─── Layout helper ─────────────────────────────────────────────────────────────
function page(string $title, string $bodyHtml): void {
    echo "<!doctype html><html lang='it'><head><meta charset='utf-8'>"
       . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
       . "<meta name='robots' content='noindex'>"
       . "<title>" . e($title) . "</title><style>"
       . "body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#FAF7F5;color:#1C1210;"
       . "margin:0;padding:24px;line-height:1.55}.wrap{max-width:640px;margin:0 auto;background:#fff;"
       . "border:1px solid #EAE5E1;border-radius:14px;padding:28px}h1{font-size:1.4rem;color:#1E3A5F}"
       . "label{display:block;font-weight:600;margin:14px 0 4px}input,textarea{width:100%;box-sizing:border-box;"
       . "padding:10px;border:1px solid #C9C2BC;border-radius:8px;font:inherit}textarea{min-height:90px}"
       . ".note{background:#F2E0E6;border-radius:8px;padding:12px 14px;font-size:.92rem;margin:14px 0}"
       . ".btn{display:inline-block;margin-top:20px;background:#1E3A5F;color:#fff;border:0;border-radius:10px;"
       . "padding:13px 22px;font:inherit;font-weight:700;cursor:pointer}.btn:hover{background:#16304d}"
       . ".muted{color:#7a5c6e;font-size:.88rem}a{color:#1E3A5F}.recap{background:#FAF7F5;border:1px solid #EAE5E1;"
       . "border-radius:8px;padding:14px;margin:14px 0;white-space:pre-wrap;font-size:.92rem}</style></head><body>"
       . "<div class='wrap'>" . $bodyHtml . "</div></body></html>";
}

// ═══ STEP 2 PROCESSING — conferma + invio ricevuta ══════════════════════════════
if ($step === 2 && $csrfOk) {
    $nome    = post('nome');
    $prodotto= post('prodotto');
    $dataAcq = post('data_acquisto');
    $emailC  = filter_var(post('email'), FILTER_VALIDATE_EMAIL);
    $dichiar = post('dichiarazione');
    // SEC-M03-001: il waiver NON arriva dal POST (manipolabile in DevTools). Il Libretto
    // è contenuto digitale personalizzato con rinuncia obbligatoria all'acquisto (invia.php
    // richiede recesso_waiver) → il recesso è SEMPRE escluso ex art. 59 c.1 lett. o).
    $waiver   = true;
    $nomeSubj = preg_replace('/[\r\n\t\0]/', '', $nome);  // mail subject injection guard

    if (!$nome || !$emailC || !$dichiar) {
        page('Errore', "<h1>Dati mancanti</h1><p>Torna indietro e compila tutti i campi. <a href='?'>Riprova</a></p>");
        exit;
    }

    $nowIt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i:s');

    // ─── Email ricevuta al consumatore (supporto durevole) ──────────────────────
    if ($waiver) {
        $risposta = <<<TXT
La tua dichiarazione di recesso è stata ricevuta e registrata.

Tuttavia, al momento dell'acquisto hai espressamente richiesto l'avvio immediato
dell'elaborazione del Libretto d'Istruzioni personalizzato e hai riconosciuto di
perdere il diritto di recesso ai sensi dell'art. 59, comma 1, lett. o) del Codice
del Consumo (D.Lgs. 206/2005). Per questo motivo l'ordine non può essere annullato
né rimborsato.

Se hai domande: info@valentinarussobg5.com — oppure piattaforma ODR-UE:
https://ec.europa.eu/consumers/odr
TXT;
    } else {
        $risposta = <<<TXT
La tua dichiarazione di recesso è stata ricevuta e accettata. Riceverai il rimborso
entro 14 giorni tramite lo stesso metodo di pagamento utilizzato per l'acquisto.

Per conferma o domande: info@valentinarussobg5.com
TXT;
    }

    $bodyCliente = <<<TXT
Ciao {$nome},

abbiamo ricevuto la tua dichiarazione di recesso in data {$nowIt}.

CONTENUTO DELLA TUA DICHIARAZIONE
{$dichiar}

DATA E ORA DI RICEZIONE
{$nowIt}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
RISPOSTA DEL PROFESSIONISTA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$risposta}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VENDITORE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Valentina Russo · P.IVA 03831440049
[INDIRIZZO FISICO COMPLETO]
info@valentinarussobg5.com · valentinarussobg5.com
TXT;

    $bodyAdmin = "RICHIESTA DI RECESSO RICEVUTA\n\nData/ora: {$nowIt}\nNome: {$nome}\nEmail: {$emailC}\n"
               . "Prodotto: {$prodotto}\nData acquisto: {$dataAcq}\nWaiver art.59: " . ($waiver ? 'SI' : 'NO')
               . "\n\nDichiarazione:\n{$dichiar}\n";

    // header-injection-safe (CVE-2025-1736 hardening): strip CR/LF/tab/null
    $emailSafe = preg_replace('/[\r\n\t\0]/', '', (string)$emailC);
    $headers = [
        'MIME-Version'              => '1.0',
        'Content-Type'              => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
        'From'                      => $FROM_NAME . ' <' . $FROM_EMAIL . '>',
        'Reply-To'                  => $FROM_EMAIL,
    ];
    @mail($emailSafe, 'Ricevuta di recesso — Libretto d\'Istruzioni HD — ' . $nowIt, $bodyCliente, $headers);
    @mail($ADMIN_EMAIL, 'Recesso ricevuto — ' . $nomeSubj, $bodyAdmin, $headers);

    // log richiesta (base giuridica art. 6(1)(c) GDPR — obbligo legale)
    if (!is_dir(dirname($REQ_LOG))) { @mkdir(dirname($REQ_LOG), 0750, true); }
    @file_put_contents($REQ_LOG, "{$nowIt} | " . substr($emailSafe, 0, 40) . " | waiver:" . ($waiver ? '1' : '0')
        . " | " . substr(preg_replace('/[^\w\- ]/', '', $prodotto), 0, 40) . "\n", FILE_APPEND);

    $_SESSION['csrf_recesso'] = bin2hex(random_bytes(32));  // SEC: rotate token (one-time use)

    page('Recesso ricevuto', "<h1>Dichiarazione ricevuta</h1>"
        . "<p>Abbiamo registrato la tua dichiarazione di recesso il <strong>" . e($nowIt) . "</strong> "
        . "e ti abbiamo inviato una ricevuta all'indirizzo <strong>" . e($emailSafe) . "</strong>.</p>"
        . ($waiver
            ? "<div class='note'>Nota: per il Libretto personalizzato il diritto di recesso risulta escluso "
              . "(art. 59 c.1 lett. o CdC, consenso espresso all'avvio immediato). Trovi i dettagli nella ricevuta email.</div>"
            : "<div class='note'>Riceverai il rimborso entro 14 giorni sullo stesso metodo di pagamento.</div>")
        . "<p class='muted'>Per qualsiasi domanda: <a href='mailto:info@valentinarussobg5.com'>info@valentinarussobg5.com</a></p>");
    exit;
}

// ═══ STEP 2 RENDER — riepilogo + conferma ════════════════════════════════════════
if ($step === 1 && $csrfOk) {
    $nome    = post('nome');
    $prodotto= post('prodotto') ?: $prodPrefill;
    $dataAcq = post('data_acquisto') ?: $dataPrefill;
    $emailC  = post('email');
    $dichiar = post('dichiarazione');

    if ($nome === '' || filter_var($emailC, FILTER_VALIDATE_EMAIL) === false || $dichiar === '') {
        $err = "<div class='note'>Compila nome, email valida e dichiarazione.</div>";
        $step = 0; // ricadi nel form sotto mostrando l'errore
    } else {
        $recap = "Nome: {$nome}\nProdotto: {$prodotto}\nData acquisto: {$dataAcq}\n"
               . "Email per la ricevuta: {$emailC}\n\nDichiarazione:\n{$dichiar}";
        $h = "<h1>Conferma il recesso</h1>"
           . "<p>Controlla i dati, poi premi <strong>Conferma recesso</strong>. "
           . "Riceverai subito una ricevuta via email.</p>"
           . "<div class='recap'>" . e($recap) . "</div>"
           . "<form method='post' action='recesso.php'>"
           . "<input type='hidden' name='csrf' value='" . e($CSRF) . "'>"
           . "<input type='hidden' name='step' value='2'>"
           . "<input type='hidden' name='nome' value='" . e($nome) . "'>"
           . "<input type='hidden' name='prodotto' value='" . e($prodotto) . "'>"
           . "<input type='hidden' name='data_acquisto' value='" . e($dataAcq) . "'>"
           . "<input type='hidden' name='email' value='" . e($emailC) . "'>"
           . "<input type='hidden' name='dichiarazione' value='" . e($dichiar) . "'>"
           . "<button class='btn' type='submit'>Conferma recesso</button> "
           . "<a class='muted' href='https://valentinarussobg5.com/'>Annulla — torna al sito</a>"
           . "</form>";
        page('Conferma recesso', $h);
        exit;
    }
}

// ═══ STEP 1 RENDER — form dichiarazione ═══════════════════════════════════════════
$dichDefault = "Con la presente comunico la mia intenzione di recedere dal contratto per l'acquisto del "
    . "Libretto d'Istruzioni Human Design" . ($prodPrefill ? " ({$tierLbl})" : "")
    . ($dataPrefill ? ", concluso in data {$dataPrefill}" : "") . ".";

$form = ($err ?? '')
    . "<h1>Recesso dal contratto</h1>"
    . "<p>Ai sensi dell'art. 54-bis del Codice del Consumo (D.Lgs. 206/2005) puoi esercitare il diritto "
    . "di recesso entro 14 giorni dalla conclusione del contratto, compilando questo modulo.</p>"
    . "<div class='note'>Nota: al momento dell'acquisto hai richiesto l'avvio immediato dell'elaborazione del "
    . "Libretto personalizzato e hai riconosciuto di perdere il diritto di recesso ai sensi dell'art. 59 c.1 "
    . "lett. o) CdC. La tua dichiarazione verrà comunque ricevuta e registrata; riceverai risposta via email.</div>"
    . "<form method='post' action='recesso.php'>"
    . "<input type='hidden' name='csrf' value='" . e($CSRF) . "'>"
    . "<input type='hidden' name='step' value='1'>"
    . "<label>Nome e cognome *</label><input name='nome' required value='" . e(post('nome')) . "'>"
    . "<label>Prodotto acquistato</label><input name='prodotto' value='" . e($prodPrefill) . "'>"
    . "<label>Data di acquisto</label><input name='data_acquisto' placeholder='gg/mm/aaaa' value='" . e($dataPrefill) . "'>"
    . "<label>Email per la ricevuta *</label><input type='email' name='email' required value='" . e(post('email')) . "'>"
    . "<label>Dichiarazione di recesso *</label><textarea name='dichiarazione' required>" . e($dichDefault) . "</textarea>"
    . "<button class='btn' type='submit'>Invia dichiarazione di recesso</button>"
    . "</form>"
    . "<p class='muted'>In alternativa puoi scrivere a info@valentinarussobg5.com. "
    . "Condizioni di vendita: <a href='https://valentinarussobg5.com/terms'>/terms</a></p>";
page('Recesso — Libretto d\'Istruzioni', $form);
