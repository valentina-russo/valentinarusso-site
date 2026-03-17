<?php
/**
 * AI Rewrite — valentinarussobg5.com
 * mode=body  → riscrive solo il testo dell'articolo
 * mode=full  → riscrive testo + description + SEO + tags + FAQ + aeo + image_prompt
 *              aggiorna il frontmatter YAML e restituisce il frontmatter aggiornato
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
    $pass = $_POST['pass'] ?? '';
    if ($pass !== ADMIN_PASS) {
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
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'API key non configurata. Aprire AI Editor per inserirla.']);
    exit;
}

/* ── INPUT ── */
$content     = trim($_POST['content']     ?? '');
$frontmatter = trim($_POST['frontmatter'] ?? '');
$title       = trim($_POST['title']       ?? '');
$mode        = trim($_POST['mode']        ?? 'body');

// Estrai title dal frontmatter se non fornito separatamente
if (!$title && $frontmatter) {
    if (preg_match("/^title:\s*['\"]?(.+?)['\"]?\s*$/m", $frontmatter, $m)) {
        $title = trim($m[1], "'\" ");
    }
}

if (empty($content)) {
    echo json_encode(['ok' => false, 'error' => 'Contenuto vuoto.']);
    exit;
}

/* ── YAML HELPERS ── */

/**
 * Aggiorna (o aggiunge) un campo scalare nel YAML frontmatter.
 * Usa il formato Grav: field: 'valore'
 */
function yamlSetField($yaml, $key, $value) {
    $escaped = str_replace("'", "''", $value);
    $keyQ    = preg_quote($key, '/');
    if (preg_match('/^' . $keyQ . ':\s*/m', $yaml)) {
        return preg_replace('/^(' . $keyQ . '):\s*.+$/m', '$1: \'' . $escaped . '\'', $yaml);
    }
    return rtrim($yaml) . "\n" . $key . ': \'' . $escaped . '\'';
}

/**
 * Sostituisce (o aggiunge) il blocco faq: nel YAML frontmatter.
 * Formato Grav a 4 spazi.
 */
function yamlSetFaq($yaml, $faqs) {
    $block = "faq:\n";
    foreach ($faqs as $item) {
        $q      = str_replace("'", "''", trim($item['question'] ?? ''));
        $a      = str_replace("'", "''", trim($item['answer']   ?? ''));
        $block .= "    -\n        question: '" . $q . "'\n        answer: '" . $a . "'\n";
    }
    $block = rtrim($block);

    if (preg_match('/^faq:/m', $yaml)) {
        // Sostituisce il blocco faq: + tutte le righe indentate che lo seguono
        $yaml = preg_replace('/^faq:\n(?:[ \t]+.*(?:\n|$))*/m', $block . "\n", $yaml);
    } else {
        $yaml .= "\n" . $block;
    }
    return $yaml;
}

/* ── KNOWLEDGE BASE ── */
$kbContent = '';
$kbDir = __DIR__ . '/knowledge-base/';
if (is_dir($kbDir)) {
    $kbFiles = glob($kbDir . '*.md');
    if ($kbFiles) {
        sort($kbFiles);
        $kbContent  = "KNOWLEDGE BASE — FONTE UFFICIALE BG5/HUMAN DESIGN\n";
        $kbContent .= "Usa queste informazioni come riferimento autorevole per la terminologia, la meccanica e i concetti BG5/Human Design.\n\n";
        foreach ($kbFiles as $kbFile) {
            $kbContent .= file_get_contents($kbFile) . "\n\n";
        }
        $kbContent = trim($kbContent);
    }
}

/* ── SYSTEM PROMPT ── */
$systemPrompt = <<<PROMPT
Sei il ghostwriter ufficiale di Valentina Russo, analista certificata BG5® e Human Design.

CHI E' VALENTINA RUSSO:
Valentina Russo è analista certificata BG5® e Human Design. Lavora su disegno di carriera, personal branding autentico e dinamiche relazionali attraverso il sistema BG5 e Human Design. Non è psicologa. Si rivolge a liberi professionisti, imprenditori e aziende.

LESSICO BG5 — REGOLA OBBLIGATORIA:
Usa SEMPRE la terminologia BG5 come termine principale. Alla prima occorrenza in ogni articolo, aggiungi tra parentesi l'equivalente Human Design. Nelle occorrenze successive usa solo il termine BG5.

