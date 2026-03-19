<?php
/**
 * ga4-stats.php — Dashboard statistiche GA4 + Search Console
 * valentinarussobg5.com
 * Version: 2026-03-19-v2
 */

// ── CONFIG ───────────────────────────────────────────────────────────────────
define('STATS_PASS',   'ValeAdmin2026');
define('GA4_PROPERTY', '527894280');
define('GA4_SA_FILE',  __DIR__ . '/ga4-service-account.json');
define('SC_SITE_URL',  'https://valentinarussobg5.com/'); // Search Console site URL

// ── AUTH ─────────────────────────────────────────────────────────────────────
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
    if (hash_equals(STATS_PASS, $_POST['pass'])) { $_SESSION['vb_stats_ok'] = true; }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}
if (isset($_POST['logout'])) {
    unset($_SESSION['vb_stats_ok']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}
$authed = !empty($_SESSION['vb_stats_ok']);

// ── LOGIN PAGE ───────────────────────────────────────────────────────────────
if (!$authed) { ?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">
<title>Statistiche — valentinarussobg5.com</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f172a;color:#f1f5f9;font-family:system-ui,sans-serif;
  display:flex;align-items:center;justify-content:center;min-height:100vh;}
.box{background:#1e293b;border-radius:12px;padding:2rem;width:320px;box-shadow:0 8px 32px rgba(0,0,0,.4);}
h1{font-size:1.2rem;margin-bottom:1.5rem;color:#7c3aed;}
input{width:100%;padding:.65rem .9rem;background:#0f172a;border:1px solid #334155;
  border-radius:6px;color:#f1f5f9;font-size:.95rem;margin-bottom:1rem;}
button{width:100%;padding:.7rem;background:#7c3aed;color:#fff;border:none;
  border-radius:6px;font-size:.95rem;font-weight:700;cursor:pointer;}
button:hover{background:#6d28d9;}
</style></head><body>
<div class="box">
  <h1>📊 Statistiche</h1>
  <form method="POST">
    <input type="password" name="pass" placeholder="Password" autofocus>
    <button type="submit">Accedi</button>
  </form>
</div>
</body></html>
<?php exit; }

// ── SETUP CHECK ───────────────────────────────────────────────────────────────
$setupNeeded = !file_exists(GA4_SA_FILE);

// ── SHARED: JWT + TOKEN ───────────────────────────────────────────────────────
function b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function getToken(string $scope): string {
    $cacheFile = sys_get_temp_dir() . '/vb_token_' . md5(GA4_SA_FILE . $scope) . '.json';
    if (file_exists($cacheFile)) {
        $c = json_decode(file_get_contents($cacheFile), true);
        if ($c && ($c['expires'] ?? 0) > time() + 60) return $c['token'];
    }
    $sa  = json_decode(file_get_contents(GA4_SA_FILE), true);
    $now = time();
    $hdr = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $pld = b64url(json_encode([
        'iss' => $sa['client_email'], 'scope' => $scope,
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now, 'exp' => $now + 3600,
    ]));
    $inp = $hdr . '.' . $pld;
    $key = openssl_pkey_get_private($sa['private_key']);
    openssl_sign($inp, $sig, $key, OPENSSL_ALGO_SHA256);
    $jwt = $inp . '.' . b64url($sig);
    $ch  = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp  = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $token = $resp['access_token'] ?? '';
    if ($token) file_put_contents($cacheFile, json_encode(['token' => $token, 'expires' => $now + 3500]));
    return $token;
}

// ── GA4 ───────────────────────────────────────────────────────────────────────
function ga4Report(string $token, array $body): array {
    $cacheKey  = md5(GA4_PROPERTY . json_encode($body));
    $cacheFile = sys_get_temp_dir() . '/vb_ga4_' . $cacheKey . '.json';
    if (!isset($_GET['refresh']) && file_exists($cacheFile) && filemtime($cacheFile) > time() - 3600) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . GA4_PROPERTY . ':runReport';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    ]);
    $data = json_decode(curl_exec($ch), true) ?? [];
    curl_close($ch);
    if (!empty($data['rows'])) file_put_contents($cacheFile, json_encode($data));
    return $data;
}

function extractRows(array $report): array {
    $rows = [];
    foreach ($report['rows'] ?? [] as $row) {
        $r = [];
        foreach ($row['dimensionValues'] ?? [] as $i => $v) { $r['d'.$i] = $v['value'] ?? ''; }
        foreach ($row['metricValues']   ?? [] as $i => $v) { $r['m'.$i] = $v['value'] ?? '0'; }
        $rows[] = $r;
    }
    return $rows;
}

// ── SEARCH CONSOLE ────────────────────────────────────────────────────────────
function scQuery(string $token, array $body): array {
    $cacheKey  = md5(SC_SITE_URL . json_encode($body));
    $cacheFile = sys_get_temp_dir() . '/vb_sc_' . $cacheKey . '.json';
    if (!isset($_GET['refresh']) && file_exists($cacheFile) && filemtime($cacheFile) > time() - 3600) {
        return json_decode(file_get_contents($cacheFile), true) ?? [];
    }
    $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
         . rawurlencode(SC_SITE_URL) . '/searchAnalytics/query';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    ]);
    $data = json_decode(curl_exec($ch), true) ?? [];
    curl_close($ch);
    if (!empty($data['rows'])) file_put_contents($cacheFile, json_encode($data));
    return $data;
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$days      = max(7, min(90, (int)($_GET['days'] ?? 30)));
$startDate = date('Y-m-d', strtotime("-{$days} days"));
$endDate   = date('Y-m-d', strtotime('-3 days')); // SC ha 2-3 gg ritardo
$dateRange = [['startDate' => $startDate, 'endDate' => 'today']];

$error = $scError = null;
$kpi   = $trend = $pages = $sources = $devices = $countries = [];
$scKpi = $scQueries = $scPages = $scTrend = [];

if (!$setupNeeded) {
    // ── GA4 ──
    try {
        $ga4Token = getToken('https://www.googleapis.com/auth/analytics.readonly');
        if (!$ga4Token) throw new Exception('Token GA4 non ottenuto.');

        $rKpi    = ga4Report($ga4Token, ['dateRanges' => $dateRange, 'metrics' => [
            ['name' => 'activeUsers'], ['name' => 'sessions'],
            ['name' => 'screenPageViews'], ['name' => 'averageSessionDuration'],
        ]]);
        $kpiVals = $rKpi['rows'][0]['metricValues'] ?? [];
        $kpi     = [
            'users'    => (int)($kpiVals[0]['value'] ?? 0),
            'sessions' => (int)($kpiVals[1]['value'] ?? 0),
            'views'    => (int)($kpiVals[2]['value'] ?? 0),
            'duration' => round((float)($kpiVals[3]['value'] ?? 0)),
        ];
        $trend   = extractRows(ga4Report($ga4Token, [
            'dateRanges' => $dateRange,
            'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'sessions'], ['name' => 'activeUsers']],
            'orderBys'   => [['dimension' => ['dimensionName' => 'date'], 'desc' => false]],
        ]));
        $pages   = extractRows(ga4Report($ga4Token, [
            'dateRanges' => $dateRange, 'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']],
            'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 10,
        ]));
        $sources = extractRows(ga4Report($ga4Token, [
            'dateRanges' => $dateRange, 'dimensions' => [['name' => 'sessionDefaultChannelGrouping']],
            'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        ]));
        $devices = extractRows(ga4Report($ga4Token, [
            'dateRanges' => $dateRange, 'dimensions' => [['name' => 'deviceCategory']],
            'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
        ]));
        $countries = extractRows(ga4Report($ga4Token, [
            'dateRanges' => $dateRange, 'dimensions' => [['name' => 'country']],
            'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 8,
        ]));
    } catch (Exception $e) { $error = $e->getMessage(); }

    // ── SEARCH CONSOLE ──
    try {
        $scToken = getToken('https://www.googleapis.com/auth/webmasters.readonly');
        if (!$scToken) throw new Exception('Token Search Console non ottenuto.');

        $scRange = ['startDate' => $startDate, 'endDate' => $endDate];

        // KPI overview
        $scRaw = scQuery($scToken, array_merge($scRange, ['rowLimit' => 1]));
        // Aggregate: sum all rows
        $scAll = scQuery($scToken, array_merge($scRange, ['dimensions' => ['date'], 'rowLimit' => 500]));
        $totClicks = $totImpr = $totPos = 0; $cnt = 0;
        foreach ($scAll['rows'] ?? [] as $row) {
            $totClicks += $row['clicks']      ?? 0;
            $totImpr   += $row['impressions'] ?? 0;
            $totPos    += $row['position']    ?? 0;
            $cnt++;
        }
        $scKpi = [
            'clicks'      => $totClicks,
            'impressions' => $totImpr,
            'ctr'         => $totImpr > 0 ? round($totClicks / $totImpr * 100, 1) : 0,
            'position'    => $cnt > 0 ? round($totPos / $cnt, 1) : 0,
        ];

        // Trend clic giornaliero
        $scTrend = $scAll['rows'] ?? [];

        // Top query
        $scQueries = (scQuery($scToken, array_merge($scRange, [
            'dimensions' => ['query'], 'rowLimit' => 10,
            'orderBy'    => ['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING'],
        ])))['rows'] ?? [];

        // Top pagine da ricerca organica
        $scPages = (scQuery($scToken, array_merge($scRange, [
            'dimensions' => ['page'], 'rowLimit' => 10,
            'orderBy'    => ['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING'],
        ])))['rows'] ?? [];

    } catch (Exception $e) { $scError = $e->getMessage(); }
}

// ── JS DATA ───────────────────────────────────────────────────────────────────
$trendLabels   = json_encode(array_map(fn($r) => substr($r['d0'],6,2).'/'.substr($r['d0'],4,2), $trend));
$trendSessions = json_encode(array_column($trend, 'm0'));
$trendUsers    = json_encode(array_column($trend, 'm1'));

// SC trend (ordina per data)
usort($scTrend, fn($a,$b) => strcmp($a['keys'][0]??'', $b['keys'][0]??''));
$scTrendLabels = json_encode(array_map(fn($r) => substr($r['keys'][0]??'',8,2).'/'.substr($r['keys'][0]??'',5,2), $scTrend));
$scTrendClicks = json_encode(array_map(fn($r) => $r['clicks'] ?? 0, $scTrend));
$scTrendImpr   = json_encode(array_map(fn($r) => $r['impressions'] ?? 0, $scTrend));

function fmtDur(int $s): string { return sprintf('%d\'%02d"', intdiv($s,60), $s%60); }
function num(int $n): string    { return number_format($n, 0, ',', '.'); }
function pct(float $v): string  { return number_format($v, 1, ',', '.') . '%'; }
function fmtPos(float $v): string { return number_format($v, 1, ',', '.'); }
function shortUrl(string $u): string {
    $u = preg_replace('#^https?://[^/]+#', '', $u);
    return strlen($u) > 40 ? substr($u,0,38).'…' : ($u ?: '/');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Statistiche — valentinarussobg5.com</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f172a;color:#f1f5f9;font-family:system-ui,sans-serif;min-height:100vh;padding:0 0 3rem}
.hd{background:#1e293b;border-bottom:1px solid #334155;padding:.9rem 1.5rem;
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;}
.hd-title{font-size:1.1rem;font-weight:700;}
.hd-title span{color:#7c3aed;}
.hd-right{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;}
.range-btn{padding:.3rem .85rem;border-radius:20px;border:1px solid #475569;
  background:transparent;color:#94a3b8;font-size:.78rem;cursor:pointer;text-decoration:none;}
.range-btn:hover,.range-btn.active{background:#7c3aed;border-color:#7c3aed;color:#fff;}
.refresh-btn{padding:.3rem .85rem;border-radius:20px;border:1px solid #475569;
  background:transparent;color:#64748b;font-size:.78rem;cursor:pointer;}
.logout-btn{padding:.3rem .85rem;border-radius:20px;border:1px solid #334155;
  background:transparent;color:#64748b;font-size:.78rem;cursor:pointer;}
.wrap{max-width:1200px;margin:0 auto;padding:1.5rem;}
.section-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
  color:#475569;margin:2rem 0 1rem;padding-bottom:.5rem;border-bottom:1px solid #1e293b;}
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
@media(max-width:700px){.kpi-grid{grid-template-columns:repeat(2,1fr);}}
.kpi-card{background:#1e293b;border-radius:10px;padding:1.25rem 1.5rem;border:1px solid #334155;}
.kpi-card.sc{border-color:#0e4429;}
.kpi-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:.5rem;}
.kpi-value{font-size:2rem;font-weight:800;color:#f1f5f9;}
.kpi-sub{font-size:.75rem;color:#64748b;margin-top:.25rem;}
.card{background:#1e293b;border-radius:10px;padding:1.25rem 1.5rem;border:1px solid #334155;margin-bottom:1rem;}
.card.sc{border-color:#0e4429;}
.card-title{font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
  color:#7c3aed;margin-bottom:1.1rem;}
.card-title.sc{color:#4ade80;}
.chart-wrap{height:220px;position:relative;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;}
@media(max-width:700px){.grid2{grid-template-columns:1fr;}}
table{width:100%;border-collapse:collapse;font-size:.83rem;}
th{text-align:left;padding:.4rem .6rem;color:#64748b;font-weight:600;font-size:.72rem;
  text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #334155;}
td{padding:.45rem .6rem;border-bottom:1px solid #1e293b;color:#cbd5e1;word-break:break-all;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#0f172a;}
.num{text-align:right;color:#f1f5f9;font-weight:600;word-break:normal;}
.bar-bg{background:#334155;border-radius:4px;height:6px;margin-top:4px;overflow:hidden;}
.bar-fill{height:100%;background:#7c3aed;border-radius:4px;}
.bar-fill.sc{background:#22c55e;}
.error{background:#450a0a;border:1px solid #991b1b;border-radius:8px;
  padding:1rem 1.25rem;color:#fca5a5;font-size:.88rem;margin-bottom:1rem;}
.warn{background:#1c1a07;border:1px solid #854d0e;border-radius:8px;
  padding:1rem 1.25rem;color:#fcd34d;font-size:.85rem;margin-bottom:1rem;}
.updated{font-size:.7rem;color:#475569;margin-top:.5rem;}
</style>
</head>
<body>

<div class="hd">
  <div class="hd-title">📊 <span>Statistiche</span> — valentinarussobg5.com</div>
  <div class="hd-right">
    <?php foreach([7,30,90] as $d): ?>
      <a href="?days=<?=$d?>" class="range-btn <?=$days===$d?'active':''?>">Ultimi <?=$d?> gg</a>
    <?php endforeach; ?>
    <a href="?days=<?=$days?>&refresh=1" class="refresh-btn">↻ Aggiorna</a>
    <form method="POST" style="display:inline">
      <button name="logout" value="1" class="logout-btn">Esci</button>
    </form>
  </div>
</div>

<div class="wrap">

<?php if ($setupNeeded): ?>
<div style="background:#1e293b;border:1px solid #334155;border-radius:10px;padding:2rem;max-width:600px;margin:2rem auto;">
  <h2 style="color:#f59e0b;margin-bottom:1rem;">⚙️ Configurazione richiesta</h2>
  <p style="color:#94a3b8;">File Service Account non trovato. Carica <code style="color:#38bdf8;">ga4-service-account.json</code> nella root del sito.</p>
</div>

<?php elseif ($error): ?>
<div class="error">❌ Errore GA4: <?=htmlspecialchars($error)?></div>

<?php else: ?>

<!-- ═══ GA4 ═══════════════════════════════════════════════════════════════ -->
<div class="section-title">📊 Google Analytics</div>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-label">Utenti unici</div>
    <div class="kpi-value"><?=num($kpi['users'])?></div>
    <div class="kpi-sub">ultimi <?=$days?> giorni</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Sessioni</div>
    <div class="kpi-value"><?=num($kpi['sessions'])?></div>
    <div class="kpi-sub">ultimi <?=$days?> giorni</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Pagine viste</div>
    <div class="kpi-value"><?=num($kpi['views'])?></div>
    <div class="kpi-sub">ultimi <?=$days?> giorni</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Durata media</div>
    <div class="kpi-value"><?=fmtDur($kpi['duration'])?></div>
    <div class="kpi-sub">per sessione</div>
  </div>
</div>

<?php if ($trend): ?>
<div class="card">
  <div class="card-title">Andamento sessioni — ultimi <?=$days?> giorni</div>
  <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <div class="card-title">Top pagine</div>
    <?php if ($pages): $maxV = max(array_column($pages,'m0')??[1]); ?>
    <table><thead><tr><th>Pagina</th><th class="num">Visite</th></tr></thead><tbody>
    <?php foreach($pages as $p): ?>
      <tr><td style="max-width:200px;">
        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;" title="<?=htmlspecialchars($p['d0'])?>">
          <?=htmlspecialchars(strlen($p['d0'])>35?substr($p['d0'],0,33).'…':$p['d0'])?>
        </div>
        <div class="bar-bg"><div class="bar-fill" style="width:<?=round($p['m0']/$maxV*100)?>%"></div></div>
      </td><td class="num"><?=num((int)$p['m0'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:#64748b;font-size:.85rem;">Nessun dato</p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title">Sorgenti traffico</div>
    <?php if ($sources): $maxS = max(array_column($sources,'m0')??[1]); ?>
    <table><thead><tr><th>Canale</th><th class="num">Sessioni</th></tr></thead><tbody>
    <?php foreach($sources as $s): ?>
      <tr><td><?=htmlspecialchars($s['d0']?:'(direct)')?>
        <div class="bar-bg"><div class="bar-fill" style="width:<?=round($s['m0']/$maxS*100)?>%"></div></div>
      </td><td class="num"><?=num((int)$s['m0'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:#64748b;font-size:.85rem;">Nessun dato</p><?php endif; ?>
  </div>
</div>

<div class="grid2">
  <div class="card">
    <div class="card-title">Dispositivi</div>
    <?php if ($devices): $maxD = max(array_column($devices,'m0')??[1]); ?>
    <table><thead><tr><th>Dispositivo</th><th class="num">Sessioni</th></tr></thead><tbody>
    <?php foreach($devices as $d): ?>
      <tr><td><?=htmlspecialchars(ucfirst($d['d0']))?>
        <div class="bar-bg"><div class="bar-fill" style="width:<?=round($d['m0']/$maxD*100)?>%"></div></div>
      </td><td class="num"><?=num((int)$d['m0'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:#64748b;font-size:.85rem;">Nessun dato</p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title">Paesi</div>
    <?php if ($countries): $maxC = max(array_column($countries,'m0')??[1]); ?>
    <table><thead><tr><th>Paese</th><th class="num">Sessioni</th></tr></thead><tbody>
    <?php foreach($countries as $c): ?>
      <tr><td><?=htmlspecialchars($c['d0'])?>
        <div class="bar-bg"><div class="bar-fill" style="width:<?=round($c['m0']/$maxC*100)?>%"></div></div>
      </td><td class="num"><?=num((int)$c['m0'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:#64748b;font-size:.85rem;">Nessun dato</p><?php endif; ?>
  </div>
</div>

<!-- ═══ SEARCH CONSOLE ════════════════════════════════════════════════════ -->
<div class="section-title">🔍 Google Search Console</div>

<?php if ($scError): ?>
<div class="warn">⚠️ Search Console non disponibile: <?=htmlspecialchars($scError)?><br>
  <small style="color:#64748b;">Assicurati che il service account sia stato aggiunto come utente in Search Console e che l'API Search Console sia abilitata.</small>
</div>
<?php elseif (empty($scKpi)): ?>
<div class="warn">⚠️ Nessun dato Search Console. L'accesso potrebbe essere in fase di propagazione (attendere qualche ora).</div>
<?php else: ?>

<div class="kpi-grid">
  <div class="kpi-card sc">
    <div class="kpi-label">Click organici</div>
    <div class="kpi-value"><?=num($scKpi['clicks'])?></div>
    <div class="kpi-sub">ultimi <?=$days?> giorni</div>
  </div>
  <div class="kpi-card sc">
    <div class="kpi-label">Impressioni</div>
    <div class="kpi-value"><?=num($scKpi['impressions'])?></div>
    <div class="kpi-sub">apparizioni in Google</div>
  </div>
  <div class="kpi-card sc">
    <div class="kpi-label">CTR medio</div>
    <div class="kpi-value"><?=pct($scKpi['ctr'])?></div>
    <div class="kpi-sub">click / impressioni</div>
  </div>
  <div class="kpi-card sc">
    <div class="kpi-label">Posizione media</div>
    <div class="kpi-value"><?=fmtPos($scKpi['position'])?></div>
    <div class="kpi-sub">nei risultati Google</div>
  </div>
</div>

<?php if ($scTrend): ?>
<div class="card sc">
  <div class="card-title sc">Andamento click organici — ultimi <?=$days?> giorni</div>
  <div class="chart-wrap"><canvas id="scChart"></canvas></div>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card sc">
    <div class="card-title sc">Top query di ricerca</div>
    <?php if ($scQueries):
      $maxQ = max(array_map(fn($r)=>$r['clicks']??0, $scQueries) ?: [1]); ?>
    <table><thead><tr><th>Query</th><th class="num">Click</th><th class="num">Impr.</th><th class="num">Pos.</th></tr></thead><tbody>
    <?php foreach($scQueries as $q):
      $qText = $q['keys'][0] ?? ''; ?>
      <tr><td>
        <div style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?=htmlspecialchars($qText)?>"><?=htmlspecialchars($qText)?></div>
        <div class="bar-bg"><div class="bar-fill sc" style="width:<?=$maxQ>0?round(($q['clicks']??0)/$maxQ*100):0?>%"></div></div>
      </td>
      <td class="num"><?=num($q['clicks']??0)?></td>
      <td class="num"><?=num($q['impressions']??0)?></td>
      <td class="num"><?=fmtPos($q['position']??0)?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:#64748b;font-size:.85rem;">Nessun dato</p><?php endif; ?>
  </div>

  <div class="card sc">
    <div class="card-title sc">Top pagine da ricerca organica</div>
    <?php if ($scPages):
      $maxP = max(array_map(fn($r)=>$r['clicks']??0, $scPages) ?: [1]); ?>
    <table><thead><tr><th>Pagina</th><th class="num">Click</th><th class="num">Pos.</th></tr></thead><tbody>
    <?php foreach($scPages as $pg):
      $pgUrl = shortUrl($pg['keys'][0] ?? ''); ?>
      <tr><td>
        <div style="max-width:190px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?=htmlspecialchars($pg['keys'][0]??'')?>">
          <?=htmlspecialchars($pgUrl)?>
        </div>
        <div class="bar-bg"><div class="bar-fill sc" style="width:<?=$maxP>0?round(($pg['clicks']??0)/$maxP*100):0?>%"></div></div>
      </td>
      <td class="num"><?=num($pg['clicks']??0)?></td>
      <td class="num"><?=fmtPos($pg['position']??0)?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p style="color:#64748b;font-size:.85rem;">Nessun dato</p><?php endif; ?>
  </div>
</div>

<?php endif; // scError / scKpi ?>

<div class="updated">Dati aggiornati ogni ora (SC: 2-3 giorni di ritardo). <a href="?days=<?=$days?>&refresh=1" style="color:#7c3aed;">Forza aggiornamento</a></div>

<?php endif; // setupNeeded / error ?>
</div>

<!-- CHARTS -->
<?php if (!$setupNeeded && !$error): ?>
<script>
const Chart_defaults = { responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{ labels:{color:'#94a3b8',font:{size:11}} }, tooltip:{mode:'index',intersect:false} },
  scales:{ x:{ticks:{color:'#64748b',font:{size:10}},grid:{color:'#1e293b'}},
           y:{ticks:{color:'#64748b',font:{size:10}},grid:{color:'#1e293b'},beginAtZero:true} }
};
<?php if($trend): ?>
new Chart(document.getElementById('trendChart').getContext('2d'), {
  type:'line', data:{ labels:<?=$trendLabels?>, datasets:[
    { label:'Sessioni', data:<?=$trendSessions?>, borderColor:'#7c3aed', backgroundColor:'rgba(124,58,237,.12)',
      borderWidth:2, fill:true, tension:.35, pointRadius:<?=count($trend)>30?0:3?>, pointHoverRadius:5 },
    { label:'Utenti',   data:<?=$trendUsers?>,    borderColor:'#38bdf8', backgroundColor:'rgba(56,189,248,.07)',
      borderWidth:1.5, fill:false, tension:.35, pointRadius:0, pointHoverRadius:4 }
  ]}, options:Chart_defaults
});
<?php endif; ?>
<?php if($scTrend && !$scError): ?>
new Chart(document.getElementById('scChart').getContext('2d'), {
  type:'line', data:{ labels:<?=$scTrendLabels?>, datasets:[
    { label:'Click', data:<?=$scTrendClicks?>, borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,.12)',
      borderWidth:2, fill:true, tension:.35, pointRadius:<?=count($scTrend)>30?0:3?>, pointHoverRadius:5 },
    { label:'Impressioni', data:<?=$scTrendImpr?>, borderColor:'#86efac', backgroundColor:'rgba(134,239,172,.05)',
      borderWidth:1.5, fill:false, tension:.35, pointRadius:0, pointHoverRadius:4, yAxisID:'y2' }
  ]},
  options:{ ...Chart_defaults, scales:{ ...Chart_defaults.scales,
    y2:{ type:'linear', position:'right', ticks:{color:'#64748b',font:{size:10}}, grid:{drawOnChartArea:false}, beginAtZero:true }
  }}
});
<?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>
