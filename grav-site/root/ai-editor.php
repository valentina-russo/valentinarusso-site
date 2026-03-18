<?php
/**
 * AI Article Editor — valentinarussobg5.com
 * Genera articoli BG5/Human Design da trascrizioni video via Claude API
 */

session_start();

/* ── CONFIG ──────────────────────────────────────────────── */
define('ADMIN_PASS',     'ValeAdmin2026');
define('CONFIG_FILE',    __DIR__ . '/ai-editor.config.php');
define('DIR_PRIVATI',    __DIR__ . '/user/pages/04.blog/articoli/');
define('DIR_AZIENDE',    __DIR__ . '/user/pages/05.aziende/02.blog/');
define('CLAUDE_MODEL_DEFAULT', 'claude-opus-4-6');
define('CLAUDE_MODELS', [
    'claude-opus-4-6'   => ['label' => 'Opus 4.6',   'desc' => 'Massima qualità — consigliato per articoli definitivi'],
    'claude-sonnet-4-6' => ['label' => 'Sonnet 4.6', 'desc' => 'Ottimo equilibrio qualità/velocità'],
    'claude-haiku-4-5'  => ['label' => 'Haiku 4.5',  'desc' => 'Veloce ed economico — bozze rapide'],
]);
define('CLAUDE_URL',     'https://api.anthropic.com/v1/messages');
define('CLAUDE_VERSION', '2023-06-01');

/* ── LOAD API KEY FROM CONFIG ────────────────────────────── */
$apiKey = '';
if (file_exists(CONFIG_FILE)) {
    $cfg = include CONFIG_FILE;
    $apiKey = $cfg['api_key'] ?? '';
}

/* ── AUTH ────────────────────────────────────────────────── */
if (isset($_POST['login'])) {
    if ($_POST['pass'] === ADMIN_PASS) {
        $_SESSION['ai_auth'] = true;
    } else {
        $loginError = 'Password errata.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /ai-editor.php');
    exit;
}
$authed = !empty($_SESSION['ai_auth']);

/* ── SAVE API KEY ────────────────────────────────────────── */
if ($authed && isset($_POST['save_key'])) {
    $newKey = trim($_POST['api_key'] ?? '');
    file_put_contents(CONFIG_FILE,
        "<?php return ['api_key' => " . var_export($newKey, true) . "];\n"
    );
    $apiKey = $newKey;
    $keyMsg = '✓ API key salvata.';
}