GLOSSARIO BG5 → Human Design (equivalenze):
- Tipo di Carriera → Tipo (HD)
  - Costruttore Classico → Generatore Puro (HD)
  - Costruttore Rapido → Generatore Manifestante (HD)
  - Iniziatore → Manifestatore (HD)
  - Guida → Proiettore (HD)
  - Valutatore → Riflettore (HD)
- Success Code → BodyGraph (HD)
- Profilo di Carriera → Profilo (HD)
- Autorità Decisionale → Autorità Interiore (HD)
- Centro Energetico → Centro (HD) [i nove centri sono identici in entrambi i sistemi]
- Canale → Canale (stesso termine)
- Porta → Gate (HD)
- Funzione → Linea (HD)
- Ciclo di Vita → Ciclo Saturno/Urano (HD)
- Analisi BG5 / Sessione BG5 → Reading Human Design (HD)

DECALOGO DELL'ARTICOLO — OBBLIGATORIO:
1. LUNGHEZZA: almeno 1.200 parole nel corpo. Ogni sezione va sviluppata con profondità, non riempita con frasi generiche.
2. APERTURA AD AGGANCIO: la prima frase descrive una situazione concreta che il lettore sta vivendo, oppure pone una domanda che si sta già facendo. Mai iniziare con "In questo articolo..." o riepiloghi introduttivi.
3. STRUTTURA: 5-7 sezioni con H2 informativi e specifici. Ogni H2 deve enunciare una tesi o un'idea precisa, non un titolo vago ("Il problema" è sbagliato; "Perché il tuo calendario è pieno ma ti senti svuotato" è corretto).
4. UNA SEZIONE = UNA TESI: ogni sezione sviluppa un'unica idea con almeno un esempio concreto, uno scenario riconoscibile o un meccanismo spiegato. Non elencare concetti: sviluppane uno per sezione.
5. INTEGRAZIONE BG5/HD: almeno due riferimenti specifici al sistema BG5 o Human Design per articolo (centri energetici, tipi, canali, autorità interiore, ecc.) spiegati in modo accessibile anche ai non esperti.
6. TONO: seconda persona singolare ("tu"). Mai il "noi" generico. Parla al lettore come a una persona specifica.
7. RITMO: paragrafi da 3-5 righe. Nessun elenco puntato nel corpo. Frasi di lunghezza variata — alterna frasi brevi a costruzioni più articolate.
8. CHIUSURA OPERATIVA: l'ultimo paragrafo indica un'azione concreta che il lettore può fare (prenotare una sessione BG5, contattare Valentina, richiedere un'analisi del bodygraph). Niente conclusioni accademiche.
9. PAROLE VIETATE: viaggio, percorso, trasformazione, rivoluzione, svolta, potenziale inespresso, benessere olistico, crescita personale, consapevolezza (a meno che non vengano citate per essere ridefinite o criticate).
10. NESSUNA AFFERMAZIONE VUOTA: ogni claim ha una spiegazione meccanica o un esempio. Se un concetto non si può supportare, non si scrive.

GLOSSARIO CENTRI MOTORE (EN → IT) — REGOLA OBBLIGATORIA:
Usa SEMPRE la traduzione italiana. Mai il termine inglese nel testo.
- Root → Radice
- Solar Plexus → Plesso Solare
- Heart / Will → Cuore / Volontà (Ego in BG5)
- Sacral → Sacrale

REGOLE DI STILE (obbligatorie):
- Vietate le costruzioni "non X ma Y" in tutte le varianti. Afferma direttamente.
- Vietate le triplette: tre aggettivi, tre verbi, tre sostantivi in sequenza.
- Vietate le meta-frasi: "è importante", "è fondamentale", "è chiaro che", "è giusto partire da".
- Niente emdash. Niente enfasi artificiale. Pochi aggettivi.
- Lingua: italiano. Sempre.
PROMPT;

// Inject knowledge base
if ($kbContent) {
    $systemPrompt .= "\n\n" . $kbContent;
}

/* ── FULL MODE ── */
if ($mode === 'full') {
    $userMsg  = "Titolo attuale: " . ($title ?: 'non specificato') . "\n\nCorpo attuale:\n\n" . $content . "\n\n";
    $userMsg .= "Riscrivi questo articolo rispettando TASSATIVAMENTE il Decalogo e le Regole di Stile sopra.\n";
    $userMsg .= "Restituisci SOLO un oggetto JSON valido (niente markdown fence, niente testo fuori dal JSON):\n\n";
    $userMsg .= "{\n";
    $userMsg .= "  \"body\": \"testo completo in Markdown (H2/H3 + paragrafi), minimo 1200 parole\",\n";
    $userMsg .= "  \"description\": \"meta-description 140-155 caratteri con keyword principale\",\n";
    $userMsg .= "  \"seo_title\": \"titolo SEO 50-60 caratteri, keyword all'inizio\",\n";
    $userMsg .= "  \"seo_desc\": \"descrizione SEO 150-155 caratteri\",\n";
    $userMsg .= "  \"tags\": \"4-6 tag separati da virgola in italiano senza #\",\n";
    $userMsg .= "  \"aeo_answer\": \"risposta diretta alla domanda principale, 80-120 parole\",\n";
    $userMsg .= "  \"geo_content\": \"3-5 affermazioni precise e autorevoli sul tema: definizioni esatte di termini BG5/HD usati nell'articolo, posizionamento unico di Valentina, risposte dirette alle domande più cercate. Usa frasi complete, tono enciclopedico, citabile da ChatGPT/Perplexity/Gemini. Max 200 parole.\",\n";
    $userMsg .= "  \"image_alt\": \"alt text accessibile per l'immagine copertina, max 120 caratteri, descrittivo e con keyword principale\",\n";
    $userMsg .= "  \"image_title\": \"titolo immagine breve e keyword-rich, max 60 caratteri\",\n";
    $userMsg .= "  \"image_caption\": \"didascalia mostrata sotto l'immagine, 1 frase chiara e informativa, max 100 caratteri\",\n";
    $userMsg .= "  \"image_desc\": \"descrizione estesa immagine per i motori di ricerca, 1-2 frasi, max 200 caratteri\",\n";
    $userMsg .= "  \"image_prompt\": \"prompt dettagliato in inglese per Midjourney/DALL-E: soggetto, stile, colori, composizione, lighting -- ar 16:9 --style raw\",\n";
    $userMsg .= "  \"faq\": [\n";
    $userMsg .= "    {\"question\": \"domanda 1\", \"answer\": \"risposta completa 50-100 parole\"},\n";
    $userMsg .= "    {\"question\": \"domanda 2\", \"answer\": \"risposta completa 50-100 parole\"},\n";
    $userMsg .= "    {\"question\": \"domanda 3\", \"answer\": \"risposta completa 50-100 parole\"},\n";
    $userMsg .= "    {\"question\": \"domanda 4\", \"answer\": \"risposta completa 50-100 parole\"}\n";
    $userMsg .= "  ]\n";
    $userMsg .= "}";

    $payload = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 8000,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ]);

