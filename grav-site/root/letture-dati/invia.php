<?php
/**
 * Letture Human Design/BG5 — Invio dati post-pagamento
 * Riceve POST da dati.php, valida, verifica il pagamento server-side su Stripe,
 * invia due email (admin + cliente) e logga l'ordine.
 *
 * BOZZA — email cliente NON contiene ancora testo legale di conferma contratto
 * definitivo (vedi nota in dati.php). Da rivedere con legal-guardian.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
letture_load_env();

function clean(string $key): string { return htmlspecialchars(trim($_POST[$key] ?? ''), ENT_QUOTES, 'UTF-8'); }
function cleanRaw(string $key): string { return trim($_POST[$key] ?? ''); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /servizi'); exit; }

session_start();
$submittedToken = $_POST['csrf_token'] ?? '';
$storedToken    = $_SESSION['csrf_token'] ?? '';
if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
    http_response_code(403);
    error_log('[letture-invia] CSRF token mismatch');
    header('Location: /servizi');
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$readingKeyRaw = strtolower(clean('reading'));
$rawSessionId  = $_POST['session_id'] ?? '';
$sessionId = preg_match('/^cs_(test|live)_[A-Za-z0-9]{1,200}$/', $rawSessionId) ? $rawSessionId : '';

// Verifica server-side del pagamento + reading autoritativo da Stripe metadata
// (NON dal form POST — evita bypass tipo "pago 210 ma submetto opposizione-urano")
$STRIPE_KEY = getenv('STRIPE_SECRET_KEY') ?: '';
$reading = null;

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
        $stripeStatus = $sess['payment_status'] ?? '';
        $stripeReading = strtolower($sess['metadata']['reading'] ?? '');
        if ($stripeStatus === 'paid' && letture_get($stripeReading)) {
            $reading = $stripeReading;
        } else {
            error_log('[letture-invia] Stripe session not paid or invalid reading: ' . $sessionId);
            header('Location: /servizi?error=session');
            exit;
        }
    } else {
        error_log('[letture-invia] Stripe verify failed HTTP ' . $verifyCode . ' for ' . $sessionId);
        header('Location: /servizi?error=session');
        exit;
    }
}
if ($STRIPE_KEY === '') {
    // Fallback solo se Stripe non configurato (test locale)
    $reading = letture_get($readingKeyRaw) ? $readingKeyRaw : null;
}
if (!$reading) { header('Location: /servizi?error=session'); exit; }

$product  = letture_get($reading);
$dataMode = $product['data_mode'];
$priceLabel = '€' . number_format($product['amount'] / 100, 0, ',', '.');

$nome        = clean('nome');
$email       = filter_var(cleanRaw('email'), FILTER_VALIDATE_EMAIL);
$telefono    = clean('telefono');
$fasceOrarie = clean('fasce_orarie');
$messaggio   = clean('messaggio');
$termini     = isset($_POST['termini_accettati']);

$redir = '/letture-dati/dati.php?reading=' . urlencode($reading) . '&session_id=' . urlencode($sessionId);

// Campi specifici per data_mode
$datiPersonaTxt = '';
$missingBase = !$nome || !$email || !$telefono || !$termini;
$missingSpecific = false;

if ($dataMode === 'single') {
    $bDate = clean('birth_date'); $bTime = clean('birth_time'); $bPlace = clean('birth_place');
    $missingSpecific = !$bDate || !$bTime || !$bPlace;
    $datiPersonaTxt = "DATA NASC. : {$bDate}\nORA        : {$bTime}\nLUOGO      : {$bPlace}";
} elseif ($dataMode === 'couple') {
    $aNome = clean('a_nome'); $aDate = clean('a_birth_date'); $aTime = clean('a_birth_time'); $aPlace = clean('a_birth_place');
    $bNome = clean('b_nome'); $bDate = clean('b_birth_date'); $bTime = clean('b_birth_time'); $bPlace = clean('b_birth_place');
    $missingSpecific = !$aNome || !$aDate || !$aTime || !$aPlace || !$bNome || !$bDate || !$bTime || !$bPlace;
    $datiPersonaTxt = "PERSONA A  : {$aNome}\n  Data/ora/luogo: {$aDate} {$aTime} — {$aPlace}\nPERSONA B  : {$bNome}\n  Data/ora/luogo: {$bDate} {$bTime} — {$bPlace}";
} elseif ($dataMode === 'child') {
    $cNome = clean('child_nome'); $cDate = clean('child_birth_date'); $cTime = clean('child_birth_time'); $cPlace = clean('child_birth_place');
    $missingSpecific = !$cNome || !$cDate || !$cTime || !$cPlace;
    $datiPersonaTxt = "FIGLIO/A   : {$cNome}\n  Data/ora/luogo: {$cDate} {$cTime} — {$cPlace}";
}

if ($missingBase || $missingSpecific) {
    header('Location: ' . $redir . '&error=missing');
    exit;
}

$ADMIN_EMAIL = 'consulenza@marcomunich.com, consulenze@valentinarussobg5.com';
$FROM_EMAIL  = 'info@valentinarussobg5.com';
$FROM_NAME   = 'Valentina Russo — Letture HD/BG5';
$nowIt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i');

$subjectAdmin = "🔮 Nuova prenotazione: {$product['name']} — {$nome}";
$bodyAdmin = <<<TEXT
=== NUOVA PRENOTAZIONE LETTURA ===

DATA ORDINE : {$nowIt}
PRODOTTO    : {$product['name']} ({$priceLabel})
STRIPE ID   : {$sessionId}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONTATTO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NOME        : {$nome}
EMAIL       : {$email}
TELEFONO    : {$telefono}
FASCE ORARIE: {$fasceOrarie}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DATI PER LA CARTA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$datiPersonaTxt}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NOTE DEL CLIENTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$messaggio}

TEXT;

$subjectCliente = "{$product['name']} — prenotazione ricevuta";
$bodyCliente = <<<TEXT
Ciao {$nome},

abbiamo ricevuto i tuoi dati per: {$product['name']} ({$priceLabel}).

Valentina ti scriverà entro 48 ore all'email o al telefono che hai indicato
per fissare data e ora della sessione, tenendo conto delle fasce orarie che
hai segnalato.

Per domande: info@valentinarussobg5.com

Grazie per la fiducia.
Valentina Russo
valentinarussobg5.com

TEXT;

$emailSafe = preg_replace('/[\r\n\t\0]/', '', $email);
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

$logDir  = __DIR__ . '/logs/';
$logFile = $logDir . 'ordini.log';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$sessionPrefix = substr(preg_replace('/[^a-zA-Z0-9_]/', '', $sessionId), 0, 16);
$logLine = date('Y-m-d H:i:s') . " | {$reading} | sess:{$sessionPrefix}… | admin:" . ($ok1?'ok':'FAIL') . ' cliente:' . ($ok2?'ok':'FAIL') . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);

if ($ok1 && $ok2) {
    header('Location: /letture-dati/dati.php?success=1&reading=' . urlencode($reading));
} else {
    error_log('[letture-invia] Mail send failure — admin:' . ($ok1?'OK':'FAIL') . ' cliente:' . ($ok2?'OK':'FAIL') . ' for ' . $email . ' reading ' . $reading);
    header('Location: ' . $redir . '&error=mail');
}
exit;
