<?php
// Disabilita output buffering per streaming in tempo reale
while (ob_get_level()) { ob_end_clean(); }
ob_implicit_flush(true);
/**
 * ai-batch-meta.php — aggiorna geo_content e aeo_answer su tutti gli articoli
 * che non li hanno ancora, usando Claude API.
 *
 * Uso: https://valentinarussobg5.com/ai-batch-meta.php
 * Parametri GET:
 *   ?dry=1        → mostra cosa verrebbe aggiornato senza toccare i file
 *   ?force=1      → sovrascrive anche i campi già presenti
 *   ?limit=N      → processa al massimo N articoli per sessione
 *   ?model=...    → modello Claude (default: haiku per economicità)
 */

session_start();
set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');

/* ── CONFIG ── */
define('ADMIN_PASS',  'ValeAdmin2026');
define('CONFIG_FILE', __DIR__ . '/ai-editor.config.php');
define('PAGES_DIR',   __DIR__ . '/user/pages');
define('CACHE_DIR',   __DIR__ . '/cache');
define('KB_DIR',      __DIR__ . '/knowledge-base/');
define('CLAUDE_URL',  'https://api.anthropic.com/v1/messages');
define('CLAUDE_VER',  '2023-06-01');
define('DELAY_MS',    800);   // ms tra una chiamata API e l'altra

/* ── AUTH ── */
if (isset($_POST['login'])) {
    if ($_POST['pass'] === ADMIN_PASS) { $_SESSION['batch_auth'] = true; }
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

/* ── HELPERS ── */
function parseFrontmatter(string $content): array {
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)/s', $content, $m)) {
        return ['fm_raw' => '', 'body' => $content, 'fields' => []];
    }
    $fmRaw = $m[1];
    $body  = $m[2];
    // Parsing semplice chiave: valore (non gestisce YAML complesso — solo scalari e liste)
    $fields = [];
    preg_match_all('/^([a-zA-Z_]+):\s*(.*)$/m', $fmRaw, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $fields[trim($match[1])] = trim($match[2], " \"'\r");
    }
    return ['fm_raw' => $fmRaw, 'body' => $body, 'fields' => $fields];
}

function fmGetField(string $fm, string $key): string {
    // Restituisce valore scalare del campo, vuoto se assente o lista
    if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+)$/m', $fm, $m)) {
        return trim($m[1], " \"'\r");
    }
    return '';
}

function fmHasField(string $fm, string $key): bool {
    return (bool) preg_match('/^' . preg_quote($key, '/') . ':\s*/m', $fm);
}

function fmSetField(string $fm, string $key, string $value): string {
    $escaped = str_replace('"', '\\"', $value);
    $newLine = $key . ': "' . $escaped . '"';
    if (fmHasField($fm, $key)) {
        // Sostituisce riga esistente (scalare su una riga)
        return preg_replace('/^' . preg_quote($key, '/') . ':\s*.*$/m', $newLine, $fm);
    }
    // Inserisce prima della chiusura del frontmatter (in fondo)
    return rtrim($fm) . "\n" . $newLine;
}

function claudeCall(string $apiKey, string $model, string $system, string $user): ?array {
    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => 600,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ]);
    $ch = curl_init(CLAUDE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . CURLOPT_VER,
        ],
    ]);
    // Fix: usa la costante corretta
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: ' . CLAUDE_VER,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || !$resp) return null;
    $data = json_decode($resp, true);
    return $data;
}

/* ── SCAN ARTICLES ── */
function scanArticles(): array {
    $articles = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PAGES_DIR));
    foreach ($it as $file) {
        if ($file->getFilename() !== 'item.md') continue;
        $path    = $file->getPathname();
        $content = file_get_contents($path);
        $parsed  = parseFrontmatter($content);
        $fm      = $parsed['fm_raw'];
        // Solo articoli published o scheduled
        $status  = fmGetField($fm, 'published');
        // published: true/false o scheduled con data
        // Includi anche le bozze (published: false) perché vogliamo preparare i metadati
        $title   = fmGetField($fm, 'title');
        if (!$title) continue; // salta pagine senza titolo (non articoli)
        $articles[] = [
            'path'        => $path,
            'title'       => $title,
            'published'   => $status,
            'has_geo'     => fmHasField($fm, 'geo_content') && fmGetField($fm, 'geo_content') !== '',
            'has_aeo'     => fmHasField($fm, 'aeo_answer')  && fmGetField($fm, 'aeo_answer')  !== '',
            'fm_raw'      => $fm,
            'body'        => $parsed['body'],
            'content_raw' => $content,
        ];
    }
    usort($articles, fn($a,$b) => strcmp($a['title'], $b['title']));
    return $articles;
}