/* ── META MODE ── */
} elseif ($mode === 'meta') {
    $userMsg  = "Titolo articolo: " . ($title ?: 'non specificato') . "\n\nCorpo articolo:\n\n" . mb_substr($content, 0, 4000) . "\n\n";
    $userMsg .= "Analizza questo articolo e genera ESCLUSIVAMENTE i metadati tecnici.\n";
    $userMsg .= "NON riscrivere ne' modificare il testo dell'articolo.\n";
    $userMsg .= "Rispondi SOLO con un oggetto JSON valido (niente markdown fence, niente testo fuori dal JSON):\n\n";
    $userMsg .= "{\n";
    $userMsg .= "  \"description\": \"meta-description 140-155 caratteri con keyword principale\",\n";
    $userMsg .= "  \"seo_title\": \"titolo SEO 50-60 caratteri, keyword all'inizio\",\n";
    $userMsg .= "  \"seo_desc\": \"descrizione SEO 150-155 caratteri\",\n";
    $userMsg .= "  \"tags\": \"4-6 tag separati da virgola in italiano senza #\",\n";
    $userMsg .= "  \"aeo_answer\": \"risposta diretta alla domanda principale dell'articolo, 80-120 parole\",\n";
    $userMsg .= "  \"geo_content\": \"3-5 affermazioni precise e autorevoli sul tema: definizioni esatte di termini BG5/HD, posizionamento di Valentina, risposte alle domande piu' cercate. Max 200 parole.\",\n";
    $userMsg .= "  \"image_alt\": \"alt text accessibile per l'immagine copertina, max 120 caratteri, descrittivo e con keyword\",\n";
    $userMsg .= "  \"image_title\": \"titolo immagine breve e keyword-rich, max 60 caratteri\",\n";
    $userMsg .= "  \"image_caption\": \"didascalia sotto l'immagine, 1 frase chiara e informativa, max 100 caratteri\",\n";
    $userMsg .= "  \"image_desc\": \"descrizione estesa immagine per motori di ricerca, max 200 caratteri\",\n";
    $userMsg .= "  \"faq\": [\n";
    $userMsg .= "    {\"question\": \"domanda 1\", \"answer\": \"risposta 50-100 parole\"},\n";
    $userMsg .= "    {\"question\": \"domanda 2\", \"answer\": \"risposta 50-100 parole\"},\n";
    $userMsg .= "    {\"question\": \"domanda 3\", \"answer\": \"risposta 50-100 parole\"}\n";
    $userMsg .= "  ]\n";
    $userMsg .= "}";

    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 2000,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ]);

