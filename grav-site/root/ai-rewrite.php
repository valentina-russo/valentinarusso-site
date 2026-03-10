<?php
/**
 * AI Rewrite — valentinarussobg5.com
 * Riscrive il corpo di un articolo esistente via Claude API.
 * Chiamato via fetch() dal plugin valentina-admin.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

define('CONFIG_FILE',    __DIR__ . '/ai-editor.config.php');
define('CLAUDE_MODEL',   'claude-opus-4-6');
define('CLAUDE_URL',     'https://api.anthropic.com/v1/messages');
define('CLAUDE_VERSION', '2023-06-01');
define('ADMIN_PASS',     'ValeAdmin2026');

/* ── AUTH ── */
if (empty($_SESSION['ai_auth'])) {
    // Accetta anche auth via header (per chiamate AJAX dall'admin Grav)
    $pass = $_POST['pass'] ?? '';
    if ($pass !== ADMIN_PASS) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Non autorizzato']);
        exit;
    }
    $_SESSION['ai_auth'] = true;
}

/* ── LOAD API KEY ── */
$apiKey = '';
if (file_exists(CONFIG_FILE)) {
    $cfg = include CONFIG_FILE;
    $apiKey = $cfg['api_key'] ?? '';
}

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'API key non configurata. Aprire AI Editor per inserirla.']);
    exit;
}

/* ── LEGGI CONTENUTO ── */
$content = trim($_POST['content'] ?? '');
if (empty($content)) {
    echo json_encode(['ok' => false, 'error' => 'Contenuto vuoto.']);
    exit;
}

/* ── PROMPT ── */
$systemPrompt = <<<PROMPT
Sei il ghostwriter ufficiale di Valentina Russo, analista certificata BG5® e Human Design a Milano, Italia.

Riscrivi il corpo dell'articolo che ti viene passato rispettando TASSATIVAMENTE le regole di stile sotto.
Restituisci SOLO il testo riscritto in Markdown (titoli H2/H3, paragrafi), senza spiegazioni, senza note, senza prefazioni, senza markdown fence.

REGOLE DI STILE OBBLIGATORIE:
- Vietato usare costruzioni "non X ma Y" e qualsiasi variante: "non si tratta di X, si tratta di Y", "non parliamo di X, parliamo di Y", "non sto dicendo X, sto dicendo Y", "non serve X, serve Y", "il punto non è X, il punto è Y", "non è una questione di X, è una questione di Y". Qualsiasi frase che definisca qualcosa negando prima il suo opposto va eliminata e riscritta affermando direttamente ciò che si vuole dire.
- Vietate le triplette: tre aggettivi, tre verbi, tre stati, tre "senza…" in sequenza.
- Vietate le meta-frasi che commentano il testo: "è importante", "è chiaro", "è la parte più forte", "è giusto partire da…".
- Evita astratti non supportati da dettagli concreti: "consapevolezza", "lucidità", "profondo", "responsabilità", "nel rispetto". Preferisci scene brevi, azioni e conseguenze verificabili.
- Non anticipare obiezioni, non scrivere in difesa preventiva.
- Chiudi con un fatto o una decisione pratica, non con frasi da comunicato.
- Ritmo disteso e fluido: ogni pensiero si sviluppa per almeno tre o quattro righe prima di chiudersi con un punto. Se in due righe ci sono più di due punti, il testo è troppo frammentato — lega i pensieri con costruzioni naturali. Il testo deve scorrere come un articolo scritto da una persona che ragiona mentre scrive.
- Pochi aggettivi, zero enfasi artificiale, niente emdash.
- Lingua: italiano sempre.
- Tono: professionale ma caldo, esperto ma non accademico.
PROMPT;

/* ── CHIAMATA CLAUDE ── */
$payload = json_encode([
    'model'      => CLAUDE_MODEL,
    'max_tokens' => 4096,
    'system'     => $systemPrompt,
    'messages'   => [['role' => 'user', 'content' => "Riscrivi questo articolo:\n\n" . $content]],
]);

$ch = curl_init(CLAUDE_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: ' . CLAUDE_VERSION,
    ],
    CURLOPT_TIMEOUT => 120,
]);

$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($cerr) {
    echo json_encode(['ok' => false, 'error' => 'cURL error: ' . $cerr]);
    exit;
}
if ($code !== 200) {
    $resp = json_decode($raw, true);
    echo json_encode(['ok' => false, 'error' => 'API error ' . $code . ': ' . ($resp['error']['message'] ?? $raw)]);
    exit;
}

$resp    = json_decode($raw, true);
$rewritten = trim($resp['content'][0]['text'] ?? '');

if (!$rewritten) {
    echo json_encode(['ok' => false, 'error' => 'Risposta Claude vuota.']);
    exit;
}

echo json_encode(['ok' => true, 'content' => $rewritten]);
