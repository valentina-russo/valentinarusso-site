<?php
/**
 * Pagina di ringraziamento del Corso Base: dove atterra chi ha pagato.
 *
 * Ci arriva invia.php dopo aver verificato il pagamento su Stripe, creato
 * l'account e mandato le due email. Il parametro "accesso" dice soltanto quale
 * versione del primo passo mostrare: e' testo, non un permesso, quindi anche se
 * qualcuno lo cambia a mano non apre niente.
 *
 *   ?course=bg5-foundation-s1&accesso=nuovo|esistente|manuale
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$course  = strtolower(trim($_GET['course'] ?? 'bg5-foundation'));
$product = course_get($course);
if (!$product) { http_response_code(404); echo 'Corso non trovato.'; exit; }

$accesso = (string)($_GET['accesso'] ?? 'nuovo');
if (!in_array($accesso, ['nuovo', 'esistente', 'manuale'], true)) { $accesso = 'nuovo'; }

$prezzo = '&euro;' . number_format($product['amount'] / 100, 0, ',', '.');

$tuttiPassi = [
    'nuovo' => [
        'titolo' => 'Apri la mail e scegli la password',
        'testo'  => 'All&rsquo;indirizzo che hai indicato trovi la mail con il tuo accesso alla '
                  . 'piattaforma del corso. Dentro c&rsquo;&egrave; un link per scegliere la password: '
                  . 'vale sette giorni.',
    ],
    'esistente' => [
        'titolo' => 'Entri con le credenziali che hai gi&agrave;',
        'testo'  => 'Il tuo indirizzo era gi&agrave; registrato: ti abbiamo aggiunta alla classe, '
                  . 'quindi entri nella piattaforma con la password che usi di solito.',
    ],
    'manuale' => [
        'titolo' => 'Valentina ti apre l&rsquo;accesso',
        'testo'  => 'La tua iscrizione &egrave; registrata. L&rsquo;accesso alla piattaforma te lo apre '
                  . 'Valentina a mano, entro 48 ore, e ti arriva per email.',
    ],
];
$passo = $tuttiPassi[$accesso];
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sei dentro &middot; <?= htmlspecialchars($product['name'], ENT_QUOTES) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;1,400;1,700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root { --rosa:#C9768A; --teal:#4A8C8C; --navy:#1A2332; --gold:#C48A3A; --parch:#FAF7F5; --soft:#EAE5E1; --text:#2D2926; --muted:#6B6560; --white:#FFFFFF; }
body { margin:0; font-family:'Outfit',sans-serif; background:var(--parch); color:var(--text); min-height:100vh; }
.gr-header { background:var(--white); border-bottom:1px solid var(--soft); padding:.875rem 1.5rem; display:flex; align-items:center; justify-content:space-between; }
.gr-logo { font-family:'Playfair Display',serif; font-size:1.0625rem; font-style:italic; color:var(--navy); text-decoration:none; }
.gr-badge { font-size:.75rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--teal); background:rgba(74,140,140,.1); padding:.3rem .875rem; border-radius:100px; }
.gr-main { max-width:620px; margin:0 auto; padding:3.5rem 1.5rem 5rem; }
.gr-cima { text-align:center; margin-bottom:2.5rem; }
.gr-segno { width:72px; height:72px; border-radius:50%; background:rgba(74,140,140,.12); border:2px solid var(--teal); display:flex; align-items:center; justify-content:center; margin:0 auto 1.75rem; color:var(--teal); }
.gr-h1 { font-family:'Playfair Display',serif; font-size:clamp(2rem,5vw,2.5rem); font-weight:700; font-style:italic; color:var(--navy); margin:0 0 .875rem; line-height:1.15; }
.gr-sotto { font-size:1.0625rem; line-height:1.65; color:var(--text); max-width:440px; margin:0 auto; }
.gr-scontrino { display:inline-flex; flex-wrap:wrap; justify-content:center; align-items:baseline; gap:.4rem .75rem; background:var(--white); border:1px solid var(--soft); border-radius:100px; padding:.6rem 1.25rem; margin-top:1.5rem; font-size:.9rem; }
.gr-scontrino strong { color:var(--navy); }
.gr-scontrino span { color:var(--muted); }
.gr-titolo { font-size:.8125rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--rosa); margin:0 0 1.125rem; }
.gr-passi { list-style:none; margin:0 0 2.25rem; padding:0; counter-reset:passo; }
.gr-passi li { counter-increment:passo; position:relative; background:var(--white); border:1px solid var(--soft); border-radius:8px; padding:1.25rem 1.5rem 1.25rem 3.75rem; margin-bottom:.75rem; }
.gr-passi li::before { content:counter(passo); position:absolute; left:1.25rem; top:1.25rem; width:26px; height:26px; border-radius:50%; background:var(--navy); color:var(--white); font-size:.8125rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
.gr-passi h3 { font-size:1rem; font-weight:700; color:var(--navy); margin:0 0 .3rem; }
.gr-passi p { font-size:.9375rem; line-height:1.6; color:var(--muted); margin:0; }
.gr-btn { display:flex; align-items:center; justify-content:center; gap:.5rem; padding:1.0625rem; background:var(--rosa); color:var(--white); font-weight:700; font-size:1.0625rem; text-decoration:none; border-radius:4px; }
.gr-btn:hover { background:#B5697B; }
.gr-nota { text-align:center; font-size:.8125rem; line-height:1.6; color:var(--muted); margin:.875rem 0 0; }
.gr-aiuto { background:var(--white); border:1px solid var(--soft); border-left:3px solid var(--gold); border-radius:6px; padding:1.25rem 1.5rem; margin-top:2.5rem; }
.gr-aiuto h3 { font-size:1rem; font-weight:700; color:var(--navy); margin:0 0 .4rem; }
.gr-aiuto p { font-size:.9375rem; line-height:1.6; color:var(--muted); margin:0 0 .6rem; }
.gr-recapiti { display:flex; flex-wrap:wrap; gap:.4rem 1.25rem; font-size:.9375rem; }
.gr-recapiti a { color:var(--navy); font-weight:600; text-decoration:none; }
.gr-recapiti a:hover { text-decoration:underline; }
.gr-torna { display:block; text-align:center; margin-top:2.5rem; font-size:.9rem; color:var(--rosa); text-decoration:none; }
.gr-torna:hover { text-decoration:underline; }
@media (max-width:600px) { .gr-main { padding:2.25rem 1rem 4rem; } .gr-passi li { padding:1.125rem 1.125rem 1.125rem 3.25rem; } .gr-passi li::before { left:1rem; top:1.125rem; } }
</style>
</head>
<body>
<header class="gr-header">
  <a href="https://valentinarussobg5.com" class="gr-logo">Valentina Russo</a>
  <span class="gr-badge">Iscrizione confermata</span>
</header>
<main class="gr-main">

  <div class="gr-cima">
    <div class="gr-segno" aria-hidden="true">
      <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </div>
    <h1 class="gr-h1">Sei dentro.</h1>
    <p class="gr-sotto">Il pagamento &egrave; andato a buon fine e il tuo posto al corso &egrave; registrato.</p>
    <p class="gr-scontrino"><strong><?= htmlspecialchars($product['name'], ENT_QUOTES) ?></strong> <span><?= $prezzo ?> &middot; pagamento ricevuto</span></p>
  </div>

  <p class="gr-titolo">Cosa succede adesso</p>
  <ol class="gr-passi">
    <li>
      <h3><?= $passo['titolo'] ?></h3>
      <p><?= $passo['testo'] ?></p>
    </li>
    <li>
      <h3>Dentro trovi la tua classe</h3>
      <p>Lezioni, materiali, registrazioni e lo spazio per i compiti. Si aggiorna lezione per lezione, insieme al corso.</p>
    </li>
    <li>
      <h3>Si parte luned&igrave; 12 ottobre alle 18</h3>
      <p>Lezioni dal vivo su Zoom, ogni luned&igrave;. Il link fisso e il calendario del semestre ti arrivano per email da Valentina.</p>
    </li>
  </ol>

  <a class="gr-btn" href="https://valentinarussobg5.com/corso/login.php">Vai alla piattaforma del corso &rarr;</a>
  <p class="gr-nota">Se la mail non arriva entro pochi minuti, guarda tra la posta indesiderata:<br>a volte la prima mail di un mittente nuovo finisce l&igrave;.</p>

  <div class="gr-aiuto">
    <h3>Qualcosa non torna?</h3>
    <p>Scrivi o chiama, rispondiamo noi. Meglio chiedere subito che restare fuori dalla prima lezione.</p>
    <p class="gr-recapiti">
      <a href="mailto:info@valentinarussobg5.com">info@valentinarussobg5.com</a>
      <a href="tel:+393791037653">+39 379 103 7653</a>
      <a href="https://wa.me/393791037653" target="_blank" rel="noopener">WhatsApp</a>
    </p>
  </div>

  <a class="gr-torna" href="https://valentinarussobg5.com/corso-base-human-design">&larr; Torna alla pagina del corso</a>
</main>
</body>
</html>