/* ── BODY MODE ── */
} else {
    $payload = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 4096,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => "Riscrivi questo articolo rispettando il Decalogo:\n\n" . $content]],
    ]);
}

/* ── CHIAMATA CLAUDE (con fallback automatico Opus → Sonnet su 529) ── */
function doClaudeRequest(string $payload, string $apiKey, int $timeout = 180): array {
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
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    return [$raw, $code, $cerr];
}

[$raw, $code, $cerr] = doClaudeRequest($payload, $apiKey);

// Fallback automatico su Sonnet se Opus è overloaded (529) — solo per mode body/full
if (!$cerr && $code === 529 && $mode !== 'meta') {
    $p = json_decode($payload, true);
    $p['model'] = 'claude-sonnet-4-6';
    $payload = json_encode($p);
    [$raw, $code, $cerr] = doClaudeRequest($payload, $apiKey);
}

if ($cerr) {
    echo json_encode(['ok' => false, 'error' => 'cURL error: ' . $cerr]);
    exit;
}
if ($code !== 200) {
    $resp = json_decode($raw, true);
    echo json_encode(['ok' => false, 'error' => 'API error ' . $code . ': ' . ($resp['error']['message'] ?? $raw)]);
    exit;
}

$resp = json_decode($raw, true);
$text = trim($resp['content'][0]['text'] ?? '');

if (!$text) {
    echo json_encode(['ok' => false, 'error' => 'Risposta Claude vuota.']);
    exit;
}

if ($mode === 'full' || $mode === 'meta') {
    $text   = preg_replace('/^```json\s*/i', '', $text);
    $text   = preg_replace('/\s*```$/',      '', $text);
    $parsed = json_decode($text, true);

    // full richiede body, meta richiede seo_title
    $requiredField = ($mode === 'full') ? 'body' : 'seo_title';
    if (!$parsed || empty($parsed[$requiredField])) {
        echo json_encode(['ok' => false, 'error' => 'Risposta JSON non valida. Riprova.', 'raw' => substr($text, 0, 300)]);
        exit;
    }

    // Aggiorna il frontmatter YAML con i nuovi valori
    $fm = $frontmatter;
    if ($fm) {
        foreach (['description', 'seo_title', 'seo_desc', 'tags', 'aeo_answer', 'geo_content', 'image_alt', 'image_title', 'image_caption', 'image_desc'] as $field) {
            if (!empty($parsed[$field])) {
                $fm = yamlSetField($fm, $field, $parsed[$field]);
            }
        }
        if (!empty($parsed['faq']) && is_array($parsed['faq'])) {
            $fm = yamlSetFaq($fm, $parsed['faq']);
        }
    }

    $result = [
        'ok'          => true,
        'mode'        => $mode,
        'frontmatter' => $fm,
        'data'        => $parsed,
    ];
    if ($mode === 'full') {
        $result['body']         = $parsed['body'];
        $result['image_prompt'] = $parsed['image_prompt'] ?? '';
    }
    echo json_encode($result);
} else {
    echo json_encode(['ok' => true, 'mode' => 'body', 'content' => $text]);
}
