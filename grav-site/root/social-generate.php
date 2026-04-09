<?php
/**
 * social-generate.php — Social Media Generator
 * Trasforma testo in slide per carosello Instagram via Claude Haiku
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

define('CONFIG_FILE', __DIR__ . '/ai-editor.config.php');
define('HAIKU_MODEL', 'claude-haiku-4-5-20251001');
define('CLAUDE_URL',  'https://api.anthropic.com/v1/messages');
define('CLAUDE_VER',  '2023-06-01');
// SEC-001: Read password from env var — set SOCIAL_ADMIN_PASS on Aruba hosting panel.
define('ADMIN_PASS', getenv('SOCIAL_ADMIN_PASS') ?: 'ValeAdmin2026');

/* ── AUTH ── */
if (empty($_SESSION['ai_auth'])) {
    if (($_POST['pass'] ?? '') !== ADMIN_PASS) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Non autorizzato']);
        exit;
    }
    $_SESSION['ai_auth'] = true;
}

/* ── API KEY ── */
$apiKey = '';
if (file_exists(CONFIG_FILE)) {
    $cfg    = include CONFIG_FILE;
    $apiKey = $cfg['api_key'] ?? '';
}
if (empty($apiKey)) {
    echo json_encode(['ok' => false, 'error' => 'API key non configurata. Aprire AI Editor.']);
    exit;
}

/* ── INPUT ── */
$inputType = $_POST['input_type'] ?? 'text';   // text | article
$inputText = trim($_POST['input_text'] ?? '');
$format    = $_POST['format']     ?? 'carousel'; // carousel | single
$tone      = $_POST['tone']       ?? 'professional';

if (empty($inputText)) {
    echo json_encode(['ok' => false, 'error' => 'Contenuto vuoto.']);
    exit;
}
// SEC-003: Prompt injection mitigation — cap length + strip prompt-override patterns
$inputText = mb_substr($inputText, 0, 4000);
$inputText = preg_replace('/\b(ignore previous|disregard|forget|new instructions?|system prompt|override|jailbreak)\b/i', '[rimosso]', $inputText);

/* ── TONO ── */
$toneMap = [
    'professional' => 'professionale e autorevole, con terminologia BG5 precisa e misurabile',
    'warm'         => 'caldo e vicino, come una conversazione tra amiche che si fidano',
    'direct'       => 'diretto e incisivo, con frasi brevi e massimo impatto per parola',
];
$toneDesc = $toneMap[$tone] ?? $toneMap['professional'];

/* ── PROMPT SISTEMA ── */
$system = 'Sei il social media manager di Valentina Russo, consulente BG5 e Human Design certificata (BG5 Business Institute). Trasformi contenuti in caroselli Instagram ad alto engagement per @valentinarussobg5.

REGOLE STILISTICHE ASSOLUTE:
- Tono richiesto: ' . $toneDesc . '
- Italiano corretto, zero anglicismi inutili
- Mai iniziare con "Non X ma Y" — costruisci sempre in modo affermativo
- No triplette retoriche identiche
- Grassetti solo per termini tecnici: **Human Design**, **BG5**, **Proiettore**, ecc.
- Max 45 caratteri per titolo Hook e CTA
- Max 250 caratteri per body di ogni slide content
- Una sola idea per slide — mai sovraffollare

OUTPUT: rispondi SOLO con JSON valido, nessun markdown fence.';

/* ── PROMPT UTENTE ── */
$user = 'Crea un carosello Instagram di 7 slide dal seguente contenuto:

---
' . $inputText . '
---

Rispondi con questo JSON esatto (nessun code block):

{
  "slides": [
    { "type": "hook", "title": "...", "subtitle": "..." },
    { "type": "content", "layout": "standard", "headline": "...", "body": "..." },
    { "type": "content", "layout": "quote", "quote": "..." },
    { "type": "content", "layout": "list", "headline": "...", "bullets": ["...", "...", "..."] },
    { "type": "content", "layout": "standard", "headline": "...", "body": "..." },
    { "type": "content", "layout": "standard", "headline": "...", "body": "..." },
    { "type": "cta", "ask": "...", "instruction": "Scrivi \'BG5\' nei commenti \u2193" }
  ],
  "caption": "...",
  "hashtags": ["HumanDesign", "BG5", "ConsulenzaBG5", "HumanDesignItaliano", "Consapevolezza"]
}

ISTRUZIONI PER OGNI SLIDE:
- Slide 1 (hook): domanda o tensione che apre un loop non risolto. Titolo max 45 caratteri, sottotitolo max 70 caratteri.
- Slide 2 (content standard): reframe con la prospettiva BG5 del problema
- Slide 3 (content quote): citazione o insight ad alto impatto, max 80 caratteri
- Slide 4 (content list): 3 punti pratici, ogni bullet max 50 caratteri
- Slide 5 (content standard): esempio pratico o meccanismo specifico
- Slide 6 (content standard): il piu grande errore che la gente fa su questo tema
- Slide 7 (cta): ask max 45 caratteri + instruction "Scrivi \'BG5\' nei commenti \u2193"

Caption: hook 1 frase + 3 paragrafi brevi valore-primo + "Dimmi nei commenti: [domanda]" + 1 CTA.
Hashtags: esattamente 5 da questo pool: HumanDesign, BG5, ConsulenzaBG5, HumanDesignItaliano, Consapevolezza, CrescitaPersonale, CoachingItalia, CarattereHD, SistemaEmozionale, StrategiaHD';

/* ── CHIAMATA CLAUDE HAIKU ── */
$payload = json_encode([
    'model'      => HAIKU_MODEL,
    'max_tokens' => 2500,
    'system'     => $system,
    'messages'   => [['role' => 'user', 'content' => $user]],
]);

$ch = curl_init(CLAUDE_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: ' . CLAUDE_VER,
    ],
    CURLOPT_TIMEOUT => 60,
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($cerr) {
    echo json_encode(['ok' => false, 'error' => 'cURL: ' . $cerr]);
    exit;
}
if ($code !== 200) {
    $r = json_decode($raw, true);
    echo json_encode(['ok' => false, 'error' => 'API ' . $code . ': ' . ($r['error']['message'] ?? substr($raw, 0, 200))]);
    exit;
}

$resp = json_decode($raw, true);
$text = trim($resp['content'][0]['text'] ?? '');

/* Strip markdown code fences if present */
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```$/', '', trim($text));

$parsed = json_decode($text, true);
if (!$parsed || !isset($parsed['slides'])) {
    echo json_encode(['ok' => false, 'error' => 'Risposta non valida: ' . substr($text, 0, 200)]);
    exit;
}

echo json_encode([
    'ok'       => true,
    'slides'   => $parsed['slides']   ?? [],
    'caption'  => $parsed['caption']  ?? '',
    'hashtags' => $parsed['hashtags'] ?? [],
]);