/* ── SLUGIFY ─────────────────────────────────────────────── */
function slugify(string $s): string {
    $map = ['à'=>'a','á'=>'a','â'=>'a','ä'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c'];
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/* ── CLAUDE HTTP HELPER ──────────────────────────────────── */
function callClaude(string $payload, string $apiKey): array {
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
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    unset($ch);
    return [$raw, $code, $cerr];
}

/* ── KNOWLEDGE BASE ─────────────────────────────────────── */
function loadKnowledgeBase(): string {
    $kbDir = __DIR__ . '/knowledge-base/';
    if (!is_dir($kbDir)) return '';
    $files = glob($kbDir . '*.md');
    if (!$files) return '';
    sort($files);
    $out  = "KNOWLEDGE BASE — FONTE UFFICIALE BG5/HUMAN DESIGN\n";
    $out .= "Usa queste informazioni come riferimento autorevole per la terminologia, la meccanica e i concetti BG5/Human Design.\n\n";
    foreach ($files as $f) {
        $out .= file_get_contents($f) . "\n\n";
    }
    return trim($out);
}

/* ── GENERATE ARTICLE ────────────────────────────────────── */
$result  = null;
$genErr  = null;
$genOk   = null;

if ($authed && isset($_POST['generate'])) {
    $transcript = trim($_POST['transcript'] ?? '');
    $category   = $_POST['category'] ?? 'privati';  // privati | aziende
    $today      = date('Y-m-d H:i:s');
    $aiModel    = $_POST['ai_model'] ?? CLAUDE_MODEL_DEFAULT;
    if (!array_key_exists($aiModel, CLAUDE_MODELS)) $aiModel = CLAUDE_MODEL_DEFAULT;

    if (empty($transcript)) {
        $genErr = 'Incolla la trascrizione del video.';
    } elseif (empty($apiKey)) {
        $genErr = 'Configura prima la Claude API key.';
    } else {

        $catLabel = $category === 'aziende'
            ? 'AZIENDE (team, leadership, carriera, BG5 in contesto professionale/aziendale)'
            : 'PRIVATI (crescita personale, consapevolezza, relazioni, emozioni, Human Design)';

        $systemPrompt = <<<PROMPT
Sei il ghostwriter ufficiale di Valentina Russo, analista certificata BG5® e Human Design, in Italia.
Il tuo compito è trasformare trascritti video in articoli blog professionali in italiano.

Restituisci SOLO un oggetto JSON valido, senza testo aggiuntivo, senza markdown fence, senza spiegazioni.

Struttura JSON richiesta:
{
  "title": "Titolo accattivante, specifico, non generico (max 70 caratteri)",
  "slug": "slug-url-kebab-case-senza-accenti",
  "description": "Anteprima lista articoli: 2-3 righe, max 200 caratteri, tono che invoglia a leggere",
  "tags": ["Tag1", "Tag2", "Tag3"],
  "image_alt": "Testo alt accessibile dell'immagine copertina",
  "image_title": "Titolo breve immagine",
  "image_caption": "Didascalia breve (max 10 parole)",
  "image_desc": "Descrizione SEO immagine per motori di ricerca (1-2 frasi)",
  "seo_title": "Titolo per Google: max 60 CARATTERI, keyword principale in testa",
  "seo_desc": "Meta description: max 155 CARATTERI, include benefit e keyword",
  "geo_location": "Italia",
  "geo_content": "3-5 affermazioni autorevoli e precise sul tema: definizioni esatte di termini BG5/HD, dati o ricerche, posizionamento unico di Valentina, risposte dirette alle domande più cercate",
  "aeo_answer": "Risposta sintetica di 2-4 righe alla domanda principale dell'articolo. Diretta, utile, usata da AI Overview e ChatGPT.",
  "faq": [
    {"question": "Domanda 1 pertinente al tema?", "answer": "Risposta completa e utile."},
    {"question": "Domanda 2?", "answer": "Risposta 2."},
    {"question": "Domanda 3?", "answer": "Risposta 3."},
    {"question": "Domanda 4?", "answer": "Risposta 4."}
  ],
  "content": "Corpo articolo completo in Markdown (600-900 parole)",
  "image_prompts": [
    "Prompt immagine 1: stile fotografico/realistico — descrizione dettagliata senza testo, senza scritte, senza persone riconoscibili",
    "Prompt immagine 2: stile illustrativo/artistico — composizione alternativa, colori caldi, atmosfera evocativa, NO testo",
    "Prompt immagine 3: stile minimalista/simbolico — forma essenziale, sfondo pulito, ispirazione concettuale, NO testo"
  ]
}

REGOLE TASSATIVE:
- Lingua: ITALIANO sempre, senza eccezioni
- Categoria: {$catLabel}
- seo_title: MASSIMO 60 caratteri (conta!)
- seo_desc: MASSIMO 155 caratteri (conta!)
- Tono: professionale ma caldo, esperto ma non accademico
- Content: usa titoli H2/H3, paragrafi distesi, nessun elenco puntato eccessivo

GLOSSARIO CENTRI MOTORE (EN → IT) — REGOLA OBBLIGATORIA:
Usa SEMPRE la traduzione italiana. Mai il termine inglese nel testo.
- Root → Radice
- Solar Plexus → Plesso Solare
- Heart / Will → Cuore / Volontà (Ego in BG5)
- Sacral → Sacrale

STILE DI SCRITTURA OBBLIGATORIO (si applica a content, description, aeo_answer, geo_content, faq):
- Vietato usare costruzioni "non X ma Y" e qualsiasi variante: "non si tratta di X, si tratta di Y", "non parliamo di X, parliamo di Y", "non sto dicendo X, sto dicendo Y", "non serve X, serve Y", "il punto non è X, il punto è Y", "non è una questione di X, è una questione di Y". Qualsiasi frase che definisca qualcosa negando prima il suo opposto va eliminata e riscritta affermando direttamente ciò che si vuole dire.
- Vietate le triplette: tre aggettivi, tre verbi, tre stati, tre "senza…" in sequenza.
- Vietate le meta-frasi che commentano il testo: "è importante", "è chiaro", "è la parte più forte", "è giusto partire da…".
- Evita astratti non supportati da dettagli concreti: "consapevolezza", "lucidità", "profondo", "responsabilità", "nel rispetto". Preferisci scene brevi, azioni e conseguenze verificabili.
- Non anticipare obiezioni, non scrivere in difesa preventiva.
- Chiudi con un fatto o una decisione pratica, non con frasi da comunicato.
- Ritmo disteso e fluido: ogni pensiero si sviluppa per almeno tre o quattro righe prima di chiudersi con un punto. Se in due righe ci sono più di due punti, il testo è troppo frammentato — lega i pensieri con costruzioni naturali. Il testo deve scorrere come un articolo scritto da una persona che ragiona mentre scrive.
- Pochi aggettivi, zero enfasi artificiale, niente emdash.
- Non inventare dati o concetti non presenti nel trascritto
- Lo slug non deve contenere accenti o caratteri speciali
- JSON: solo valori stringa per i campi semplici, array per tags, faq e image_prompts
- geo_content: 3-5 affermazioni in italiano, precise e autorevoli — definizioni tecniche BG5/HD, fatti verificabili, posizionamento unico di Valentina. Saranno lette da ChatGPT/Perplexity per generare risposte.
- image_prompts: 3 prompt in INGLESE per generatori AI (Midjourney, Gemini, DALL-E)
  * Ogni prompt: 1-3 frasi descrittive, molto visivo e specifico per il tema dell'articolo
  * Stile: fotografico realistico / illustrativo caldo / minimalista simbolico
  * OBBLIGATORIO: niente testo nell'immagine, niente scritte, niente loghi
  * Includi: colori suggeriti, atmosfera, composizione, stile artistico di riferimento
  * Esempio buono: "Soft natural light falling on an open journal and dried flowers on a wooden desk, warm amber tones, shallow depth of field, minimalist aesthetic, no text, no people"
PROMPT;

        // Inject knowledge base
        $kb = loadKnowledgeBase();
        if ($kb) {
            $systemPrompt .= "\n\n" . $kb;
        }

        $userMsg = "Categoria: {$category}\n\nTRASCRITTO VIDEO:\n\n{$transcript}";

        $payload = json_encode([
            'model'      => $aiModel,
            'max_tokens' => 4096,
            'system'     => $systemPrompt,
            'messages'   => [['role' => 'user', 'content' => $userMsg]],
        ]);

        [$raw, $code, $cerr] = callClaude($payload, $apiKey);

        if ($cerr) {
            $genErr = 'cURL error: ' . $cerr;
        } elseif ($code !== 200) {
            $resp = json_decode($raw, true);
            $genErr = 'API error ' . $code . ': ' . ($resp['error']['message'] ?? $raw);
        } else {
            $resp = json_decode($raw, true);
            $jsonText = $resp['content'][0]['text'] ?? '';
            // Strip markdown fences if present
            $jsonText = preg_replace('/^```(?:json)?\s*/i', '', trim($jsonText));
            $jsonText = preg_replace('/\s*```$/', '', $jsonText);
            $article = json_decode($jsonText, true);

            if (!$article || !isset($article['title'])) {
                $genErr = 'Risposta Claude non valida. Raw: ' . substr($jsonText, 0, 500);
            } else {
                $result         = $article;
                $category_saved = $category;
                $model_used     = $aiModel;
            }
        }
    }
}

/* ── SAVE ARTICLE TO DISK ────────────────────────────────── */
if ($authed && isset($_POST['save_article']) && isset($_POST['article_json'])) {
    $article  = json_decode($_POST['article_json'], true);
    $category = $_POST['save_category'] ?? 'privati';

    if (!$article) {
        $genErr = 'JSON articolo non valido.';
    } else {
        $slug   = slugify($article['slug'] ?? $article['title'] ?? 'articolo-' . time());
        $today  = date('Y-m-d H:i:s');
        $dir    = $category === 'aziende' ? DIR_AZIENDE : DIR_PRIVATI;
        $folder = $dir . $slug;

        if (!is_dir($folder)) mkdir($folder, 0775, true);

        // Build FAQ YAML
        $faqYaml = '';
        foreach ($article['faq'] ?? [] as $faq) {
            // In YAML double-quoted strings solo " e \ vanno escaped — NON gli apostrofi
            $q = str_replace(['\\', '"'], ['\\\\', '\\"'], $faq['question'] ?? '');
            $a = str_replace(['\\', '"'], ['\\\\', '\\"'], $faq['answer'] ?? '');
            $faqYaml .= "    -\n      question: \"{$q}\"\n      answer: \"{$a}\"\n";
        }

        // Build tags YAML
        $tagsYaml = implode("\n    - ", array_map('trim', $article['tags'] ?? []));
        $tagsYaml = $tagsYaml ? "    - {$tagsYaml}" : '';

        $md = "---\ntitle: " . json_encode($article['title'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "date: {$today}\n"
            . "published: false\n"
            . "author: \"Valentina Russo\"\n"
            . "featured_image: \"/user/images/blog/placeholder.jpg\"\n"
            . "image_alt: " . json_encode($article['image_alt'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "image_title: " . json_encode($article['image_title'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "image_caption: " . json_encode($article['image_caption'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "image_desc: " . json_encode($article['image_desc'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "description: " . json_encode($article['description'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "tags:\n{$tagsYaml}\n"
            . "seo_title: " . json_encode($article['seo_title'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "seo_desc: " . json_encode($article['seo_desc'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "geo_location: " . json_encode($article['geo_location'] ?? 'Italia', JSON_UNESCAPED_UNICODE) . "\n"
            . "geo_content: " . json_encode($article['geo_content'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "aeo_answer: " . json_encode($article['aeo_answer'] ?? '', JSON_UNESCAPED_UNICODE) . "\n"
            . "faq:\n{$faqYaml}"
            . "---\n\n"
            . ($article['content'] ?? '');

        if (file_put_contents($folder . '/item.md', $md) !== false) {
            $adminPath = $category === 'aziende'
                ? "/admin/pages/aziende/blog/{$slug}"
                : "/admin/pages/blog/articoli/{$slug}";
            $genOk = $adminPath;
        } else {
            $genErr = 'Errore scrittura file. Controlla i permessi della cartella ' . $folder;
        }
    }
}

/* ── HELPERS ─────────────────────────────────────────────── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AI Article Editor | Valentina Russo</title>
<style>
:root{--bg:#f4f6f9;--card:#fff;--border:#dde1e7;--primary:#1e3a5f;--accent:#B68397;--green:#27ae60;--red:#e74c3c;--text:#1a1a2e;--muted:#6b7280;--radius:10px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.topbar{background:var(--primary);color:#fff;padding:14px 28px;display:flex;align-items:center;justify-content:space-between}
.topbar h1{font-size:1.1rem;font-weight:700;letter-spacing:.03em}
.topbar a{color:rgba(255,255,255,.75);font-size:.85rem;text-decoration:none}
.topbar a:hover{color:#fff}
.wrap{max-width:860px;margin:0 auto;padding:32px 20px}
.card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);padding:28px;margin-bottom:24px}
.card h2{font-size:1rem;font-weight:700;margin-bottom:18px;color:var(--primary)}
label{display:block;font-size:.82rem;font-weight:600;margin-bottom:5px;color:var(--muted)}
input[type=text],input[type=password],textarea,select{width:100%;padding:10px 13px;border:1px solid var(--border);border-radius:6px;font-size:.92rem;font-family:inherit;background:#fafbfc;transition:border .2s}
input:focus,textarea:focus{outline:none;border-color:var(--primary)}
textarea{resize:vertical;min-height:200px;font-family:monospace;font-size:.82rem}
.btn{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border:none;border-radius:6px;font-size:.92rem;font-weight:700;cursor:pointer;transition:opacity .2s;text-decoration:none}
.btn:hover{opacity:.88}
.btn-primary{background:var(--primary);color:#fff}
.btn-accent{background:var(--accent);color:#fff}
.btn-green{background:var(--green);color:#fff}
.btn-sm{padding:7px 14px;font-size:.8rem}
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.radios{display:flex;gap:20px;margin-top:6px}
.radios label{display:flex;align-items:center;gap:8px;font-size:.92rem;font-weight:400;color:var(--text);cursor:pointer}
.radios input{width:auto}
.alert{padding:13px 16px;border-radius:6px;font-size:.88rem;margin-bottom:20px}
.alert-err{background:#fdecea;border:1px solid #f5c6cb;color:#721c24}
.alert-ok{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.alert-info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460}
.sep{border:none;border-top:1px solid var(--border);margin:20px 0}
.field{margin-bottom:16px}
.result-box{background:#f8f9fa;border:1px solid var(--border);border-radius:6px;padding:14px;font-size:.82rem;font-family:monospace;white-space:pre-wrap;max-height:260px;overflow-y:auto}
.tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
.tag{background:var(--primary);color:#fff;padding:3px 10px;border-radius:20px;font-size:.75rem}
.tag-acc{background:var(--accent)}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.meta-item label{font-size:.75rem}
.meta-item p{font-size:.82rem;padding:8px 10px;background:#f8f9fa;border-radius:4px;border:1px solid var(--border);min-height:36px}
.char-ok{color:var(--green);font-size:.75rem;margin-top:3px}
.char-warn{color:var(--red);font-size:.75rem;margin-top:3px}
.faq-item{background:#f8f9fa;border-radius:6px;padding:12px;margin-bottom:10px;border:1px solid var(--border)}
.faq-item strong{font-size:.82rem;color:var(--primary)}
.faq-item p{font-size:.82rem;margin-top:5px;color:var(--muted)}
.content-preview{background:#f8f9fa;border-radius:6px;padding:16px;font-size:.85rem;line-height:1.7;max-height:320px;overflow-y:auto}
.content-preview h1,.content-preview h2{font-size:1rem;margin:12px 0 6px;color:var(--primary)}
.content-preview h3{font-size:.92rem;margin:10px 0 4px;color:var(--primary)}
.content-preview p{margin-bottom:8px}
.badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:.73rem;font-weight:700;text-transform:uppercase}
.badge-priv{background:#B68397;color:#fff}
.badge-az{background:#1e3a5f;color:#fff}
@media(max-width:600px){.row,.meta-grid{grid-template-columns:1fr}}
.model-selector{display:flex;gap:10px;flex-wrap:wrap}
.model-option{flex:1;min-width:180px;position:relative}
.model-option input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.model-option label{display:block;padding:12px 14px;border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:border-color .2s,background .2s;background:#fafbfc}
.model-option input:checked + label{border-color:var(--primary);background:#eef2f8}
.model-option label:hover{border-color:#aab4c4}
.model-name{font-weight:700;font-size:.9rem;color:var(--primary);display:block;margin-bottom:3px}
.model-desc{font-size:.76rem;color:var(--muted);font-weight:400}
.img-prompt-box{background:#f0f4ff;border:1px solid #c7d4f0;border-radius:8px;padding:14px 16px;margin-bottom:12px;position:relative}
.img-prompt-label{font-size:.75rem;font-weight:700;color:var(--primary);margin-bottom:7px;text-transform:uppercase;letter-spacing:.04em}
.img-prompt-text{font-size:.83rem;line-height:1.6;color:var(--text);padding-right:90px}
.img-copy-btn{background:var(--primary);color:#fff;position:absolute;top:12px;right:12px;border-radius:5px}
.img-copy-btn.copied{background:var(--green)}
/* Loading overlay */
#gen-overlay{display:none;position:fixed;inset:0;background:rgba(10,20,40,.82);z-index:9999;flex-direction:column;align-items:center;justify-content:center;gap:22px}
#gen-overlay.show{display:flex}
.gen-spinner{width:52px;height:52px;border:4px solid rgba(255,255,255,.2);border-top-color:#fff;border-radius:50%;animation:spin .9s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.gen-title{color:#fff;font-size:1.15rem;font-weight:700;letter-spacing:.02em}
.gen-step{color:rgba(255,255,255,.75);font-size:.88rem;min-height:1.2em;transition:opacity .4s}
.gen-bar-wrap{width:320px;max-width:90vw;background:rgba(255,255,255,.18);border-radius:20px;height:8px;overflow:hidden}
.gen-bar{height:100%;background:linear-gradient(90deg,#7c3aed,#B68397);border-radius:20px;width:0%;transition:width .6s ease}
</style>
<script>
function copyPrompt(i){
  const text = document.getElementById('ip'+i).textContent;
  navigator.clipboard.writeText(text).then(()=>{
    const btn = document.getElementById('copybtn'+i);
    btn.textContent='✓ Copiato!';btn.classList.add('copied');
    setTimeout(()=>{btn.textContent='📋 Copia';btn.classList.remove('copied');},2200);
  });
}

/* Loading bar per generazione Claude */
(function(){
  var steps = [
    [0,  'Connessione a Claude AI…'],
    [8,  'Analisi del testo di partenza…'],
    [20, 'Costruzione struttura articolo…'],
    [38, 'Generazione contenuto e SEO…'],
    [55, 'Ottimizzazione AEO e GEO…'],
    [70, 'Creazione FAQ e tag…'],
    [82, 'Generazione prompt immagini…'],
    [92, 'Revisione e pulizia JSON…'],
  ];
  var overlay, bar, stepEl, timer, idx=0;

  function tick(){
    if(idx >= steps.length) return;
    bar.style.width = steps[idx][0] + '%';
    stepEl.textContent = steps[idx][1];
    idx++;
    timer = setTimeout(tick, 4200);
  }

  document.addEventListener('DOMContentLoaded', function(){
    overlay = document.getElementById('gen-overlay');
    bar     = document.getElementById('gen-bar');
    stepEl  = document.getElementById('gen-step');
    var form = document.querySelector('form[data-gen]');
    if(!form || !overlay) return;
    form.addEventListener('submit', function(){
      overlay.classList.add('show');
      idx = 0;
      tick();
    });
  });
})();
</script>
</head>
<body>

<!-- Loading overlay -->
<div id="gen-overlay">
  <div class="gen-spinner"></div>
  <div class="gen-title">✨ Claude sta scrivendo il tuo articolo…</div>
  <div class="gen-bar-wrap"><div class="gen-bar" id="gen-bar"></div></div>
  <div class="gen-step" id="gen-step">Avvio…</div>
</div>

<div class="topbar">
  <h1>⚡ AI Article Editor</h1>
  <?php if($authed): ?>
  <a href="?logout">Esci</a>
  <?php endif; ?>
</div>

<div class="wrap">

<?php if (!$authed): ?>
<!-- LOGIN -->
<div class="card" style="max-width:360px;margin:60px auto">
  <h2>Accesso</h2>
  <?php if(!empty($loginError)): ?><div class="alert alert-err"><?= h($loginError) ?></div><?php endif; ?>
  <form method="POST">
    <div class="field">
      <label>Password admin</label>
      <input type="password" name="pass" required autofocus>
    </div>
    <button class="btn btn-primary" name="login" value="1" style="width:100%">Entra</button>
  </form>
</div>

<?php else: ?>

<!-- API KEY SETUP -->
<?php if(empty($apiKey)): ?>
<div class="alert alert-info">⚠️ Claude API key non configurata. Inseriscila qui sotto — verrà salvata sul server e non sarà mai visibile nel codice.</div>
<?php endif; ?>
<div class="card">
  <h2>🔑 Claude API Key</h2>
  <?php if(!empty($keyMsg)): ?><div class="alert alert-ok"><?= h($keyMsg) ?></div><?php endif; ?>
  <form method="POST">
    <div class="field">
      <label>Anthropic API Key</label>
      <input type="text" name="api_key" value="<?= h($apiKey) ?>" placeholder="sk-ant-api03-..." autocomplete="off">
    </div>
    <button class="btn btn-primary btn-sm" name="save_key" value="1">Salva key</button>
  </form>
</div>

<!-- GENERATOR -->
<?php if(!empty($genErr)): ?><div class="alert alert-err">❌ <?= h($genErr) ?></div><?php endif; ?>

<?php if(!empty($genOk)): ?>
<div class="alert alert-ok">
  ✅ Articolo creato come bozza! <strong><a href="<?= h($genOk) ?>">Apri in Grav Admin →</a></strong>
</div>
<?php endif; ?>

<?php if (!$result): ?>
<!-- FORM GENERA -->
<div class="card">
  <h2>📝 Genera Articolo da Testo</h2>
  <form method="POST" data-gen>
    <div class="field">
      <label>Modello AI</label>
      <div class="model-selector">
        <?php foreach (CLAUDE_MODELS as $modelId => $modelInfo): ?>
        <div class="model-option">
          <input type="radio" name="ai_model" id="m_<?= h($modelId) ?>" value="<?= h($modelId) ?>"
            <?= $modelId === CLAUDE_MODEL_DEFAULT ? 'checked' : '' ?>>
          <label for="m_<?= h($modelId) ?>">
            <span class="model-name"><?= h($modelInfo['label']) ?></span>
            <span class="model-desc"><?= h($modelInfo['desc']) ?></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="field">
      <label>Categoria articolo</label>
      <div class="radios">
        <label><input type="radio" name="category" value="privati" checked> 🌸 Privati (crescita personale, HD)</label>
        <label><input type="radio" name="category" value="aziende"> 💼 Aziende (team, leadership, BG5)</label>
      </div>
    </div>
    <div class="field">
      <label>Testo di partenza — trascrizione video, appunti, bozza, ricerca, qualsiasi testo</label>
      <textarea name="transcript" placeholder="Incolla qui qualsiasi testo:
• Trascrizione video YouTube
• Appunti o scaletta
• Bozza articolo
• Ricerca o materiale grezzo
• Email o post social da sviluppare
..." required></textarea>
    </div>
    <?php if(!empty($apiKey)): ?>
    <button class="btn btn-accent" name="generate" value="1">✨ Genera Articolo con Claude</button>
    <?php else: ?>
    <div class="alert alert-err">Configura prima la Claude API key.</div>
    <?php endif; ?>
  </form>
</div>

<?php else: ?>
<!-- RESULT -->
<?php
  $cat         = $category_saved ?? 'privati';
  $seoTitleLen = mb_strlen($result['seo_title'] ?? '');
  $seoDescLen  = mb_strlen($result['seo_desc'] ?? '');
  $usedModel   = $model_used ?? CLAUDE_MODEL_DEFAULT;
  $usedLabel   = CLAUDE_MODELS[$usedModel]['label'] ?? $usedModel;
?>
<div class="card">
  <h2>✅ Articolo Generato <span class="badge <?= $cat==='aziende'?'badge-az':'badge-priv' ?>"><?= $cat ?></span> <span style="font-size:.72rem;font-weight:400;color:var(--muted);background:#f0f4ff;border-radius:12px;padding:2px 10px;border:1px solid #c7d4f0"><?= h($usedLabel) ?></span></h2>

  <div class="field">
    <label>Titolo</label>
    <p style="font-size:1.1rem;font-weight:700"><?= h($result['title'] ?? '') ?></p>
  </div>

  <div class="field">
    <label>Slug URL</label>
    <p style="font-family:monospace">/<?= $cat==='aziende'?'aziende/':''; ?>blog/<?= h($result['slug'] ?? '') ?></p>
  </div>

  <div class="field">
    <label>Tag</label>
    <div class="tags">
      <?php foreach(($result['tags']??[]) as $t): ?>
      <span class="tag <?= $cat==='aziende'?'':'tag-acc' ?>"><?= h($t) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="field">
    <label>Descrizione anteprima</label>
    <p><?= h($result['description'] ?? '') ?></p>
  </div>

  <hr class="sep">
  <h2 style="font-size:.9rem;margin-bottom:14px">🔍 SEO / AEO</h2>

  <div class="meta-grid">
    <div class="meta-item">
      <label>Titolo Google</label>
      <p><?= h($result['seo_title'] ?? '') ?></p>
      <div class="<?= $seoTitleLen<=60?'char-ok':'char-warn' ?>"><?= $seoTitleLen ?>/60 caratteri <?= $seoTitleLen>60?'⚠ TROPPO LUNGO':'' ?></div>
    </div>
    <div class="meta-item">
      <label>Geo</label>
      <p><?= h($result['geo_location'] ?? '') ?></p>
    </div>
    <div class="meta-item" style="grid-column:1/-1">
      <label>Meta description Google</label>
      <p><?= h($result['seo_desc'] ?? '') ?></p>
      <div class="<?= $seoDescLen<=155?'char-ok':'char-warn' ?>"><?= $seoDescLen ?>/155 caratteri <?= $seoDescLen>155?'⚠ TROPPO LUNGO':'' ?></div>
    </div>
    <div class="meta-item" style="grid-column:1/-1">
      <label>AEO — Risposta per AI (Google Overview, ChatGPT)</label>
      <p><?= h($result['aeo_answer'] ?? '') ?></p>
    </div>
  </div>

  <hr class="sep">
  <h2 style="font-size:.9rem;margin-bottom:14px">❓ FAQ</h2>
  <?php foreach(($result['faq']??[]) as $faq): ?>
  <div class="faq-item">
    <strong>Q: <?= h($faq['question'] ?? '') ?></strong>
    <p><?= h($faq['answer'] ?? '') ?></p>
  </div>
  <?php endforeach; ?>

  <hr class="sep">
  <h2 style="font-size:.9rem;margin-bottom:14px">🎨 Prompt Immagini — pronto per Gemini / Midjourney / DALL-E</h2>
  <p style="font-size:.78rem;color:var(--muted);margin-bottom:12px">3 varianti di stile. Clicca <strong>Copia</strong> e incolla direttamente nel generatore. Nessuna immagine conterrà testo.</p>
  <?php
  $imgLabels = ['📷 Fotografico / Realistico', '🎨 Illustrativo / Artistico', '◻️ Minimalista / Simbolico'];
  foreach(($result['image_prompts']??[]) as $i => $prompt): ?>
  <div class="img-prompt-box" id="ipbox<?= $i ?>">
    <div class="img-prompt-label"><?= $imgLabels[$i] ?? ('Prompt ' . ($i+1)) ?></div>
    <div class="img-prompt-text" id="ip<?= $i ?>"><?= h($prompt) ?></div>
    <button class="btn btn-sm img-copy-btn" onclick="copyPrompt(<?= $i ?>)" id="copybtn<?= $i ?>">📋 Copia</button>
  </div>
  <?php endforeach; ?>

  <hr class="sep">
  <h2 style="font-size:.9rem;margin-bottom:14px">📄 Contenuto Articolo</h2>
  <div class="content-preview">
    <?= nl2br(h($result['content'] ?? '')) ?>
  </div>

  <hr class="sep">

  <!-- SAVE FORM -->
  <form method="POST">
    <input type="hidden" name="article_json" value="<?= h(json_encode($result, JSON_UNESCAPED_UNICODE)) ?>">
    <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:14px 16px;margin-bottom:16px">
      <label style="font-size:.82rem;font-weight:700;color:#7c5c00;display:block;margin-bottom:8px">📂 Dove vuoi salvarlo? Verifica o correggi la categoria:</label>
      <div class="radios">
        <label><input type="radio" name="save_category" value="privati" <?= $cat==='privati'?'checked':'' ?>> 🌸 Privati</label>
        <label><input type="radio" name="save_category" value="aziende" <?= $cat==='aziende'?'checked':'' ?>> 💼 Aziende</label>
      </div>
    </div>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <button class="btn btn-green" name="save_article" value="1">💾 Salva come Bozza in Grav</button>
      <a href="/ai-editor.php" class="btn btn-primary" style="background:#6b7280">↩ Genera Nuovo</a>
    </div>
    <p style="font-size:.77rem;color:var(--muted);margin-top:8px">L'articolo viene salvato come <strong>bozza</strong> (non pubblicato). Potrai modificarlo e pubblicarlo da Grav Admin.</p>
  </form>
</div>
<?php endif; ?>

<?php endif; // authed ?>

</div><!-- /wrap -->
</body>
</html>
