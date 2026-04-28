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

// ─── Validazione campi obbligatori ────────────────────────────────────────────
$redir = '/libretto-dati/dati.php?tier=' . urlencode($tier) . '&session_id=' . urlencode($sessionId);

if (!$nome || !$email || !$birthDate || !$birthTime || !$birthPlace) {
    header('Location: ' . $redir . '&error=missing');
    exit;
}

// ─── Configurazione email ─────────────────────────────────────────────────────
$ADMIN_EMAIL   = 'consulenza@marcomunich.com';
$FROM_EMAIL    = 'noreply@valentinarussobg5.com';
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
valentina@valentinarussobg5.com

Valentina Russo
valentinarussobg5.com

TEXT;

// ─── Invio email ──────────────────────────────────────────────────────────────
$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: ' . $FROM_NAME . ' <' . $FROM_EMAIL . '>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . PHP_VERSION,
]);

$ok1 = mail($ADMIN_EMAIL, $subjectAdmin,  $bodyAdmin,   $headers);
$ok2 = mail($email,       $subjectCliente, $bodyCliente, $headers);

// ─── Log ─────────────────────────────────────────────────────────────────────
$logDir  = __DIR__ . '/logs/';
$logFile = $logDir . 'ordini.log';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }

$logLine = date('Y-m-d H:i:s') . " | {$tier} | {$nome} | {$email} | admin:" . ($ok1?'ok':'FAIL') . ' cliente:' . ($ok2?'ok':'FAIL') . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);

// ─── Redirect finale ──────────────────────────────────────────────────────────
if ($ok1 || $ok2) {
    header('Location: /libretto-dati/dati.php?success=1&tier=' . urlencode($tier));
} else {
    header('Location: ' . $redir . '&error=mail');
}
exit;
