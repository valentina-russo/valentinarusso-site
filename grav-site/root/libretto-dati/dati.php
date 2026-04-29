<?php
/**
 * Libretto d'Istruzioni — Raccolta dati post-pagamento
 *
 * Mostrata dopo il successo di Stripe Checkout.
 * Query params: ?session_id=cs_xxx&tier=base|avanzato
 *
 * Se ?success=1 → mostra il messaggio di conferma finale.
 */

declare(strict_types=1);

// CSRF (SEC-PAY-002): generate per-session token, used to verify form POST in invia.php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$tier      = strtolower(trim($_GET['tier'] ?? 'avanzato'));
if (!in_array($tier, ['base', 'avanzato'], true)) { $tier = 'avanzato'; }
$tierLabel = $tier === 'base' ? 'Base' : 'Avanzato';
$tierPrice = $tier === 'base' ? '€90' : '€147';
$sessionId = htmlspecialchars($_GET['session_id'] ?? '', ENT_QUOTES, 'UTF-8');
$success   = isset($_GET['success']) && $_GET['success'] === '1';

// Messaggio di errore se arriva da submit con problemi
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';

$pageTitle = $success
    ? "Ricevuto! · Libretto d'Istruzioni Human Design"
    : "Inserisci i tuoi dati · Libretto d'Istruzioni Human Design";
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;1,400;1,700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    :root {
      --rosa:    #C9768A;
      --teal:    #4A8C8C;
      --navy:    #1A2332;
      --gold:    #C48A3A;
      --parch:   #FAF7F5;
      --soft:    #EAE5E1;
      --text:    #2D2926;
      --muted:   #6B6560;
      --white:   #FFFFFF;
    }

    body {
      margin: 0;
      font-family: 'Outfit', sans-serif;
      background: var(--parch);
      color: var(--text);
      min-height: 100vh;
    }

    /* ── Header sottile ── */
    .ld-header {
      background: var(--white);
      border-bottom: 1px solid var(--soft);
      padding: .875rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .ld-logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.0625rem;
      font-style: italic;
      color: var(--navy);
      text-decoration: none;
    }
    .ld-badge {
      font-size: .75rem;
      font-weight: 600;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--teal);
      background: rgba(74,140,140,.1);
      padding: .3rem .875rem;
      border-radius: 100px;
    }

    /* ── Layout principale ── */
    .ld-main {
      max-width: 620px;
      margin: 0 auto;
      padding: 3rem 1.5rem 5rem;
    }

    /* ── Progress indicator ── */
    .ld-progress {
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-bottom: 2.5rem;
      font-size: .8125rem;
      font-weight: 600;
      color: var(--muted);
      letter-spacing: .05em;
    }
    .ld-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--soft);
      flex-shrink: 0;
    }
    .ld-dot--active { background: var(--teal); }
    .ld-dot--done   { background: var(--teal); opacity: .4; }
    .ld-progress-label { color: var(--teal); }

    /* ── Titolo sezione ── */
    .ld-eyebrow {
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .18em;
      text-transform: uppercase;
      color: var(--rosa);
      margin-bottom: .75rem;
    }
    .ld-h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.75rem, 4vw, 2.25rem);
      font-weight: 700;
      font-style: italic;
      color: var(--navy);
      margin: 0 0 .75rem;
      line-height: 1.2;
    }
    .ld-intro {
      font-size: 1rem;
      line-height: 1.65;
      color: var(--muted);
      margin: 0 0 2.5rem;
    }

    /* ── Box conferma pagamento ── */
    .ld-confirm-box {
      background: var(--white);
      border: 1.5px solid rgba(74,140,140,.25);
      border-left: 3px solid var(--teal);
      border-radius: 6px;
      padding: 1.125rem 1.25rem;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .ld-confirm-icon {
      flex-shrink: 0;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: rgba(74,140,140,.12);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--teal);
      font-size: 1.125rem;
    }
    .ld-confirm-text { font-size: .9rem; line-height: 1.5; }
    .ld-confirm-text strong { color: var(--navy); }

    /* ── Form ── */
    .ld-form { margin-top: .5rem; }
    .ld-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .ld-group { margin-bottom: 1.25rem; }
    .ld-label {
      display: block;
      font-size: .875rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: .375rem;
    }
    .ld-label .ld-req { color: var(--rosa); margin-left: 2px; }
    .ld-hint {
      display: block;
      font-size: .8rem;
      color: var(--muted);
      font-weight: 400;
      margin-top: .2rem;
    }
    .ld-input,
    .ld-textarea {
      width: 100%;
      padding: .8rem 1rem;
      background: var(--white);
      border: 1.5px solid var(--soft);
      border-radius: 4px;
      color: var(--text);
      font-family: 'Outfit', sans-serif;
      font-size: 1rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .ld-input:focus,
    .ld-textarea:focus {
      outline: none;
      border-color: var(--teal);
      box-shadow: 0 0 0 3px rgba(74,140,140,.12);
    }
    .ld-input::placeholder,
    .ld-textarea::placeholder { color: #B0A8A3; }
    .ld-textarea {
      resize: vertical;
      min-height: 130px;
      line-height: 1.6;
    }

    /* ── Divider ── */
    .ld-divider {
      border: none;
      border-top: 1px solid var(--soft);
      margin: 1.75rem 0;
    }

    /* ── Submit ── */
    .ld-submit {
      width: 100%;
      padding: 1.0625rem;
      background: var(--rosa);
      color: var(--white);
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 1.0625rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background .2s, transform .15s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
    }
    .ld-submit:hover { background: #B5697B; transform: translateY(-1px); }
    .ld-submit:disabled { opacity: .6; cursor: not-allowed; transform: none; }
    .ld-submit-note {
      text-align: center;
      font-size: .8125rem;
      color: var(--muted);
      margin-top: .75rem;
    }

    /* ── Error banner ── */
    .ld-error {
      background: #FEF2F2;
      border: 1.5px solid #FCA5A5;
      border-radius: 6px;
      padding: 1rem 1.25rem;
      color: #B91C1C;
      font-size: .9rem;
      margin-bottom: 1.5rem;
    }

    /* ── SUCCESS STATE ── */
    .ld-success {
      text-align: center;
      padding: 3rem 1rem;
    }
    .ld-success__icon {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: rgba(74,140,140,.12);
      border: 2px solid var(--teal);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 2rem;
      color: var(--teal);
    }
    .ld-success__h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 700;
      font-style: italic;
      color: var(--navy);
      margin: 0 0 1rem;
    }
    .ld-success__body {
      font-size: 1.0625rem;
      line-height: 1.7;
      color: var(--text);
      max-width: 480px;
      margin: 0 auto 2rem;
    }
    .ld-success__box {
      background: var(--white);
      border: 1px solid var(--soft);
      border-radius: 8px;
      padding: 1.5rem 1.75rem;
      max-width: 440px;
      margin: 0 auto 2.5rem;
      text-align: left;
    }
    .ld-success__box h3 {
      font-family: 'Outfit', sans-serif;
      font-size: .8125rem;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--rosa);
      margin: 0 0 1rem;
    }
    .ld-success__box ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .ld-success__box li {
      font-size: .9375rem;
      line-height: 1.6;
      color: var(--text);
      padding: .4rem 0;
      border-bottom: 1px solid var(--soft);
      display: flex;
      gap: .75rem;
    }
    .ld-success__box li:last-child { border-bottom: none; }
    .ld-success__box li::before {
      content: '·';
      color: var(--teal);
      font-weight: 800;
      flex-shrink: 0;
    }
    .ld-back-link {
      font-size: .9rem;
      color: var(--rosa);
      text-decoration: none;
    }
    .ld-back-link:hover { text-decoration: underline; }

    @media (max-width: 600px) {
      .ld-row { grid-template-columns: 1fr; }
      .ld-main { padding: 2rem 1rem 4rem; }
    }
  </style>
</head>
<body>

<!-- Header -->
<header class="ld-header">
  <a href="https://valentinarussobg5.com" class="ld-logo">Valentina Russo</a>
  <span class="ld-badge">Ordine in corso</span>
</header>

<main class="ld-main">
<?php if ($success): ?>

  <!-- ══════════════════════════════════════════
       STATO SUCCESSO — dopo l'invio del form
  ══════════════════════════════════════════ -->
  <div class="ld-success">
    <div class="ld-success__icon" aria-hidden="true">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <h1 class="ld-success__h1">Tutto ricevuto.</h1>
    <p class="ld-success__body">
      Valentina preparerà il tuo <strong>Libretto d'Istruzioni <?= $tierLabel ?></strong> personalmente.<br>
      Lo riceverai all'email che hai indicato <strong>entro 2–4 giorni lavorativi</strong>.
    </p>
    <div class="ld-success__box">
      <h3>Cosa succede adesso</h3>
      <ul>
        <li>Valentina calcola la tua carta Human Design sui dati che hai inserito</li>
        <li>Prepara il documento e lo revisiona capitolo per capitolo</li>
        <li>Il PDF arriva via email, già pronto da leggere su qualsiasi dispositivo</li>
        <li>Per domande o chiarimenti: <a href="mailto:info@valentinarussobg5.com" style="color:var(--rosa)">info@valentinarussobg5.com</a></li>
      </ul>
    </div>
    <a href="https://valentinarussobg5.com" class="ld-back-link">← Torna al sito</a>
  </div>

<?php else: ?>

  <!-- ══════════════════════════════════════════
       FORM RACCOLTA DATI
  ══════════════════════════════════════════ -->

  <!-- Progress -->
  <div class="ld-progress">
    <span class="ld-dot ld-dot--done"></span>
    <span style="color:var(--muted)">Pagamento</span>
    <span style="color:var(--soft);margin:0 .25rem">→</span>
    <span class="ld-dot ld-dot--active"></span>
    <span class="ld-progress-label">Dati di nascita</span>
    <span style="color:var(--soft);margin:0 .25rem">→</span>
    <span class="ld-dot"></span>
    <span style="color:var(--muted)">Il tuo Libretto</span>
  </div>

  <!-- Conferma pagamento ricevuto -->
  <div class="ld-confirm-box">
    <div class="ld-confirm-icon" aria-hidden="true">✓</div>
    <div class="ld-confirm-text">
      <strong>Hai scelto il Libretto <?= $tierLabel ?>, hai fatto la cosa giusta.</strong><br>
      Pagamento confermato (<?= $tierPrice ?>). Adesso abbiamo bisogno di alcune informazioni per calcolare la tua carta Human Design.
    </div>
  </div>

  <p class="ld-eyebrow">Passo 2 di 2</p>
  <h1 class="ld-h1">I tuoi dati di nascita</h1>
  <p class="ld-intro">
    Questi dati servono per calcolare la tua carta Human Design precisa. L'ora di nascita ci serve per essere precisi: se non la conosci esatta, inserisci un'approssimazione nel messaggio in fondo.
  </p>

  <?php if ($error): ?>
  <div class="ld-error" role="alert">
    <?php if ($error === 'missing'): ?>
      Compila tutti i campi obbligatori (marcati con *) prima di procedere.
    <?php else: ?>
      Si è verificato un errore nell'invio. Riprova o scrivi a <a href="mailto:info@valentinarussobg5.com">info@valentinarussobg5.com</a>.
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <form class="ld-form" method="POST" action="/libretto-dati/invia.php" id="ld-form">
    <input type="hidden" name="tier"       value="<?= htmlspecialchars($tier, ENT_QUOTES) ?>">
    <input type="hidden" name="tier_label" value="<?= htmlspecialchars($tierLabel, ENT_QUOTES) ?>">
    <input type="hidden" name="tier_price" value="<?= htmlspecialchars($tierPrice, ENT_QUOTES) ?>">
    <input type="hidden" name="session_id" value="<?= $sessionId ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

    <!-- Nome e email -->
    <div class="ld-row">
      <div class="ld-group">
        <label class="ld-label" for="ld-nome">Nome e cognome <span class="ld-req">*</span></label>
        <input class="ld-input" type="text" id="ld-nome" name="nome"
               required autocomplete="name" placeholder="es. Maria Rossi"
               value="<?= htmlspecialchars($_GET['nome'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div class="ld-group">
        <label class="ld-label" for="ld-email">Email <span class="ld-req">*</span></label>
        <input class="ld-input" type="email" id="ld-email" name="email"
               required autocomplete="email" placeholder="tu@esempio.it"
               value="<?= htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES) ?>">
        <span class="ld-hint">L'email dove riceverai il PDF</span>
      </div>
    </div>

    <hr class="ld-divider">

    <!-- Dati di nascita -->
    <div class="ld-row">
      <div class="ld-group">
        <label class="ld-label" for="ld-data">Data di nascita <span class="ld-req">*</span></label>
        <input class="ld-input" type="date" id="ld-data" name="birth_date" required>
      </div>
      <div class="ld-group">
        <label class="ld-label" for="ld-ora">Ora di nascita <span class="ld-req">*</span></label>
        <input class="ld-input" type="time" id="ld-ora" name="birth_time" required>
        <span class="ld-hint">Se non è esatta, inserisci un'approssimazione e scrivi i dettagli nel messaggio sotto</span>
      </div>
    </div>

    <div class="ld-group">
      <label class="ld-label" for="ld-luogo">Luogo di nascita <span class="ld-req">*</span></label>
      <input class="ld-input" type="text" id="ld-luogo" name="birth_place"
             required autocomplete="off" placeholder="es. Milano, Italia">
    </div>

    <hr class="ld-divider">

    <!-- Messaggio situazione attuale -->
    <div class="ld-group">
      <label class="ld-label" for="ld-msg">
        In che momento ti trovi?
        <span class="ld-hint" style="font-style:italic">Opzionale, ma più ci dici e più il Libretto sarà preciso per la tua situazione attuale</span>
      </label>
      <textarea class="ld-textarea" id="ld-msg" name="messaggio"
                placeholder="Qualcosa del tipo: sono in un momento di transizione professionale e non so bene che direzione prendere... oppure: ho appena sentito parlare di Human Design e voglio capire se posso smettere di fare cose che mi prosciugano... Non c'è un formato giusto o sbagliato."
                rows="6"></textarea>
    </div>

    <!-- BLOCCO RECESSO OBBLIGATORIO — art. 59 c.1 lett. o) D.Lgs. 206/2005 — NON RIMUOVERE -->
    <div class="ld-group" style="background:rgba(74,140,140,.06);border:1.5px solid rgba(74,140,140,.25);border-radius:6px;padding:1rem 1.25rem;margin-bottom:1.5rem">
      <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;font-size:.9rem;line-height:1.55;color:var(--text)">
        <input type="checkbox" name="recesso_waiver" id="ld-recesso-waiver" required
               style="flex-shrink:0;margin-top:.2rem;accent-color:var(--teal);width:18px;height:18px">
        <span>
          <strong>Richiedo l'esecuzione immediata del contratto e confermo di aver compreso che, avviando la preparazione del Libretto d'Istruzioni personalizzato, <u>perdo il diritto di recesso</u> previsto dall'art. 52 del Codice del Consumo, ai sensi dell'art. 59, comma 1, lett. o).</strong>
          <span style="display:block;margin-top:.35rem;font-size:.8125rem;color:var(--muted)">Il documento è creato su misura sulla tua carta Human Design. Una volta avviata la preparazione, non è possibile annullare l'ordine o richiedere un rimborso.</span>
        </span>
      </label>
    </div>

    <button type="submit" class="ld-submit" id="ld-submit-btn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
      Invia i miei dati →
    </button>
    <p class="ld-submit-note">I tuoi dati vengono usati solo per preparare il tuo Libretto · Non vengono condivisi con terzi</p>

  </form>

<?php endif; ?>
</main>

<script>
// Prevenire doppio submit
document.getElementById('ld-form') && document.getElementById('ld-form').addEventListener('submit', function() {
  var btn = document.getElementById('ld-submit-btn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M9 12l2 2 4-4"/></svg> Invio in corso…';
  }
});
</script>

</body>
</html>