/* ══════════════════════════════════════════════════════════
   HTML OUTPUT
   ══════════════════════════════════════════════════════════ */
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Batch Meta — AI</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:2rem}
h1{font-size:1.4rem;font-weight:700;margin-bottom:1.5rem;color:#f8fafc}
.card{background:#1e293b;border-radius:10px;padding:1.5rem;margin-bottom:1rem}
.btn{display:inline-block;padding:.6rem 1.4rem;border-radius:6px;border:none;cursor:pointer;font-weight:700;font-size:.85rem}
.btn-primary{background:#3b82f6;color:#fff}.btn-primary:hover{background:#2563eb}
.btn-danger{background:#ef4444;color:#fff}
.btn-sm{padding:.35rem .9rem;font-size:.78rem}
input[type=password],input[type=number],select{background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:.5rem .8rem;border-radius:6px;font-size:.9rem}
table{width:100%;border-collapse:collapse;font-size:.82rem}
th{background:#0f172a;color:#94a3b8;font-weight:600;text-align:left;padding:.5rem .7rem;border-bottom:1px solid #334155}
td{padding:.45rem .7rem;border-bottom:1px solid #1e293b;vertical-align:top}
tr:hover td{background:#1e293b}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:4px;font-size:.7rem;font-weight:700;text-transform:uppercase}
.ok{background:#14532d;color:#86efac}
.miss{background:#7f1d1d;color:#fca5a5}
.skip{background:#1e3a5f;color:#93c5fd}
.warn{background:#78350f;color:#fcd34d}
pre.log{background:#0f172a;border:1px solid #334155;border-radius:6px;padding:1rem;font-size:.78rem;line-height:1.6;max-height:500px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;margin-top:1rem}
.progress{background:#0f172a;border-radius:6px;height:10px;margin:1rem 0}
.progress-bar{background:#3b82f6;height:10px;border-radius:6px;transition:width .3s}
.sep{color:#475569}
a{color:#60a5fa}
</style>
</head>
<body>

<?php if (!$authed): ?>
<div class="card" style="max-width:360px;margin:4rem auto">
  <h1>🤖 Batch Meta AI</h1>
  <?php if (isset($_POST['login'])): ?><p style="color:#fca5a5;margin-bottom:1rem">Password errata.</p><?php endif ?>
  <form method="POST">
    <input type="password" name="pass" placeholder="Password admin" style="width:100%;margin-bottom:.8rem" autofocus>
    <button type="submit" name="login" class="btn btn-primary" style="width:100%">Accedi</button>
  </form>
</div>
<?php exit; endif; ?>

<?php
/* ── SCAN ── */
$articles  = scanArticles();
$dry       = isset($_GET['dry'])   && $_GET['dry']   == '1';
$force     = isset($_GET['force']) && $_GET['force']  == '1';
$limit     = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
$model     = $_GET['model'] ?? 'claude-haiku-4-5';
$doRun     = isset($_GET['run'])   && $_GET['run']    == '1';
$needUpdate = array_filter($articles, fn($a) => $force || !$a['has_geo'] || !$a['has_aeo']);
$needUpdate = array_values($needUpdate);
$total      = count($needUpdate);
$kb         = loadKB();

/* ── INFO PANEL ── */
$allCount  = count($articles);
$misGeo    = count(array_filter($articles, fn($a) => !$a['has_geo']));
$misAeo    = count(array_filter($articles, fn($a) => !$a['has_aeo']));
?>

<h1>🤖 Batch Meta AI <a href="?logout" style="font-size:.75rem;color:#64748b;font-weight:400;margin-left:1rem">Esci</a></h1>

<div class="card">
  <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <div><span style="font-size:1.8rem;font-weight:700;color:#f8fafc"><?= $allCount ?></span><br><span style="color:#94a3b8;font-size:.8rem">Articoli totali</span></div>
    <div><span style="font-size:1.8rem;font-weight:700;color:#fca5a5"><?= $misGeo ?></span><br><span style="color:#94a3b8;font-size:.8rem">Senza geo_content</span></div>
    <div><span style="font-size:1.8rem;font-weight:700;color:#fcd34d"><?= $misAeo ?></span><br><span style="color:#94a3b8;font-size:.8rem">Senza aeo_answer</span></div>
    <div><span style="font-size:1.8rem;font-weight:700;color:#86efac"><?= $allCount - $misGeo - $misAeo + min($misGeo, $misAeo) ?></span><br><span style="color:#94a3b8;font-size:.8rem">Completi</span></div>
  </div>

  <form method="GET" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
    <select name="model">
      <?php foreach(['claude-haiku-4-5'=>'Haiku 4.5 (economico)','claude-sonnet-4-6'=>'Sonnet 4.6 (qualità)','claude-opus-4-6'=>'Opus 4.6 (max qualità)'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= $model===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach ?>
    </select>
    <label style="color:#94a3b8;font-size:.85rem">Max articoli:
      <input type="number" name="limit" value="<?= $limit ?>" min="1" max="<?= $total ?>" style="width:60px;margin-left:.3rem">
    </label>
    <label style="color:#94a3b8;font-size:.85rem">
      <input type="checkbox" name="force" value="1" <?= $force?'checked':'' ?>> Forza (sovrascrivi esistenti)
    </label>
    <label style="color:#94a3b8;font-size:.85rem">
      <input type="checkbox" name="dry" value="1" <?= $dry?'checked':'' ?>> Dry run (anteprima)
    </label>
    <input type="hidden" name="run" value="1">
    <?php if (!$apiKey): ?>
      <span style="color:#fca5a5;font-size:.82rem">⚠️ API key mancante — configurala in <a href="/ai-editor.php">ai-editor.php</a></span>
    <?php else: ?>
      <button type="submit" class="btn btn-primary">▶ Avvia Batch</button>
    <?php endif ?>
  </form>
</div>

<!-- Tabella riepilogo articoli -->
<div class="card">
<table>
<thead><tr>
  <th>Titolo</th>
  <th>Sezione</th>
  <th>geo_content</th>
  <th>aeo_answer</th>
  <th>Azione</th>
</tr></thead>
<tbody>
<?php foreach($articles as $a):
  $isAz  = strpos($a['path'], '05.aziende') !== false;
  $sect  = $isAz ? 'Aziende' : 'Privati';
  $needG = !$a['has_geo'];
  $needA = !$a['has_aeo'];
  $needs = $force || $needG || $needA;
?>
<tr>
  <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($a['path']) ?>"><?= htmlspecialchars($a['title']) ?></td>
  <td><span class="badge <?= $isAz?'skip':'warn' ?>"><?= $sect ?></span></td>
  <td><?= $a['has_geo'] ? '<span class="badge ok">✓ presente</span>' : '<span class="badge miss">✗ mancante</span>' ?></td>
  <td><?= $a['has_aeo'] ? '<span class="badge ok">✓ presente</span>' : '<span class="badge miss">✗ mancante</span>' ?></td>
  <td><?= $needs ? '<span class="badge miss">da aggiornare</span>' : '<span class="badge ok">ok</span>' ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>

<?php if (!$doRun) { exit; } ?>

<?php
/* ══════════════════════════════════════════════════════════
   ESECUZIONE BATCH
   ══════════════════════════════════════════════════════════ */
if (!$apiKey) { echo '<div class="card" style="color:#fca5a5">API key mancante.</div>'; exit; }

$toProcess = array_slice($needUpdate, 0, $limit);
$done = 0; $errors = 0;

echo '<div class="card">';
echo '<h2 style="margin-bottom:1rem;font-size:1rem">Elaborazione ' . count($toProcess) . ' articoli (' . ($dry ? 'DRY RUN' : 'LIVE') . ') — modello: ' . htmlspecialchars($model) . '</h2>';
echo '<div class="progress"><div class="progress-bar" id="pb" style="width:0%"></div></div>';
echo '<pre class="log" id="log">';
flush();

$systemPrompt = <<<PROMPT
Sei il ghostwriter ufficiale di Valentina Russo, analista certificata BG5® e Human Design, in Italia.
Ricevi il testo di un articolo blog e devi generare DUE campi mancanti.

Restituisci SOLO un oggetto JSON valido, niente altro:
{
  "geo_content": "3-5 affermazioni autorevoli e precise sul tema (italiano). Definizioni esatte BG5/HD, fatti verificabili, posizionamento unico di Valentina. Tono enciclopedico, citabile da ChatGPT/Perplexity. Una stringa unica, NON un array.",
  "aeo_answer": "Risposta diretta alla domanda principale dell'articolo in 40-60 parole. Inizia con il soggetto, niente preamboli. Pensata per Google Featured Snippet e AI Overview."
}

REGOLE:
- Lingua: italiano, sempre
- geo_content: stringa singola (non array JSON), max 250 parole
- aeo_answer: 40-60 parole esatte, frase concisa e completa
- Non inventare dati non presenti nel testo
- Termini BG5/HD: usa sempre la traduzione italiana (Sacrale, Plesso Solare, Radice, Cuore/Ego, Costruttore, Proiettore, Manifestatore, Riflettore/Valutatore)
PROMPT;

if ($kb) { $systemPrompt .= "\n\n" . $kb; }

$processed = 0;
foreach ($toProcess as $a) {
    $processed++;
    $pct = round($processed / count($toProcess) * 100);

    $needG = $force || !$a['has_geo'];
    $needA = $force || !$a['has_aeo'];

    $pbar = str_pad('', (int)($pct/2), '█') . str_pad('', 50-(int)($pct/2), '░');
    echo "\n[{$processed}/" . count($toProcess) . "] {$pbar} {$pct}%\n";
    echo htmlspecialchars($a['title']) . "\n";
    echo "  geo_content: " . ($needG ? '⏳ da generare' : '✓ già presente' . ($force?' (force)':'')) . "\n";
    echo "  aeo_answer:  " . ($needA ? '⏳ da generare' : '✓ già presente' . ($force?' (force)':'')) . "\n";
    ob_flush(); flush();

    if (!$needG && !$needA) {
        echo "  → Saltato (entrambi presenti)\n";
        ob_flush(); flush();
        continue;
    }

    if ($dry) {
        echo "  → [DRY RUN] Nessuna modifica\n";
        $done++;
        ob_flush(); flush();
        continue;
    }

    // Prepara testo articolo
    $bodyPreview = trim(strip_tags(preg_replace('/#{1,6}\s/', '', $a['body'])));
    $bodyPreview = mb_substr($bodyPreview, 0, 2000);
    $userMsg  = "Titolo: " . $a['title'] . "\n\n";
    $userMsg .= "Testo articolo:\n" . $bodyPreview;
    if (!$needG) $userMsg .= "\n\n[NOTA: geo_content esiste già — genera solo aeo_answer, lascia geo_content stringa vuota]";
    if (!$needA) $userMsg .= "\n\n[NOTA: aeo_answer esiste già — genera solo geo_content, lascia aeo_answer stringa vuota]";

    echo "  ⏳ Chiamata API Claude ({$model})...\n";
    ob_flush(); flush();

    $resp = claudeCall($apiKey, $model, $systemPrompt, $userMsg);

    if (!$resp || empty($resp['content'][0]['text'])) {
        echo "  → ❌ Errore API: " . htmlspecialchars(json_encode($resp['error'] ?? 'risposta vuota')) . "\n";
        $errors++;
        ob_flush(); flush();
        continue;
    }

    $text = trim($resp['content'][0]['text']);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $parsed = json_decode($text, true);

    if (!$parsed) {
        echo "  → ❌ JSON non valido: " . htmlspecialchars(substr($text, 0, 200)) . "\n";
        $errors++;
        ob_flush(); flush();
        continue;
    }

    $geoNew = is_array($parsed['geo_content'] ?? null)
        ? implode(' ', $parsed['geo_content'])
        : trim($parsed['geo_content'] ?? '');
    $aeoNew = trim($parsed['aeo_answer'] ?? '');

    echo "  geo → " . htmlspecialchars(mb_substr($geoNew, 0, 90)) . "…\n";
    echo "  aeo → " . htmlspecialchars(mb_substr($aeoNew, 0, 90)) . "…\n";

    $fm = $a['fm_raw'];
    if ($needG && $geoNew) { $fm = fmSetField($fm, 'geo_content', $geoNew); }
    if ($needA && $aeoNew) { $fm = fmSetField($fm, 'aeo_answer',  $aeoNew); }

    $newContent = "---\n" . $fm . "\n---\n" . $a['body'];
    file_put_contents($a['path'], $newContent);
    echo "  → ✅ Salvato\n";
    $done++;
    ob_flush(); flush();

    usleep(DELAY_MS * 1000);
}

/* ── SVUOTA CACHE GRAV ── */
if (!$dry && $done > 0 && is_dir(CACHE_DIR)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $f) { if ($f->isFile()) @unlink($f->getPathname()); }
    echo "\n🗑️  Cache Grav svuotata.\n";
}

echo "\n═══════════════════════════════\n";
echo "✅ Completati: {$done}  |  ❌ Errori: {$errors}  |  Totale: " . count($toProcess) . "\n";
if ($dry) echo "⚠️  DRY RUN — nessun file modificato.\n";
echo '</pre>';

echo '<div style="margin-top:1rem;display:flex;gap:.8rem">';
echo '<a href="/ai-batch-meta.php" class="btn btn-primary btn-sm">← Torna al pannello</a>';
if ($done > 0 && !$dry) {
    echo '<a href="/ai-batch-meta.php?run=1&model=' . urlencode($model) . '&limit=' . $limit . '" class="btn btn-primary btn-sm" style="background:#10b981">▶ Processa altri</a>';
}
echo '</div>';
echo '</div>';
?>

<script>
// Scroll automatico del log
var log = document.getElementById('log');
if(log){ var t = setInterval(function(){ log.scrollTop = log.scrollHeight; }, 300); }
</script>
</body>
</html>
