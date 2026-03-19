<?php
/**
 * ai-batch-meta.php — aggiorna geo_content e aeo_answer su tutti gli articoli
 * Approccio AJAX: JS elabora un articolo alla volta, nessun timeout/buffering.
 */
session_start();

define('ADMIN_PASS',  'ValeAdmin2026');
define('CONFIG_FILE', __DIR__ . '/ai-editor.config.php');
define('PAGES_DIR',   __DIR__ . '/user/pages');
define('CACHE_DIR',   __DIR__ . '/cache');
define('KB_DIR',      __DIR__ . '/knowledge-base/');
define('CLAUDE_URL',  'https://api.anthropic.com/v1/messages');
define('CLAUDE_VER',  '2023-06-01');

/* ── AUTH ── */
if (isset($_POST['login'])) {
    if ($_POST['pass'] === ADMIN_PASS) { $_SESSION['batch_auth'] = true; }
    else { $_SESSION['batch_auth'] = false; }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: /ai-batch-meta.php'); exit; }
$authed = !empty($_SESSION['batch_auth']);

/* ── API KEY ── */
$apiKey = '';
if (file_exists(CONFIG_FILE)) { $cfg = include CONFIG_FILE; $apiKey = $cfg['api_key'] ?? ''; }

/* ── KNOWLEDGE BASE ── */
function loadKB(): string {
    if (!is_dir(KB_DIR)) return '';
    $out = '';
    foreach (glob(KB_DIR . '*.md') as $f) { $out .= file_get_contents($f) . "\n\n"; }
    return trim($out);
}

/* ── PARSE FRONTMATTER ── */
function parseFM(string $content): array {
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)/s', $content, $m)) {
        return ['fm' => '', 'body' => $content];
    }
    return ['fm' => $m[1], 'body' => $m[2]];
}

function fmGet(string $fm, string $key): string {
    if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+)$/m', $fm, $m)) {
        return trim($m[1], " \"'\r");
    }
    return '';
}

function fmHas(string $fm, string $key): bool {
    return (bool) preg_match('/^' . preg_quote($key, '/') . ':\s*/m', $fm);
}

function fmSet(string $fm, string $key, string $value): string {
    $escaped = str_replace('"', '\\"', $value);
    $line    = $key . ': "' . $escaped . '"';
    if (fmHas($fm, $key)) {
        return preg_replace('/^' . preg_quote($key, '/') . ':\s*.*$/m', $line, $fm);
    }
    return rtrim($fm) . "\n" . $line;
}

/* ── SCAN ARTICLES ── */
function scanArticles(): array {
    $articles = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PAGES_DIR));
    foreach ($it as $file) {
        if ($file->getFilename() !== 'item.md') continue;
        $path    = $file->getPathname();
        $content = file_get_contents($path);
        $p       = parseFM($content);
        $title   = fmGet($p['fm'], 'title');
        if (!$title) continue;
        $articles[] = [
            'path'    => $path,
            'title'   => $title,
            'has_geo' => fmHas($p['fm'], 'geo_content') && fmGet($p['fm'], 'geo_content') !== '',
            'has_aeo' => fmHas($p['fm'], 'aeo_answer')  && fmGet($p['fm'], 'aeo_answer')  !== '',
            'section' => strpos($path, '05.aziende') !== false ? 'Aziende' : 'Privati',
        ];
    }
    usort($articles, fn($a,$b) => strcmp($a['title'], $b['title']));
    return $articles;
}

/* ════════════════════════════════════════════════════════
   API ENDPOINT — chiamato da JS per ogni singolo articolo
   ════════════════════════════════════════════════════════ */
