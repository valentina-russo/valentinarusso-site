<?php
/**
 * BG5 Blueprint — Stripe Webhook Handler
 *
 * Endpoint: POST /bg5-blueprint/stripe-webhook.php
 *
 * Flusso:
 * 1. Stripe invia checkout.session.completed
 * 2. Verifichiamo la firma (STRIPE_WEBHOOK_SECRET)
 * 3. Estraiamo i dati cliente + data/ora/luogo di nascita
 * 4. Creiamo un job in coda (jobs/pending/)
 * 5. Inviamo email di conferma alla cliente
 * 6. Il generator.py (lanciato via cron o worker) processa il job
 */

declare(strict_types=1);

// ─── CONFIG (da env) ──────────────────────────────────────────────────────────
$STRIPE_WEBHOOK_SECRET = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
$GITHUB_PAT            = getenv('GITHUB_PAT') ?: '';
$GITHUB_REPO           = getenv('GITHUB_REPO') ?: 'valentina-russo/valentinarusso-site';
$JOBS_DIR              = __DIR__ . '/jobs/';
$LOG_FILE              = __DIR__ . '/logs/webhook.log';

// ─── LOGGING ──────────────────────────────────────────────────────────────────
function wlog(string $msg): void {
    global $LOG_FILE;
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    @file_put_contents($LOG_FILE, $line, FILE_APPEND);
}

// ─── RISPOSTA HTTP ────────────────────────────────────────────────────────────
function respond(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => $msg]);
    exit;
}

// ─── MAIN ─────────────────────────────────────────────────────────────────────
$payload   = file_get_contents('php://input');
$sig       = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    wlog('ERROR: empty payload');
    respond(400, 'empty payload');
}

// SEC-PAY-004: hard reject if webhook secret not configured.
// Senza questo controllo, un attaccante che scopre l'endpoint può POSTare
// un evento checkout.session.completed forgiato e creare job fittizi.
if (empty($STRIPE_WEBHOOK_SECRET)) {
    wlog('ERROR: STRIPE_WEBHOOK_SECRET not configured — rejecting all requests');
    respond(501, 'webhook not configured');
}

// Verifica firma Stripe (sempre eseguita)
$parts    = [];
foreach (explode(',', $sig) as $part) {
    [$k, $v] = explode('=', $part, 2);
    $parts[$k][] = $v;
}
$timestamp  = $parts['t'][0] ?? '';
$signatures = $parts['v1'] ?? [];
$signed     = $timestamp . '.' . $payload;
$expected   = hash_hmac('sha256', $signed, $STRIPE_WEBHOOK_SECRET);

$valid = false;
foreach ($signatures as $s) {
    if (hash_equals($expected, $s)) {
        $valid = true;
        break;
    }
}
if (!$valid) {
    wlog('ERROR: invalid signature');
    respond(400, 'invalid signature');
}
// Protezione replay attack (5 min tolerance)
if (abs(time() - (int)$timestamp) > 300) {
    wlog('ERROR: stale timestamp ' . $timestamp);
    respond(400, 'stale timestamp');
}

$event = json_decode($payload, true);
if (!$event || $event['type'] !== 'checkout.session.completed') {
    // Evento non gestito — Stripe si aspetta 200
    respond(200, 'ignored');
}

$session  = $event['data']['object'];
$metadata = $session['metadata'] ?? [];

// Campi obbligatori dal checkout Stripe (passati come metadata)
$required = ['birth_date', 'birth_time', 'birth_place', 'customer_name'];
foreach ($required as $field) {
    if (empty($metadata[$field])) {
        wlog('ERROR: missing metadata field ' . $field);
        respond(400, 'missing metadata: ' . $field);
    }
}

// Crea job file
$job_id  = 'job_' . time() . '_' . bin2hex(random_bytes(4));
$job     = [
    'id'           => $job_id,
    'status'       => 'pending',
    'created_at'   => date('c'),
    'stripe_id'    => $session['id'],
    'payment_intent' => $session['payment_intent'] ?? '',
    'customer_email' => $session['customer_details']['email'] ?? $metadata['email'] ?? '',
    'customer_name'  => $metadata['customer_name'],
    'birth_date'     => $metadata['birth_date'],   // formato: YYYY-MM-DD
    'birth_time'     => $metadata['birth_time'],   // formato: HH:MM
    'birth_place'    => $metadata['birth_place'],  // es: "Torino, Italia"
    'birth_lat'      => $metadata['birth_lat'] ?? null,
    'birth_lon'      => $metadata['birth_lon'] ?? null,
    'amount_eur'     => ($session['amount_total'] ?? 9000) / 100,
];

if (!is_dir($JOBS_DIR . 'pending/')) {
    mkdir($JOBS_DIR . 'pending/', 0755, true);
}

$job_file = $JOBS_DIR . 'pending/' . $job_id . '.json';
file_put_contents($job_file, json_encode($job, JSON_PRETTY_PRINT));

wlog('JOB created: ' . $job_id . ' for ' . $job['customer_email']);

// ─── TRIGGER GITHUB ACTIONS ──────────────────────────────────────────────────
if (!empty($GITHUB_PAT)) {
    $dispatch = json_encode([
        'event_type'     => 'blueprint_job',
        'client_payload' => [
            'job_id'   => $job_id,
            'job_data' => $job,
        ],
    ]);

    $ch = curl_init("https://api.github.com/repos/{$GITHUB_REPO}/dispatches");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $dispatch,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $GITHUB_PAT,
            'Accept: application/vnd.github.v3+json',
            'Content-Type: application/json',
            'User-Agent: BG5-Blueprint-Webhook',
            'X-GitHub-Api-Version: 2022-11-28',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $gh_response  = curl_exec($ch);
    $gh_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    wlog("GitHub dispatch: HTTP {$gh_http_code} → job {$job_id}");
    if ($gh_http_code !== 204) {
        wlog("GitHub dispatch WARNING: risposta inattesa {$gh_http_code}: {$gh_response}");
    }
} else {
    wlog("WARN: GITHUB_PAT non configurato — generazione non avviata per job {$job_id}");
}

// ─── EMAIL CONFERMA CLIENTE ───────────────────────────────────────────────────
// TODO (FASE C2): chiamare mailer.php interno dopo dispatch confermato

respond(200, 'job created: ' . $job_id);
