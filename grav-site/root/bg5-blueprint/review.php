<?php
/**
 * BG5 Blueprint — Pagina di revisione per Valentina
 *
 * Endpoint: GET  /bg5-blueprint/review.php           → lista job in attesa
 * Endpoint: GET  /bg5-blueprint/review.php?id=job_X  → visualizza bozza + editor
 * Endpoint: POST /bg5-blueprint/review.php            → approva o salva modifiche
 */

declare(strict_types=1);

session_start();

$REVIEW_PASS = getenv('ADMIN_PASSWORD') ?: 'changeme';
$JOBS_DIR    = __DIR__ . '/jobs/';
$PDFS_DIR    = __DIR__ . '/pdfs/';

// ─── AUTH ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pass'])) {
    if (hash_equals(hash('sha256', $_POST['login_pass']), hash('sha256', $REVIEW_PASS))) {
        $_SESSION['bg5_review_auth'] = true;
        session_regenerate_id(true);
    }
}

if (!($_SESSION['bg5_review_auth'] ?? false)) {
    http_response_code(401);
    echo <<<HTML
    <!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">
    <title>Revisione Blueprint — Accesso</title>
    <style>
      body{font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;
           height:100vh;margin:0;background:#F8EEF1}
      .box{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);
           min-width:320px;text-align:center}
      h2{color:#C9768A;margin:0 0 24px}
      input{width:100%;padding:12px;border:1px solid #E0E0E0;border-radius:6px;font-size:14px;
            box-sizing:border-box}
      button{margin-top:16px;width:100%;padding:12px;background:#C9768A;color:#fff;border:none;
             border-radius:6px;cursor:pointer;font-size:14px;font-weight:bold}
      button:hover{background:#a85a72}
    </style>
    </head><body><div class="box">
    <h2>Revisione Blueprint</h2>
    <form method="post">
      <input type="password" name="login_pass" placeholder="Password" autofocus>
      <button type="submit">Accedi</button>
    </form>
    </div></body></html>
    HTML;
    exit;
}

// ─── AZIONI POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $job_id = preg_replace('/[^a-z0-9_]/', '', $_POST['job_id'] ?? '');
    $action = $_POST['action'];

    foreach (['approved', 'sent'] as $sub) {
        $d = $JOBS_DIR . $sub . '/';
        if (!is_dir($d)) mkdir($d, 0755, true);
    }

    $job_file = $JOBS_DIR . 'review/' . $job_id . '.json';
    if (!file_exists($job_file)) {
        die('Job non trovato.');
    }
    $job = json_decode(file_get_contents($job_file), true);

    // Salva modifiche (AJAX)
    if ($action === 'save_edits') {
        $raw = $_POST['modified_sections'] ?? '{}';
        $decoded = json_decode($raw, true);
        if ($decoded !== null) {
            $job['modified_sections'] = $decoded;
            $job['last_edited'] = date('c');
            file_put_contents($job_file, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // Approva e invia
    if ($action === 'approve') {
        if (!empty($_POST['modified_sections'])) {
            $decoded = json_decode($_POST['modified_sections'], true);
            if ($decoded !== null) {
                $job['modified_sections'] = $decoded;
            }
        }
        $job['status']      = 'approved';
        $job['approved_at'] = date('c');

        $approved_file = $JOBS_DIR . 'approved/' . $job_id . '.json';
        file_put_contents($approved_file, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        unlink($job_file);

        // TODO (C2): inviare email al cliente con PDF in allegato via mailer.php

        header('Location: review.php?approved=' . urlencode($job['customer_name']));
        exit;
    }
}

// ─── VISTA SINGOLO JOB ────────────────────────────────────────────────────────
$job_id = preg_replace('/[^a-z0-9_]/', '', $_GET['id'] ?? '');

if ($job_id) {
    $job_file = $JOBS_DIR . 'review/' . $job_id . '.json';
    if (!file_exists($job_file)) {
        die('Bozza non trovata o già approvata.');
    }
    $job      = json_decode(file_get_contents($job_file), true);
    $sections = $job['sections']         ?? [];
    $modified = $job['modified_sections'] ?? [];
    $chart    = $job['chart']             ?? [];

    // Titoli leggibili per le sezioni
    $section_labels = [
        'intro'               => '01 · Introduzione',
        'tipo_strategia'      => '02 · Tipologia e Strategia',
        'autorita'            => '03 · Autorità interiore',
        'profilo'             => '04 · Profilo',
        'definizione'         => '05 · Definizione',
        'centri_definiti'     => '06 · Centri definiti',
        'centri_aperti'       => '07 · Centri aperti',
        'canali'              => '08 · Canali',
        'porte'               => '09 · Porte',
        'architettura_cognitiva' => '10 · Architettura cognitiva',
        'offerte_allineate'   => '11 · Offerte allineate',
        'voce_e_mercato'      => '12 · Voce e mercato',
        'suggerimenti'        => '13 · Suggerimenti pratici',
    ];

    // URL PDF (relativo al web root bg5-blueprint/)
    $pdf_completo  = 'pdfs/' . $job_id . '-completo.pdf';
    $pdf_essenziale= 'pdfs/' . $job_id . '-essenziale.pdf';
    $has_pdf       = file_exists($PDFS_DIR . $job_id . '-completo.pdf');

    $jname  = htmlspecialchars($job['customer_name']   ?? '');
    $jdate  = htmlspecialchars($job['birth_date']       ?? '');
    $jtime  = htmlspecialchars($job['birth_time']       ?? '');
    $jplace = htmlspecialchars($job['birth_place']      ?? '');
    $jemail = htmlspecialchars($job['customer_email']   ?? '');
    $jordat = $job['created_at'] ? date('d/m/Y H:i', strtotime($job['created_at'])) : '';
    $jtype  = htmlspecialchars($chart['career_type'] ?? '');
    $jprof  = htmlspecialchars($chart['profile']      ?? '');
    $jauth  = htmlspecialchars($chart['authority']    ?? '');

    ?><!DOCTYPE html>
    <html lang="it">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Revisione: <?= $jname ?></title>
    <style>
      :root{--rosa:#C9768A;--navy:#1A2332;--bg:#F8EEF1;--white:#fff;--border:#E5E0DD}
      *{box-sizing:border-box}
      body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
           background:var(--bg);color:var(--navy)}
      a{color:var(--rosa);text-decoration:none}

      /* Layout */
      .topbar{background:var(--navy);color:#fff;padding:14px 24px;display:flex;
              align-items:center;justify-content:space-between}
      .topbar a{color:#aaa;font-size:13px}
      .topbar h1{margin:0;font-size:16px;font-weight:600}
      .layout{display:grid;grid-template-columns:1fr 1fr;gap:0;height:calc(100vh - 50px)}

      /* Pannello sinistro: editor */
      .editor-panel{overflow-y:auto;padding:24px;border-right:1px solid var(--border)}
      .client-card{background:#fff;border-radius:10px;padding:16px 20px;margin-bottom:20px;
                   box-shadow:0 2px 8px rgba(0,0,0,.06)}
      .client-card .name{font-size:18px;font-weight:700;color:var(--navy);margin-bottom:6px}
      .client-card .meta{font-size:12px;color:#888;line-height:1.8}
      .client-card .hd-badge{display:inline-block;background:#EAE4F2;color:#5B4A8C;
                              padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                              margin-top:8px}

      /* Sezione accordion */
      .section-block{background:#fff;border-radius:8px;margin-bottom:10px;
                     box-shadow:0 1px 4px rgba(0,0,0,.05)}
      .section-header{padding:12px 16px;cursor:pointer;display:flex;justify-content:space-between;
                       align-items:center;font-weight:600;font-size:13px;color:var(--navy)}
      .section-header:hover{background:#fafafa}
      .section-header .chevron{transition:transform .2s;font-size:10px;color:#aaa}
      .section-header.open .chevron{transform:rotate(180deg)}
      .section-body{display:none;padding:0 16px 14px}
      .section-body.open{display:block}
      .section-body textarea{width:100%;min-height:160px;border:1px solid var(--border);
                              border-radius:6px;padding:10px;font-size:12px;line-height:1.6;
                              font-family:monospace;resize:vertical;color:#333}
      .section-body textarea:focus{outline:none;border-color:var(--rosa)}
      .modified-badge{font-size:10px;background:#FFF3CD;color:#856404;padding:2px 7px;
                      border-radius:10px;margin-left:8px}

      /* Azioni */
      .actions{position:sticky;bottom:0;background:var(--bg);padding:16px 24px;
               border-top:1px solid var(--border);display:flex;gap:12px}
      .btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;
           font-size:13px;font-weight:600;transition:opacity .15s}
      .btn-save{background:#4A8C8C;color:#fff}
      .btn-approve{background:var(--rosa);color:#fff;flex:1}
      .btn:hover{opacity:.85}
      .btn:disabled{opacity:.5;cursor:not-allowed}
      .save-status{font-size:12px;color:#888;margin-top:4px;display:none}

      /* Pannello destro: PDF viewer */
      .pdf-panel{display:flex;flex-direction:column;background:#ddd}
      .pdf-toolbar{background:var(--navy);padding:10px 16px;display:flex;gap:10px;align-items:center}
      .pdf-toolbar a{color:#ddd;font-size:12px;text-decoration:none;padding:5px 12px;
                     border:1px solid #444;border-radius:5px}
      .pdf-toolbar a:hover{background:#333}
      .pdf-toolbar .pdf-label{color:#888;font-size:12px}
      iframe.pdf-embed{flex:1;border:none;width:100%;height:100%}
      .no-pdf{display:flex;align-items:center;justify-content:center;flex:1;
              flex-direction:column;color:#888;gap:8px}
      .no-pdf .icon{font-size:48px}

      /* Responsive */
      @media(max-width:900px){
        .layout{grid-template-columns:1fr;height:auto}
        .pdf-panel{height:60vh}
      }
    </style>
    </head>
    <body>

    <div class="topbar">
      <div>
        <a href="review.php">← Tutti i blueprint</a>
        <h1 style="display:inline;margin-left:16px"><?= $jname ?></h1>
      </div>
      <span style="font-size:12px;color:#aaa">
        Ordine <?= $jordat ?>
        <?php if ($has_pdf): ?>
          &nbsp;·&nbsp;<a href="<?= $pdf_completo ?>" target="_blank" style="color:#7EC8C8">
            ↓ Scarica PDF Completo
          </a>
        <?php endif; ?>
      </span>
    </div>

    <div class="layout">

      <!-- ── Pannello sinistro: editor ── -->
      <div class="editor-panel">

        <div class="client-card">
          <div class="name"><?= $jname ?></div>
          <div class="meta">
            📅 <?= $jdate ?> · <?= $jtime ?><br>
            📍 <?= $jplace ?><br>
            📧 <?= $jemail ?>
          </div>
          <?php if ($jtype): ?>
            <div class="hd-badge"><?= $jtype ?> · Profilo <?= $jprof ?> · <?= $jauth ?></div>
          <?php endif; ?>
        </div>

        <p style="font-size:12px;color:#888;margin:0 0 12px">
          Espandi le sezioni per leggere e modificare il testo generato.
          Le modifiche sono salvate automaticamente prima dell'approvazione.
        </p>

        <form id="review-form" method="post">
          <input type="hidden" name="job_id" value="<?= htmlspecialchars($job_id) ?>">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="modified_sections" id="modified_sections_input" value="">

          <?php foreach ($section_labels as $key => $label):
            $text = $modified[$key] ?? $sections[$key] ?? '';
            $is_modified = isset($modified[$key]);
          ?>
          <div class="section-block">
            <div class="section-header" onclick="toggleSection(this)">
              <span>
                <?= htmlspecialchars($label) ?>
                <?php if ($is_modified): ?><span class="modified-badge">modificata</span><?php endif; ?>
              </span>
              <span class="chevron">▼</span>
            </div>
            <div class="section-body">
              <textarea
                name="sec_<?= $key ?>"
                data-key="<?= $key ?>"
                oninput="markDirty(this)"
              ><?= htmlspecialchars($text) ?></textarea>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="actions">
            <button type="button" class="btn btn-save" id="btn-save" onclick="saveEdits()">
              Salva bozza
            </button>
            <button type="submit" class="btn btn-approve" id="btn-approve"
                    onclick="return confirmApprove()">
              ✓ Approva e invia
            </button>
          </div>
          <div class="save-status" id="save-status"></div>
        </form>

      </div>

      <!-- ── Pannello destro: PDF viewer ── -->
      <div class="pdf-panel">
        <div class="pdf-toolbar">
          <span class="pdf-label">Anteprima PDF</span>
          <?php if ($has_pdf): ?>
            <a href="<?= $pdf_completo ?>" target="_blank">↗ Apri completo</a>
            <a href="<?= $pdf_essenziale ?>" target="_blank">↗ Apri essenziale</a>
          <?php endif; ?>
        </div>
        <?php if ($has_pdf): ?>
          <iframe
            class="pdf-embed"
            src="<?= $pdf_completo ?>#toolbar=0"
            title="PDF Blueprint <?= $jname ?>">
          </iframe>
        <?php else: ?>
          <div class="no-pdf">
            <div class="icon">⏳</div>
            <div>PDF in generazione — ricarica tra qualche minuto</div>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <script>
    // Stato modifiche in-memory
    const modified = <?= json_encode($modified, JSON_UNESCAPED_UNICODE) ?>;

    function toggleSection(header) {
      const body = header.nextElementSibling;
      header.classList.toggle('open');
      body.classList.toggle('open');
    }

    function markDirty(textarea) {
      const key = textarea.getAttribute('data-key');
      modified[key] = textarea.value;
      // Segna visivamente come modificata
      const header = textarea.closest('.section-block').querySelector('.section-header span:first-child');
      if (!header.querySelector('.modified-badge')) {
        const badge = document.createElement('span');
        badge.className = 'modified-badge';
        badge.textContent = 'modificata';
        header.appendChild(badge);
      }
    }

    function syncModifiedInput() {
      document.getElementById('modified_sections_input').value = JSON.stringify(modified);
    }

    function saveEdits() {
      syncModifiedInput();
      const btn = document.getElementById('btn-save');
      const status = document.getElementById('save-status');
      btn.disabled = true;
      status.style.display = 'block';
      status.textContent = 'Salvataggio...';

      const fd = new FormData();
      fd.append('action', 'save_edits');
      fd.append('job_id', '<?= htmlspecialchars($job_id) ?>');
      fd.append('modified_sections', JSON.stringify(modified));

      fetch('review.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          status.textContent = data.ok ? '✓ Salvato' : '✗ Errore salvataggio';
          btn.disabled = false;
          setTimeout(() => { status.style.display = 'none'; }, 3000);
        })
        .catch(() => {
          status.textContent = '✗ Errore di rete';
          btn.disabled = false;
        });
    }

    function confirmApprove() {
      syncModifiedInput();
      return confirm(
        'Confermi l\'approvazione del Blueprint per <?= addslashes($jname) ?>?\n\n' +
        'Il PDF verrà segnato come approvato e sarà pronto per l\'invio.'
      );
    }
    </script>

    </body>
    </html>
    <?php
    exit;
}

// ─── LISTA JOB IN ATTESA ──────────────────────────────────────────────────────
$review_dir = $JOBS_DIR . 'review/';
$jobs = [];
if (is_dir($review_dir)) {
    foreach (glob($review_dir . '*.json') as $f) {
        $j = json_decode(file_get_contents($f), true);
        if ($j) $jobs[] = $j;
    }
}
usort($jobs, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

// Conta approved per stats
$approved_count = is_dir($JOBS_DIR . 'approved/')
    ? count(glob($JOBS_DIR . 'approved/*.json'))
    : 0;

?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Blueprint in revisione</title>
<style>
  :root{--rosa:#C9768A;--navy:#1A2332;--bg:#F8EEF1;--white:#fff;--border:#E5E0DD}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
       background:var(--bg);color:var(--navy);margin:0;padding:24px}
  h1{color:var(--navy);margin:0 0 6px;font-size:24px}
  .subtitle{color:#888;font-size:14px;margin-bottom:24px}
  .stats{display:flex;gap:12px;margin-bottom:24px}
  .stat{background:#fff;border-radius:10px;padding:14px 20px;text-align:center;flex:1;
        box-shadow:0 1px 6px rgba(0,0,0,.06)}
  .stat .n{font-size:28px;font-weight:700;color:var(--rosa)}
  .stat .l{font-size:12px;color:#888;margin-top:2px}
  .card{background:#fff;border-radius:12px;padding:18px 20px;margin-bottom:12px;
        box-shadow:0 2px 8px rgba(0,0,0,.07);display:flex;align-items:center;gap:16px}
  .card .avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#B68397,#4A8C8C);
                display:flex;align-items:center;justify-content:center;color:#fff;
                font-weight:700;font-size:18px;flex-shrink:0}
  .card .name{font-weight:700;font-size:15px;color:var(--navy);margin-bottom:3px}
  .card .meta{color:#888;font-size:12px;line-height:1.7}
  .card .hd-pill{display:inline-block;background:#EAE4F2;color:#5B4A8C;padding:2px 8px;
                 border-radius:12px;font-size:11px;font-weight:600;margin-top:4px}
  .btn{background:var(--rosa);color:#fff;padding:10px 18px;border-radius:8px;
       text-decoration:none;font-size:13px;font-weight:600;white-space:nowrap}
  .btn:hover{background:#a85a72}
  .empty{text-align:center;color:#aaa;padding:60px;font-size:15px}
  .logout{float:right;color:#aaa;font-size:12px}
</style>
</head>
<body>

<a href="?logout=1" class="logout" onclick="return logout()">Esci</a>
<h1>Blueprint in revisione</h1>
<p class="subtitle">Nuovi ordini pronti per essere approvati e inviati.</p>

<?php if (isset($_GET['approved'])): ?>
  <div style="background:#E8F5EE;border-radius:8px;padding:14px 18px;margin-bottom:20px;color:#2D8A5F;font-weight:600">
    ✓ Blueprint approvato per <strong><?= htmlspecialchars($_GET['approved']) ?></strong> — in attesa di invio.
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="n"><?= count($jobs) ?></div><div class="l">Da revisionare</div></div>
  <div class="stat"><div class="n"><?= $approved_count ?></div><div class="l">Approvati</div></div>
</div>

<?php if (empty($jobs)): ?>
  <div class="empty">
    <div style="font-size:40px;margin-bottom:12px">🌟</div>
    Nessuna bozza in attesa.<br>
    <span style="font-size:13px">Torna quando arriva un nuovo ordine.</span>
  </div>
<?php else: ?>
  <?php foreach ($jobs as $j):
    $initials = strtoupper(substr($j['customer_name'] ?? '?', 0, 1));
    $chart     = $j['chart'] ?? [];
    $type_str  = $chart['career_type'] ?? '';
    $prof_str  = $chart['profile']     ?? '';
  ?>
  <div class="card">
    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
    <div style="flex:1">
      <div class="name"><?= htmlspecialchars($j['customer_name'] ?? '') ?></div>
      <div class="meta">
        <?= htmlspecialchars($j['birth_date'] ?? '') ?>
        · <?= htmlspecialchars($j['birth_time'] ?? '') ?>
        · <?= htmlspecialchars($j['birth_place'] ?? '') ?><br>
        📧 <?= htmlspecialchars($j['customer_email'] ?? '') ?>
        · Ordine: <?= $j['created_at'] ? date('d/m/Y H:i', strtotime($j['created_at'])) : '' ?>
      </div>
      <?php if ($type_str): ?>
        <div class="hd-pill"><?= htmlspecialchars($type_str) ?> · Profilo <?= htmlspecialchars($prof_str) ?></div>
      <?php endif; ?>
    </div>
    <a href="review.php?id=<?= urlencode($j['id']) ?>" class="btn">Visualizza →</a>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<script>
function logout() {
  fetch('review.php', {method:'POST', body: new URLSearchParams({logout:'1'})});
  location.reload();
  return false;
}
</script>
<?php
// Logout via POST
if (isset($_POST['logout']) || isset($_GET['logout'])) {
    session_destroy();
    header('Location: review.php');
    exit;
}
?>
</body>
</html>
