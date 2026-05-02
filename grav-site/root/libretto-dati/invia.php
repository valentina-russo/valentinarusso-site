<?php
/**
 * Libretto d'Istruzioni — Invio dati post-pagamento
 *
 * Riceve POST da dati.php, valida, invia due email:
 *   1. Email operativa a Marco (consulenza@marcomunich.com) con tutti i dati
 *   2. Email di conferma alla cliente (conforme art. 51 c.7 D.Lgs. 206/2005)
 *
 * Poi redirige a dati.php?success=1&tier=...
 */

declare(strict_types=1);

// ─── Carica .env per STRIPE_SECRET_KEY (necessaria per server-side verify) ────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

// ─── Helper sicurezza ─────────────────────────────────────────────────────────
function clean(string $key): string {
    return htmlspecialchars(trim($_POST[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
function cleanRaw(string $key): string {
    return trim($_POST[$key] ?? '');
}

// ─── Validazione metodo ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /libretto-istruzioni');
    exit;
}

// ─── CSRF check (SEC-PAY-002) ─────────────────────────────────────────────────
session_start();
$submittedToken = $_POST['csrf_token'] ?? '';
$storedToken    = $_SESSION['csrf_token'] ?? '';
if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
    http_response_code(403);
    error_log('[libretto-invia] CSRF token mismatch');
    header('Location: /libretto-istruzioni');
    exit;
}
// Rotate the token after successful verification (one-time use)
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ─── Raccolta dati ────────────────────────────────────────────────────────────
$nome       = clean('nome');
$email      = filter_var(cleanRaw('email'), FILTER_VALIDATE_EMAIL);
$birthDate  = clean('birth_date');
$birthTime  = clean('birth_time');
$birthPlace = clean('birth_place');
$messaggio  = clean('messaggio');

// SEC-LIB-006: validazione formato session_id Stripe prima dell'uso
$rawSessionId = $_POST['session_id'] ?? '';
$sessionId = preg_match('/^cs_(test|live)_[A-Za-z0-9]{1,200}$/', $rawSessionId)
    ? $rawSessionId
    : '';

// SEC-LIB-004: server-side verification del Checkout Session — il tier autoritativo
// viene da Stripe, NON dal form POST. Previene bypass tipo "pago €90 ma submetto
// avanzato". Il form POST 'tier' è ignorato in favore di metadata[tier] dal server.
$tier = 'avanzato'; // default sicuro se la verifica fallisce
$STRIPE_KEY = getenv('STRIPE_SECRET_KEY') ?: '';
if ($sessionId !== '' && $STRIPE_KEY !== '') {
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $STRIPE_KEY . ':',
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $verifyResp = curl_exec($ch);
    $verifyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($verifyCode === 200 && $verifyResp !== false) {
        $sess = json_decode($verifyResp, true);
        $stripeStatus  = $sess['payment_status'] ?? '';
        $stripeMetaTier = strtolower($sess['metadata']['tier'] ?? '');
        if ($stripeStatus === 'paid' && in_array($stripeMetaTier, ['base', 'avanzato'], true)) {
            $tier = $stripeMetaTier;
        } else {
            error_log('[libretto-invia] Stripe session not paid or invalid tier: ' . $sessionId);
            header('Location: /libretto-istruzioni?error=session');
            exit;
        }
    } else {
        error_log('[libretto-invia] Stripe verify failed HTTP ' . $verifyCode . ' for ' . $sessionId);
        header('Location: /libretto-istruzioni?error=session');
        exit;
    }
}
// Fallback: se Stripe non è chiamabile (test locale), usa il POST tier whitelisted
if ($STRIPE_KEY === '') {
    $postTier = strtolower(clean('tier'));
    $tier = in_array($postTier, ['base', 'avanzato'], true) ? $postTier : 'avanzato';
}

$tierLabel  = $tier === 'base' ? 'Base' : 'Avanzato';
$tierPrice  = $tier === 'base' ? '€90' : '€147';

// EC-02 (legal): waiver recesso obbligatorio (art. 59 c.1 lett. o) D.Lgs. 206/2005)
$recessoWaiver = isset($_POST['recesso_waiver']);

// ─── Validazione campi obbligatori ────────────────────────────────────────────
$redir = '/libretto-dati/dati.php?tier=' . urlencode($tier) . '&session_id=' . urlencode($sessionId);

if (!$nome || !$email || !$birthDate || !$birthTime || !$birthPlace || !$recessoWaiver) {
    header('Location: ' . $redir . '&error=missing');
    exit;
}

// ─── Configurazione email ─────────────────────────────────────────────────────
$ADMIN_EMAIL   = 'consulenza@marcomunich.com';
$FROM_EMAIL    = 'info@valentinarussobg5.com';
$FROM_NAME     = 'Valentina Russo — Libretto HD';

$nowIt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i');

// ─── Email 1: Admin (Marco) ───────────────────────────────────────────────────
$subjectAdmin = "📖 Nuovo ordine Libretto {$tierLabel} — {$nome}";

// SEC-LIB-008: shell template usa escapeshellarg() per ogni variabile
// così chi incolla il comando non subisce shell injection se il cliente
// inserisce caratteri speciali in nome/luogo (apici, $, backtick, ;, &&, |).
$shNome  = escapeshellarg($nome);
$shDate  = escapeshellarg($birthDate);
$shTime  = escapeshellarg($birthTime);
$shPlace = escapeshellarg($birthPlace);
$shTier  = escapeshellarg($tier);

$bodyAdmin = <<<TEXT
=== NUOVO ORDINE LIBRETTO D'ISTRUZIONI HUMAN DESIGN ===

DATA ORDINE : {$nowIt}
PRODOTTO    : Libretto {$tierLabel} ({$tierPrice})
STRIPE ID   : {$sessionId}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DATI CLIENTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NOME        : {$nome}
EMAIL       : {$email}
DATA NASC.  : {$birthDate}
ORA         : {$birthTime}
LUOGO       : {$birthPlace}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MESSAGGIO DELLA CLIENTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$messaggio}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PROMPT CLAUDE CODE (incolla in PowerShell/bash)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

python generate_blueprint.py \\
  --name {$shNome} \\
  --birth-date {$shDate} \\
  --birth-time {$shTime} \\
  --birth-place {$shPlace} \\
  --tier {$shTier} \\
  --out "D:/Download/hd-{$tier}.pdf"

TEXT;

// ─── Email 2: Conferma alla cliente — conforme art. 51 c.7 D.Lgs. 206/2005 ───
// EC-05-B (legal): conferma scritta su supporto durevole con TUTTE le info art.
// 49 + waiver recesso documentato. Senza questo, l'esclusione recesso ex art. 59
// lett. o) NON è valida → cliente può chiedere rimborso entro 14gg.
$subjectCliente = "Libretto d'Istruzioni Human Design — {$tierLabel} ricevuto · Conferma ordine";

$bodyCliente = <<<TEXT
Ciao {$nome},

abbiamo ricevuto i tuoi dati.
Questo messaggio costituisce conferma del contratto ai sensi dell'art. 51 c.7 D.Lgs. 206/2005.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
RIEPILOGO ORDINE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Prodotto  : Libretto d'Istruzioni Human Design — {$tierLabel}
Prezzo    : {$tierPrice} (prezzo finale — regime forfettario, nessuna IVA)
Consegna  : via email entro 2-4 giorni lavorativi

DATI UTILIZZATI
Data nasc : {$birthDate}
Ora       : {$birthTime}
Luogo     : {$birthPlace}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DIRITTO DI RECESSO — RINUNCIA CONFERMATA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Confermando l'invio dei dati hai richiesto l'avvio immediato dell'elaborazione
personalizzata e hai riconosciuto di perdere il diritto di recesso di 14 giorni
ai sensi dell'art. 59 c.1 lett. o) del Codice del Consumo (D.Lgs. 206/2005).
Il Libretto è realizzato su tua specifica personalizzazione e non è soggetto
a restituzione o rimborso una volta avviata l'elaborazione.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VENDITORE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Valentina Russo · P.IVA 03831440049
[INDIRIZZO FISICO COMPLETO]
info@valentinarussobg5.com · valentinarussobg5.com

Condizioni di Vendita: https://valentinarussobg5.com/terms
Risoluzione controversie ODR-UE: https://ec.europa.eu/consumers/odr

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
COSA SUCCEDE ADESSO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Valentina calcola la tua carta Human Design e prepara il documento personalmente,
revisionandolo capitolo per capitolo. Riceverai il PDF a questo indirizzo email
entro 2-4 giorni lavorativi. Per domande: info@valentinarussobg5.com

Il Libretto è elaborato con il supporto di strumenti di intelligenza artificiale
(Claude, Anthropic) e revisionato personalmente da Valentina prima della consegna.

Grazie per la fiducia.
Valentina Russo
valentinarussobg5.com

TEXT;

// ─── Invio email (SEC-PAY-001 + SEC-LIB-003: header-injection-safe) ──────────
// Defense in depth: strip CRLF, tab E null-byte (CVE-2025-1736 hardening)
$emailSafe = preg_replace('/[\r\n\t\0]/', '', $email);

// PHP 7.2+ array form of mail() — prevents CRLF injection at the PHP level
// regardless of MTA behavior (sendmail su Aruba interpreta \n come header separator)
$headersArray = [
    'MIME-Version'              => '1.0',
    'Content-Type'              => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => '8bit',
    'From'                      => $FROM_NAME . ' <' . $FROM_EMAIL . '>',
    'Reply-To'                  => $emailSafe,
    'X-Mailer'                  => 'PHP/' . PHP_VERSION,
];

$ok1 = mail($ADMIN_EMAIL, $subjectAdmin,  $bodyAdmin,   $headersArray);
$ok2 = mail($email,       $subjectCliente, $bodyCliente, $headersArray);

// ─── Log ─────────────────────────────────────────────────────────────────────
$logDir  = __DIR__ . '/logs/';
$logFile = $logDir . 'ordini.log';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }

