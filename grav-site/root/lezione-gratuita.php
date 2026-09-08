<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['csrf_lezione'])) {
    $_SESSION['csrf_lezione'] = bin2hex(random_bytes(32));
}
$csrf  = $_SESSION['csrf_lezione'];
$esito = (string)($_GET['esito'] ?? '');
$da    = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['da'] ?? ''));
?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#1A2332">
<title>Corso Base di Human Design &mdash; prima lezione gratuita il 14 settembre | Valentina Russo</title>
<meta name="description" content="Corso Base di Human Design con Valentina Russo: la prima lezione &egrave; gratuita, in diretta su Zoom luned&igrave; 14 settembre alle 20:30. Iscriviti e ricevi il link.">
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;500;600;700&family=Public+Sans:wght@400;500;600;700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box}
body{margin:0}
.lz{--navy:#1A2332;--brass:#8A5A12;--ottone:#C48A3A;--paper:#F2EEE3;--carta:#fff;
    --ink:#2D2926;--muted:#6B6560;--line:rgba(138,90,18,.28);
    font-family:'Public Sans',system-ui,sans-serif;color:var(--ink);background:var(--paper);line-height:1.65}
.lz h1,.lz h2{font-family:'Fraunces',Georgia,serif;font-weight:600;color:var(--navy);letter-spacing:-.02em;margin:0}
.lz a{color:var(--brass)}
.lz .wrap{max-width:760px;margin:0 auto;padding:0 1.5rem}
.lz .occhiello{font:700 .8125rem/1 Outfit,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:var(--brass);margin:0 0 .9rem}
.lz .hero{padding:4rem 0 2.5rem;text-align:center}
.lz .hero h1{font-size:clamp(2.1rem,6vw,3.3rem);line-height:1.08}
.lz .quando{display:inline-flex;align-items:center;gap:.6rem;margin:1.6rem 0 0;padding:.85rem 1.6rem;
  border-radius:999px;background:var(--navy);color:#fff;font:600 1.05rem Outfit,sans-serif}
.lz .sotto{margin:1.1rem auto 0;max-width:44ch;color:var(--muted)}
.lz .richiamo{margin:.7rem 0 0;font:600 clamp(1.15rem,3vw,1.5rem) Outfit,sans-serif;color:var(--brass)}
.lz .vai{display:inline-flex;align-items:center;gap:.55rem;margin-top:2rem;padding:1rem 2.4rem;
  border-radius:3px;background:var(--navy);color:#fff;font:700 1.05rem Outfit,sans-serif;
  text-decoration:none;box-shadow:0 10px 26px rgba(26,35,50,.18)}
.lz .vai:hover{background:#0F1721;color:#fff}
.lz .vai em{font-style:normal;font-size:1.15em;line-height:1}
.lz #iscriviti{scroll-margin-top:1.5rem}
html{scroll-behavior:smooth}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
.lz .scheda{background:var(--carta);border:1px solid var(--line);border-radius:6px;padding:2.2rem 2rem;margin:2.5rem 0}
.lz .scheda h2{font-size:1.45rem;margin-bottom:1.1rem}
.lz ul.punti{list-style:none;margin:0;padding:0}
.lz ul.punti li{display:flex;gap:.8rem;margin-bottom:.85rem}
.lz ul.punti li::before{content:'';flex:0 0 9px;height:9px;margin-top:.62rem;border-radius:50%;background:var(--ottone)}
.lz label{display:block;font:600 .95rem Outfit,sans-serif;color:var(--navy);margin:0 0 .4rem}
.lz input[type=text],.lz input[type=email]{width:100%;padding:.95rem 1rem;border:1.5px solid var(--line);
  border-radius:4px;font:1rem 'Public Sans',sans-serif;background:#fff;color:var(--ink)}
.lz input:focus{outline:3px solid var(--navy);outline-offset:2px}
.lz .campo{margin-bottom:1.3rem}
.lz .consenso{display:flex;gap:.75rem;align-items:flex-start;font:400 .9rem/1.55 'Public Sans',sans-serif;color:var(--muted)}
.lz .consenso input{flex:0 0 auto;margin-top:.28rem;width:18px;height:18px;accent-color:var(--navy)}
.lz .esca{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.lz button{width:100%;margin-top:1.5rem;padding:1.1rem 1.5rem;border:0;border-radius:3px;cursor:pointer;
  background:var(--navy);color:#fff;font:700 1.05rem Outfit,sans-serif}
.lz button:hover{background:#0F1721}
.lz .avviso{padding:1.1rem 1.3rem;border-radius:4px;margin-bottom:1.8rem;font-size:.98rem}
.lz .avviso.ok{background:#EAF3EC;border:1px solid #9CC3A6;color:#1F4C2C}
.lz .avviso.no{background:#FBEDEA;border:1px solid #DDA79A;color:#7A2E1C}
.lz .dopo{padding:2.5rem 0 1rem;border-top:1px solid var(--line);margin-top:3rem;color:var(--muted);font-size:.95rem}
.lz .dopo strong{color:var(--navy)}
.lz footer{padding:2rem 0 3rem;text-align:center;color:var(--muted);font-size:.85rem}
</style>
</head>
<body>
<div class="lz">
<main class="wrap">

  <section class="hero">
    <p class="occhiello">Con Valentina Russo, analista BG5</p>
    <h1>Corso Base di Human Design</h1>
    <p class="richiamo">La prima lezione &egrave; gratuita</p>
    <div class="quando">Luned&igrave; 14 settembre, ore 20:30</div>
    <p class="sotto">Un&rsquo;ora su Zoom per vedere come si impara a leggere un Bodygraph, il proprio e quello degli altri. Poi decidi con calma se il corso fa per te.</p>
    <a class="vai" href="#iscriviti">Iscriviti <em>&darr;</em></a>
  </section>

<?php if ($esito === 'ok'): ?>
  <div class="avviso ok"><strong>Ci sei.</strong> Fra pochi minuti ricevi un&rsquo;email con il link di Zoom, gi&agrave; attivo, e l&rsquo;evento da aggiungere al calendario. Se non trovi nulla, guarda nella posta indesiderata. Puoi anche <a href="/lezione-zero/lezione-14-settembre.ics">aggiungere subito la lezione al calendario</a>.</div>
<?php elseif ($esito === 'dati'): ?>
  <div class="avviso no">Manca qualcosa: servono il nome, un indirizzo email valido e la spunta sul consenso.</div>
<?php elseif ($esito === 'troppi'): ?>
  <div class="avviso no">Troppe iscrizioni dallo stesso collegamento. Riprova fra un&rsquo;ora.</div>
<?php elseif ($esito !== ''): ?>
  <div class="avviso no">Qualcosa non ha funzionato. Riprova, oppure scrivi a <a href="mailto:info@valentinarussobg5.com">info@valentinarussobg5.com</a>.</div>
<?php endif; ?>

  <div class="scheda">
    <h2>Cosa succede in quell&rsquo;ora</h2>
    <ul class="punti">
      <li>Valentina presenta il programma delle venti lezioni, semestre per semestre.</li>
      <li>Vedi da vicino il suo modo di spiegare, prima di impegnarti con un corso a pagamento.</li>
      <li>Puoi farle tutte le domande che vuoi, comprese quelle sui costi e sugli impegni.</li>
      <li>Resti libero: alla lezione gratuita non &egrave; legata nessuna iscrizione automatica.</li>
    </ul>
  </div>

  <div class="scheda" id="iscriviti">
    <h2>Iscriviti</h2>
    <form method="POST" action="/lezione-zero/iscrivi.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <input type="hidden" name="origine" value="<?= htmlspecialchars($da, ENT_QUOTES) ?>">
      <div class="esca" aria-hidden="true"><label>Citt&agrave;<input type="text" name="citta" tabindex="-1" autocomplete="off"></label></div>

      <div class="campo">
        <label for="lz-nome">Come ti chiami</label>
        <input type="text" id="lz-nome" name="nome" required maxlength="80" autocomplete="given-name" placeholder="Il tuo nome">
      </div>
      <div class="campo">
        <label for="lz-email">La tua email</label>
        <input type="email" id="lz-email" name="email" required autocomplete="email" placeholder="tu@esempio.it">
      </div>
      <label class="consenso">
        <input type="checkbox" name="consenso" required>
        <span>Acconsento a essere ricontattato via email per la lezione del 14 settembre e per gli aggiornamenti sul Corso Base. Ho letto l&rsquo;<a href="/privacy">informativa privacy</a> e posso chiedere la cancellazione quando voglio.</span>
      </label>
      <button type="submit">Mandami il link della lezione</button>
    </form>
  </div>

  <section class="dopo">
    <p><strong>E dopo la lezione gratuita?</strong> Il Corso Base parte luned&igrave; 12 ottobre e prosegue ogni luned&igrave; alle 18, per venti lezioni divise in due semestri. Ci si pu&ograve; iscrivere a un semestre alla volta oppure al percorso intero. I dettagli sono sulla <a href="/corso-base-human-design.html">pagina del corso</a>.</p>
    <p>Se prima vuoi vedere com&rsquo;&egrave; fatto il tuo disegno, il calcolo &egrave; gratuito: <a href="/genera-carta">calcola il tuo Bodygraph</a>.</p>
  </section>

  <footer>
    Valentina Russo &middot; Analista BG5 (Business Group 5) e Human Design<br>
    <a href="/privacy">Privacy</a> &middot; <a href="/contatti">Contatti</a>
  </footer>

</main>
</div>
</body>
</html>