if (isset($_POST['ajax_process']) && $authed) {
    header('Content-Type: application/json; charset=utf-8');

    $path  = $_POST['path']  ?? '';
    $model = $_POST['model'] ?? 'claude-haiku-4-5';
    $force = !empty($_POST['force']);

    // Sicurezza: il path deve essere dentro PAGES_DIR
    $realPath = realpath($path);
    $realPages = realpath(PAGES_DIR);
    if (!$realPath || !$realPages || strpos($realPath, $realPages) !== 0 || basename($realPath) !== 'item.md') {
        echo json_encode(['ok' => false, 'error' => 'Path non valido']);
        exit;
    }

    $content = file_get_contents($realPath);
    $p       = parseFM($content);
    $fm      = $p['fm'];
    $body    = $p['body'];

    $needG = $force || !(fmHas($fm, 'geo_content') && fmGet($fm, 'geo_content') !== '');
    $needA = $force || !(fmHas($fm, 'aeo_answer')  && fmGet($fm, 'aeo_answer')  !== '');

    if (!$needG && !$needA) {
        echo json_encode(['ok' => true, 'skipped' => true, 'msg' => 'Già completo']);
        exit;
    }

    // Prompt
    $systemPrompt = "Sei il ghostwriter ufficiale di Valentina Russo, analista certificata BG5® e Human Design, in Italia.\n"
        . "Ricevi il testo di un articolo blog e generi DUE campi mancanti.\n\n"
        . "Restituisci SOLO un oggetto JSON valido, niente altro:\n"
        . "{\n"
        . "  \"geo_content\": \"3-5 affermazioni autorevoli e precise sul tema (italiano). Definizioni esatte BG5/HD, fatti verificabili, posizionamento unico di Valentina. Tono enciclopedico, citabile da ChatGPT/Perplexity. Stringa unica, NON array. Max 250 parole.\",\n"
        . "  \"aeo_answer\": \"Risposta diretta alla domanda principale in 40-60 parole. Inizia con il soggetto, niente preamboli. Per Google Featured Snippet e AI Overview.\"\n"
        . "}\n\n"
        . "REGOLE: italiano sempre. geo_content = stringa singola. aeo_answer = 40-60 parole esatte.\n"
        . "Termini BG5/HD in italiano: Sacrale, Plesso Solare, Radice, Cuore/Ego, Costruttore, Proiettore, Manifestatore, Valutatore.";

    $kb = loadKB();
    if ($kb) $systemPrompt .= "\n\n" . $kb;

    $title       = fmGet($fm, 'title');
    $bodyClean   = mb_substr(trim(strip_tags(preg_replace('/#{1,6}\s/', '', $body))), 0, 2000);
    $userMsg     = "Titolo: {$title}\n\nTesto:\n{$bodyClean}";
    if (!$needG) $userMsg .= "\n\n[Solo aeo_answer — geo_content già presente, mettilo vuoto]";
    if (!$needA) $userMsg .= "\n\n[Solo geo_content — aeo_answer già presente, mettilo vuoto]";

    // Chiamata Claude
    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => 600,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ]);

    $ch = curl_init(CLAUDE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . CLAUDE_VER,
        ],
    ]);
    $resp    = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr || !$resp) {
        echo json_encode(['ok' => false, 'error' => 'cURL: ' . $curlErr]);
        exit;
    }

    $data = json_decode($resp, true);
    $text = trim($data['content'][0]['text'] ?? '');
    if (!$text) {
        echo json_encode(['ok' => false, 'error' => 'Risposta vuota. ' . ($data['error']['message'] ?? '')]);
        exit;
    }

    $text   = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text   = preg_replace('/\s*```$/', '', $text);
    $parsed = json_decode($text, true);
    if (!$parsed) {
        echo json_encode(['ok' => false, 'error' => 'JSON non valido: ' . substr($text, 0, 150)]);
        exit;
    }

    $geoNew = is_array($parsed['geo_content'] ?? null)
        ? implode(' ', $parsed['geo_content'])
        : trim($parsed['geo_content'] ?? '');
    $aeoNew = trim($parsed['aeo_answer'] ?? '');

    if ($needG && $geoNew) $fm = fmSet($fm, 'geo_content', $geoNew);
    if ($needA && $aeoNew) $fm = fmSet($fm, 'aeo_answer',  $aeoNew);

    file_put_contents($realPath, "---\n{$fm}\n---\n{$body}");

    // Svuota cache Grav
    if (is_dir(CACHE_DIR)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $f) { if ($f->isFile()) @unlink($f->getPathname()); }
    }

    echo json_encode([
        'ok'  => true,
        'geo' => mb_substr($geoNew, 0, 100),
        'aeo' => mb_substr($aeoNew, 0, 100),
    ]);
    exit;
}