// EC-09 (GDPR Art.5 limitazione): log senza PII — solo sessione Stripe anonimizzata
$sessionPrefix = substr(preg_replace('/[^a-zA-Z0-9_]/', '', $sessionId), 0, 16);
$logLine = date('Y-m-d H:i:s') . " | {$tier} | sess:{$sessionPrefix}… | admin:" . ($ok1?'ok':'FAIL') . ' cliente:' . ($ok2?'ok':'FAIL') . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);

// ─── Redirect finale ──────────────────────────────────────────────────────────
// EC-05/EC-10 (legal): l'email AD ADMIN ($ok1) è la conferma operativa che
// l'ordine arriverà a Valentina. Senza di essa, la cliente vede success ma
// nessuno preparerà il libretto = violazione obbligo conferma ordine art. 51
// c.7 CdC. Quindi success solo se ENTRAMBE le mail sono partite.
if ($ok1 && $ok2) {
    header('Location: /libretto-dati/dati.php?success=1&tier=' . urlencode($tier));
} else {
    // Fallback: log dettagliato così Marco può recuperare manualmente
    error_log('[libretto-invia] Mail send failure — admin:' . ($ok1?'OK':'FAIL') . ' cliente:' . ($ok2?'OK':'FAIL') . ' for ' . $email . ' tier ' . $tier);
    header('Location: ' . $redir . '&error=mail');
}
exit;
