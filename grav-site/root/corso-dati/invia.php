<?php
/**
 * Corso BG5 Foundation — Invio dati post-pagamento
 * Riceve POST da dati.php, valida, verifica il pagamento server-side su Stripe,
 * invia due email (admin + cliente) e logga l'iscrizione.
 *
 * BOZZA — email cliente NON contiene ancora testo legale di conferma contratto
 * definitivo (vedi nota in dati.php). Da rivedere con legal-guardian.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
course_load_env();

function clean(string $key): string { return htmlspecialchars(trim($_POST[$key] ?? ''), ENT_QUOTES, 'UTF-8'); }
function cleanRaw(string $key): string { return trim($_POST[$key] ?? ''); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /corso-base-human-design.html'); exit; }

session_start();
$submittedToken = $_POST['csrf_token'] ?? '';
$storedToken    = $_SESSION['csrf_token'] ?? '';
if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
    http_response_code(403);
    error_log('[corso-invia] CSRF token mismatch');
    header('Location: /corso-base-human-design.html');
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$courseKeyRaw = strtolower(clean('course'));
$rawSessionId = $_POST['session_id'] ?? '';
$sessionId = preg_match('/^cs_(test|live)_[A-Za-z0-9]{1,200}$/', $rawSessionId) ? $rawSessionId : '';

// Verifica server-side del pagamento + course autoritativo da Stripe metadata
$STRIPE_KEY = getenv('STRIPE_SECRET_KEY') ?: '';
$course = null;

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
        $stripeCourse = strtolower($sess['metadata']['course'] ?? '');
        if ($stripeStatus === 'paid' && course_get($stripeCourse)) {
            $course = $stripeCourse;
        } else {
            error_log('[corso-invia] Stripe session not paid or invalid course: ' . $sessionId);
            header('Location: /corso-base-human-design.html?error=session');
            exit;
        }
    } else {
        error_log('[corso-invia] Stripe verify failed HTTP ' . $verifyCode . ' for ' . $sessionId);
        header('Location: /corso-base-human-design.html?error=session');
        exit;
    }
}
if ($STRIPE_KEY === '') {
    $course = course_get($courseKeyRaw) ? $courseKeyRaw : null;
}
if (!$course) { header('Location: /corso-base-human-design.html?error=session'); exit; }

$product  = course_get($course);
$priceLabel = '€' . number_format($product['amount'] / 100, 0, ',', '.');

$nome          = clean('nome');
$email         = filter_var(cleanRaw('email'), FILTER_VALIDATE_EMAIL);
$telefono      = clean('telefono');
$codiceFiscale = clean('codice_fiscale');
$indirizzo     = clean('indirizzo');
$messaggio     = clean('messaggio');
$termini       = isset($_POST['termini_accettati']);

$redir = '/corso-dati/dati.php?course=' . urlencode($course) . '&session_id=' . urlencode($sessionId);

$missing = !$nome || !$email || !$telefono || !$termini;
if ($missing) {
    header('Location: ' . $redir . '&error=missing');
    exit;
}

$ADMIN_EMAIL = 'consulenza@marcomunich.com';
$FROM_EMAIL  = 'info@valentinarussobg5.com';
$FROM_NAME   = 'Valentina Russo — Corso BG5 Foundation';
$nowIt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i');

$subjectAdmin = "🎓 Nuova iscrizione: {$product['name']} — {$nome}";
$bodyAdmin = <<<TEXT
=== NUOVA ISCRIZIONE CORSO ===

DATA ORDINE : {$nowIt}
PRODOTTO    : {$product['name']} ({$priceLabel})
STRIPE ID   : {$sessionId}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONTATTO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NOME        : {$nome}
EMAIL       : {$email}
TELEFONO    : {$telefono}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DATI FATTURA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
COD. FISC./P.IVA: {$codiceFiscale}
INDIRIZZO       : {$indirizzo}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NOTE DEL CLIENTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$messaggio}

TEXT;

$subjectCliente = "{$product['name']} — iscrizione ricevuta";
$bodyCliente = <<<TEXT
Ciao {$nome},

abbiamo ricevuto la tua iscrizione a: {$product['name']} ({$priceLabel}).

Valentina ti scriverà entro 48 ore con il link Zoom fisso per tutte le
lezioni e il calendario completo del semestre.

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
$logFile = $logDir . 'iscrizioni.log';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$sessionPrefix = substr(preg_replace('/[^a-zA-Z0-9_]/', '', $sessionId), 0, 16);
$logLine = date('Y-m-d H:i:s') . " | {$course} | sess:{$sessionPrefix}… | admin:" . ($ok1?'ok':'FAIL') . ' cliente:' . ($ok2?'ok':'FAIL') . "\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);

if ($ok1 && $ok2) {
    header('Location: /corso-dati/dati.php?success=1&course=' . urlencode($course));
} else {
    error_log('[corso-invia] Mail send failure — admin:' . ($ok1?'OK':'FAIL') . ' cliente:' . ($ok2?'OK':'FAIL') . ' for ' . $email . ' course ' . $course);
    header('Location: ' . $redir . '&error=mail');
}
exit;
