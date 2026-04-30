<?php
/**
 * Libretto d'Istruzioni — Invio dati post-pagamento
 *
 * Riceve POST da dati.php, valida, invia due email:
 *   1. Email operativa a Marco (consulenza@marcomunich.com) con tutti i dati
 *   2. Email di conferma alla cliente
 *
 * Poi redirige a dati.php?success=1&tier=...
 */

declare(strict_types=1);

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
$tier       = strtolower(clean('tier'));
if (!in_array($tier, ['base', 'avanzato'], true)) { $tier = 'avanzato'; }
$tierLabel  = $tier === 'base' ? 'Base' : 'Avanzato';
$tierPrice  = $tier === 'base' ? '€90' : '€147';

$nome       = clean('nome');
$email      = filter_var(cleanRaw('email'), FILTER_VALIDATE_EMAIL);
$birthDate  = clean('birth_date');
$birthTime  = clean('birth_time');
$birthPlace = clean('birth_place');
$messaggio  = clean('messaggio');
$sessionId  = clean('session_id');

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
PROMPT CLAUDE CODE (incolla e personalizza)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

python generate_blueprint.py \\
  --name "{$nome}" \\
  --birth-date "{$birthDate}" \\
  --birth-time "{$birthTime}" \\
  --birth-place "{$birthPlace}" \\
  --tier {$tier} \\
  --out "D:/Download/hd-{$tier}.pdf"

TEXT;

// ─── Email 2: Conferma alla cliente ───────────────────────────────────────────
$subjectCliente = "Libretto d'Istruzioni Human Design — {$tierLabel} ricevuto ✓";

$bodyCliente = <<<TEXT
Ciao {$nome},

abbiamo ricevuto i tuoi dati.

Valentina preparerà il tuo Libretto d'Istruzioni {$tierLabel} personalmente.
Lo riceverai a questo indirizzo email entro 2–4 giorni lavorativi.

I tuoi dati:
- Data di nascita: {$birthDate}
- Ora: {$birthTime}
- Luogo: {$birthPlace}

Per qualsiasi domanda o chiarimento:
info@valentinarussobg5.com

Valentina Russo
valentinarussobg5.com

TEXT;

// ─── Invio email (SEC-PAY-001: header-injection-safe) ────────────────────────
// Defense in depth: strip any newline characters from email used in Reply-To
$emailSafe = preg_replace('/[\r\n\t]/', '', $email);

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
if ($ok1 || $ok2) {
    header('Location: /libretto-dati/dati.php?success=1&tier=' . urlencode($tier));
} else {
    header('Location: ' . $redir . '&error=mail');
}
exit;