/* ════════════════════════════════════════════════════════
   ENDPOINT: lista articoli (JSON)
   ════════════════════════════════════════════════════════ */
if (isset($_GET['ajax_list']) && $authed) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(scanArticles());
    exit;
}

/* ════════════════════════════════════════════════════════
   HTML
   ════════════════════════════════════════════════════════ */
?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Batch Meta AI</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:2rem}
h1{font-size:1.4rem;font-weight:700;margin-bottom:1.5rem}
.card{background:#1e293b;border-radius:10px;padding:1.5rem;margin-bottom:1rem}
.btn{display:inline-block;padding:.55rem 1.3rem;border-radius:6px;border:none;cursor:pointer;font-weight:700;font-size:.85rem;transition:.15s}
.btn-blue{background:#3b82f6;color:#fff}.btn-blue:hover{background:#2563eb}
.btn-green{background:#10b981;color:#fff}.btn-green:hover{background:#059669}
.btn-red{background:#ef4444;color:#fff}
.btn:disabled{opacity:.45;cursor:not-allowed}
input[type=password],select,input[type=number]{background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:.45rem .8rem;border-radius:6px;font-size:.88rem}
label{color:#94a3b8;font-size:.85rem}
.stats{display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1.5rem}
.stat-n{font-size:2rem;font-weight:700;color:#f8fafc;line-height:1}
.stat-l{color:#64748b;font-size:.75rem;margin-top:.2rem}
table{width:100%;border-collapse:collapse;font-size:.8rem}
th{background:#0f172a;color:#64748b;font-weight:600;padding:.45rem .7rem;text-align:left;border-bottom:1px solid #1e293b}
td{padding:.4rem .7rem;border-bottom:1px solid #0f172a;vertical-align:middle}
.b{display:inline-block;padding:.12rem .45rem;border-radius:3px;font-size:.68rem;font-weight:700;text-transform:uppercase}
.ok{background:#14532d;color:#86efac}
.miss{background:#7f1d1d;color:#fca5a5}
.az{background:#1e3a5f;color:#93c5fd}
.pr{background:#3b2156;color:#d8b4fe}
.prog{background:#0f172a;border-radius:6px;height:8px;margin:.8rem 0;overflow:hidden}
.prog-bar{background:#3b82f6;height:8px;border-radius:6px;width:0;transition:width .4s}
#log{background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:1rem;font-size:.78rem;line-height:1.7;max-height:380px;overflow-y:auto;font-family:monospace;margin-top:1rem;white-space:pre-wrap;word-break:break-word}
.row-done td{opacity:.5}
.row-running td{background:#1a2744}
</style>
</head>
<body>

<?php if (!$authed): ?>
<div class="card" style="max-width:340px;margin:5rem auto">
  <h1 style="margin-bottom:1.2rem">🤖 Batch Meta AI</h1>
  <?php if (isset($_POST['login']) && $_POST['pass'] !== ADMIN_PASS): ?>
  <p style="color:#fca5a5;margin-bottom:.8rem;font-size:.85rem">Password errata.</p>
  <?php endif ?>
  <form method="POST">
    <input type="password" name="pass" placeholder="Password admin" style="width:100%;margin-bottom:.8rem" autofocus>
    <button type="submit" name="login" class="btn btn-blue" style="width:100%">Accedi</button>
  </form>
</div>
<?php exit; endif; ?>

<h1>🤖 Batch Meta AI <a href="?logout" style="font-size:.75rem;color:#475569;font-weight:400;margin-left:1rem">esci</a></h1>

<?php if (!$apiKey): ?>
<div class="card" style="border:1px solid #7f1d1d">
  <p style="color:#fca5a5">⚠️ API key mancante — configurala prima in <a href="/ai-editor.php" style="color:#60a5fa">ai-editor.php</a></p>
</div>
<?php endif ?>

<div class="card">
  <div class="stats" id="stats">
    <div><div class="stat-n" id="s-tot">—</div><div class="stat-l">Totali</div></div>
    <div><div class="stat-n" id="s-geo" style="color:#fca5a5">—</div><div class="stat-l">Senza geo_content</div></div>
    <div><div class="stat-n" id="s-aeo" style="color:#fcd34d">—</div><div class="stat-l">Senza aeo_answer</div></div>
    <div><div class="stat-n" id="s-ok" style="color:#86efac">—</div><div class="stat-l">Completi</div></div>
  </div>

  <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
    <select id="sel-model">
      <option value="claude-haiku-4-5">Haiku 4.5 — veloce/economico</option>
      <option value="claude-sonnet-4-6">Sonnet 4.6 — qualità</option>
      <option value="claude-opus-4-6">Opus 4.6 — massima qualità</option>
    </select>
    <label><input type="checkbox" id="chk-force" style="margin-right:.3rem">Forza (sovrascrivi esistenti)</label>
    <label><input type="checkbox" id="chk-dry" style="margin-right:.3rem">Dry run</label>
    <button id="btn-start" class="btn btn-green" <?= !$apiKey?'disabled':'' ?>>▶ Avvia Batch</button>
    <button id="btn-stop" class="btn btn-red" style="display:none">■ Ferma</button>
  </div>

  <div class="prog"><div class="prog-bar" id="prog-bar"></div></div>
  <div id="prog-txt" style="font-size:.78rem;color:#64748b">Caricamento articoli...</div>
</div>

<div class="card" style="padding:0;overflow:hidden">
<table>
<thead><tr><th>Titolo</th><th>Sezione</th><th>geo_content</th><th>aeo_answer</th><th>Stato</th></tr></thead>
<tbody id="tbl"></tbody>
</table>
</div>

<div id="log-wrap" style="display:none" class="card">
  <div style="font-size:.78rem;font-weight:700;color:#64748b;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em">Log</div>
  <div id="log"></div>
</div>

<script>
var articles = [];
var running  = false;
var stopped  = false;

function log(msg, color){
  var el = document.getElementById('log');
  document.getElementById('log-wrap').style.display = '';
  var line = document.createElement('span');
  if(color) line.style.color = color;
  line.textContent = msg + '\n';
  el.appendChild(line);
  el.scrollTop = el.scrollHeight;
}

function badge(has){
  return has
    ? '<span class="b ok">✓ sì</span>'
    : '<span class="b miss">✗ no</span>';
}

function renderTable(){
  var tb = document.getElementById('tbl');
  tb.innerHTML = '';
  articles.forEach(function(a, i){
    var tr = document.createElement('tr');
    tr.id  = 'row-' + i;
    tr.innerHTML = '<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+a.path+'">'+a.title+'</td>'
      + '<td><span class="b '+(a.section==='Aziende'?'az':'pr')+'">'+a.section+'</span></td>'
      + '<td id="geo-'+i+'">'+badge(a.has_geo)+'</td>'
      + '<td id="aeo-'+i+'">'+badge(a.has_aeo)+'</td>'
      + '<td id="st-'+i+'"><span class="b" style="background:#1e293b;color:#64748b">—</span></td>';
    tb.appendChild(tr);
  });
}

function updateStats(){
  var misGeo = articles.filter(function(a){return !a.has_geo;}).length;
  var misAeo = articles.filter(function(a){return !a.has_aeo;}).length;
  var ok     = articles.filter(function(a){return a.has_geo && a.has_aeo;}).length;
  document.getElementById('s-tot').textContent = articles.length;
  document.getElementById('s-geo').textContent = misGeo;
  document.getElementById('s-aeo').textContent = misAeo;
  document.getElementById('s-ok').textContent  = ok;
}

// Carica lista articoli
fetch('?ajax_list=1')
  .then(function(r){ return r.json(); })
  .then(function(data){
    articles = data;
    updateStats();
    renderTable();
    document.getElementById('prog-txt').textContent = articles.length + ' articoli trovati. Premi Avvia per iniziare.';
  });

document.getElementById('btn-stop').addEventListener('click', function(){
  stopped = true;
  this.disabled = true;
  log('\n■ Fermato dall\'utente.', '#fcd34d');
});

document.getElementById('btn-start').addEventListener('click', function(){
  if (running) return;
  running = true; stopped = false;
  document.getElementById('btn-start').style.display = 'none';
  document.getElementById('btn-stop').style.display  = '';
  runBatch();
});

async function runBatch(){
  var model  = document.getElementById('sel-model').value;
  var force  = document.getElementById('chk-force').checked;
  var dry    = document.getElementById('chk-dry').checked;
  var done   = 0; var errors = 0; var skipped = 0;
  var toProcess = articles.filter(function(a){
    return force || !a.has_geo || !a.has_aeo;
  });

  log('Avvio batch — ' + toProcess.length + ' articoli da elaborare | modello: ' + model + (dry?' | DRY RUN':''), '#60a5fa');

  for(var i = 0; i < toProcess.length; i++){
    if(stopped) break;
    var a    = toProcess[i];
    var idx  = articles.indexOf(a);
    var pct  = Math.round((i+1) / toProcess.length * 100);

    document.getElementById('prog-bar').style.width = pct + '%';
    document.getElementById('prog-txt').textContent = (i+1) + ' / ' + toProcess.length + ' (' + pct + '%) — ' + a.title;
    document.getElementById('row-' + idx).className = 'row-running';
    document.getElementById('st-' + idx).innerHTML  = '<span class="b" style="background:#1e3a5f;color:#93c5fd">⏳ elaborazione</span>';
    log('\n[' + (i+1) + '/' + toProcess.length + '] ' + a.title);

    if(dry){
      log('  → [DRY RUN] saltato', '#fcd34d');
      document.getElementById('st-'+idx).innerHTML = '<span class="b" style="background:#78350f;color:#fcd34d">dry run</span>';
      skipped++; continue;
    }

    try{
      var fd = new FormData();
      fd.append('ajax_process', '1');
      fd.append('path',  a.path);
      fd.append('model', model);
      if(force) fd.append('force','1');

      log('  ⏳ Chiamata API...');
      var resp = await fetch('', { method:'POST', body: fd });
      var data = await resp.json();

      if(data.skipped){
        log('  → già completo, saltato', '#64748b');
        document.getElementById('st-'+idx).innerHTML = '<span class="b ok">ok</span>';
        document.getElementById('row-'+idx).className = 'row-done';
        skipped++; continue;
      }

      if(!data.ok){
        log('  → ❌ Errore: ' + data.error, '#fca5a5');
        document.getElementById('st-'+idx).innerHTML = '<span class="b miss">errore</span>';
        errors++; continue;
      }

      if(data.geo){ log('  geo → ' + data.geo + '…', '#86efac'); }
      if(data.aeo){ log('  aeo → ' + data.aeo + '…', '#86efac'); }
      log('  → ✅ Salvato', '#86efac');

      // Aggiorna icone nella tabella
      if(data.geo || force){ document.getElementById('geo-'+idx).innerHTML = badge(true); a.has_geo = true; }
      if(data.aeo || force){ document.getElementById('aeo-'+idx).innerHTML = badge(true); a.has_aeo = true; }
      document.getElementById('st-'+idx).innerHTML  = '<span class="b ok">✅ fatto</span>';
      document.getElementById('row-'+idx).className = 'row-done';
      updateStats();
      done++;

    } catch(e){
      log('  → ❌ Errore fetch: ' + e, '#fca5a5');
      document.getElementById('st-'+idx).innerHTML = '<span class="b miss">errore</span>';
      errors++;
    }

    // Piccola pausa tra chiamate
    await new Promise(function(r){ setTimeout(r, 600); });
  }

  document.getElementById('prog-bar').style.width = '100%';
  document.getElementById('prog-txt').textContent = 'Completato!';
  document.getElementById('btn-stop').style.display  = 'none';
  document.getElementById('btn-start').style.display = '';
  document.getElementById('btn-start').textContent   = '▶ Avvia di nuovo';
  running = false;
  log('\n═══════════════════════════════════════', '#475569');
  log('✅ Completati: ' + done + '  |  ❌ Errori: ' + errors + '  |  ⏭ Saltati: ' + skipped, '#f8fafc');
}
</script>
</body>
</html>
