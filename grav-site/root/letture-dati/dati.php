<?php
/**
 * Letture Human Design/BG5 — Raccolta dati post-pagamento
 * Query params: ?session_id=cs_xxx&reading=coppia|figlio|...
 *
 * BOZZA — la sezione "recesso/condizioni" qui sotto è un placeholder generico,
 * NON il testo legale definitivo. A differenza del Libretto (contenuto digitale
 * istantaneo, art.59 lett.o CdC), questa è una sessione dal vivo su data da
 * concordare: la base giuridica corretta per il recesso è diversa e richiede
 * revisione legal-guardian prima di andare live. NON copiare il testo del
 * Libretto qui.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$reading = strtolower(trim($_GET['reading'] ?? ''));
$product = letture_get($reading);
if (!$product) { http_response_code(404); echo 'Lettura non trovata.'; exit; }

$rawSessionId = $_GET['session_id'] ?? '';
$sessionId = preg_match('/^cs_(test|live)_[A-Za-z0-9]{1,200}$/', $rawSessionId)
    ? htmlspecialchars($rawSessionId, ENT_QUOTES, 'UTF-8')
    : '';
$success = isset($_GET['success']) && $_GET['success'] === '1';
$error   = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';

$dataMode = $product['data_mode'];
$priceLabel = '€' . number_format($product['amount'] / 100, 0, ',', '.');
$pageTitle = $success ? "Ricevuto! · {$product['name']}" : "I tuoi dati · {$product['name']}";
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;1,400;1,700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root { --rosa:#C9768A; --teal:#4A8C8C; --navy:#1A2332; --gold:#C48A3A; --parch:#FAF7F5; --soft:#EAE5E1; --text:#2D2926; --muted:#6B6560; --white:#FFFFFF; }
body { margin:0; font-family:'Outfit',sans-serif; background:var(--parch); color:var(--text); min-height:100vh; }
.ld-header { background:var(--white); border-bottom:1px solid var(--soft); padding:.875rem 1.5rem; display:flex; align-items:center; justify-content:space-between; }
.ld-logo { font-family:'Playfair Display',serif; font-size:1.0625rem; font-style:italic; color:var(--navy); text-decoration:none; }
.ld-badge { font-size:.75rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--teal); background:rgba(74,140,140,.1); padding:.3rem .875rem; border-radius:100px; }
.ld-main { max-width:620px; margin:0 auto; padding:3rem 1.5rem 5rem; }
.ld-eyebrow { font-size:.75rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--rosa); margin-bottom:.75rem; }
.ld-h1 { font-family:'Playfair Display',serif; font-size:clamp(1.75rem,4vw,2.25rem); font-weight:700; font-style:italic; color:var(--navy); margin:0 0 .75rem; line-height:1.2; }
.ld-intro { font-size:1rem; line-height:1.65; color:var(--muted); margin:0 0 2rem; }
.ld-confirm-box { background:var(--white); border:1.5px solid rgba(74,140,140,.25); border-left:3px solid var(--teal); border-radius:6px; padding:1.125rem 1.25rem; margin-bottom:2rem; display:flex; align-items:center; gap:1rem; }
.ld-confirm-icon { flex-shrink:0; width:36px; height:36px; border-radius:50%; background:rgba(74,140,140,.12); display:flex; align-items:center; justify-content:center; color:var(--teal); font-size:1.125rem; }
.ld-confirm-text { font-size:.9rem; line-height:1.5; }
.ld-confirm-text strong { color:var(--navy); }
.ld-section-title { font-size:.8125rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--teal); margin:2rem 0 1rem; }
.ld-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.ld-group { margin-bottom:1.25rem; }
.ld-label { display:block; font-size:.875rem; font-weight:600; color:var(--text); margin-bottom:.375rem; }
.ld-label .ld-req { color:var(--rosa); margin-left:2px; }
.ld-hint { display:block; font-size:.8rem; color:var(--muted); font-weight:400; margin-top:.2rem; }
.ld-input, .ld-textarea { width:100%; padding:.8rem 1rem; background:var(--white); border:1.5px solid var(--soft); border-radius:4px; color:var(--text); font-family:'Outfit',sans-serif; font-size:1rem; }
.ld-input:focus, .ld-textarea:focus { outline:none; border-color:var(--teal); box-shadow:0 0 0 3px rgba(74,140,140,.12); }
.ld-textarea { resize:vertical; min-height:100px; line-height:1.6; }
.ld-divider { border:none; border-top:1px solid var(--soft); margin:1.75rem 0; }
.ld-submit { width:100%; padding:1.0625rem; background:var(--rosa); color:var(--white); font-weight:700; font-size:1.0625rem; border:none; border-radius:4px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.5rem; }
.ld-submit:hover { background:#B5697B; }
.ld-submit:disabled { opacity:.6; cursor:not-allowed; }
.ld-submit-note { text-align:center; font-size:.8125rem; color:var(--muted); margin-top:.75rem; }
.ld-error { background:#FEF2F2; border:1.5px solid #FCA5A5; border-radius:6px; padding:1rem 1.25rem; color:#B91C1C; font-size:.9rem; margin-bottom:1.5rem; }
.ld-success { text-align:center; padding:3rem 1rem; }
.ld-success__icon { width:72px; height:72px; border-radius:50%; background:rgba(74,140,140,.12); border:2px solid var(--teal); display:flex; align-items:center; justify-content:center; margin:0 auto 2rem; color:var(--teal); }
.ld-success__h1 { font-family:'Playfair Display',serif; font-size:2rem; font-weight:700; font-style:italic; color:var(--navy); margin:0 0 1rem; }
.ld-success__body { font-size:1.0625rem; line-height:1.7; color:var(--text); max-width:480px; margin:0 auto 2rem; }
.ld-success__box { background:var(--white); border:1px solid var(--soft); border-radius:8px; padding:1.5rem 1.75rem; max-width:440px; margin:0 auto 2.5rem; text-align:left; }
.ld-success__box h3 { font-size:.8125rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--rosa); margin:0 0 1rem; }
.ld-success__box ul { list-style:none; padding:0; margin:0; }
.ld-success__box li { font-size:.9375rem; line-height:1.6; color:var(--text); padding:.4rem 0; border-bottom:1px solid var(--soft); display:flex; gap:.75rem; }
.ld-success__box li:last-child { border-bottom:none; }
.ld-success__box li::before { content:'·'; color:var(--teal); font-weight:800; flex-shrink:0; }
.ld-back-link { font-size:.9rem; color:var(--rosa); text-decoration:none; }
.ld-back-link:hover { text-decoration:underline; }
@media (max-width:600px) { .ld-row { grid-template-columns:1fr; } .ld-main { padding:2rem 1rem 4rem; } }
</style>
</head>
<body>
<header class="ld-header">
  <a href="https://valentinarussobg5.com" class="ld-logo">Valentina Russo</a>
  <span class="ld-badge">Ordine in corso</span>
</header>
<main class="ld-main">
<?php if ($success): ?>

  <div class="ld-success">
    <div class="ld-success__icon" aria-hidden="true">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h1 class="ld-success__h1">Tutto ricevuto.</h1>
    <p class="ld-success__body">
      Hai prenotato: <strong><?= htmlspecialchars($product['name'], ENT_QUOTES) ?></strong>.<br>
      Valentina ti scriverà entro 48 ore per fissare data e ora della sessione.
    </p>
    <div class="ld-success__box">
      <h3>Cosa succede adesso</h3>
      <ul>
        <li>Valentina riceve i tuoi dati e calcola la carta necessaria alla lettura</li>
        <li>Ti contatta via email o telefono per concordare data e ora</li>
        <li>Fate la sessione, dal vivo o online, come preferisci</li>
        <li>Per domande: <a href="mailto:info@valentinarussobg5.com" style="color:var(--rosa)">info@valentinarussobg5.com</a></li>
      </ul>
    </div>
    <a href="https://valentinarussobg5.com/servizi" class="ld-back-link">← Torna ai servizi</a>
  </div>

<?php else: ?>

  <div class="ld-confirm-box">
    <div class="ld-confirm-icon" aria-hidden="true">✓</div>
    <div class="ld-confirm-text">
      <strong>Hai prenotato: <?= htmlspecialchars($product['name'], ENT_QUOTES) ?></strong><br>
      Pagamento confermato (<?= $priceLabel ?>). Ora servono alcuni dati per preparare la lettura e fissare un appuntamento.
    </div>
  </div>

  <p class="ld-eyebrow">Passo 2 di 2</p>
  <h1 class="ld-h1">I tuoi dati</h1>
  <p class="ld-intro"><?= htmlspecialchars($product['description'], ENT_QUOTES) ?></p>

  <?php if ($error): ?>
  <div class="ld-error" role="alert">
    <?php if ($error === 'missing'): ?>
      Compila tutti i campi obbligatori (marcati con *) prima di procedere.
    <?php else: ?>
      Si è verificato un errore nell'invio. Riprova o scrivi a <a href="mailto:info@valentinarussobg5.com">info@valentinarussobg5.com</a>.
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <form class="ld-form" method="POST" action="/letture-dati/invia.php" id="ld-form">
    <input type="hidden" name="reading" value="<?= htmlspecialchars($reading, ENT_QUOTES) ?>">
    <input type="hidden" name="session_id" value="<?= $sessionId ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

    <!-- Contatto (sempre presente) -->
    <div class="ld-row">
      <div class="ld-group">
        <label class="ld-label" for="ld-nome">
          <?= $dataMode === 'child' ? 'Nome e cognome (genitore)' : 'Nome e cognome' ?> <span class="ld-req">*</span>
        </label>
        <input class="ld-input" type="text" id="ld-nome" name="nome" required autocomplete="name" placeholder="es. Maria Rossi">
      </div>
      <div class="ld-group">
        <label class="ld-label" for="ld-email">Email <span class="ld-req">*</span></label>
        <input class="ld-input" type="email" id="ld-email" name="email" required autocomplete="email" placeholder="tu@esempio.it">
      </div>
    </div>
    <div class="ld-group">
      <label class="ld-label" for="ld-telefono">Telefono <span class="ld-req">*</span></label>
      <input class="ld-input" type="tel" id="ld-telefono" name="telefono" required autocomplete="tel" placeholder="es. 333 1234567">
      <span class="ld-hint">Serve a Valentina per contattarti e fissare data e ora</span>
    </div>

    <hr class="ld-divider">

    <?php if ($dataMode === 'single'): ?>
      <p class="ld-section-title">Dati di nascita</p>
      <div class="ld-row">
        <div class="ld-group">
          <label class="ld-label" for="ld-data">Data di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="date" id="ld-data" name="birth_date" required>
        </div>
        <div class="ld-group">
          <label class="ld-label" for="ld-ora">Ora di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="time" id="ld-ora" name="birth_time" required>
        </div>
      </div>
      <div class="ld-group">
        <label class="ld-label" for="ld-luogo">Luogo di nascita <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-luogo" name="birth_place" required autocomplete="off" placeholder="es. Milano, Italia">
      </div>

    <?php elseif ($dataMode === 'couple'): ?>
      <p class="ld-section-title">Persona A (tu)</p>
      <div class="ld-group">
        <label class="ld-label" for="ld-a-nome">Nome <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-a-nome" name="a_nome" required placeholder="Nome persona A">
      </div>
      <div class="ld-row">
        <div class="ld-group">
          <label class="ld-label" for="ld-a-data">Data di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="date" id="ld-a-data" name="a_birth_date" required>
        </div>
        <div class="ld-group">
          <label class="ld-label" for="ld-a-ora">Ora di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="time" id="ld-a-ora" name="a_birth_time" required>
        </div>
      </div>
      <div class="ld-group">
        <label class="ld-label" for="ld-a-luogo">Luogo di nascita <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-a-luogo" name="a_birth_place" required placeholder="es. Milano, Italia">
      </div>

      <p class="ld-section-title">Persona B (il/la tuo/a partner)</p>
      <div class="ld-group">
        <label class="ld-label" for="ld-b-nome">Nome <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-b-nome" name="b_nome" required placeholder="Nome persona B">
      </div>
      <div class="ld-row">
        <div class="ld-group">
          <label class="ld-label" for="ld-b-data">Data di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="date" id="ld-b-data" name="b_birth_date" required>
        </div>
        <div class="ld-group">
          <label class="ld-label" for="ld-b-ora">Ora di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="time" id="ld-b-ora" name="b_birth_time" required>
        </div>
      </div>
      <div class="ld-group">
        <label class="ld-label" for="ld-b-luogo">Luogo di nascita <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-b-luogo" name="b_birth_place" required placeholder="es. Torino, Italia">
      </div>

    <?php elseif ($dataMode === 'child'): ?>
      <p class="ld-section-title">Dati del figlio/a</p>
      <div class="ld-group">
        <label class="ld-label" for="ld-c-nome">Nome del bambino/a <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-c-nome" name="child_nome" required placeholder="Nome del figlio/a">
      </div>
      <div class="ld-row">
        <div class="ld-group">
          <label class="ld-label" for="ld-c-data">Data di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="date" id="ld-c-data" name="child_birth_date" required>
        </div>
        <div class="ld-group">
          <label class="ld-label" for="ld-c-ora">Ora di nascita <span class="ld-req">*</span></label>
          <input class="ld-input" type="time" id="ld-c-ora" name="child_birth_time" required>
        </div>
      </div>
      <div class="ld-group">
        <label class="ld-label" for="ld-c-luogo">Luogo di nascita <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-c-luogo" name="child_birth_place" required placeholder="es. Bologna, Italia">
      </div>
    <?php endif; ?>

    <hr class="ld-divider">

    <div class="ld-group">
      <label class="ld-label" for="ld-orari">Fasce orarie in cui preferisci essere contattato/a</label>
      <input class="ld-input" type="text" id="ld-orari" name="fasce_orarie" placeholder="es. sere infrasettimanali, weekend mattina...">
      <span class="ld-hint">Valentina userà queste indicazioni per proporti data e ora della sessione</span>
    </div>

    <div class="ld-group">
      <label class="ld-label" for="ld-msg">Note per Valentina <span class="ld-hint" style="font-style:italic">(opzionale)</span></label>
      <textarea class="ld-textarea" id="ld-msg" name="messaggio" rows="5" placeholder="Qualcosa che vuoi farle sapere prima della sessione..."></textarea>
    </div>

    <!-- BOZZA: placeholder generico, NON testo legale definitivo — vedi nota in cima al file -->
    <div class="ld-group" style="background:rgba(74,140,140,.06);border:1.5px solid rgba(74,140,140,.25);border-radius:6px;padding:1rem 1.25rem;margin-bottom:1.5rem">
      <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;font-size:.9rem;line-height:1.55;color:var(--text)">
        <input type="checkbox" name="termini_accettati" id="ld-termini" required style="flex-shrink:0;margin-top:.2rem;accent-color:var(--teal);width:18px;height:18px">
        <span>
          Ho letto e accetto i <a href="https://valentinarussobg5.com/terms" style="color:var(--teal)">Termini di Servizio</a> e l'<a href="https://valentinarussobg5.com/privacy" style="color:var(--teal)">Informativa Privacy</a>.
          <span style="display:block;margin-top:.35rem;font-size:.8125rem;color:var(--muted)">[BOZZA — testo condizioni di recesso/cancellazione da definire con legal-guardian prima della pubblicazione]</span>
        </span>
      </label>
    </div>

    <button type="submit" class="ld-submit" id="ld-submit-btn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Invia i miei dati →
    </button>
    <p class="ld-submit-note">I tuoi dati vengono usati solo per preparare la lettura · Non vengono condivisi con terzi</p>
  </form>

<?php endif; ?>
</main>
<script>
document.getElementById('ld-form') && document.getElementById('ld-form').addEventListener('submit', function() {
  var btn = document.getElementById('ld-submit-btn');
  if (btn) { btn.disabled = true; btn.innerHTML = 'Invio in corso…'; }
});
</script>
</body>
</html>
