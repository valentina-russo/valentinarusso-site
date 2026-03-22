<?php
/**
 * seo-patch.php — Applica seo_title e seo_desc a tutte le pagine Grav
 *
 * Uso UNICO: apri https://valentinarussobg5.com/seo-patch.php?token=<GRAV_CACHE_TOKEN>
 * Il script:
 *   1. Legge ogni .md in user/pages/
 *   2. Parsa il frontmatter YAML
 *   3. Aggiunge/corregge SOLO seo_title e seo_desc (mai tocca il body)
 *   4. Mostra un report di cosa è cambiato
 *
 * SICUREZZA: usa lo stesso token di cache-clear.php
 * DOPO L'USO: eliminare questo file dal server (o lasciarlo — è read-safe con token)
 */

define('PAGES_DIR', __DIR__ . '/user/pages');

// ── Token guard ─────────────────────────────────────────────────────────────
$token = getenv('GRAV_CACHE_TOKEN') ?: (file_exists(__DIR__ . '/../.env')
    ? (function() {
        foreach (file(__DIR__ . '/../.env') as $line) {
            if (strpos($line, 'GRAV_CACHE_TOKEN=') === 0)
                return trim(explode('=', $line, 2)[1]);
        }
        return '';
    })()
    : '');

$provided = $_GET['token'] ?? '';
if (!$token || $provided !== $token) {
    http_response_code(403);
    die('Accesso negato. Usa ?token=<GRAV_CACHE_TOKEN>');
}

// ── Mappa pagina → metadati SEO ottimizzati ──────────────────────────────────
// Formato: 'percorso/relativo/file.md' => ['seo_title' => '...', 'seo_desc' => '...']
// Il percorso è relativo a PAGES_DIR
$SEO_MAP = [
    '01.home/home.md' => [
        'seo_title' => 'Human Design e BG5® — Consulente Certificata',
        'seo_desc'  => 'Consulente BG5® certificata e Human Design Analyst. Scopri il tuo tipo energetico, carriera e relazioni. Consulenze individuali e per aziende italiane.',
    ],
    '02.chi-sono/chi-sono.md' => [
        'seo_title' => 'Analista Certificata BG5® e Human Design — Chi Sono',
        'seo_desc'  => 'Valentina Russo, Certified BG5® Career Analyst e Human Design Analyst IHDS. Proiettrice Mentale 2/4. Guido verso carriera autentica, relazioni e amor proprio.',
    ],
    '03.servizi/servizi.md' => [
        'seo_title' => 'Consulenza e Lettura Human Design — Servizi',
        'seo_desc'  => 'Lettura Human Design individuale per tipo, strategia e autorità interna. Percorsi per amor proprio, relazioni e carriera. Scopri la tua mappa energetica.',
    ],
    '04.blog/blog_list.md' => [
        'seo_title' => 'Blog Human Design e BG5® — Articoli',
        'seo_desc'  => 'Articoli su Human Design, onde emotive, relazioni e amor proprio. Approfondimenti pratici per chi vuole capire il proprio design energetico.',
    ],
    '04.blog/articolo-privati-2/item.md' => [
        'seo_title' => "Cos'è il BG5®: guida semplice al Design System",
        'seo_desc'  => "Il BG5® è l'incrocio tra il tuo tipo di personalità e la tua identità genetica. Scopri come rivela chi sei davvero e come funziona il tuo codice unico.",
    ],
    '04.blog/esplosioni-emotive/item.md' => [
        'seo_title' => "Esplosioni Emotive: l'Onda Tribale in Human Design",
        'seo_desc'  => "Cos'è l'onda emotiva tribale nel Human Design e come gestire le esplosioni emotive. Il tocco fisico come strumento di rilascio della tensione tribale.",
    ],
    '04.blog/la-mente-innamorata-1/item.md' => [
        'seo_title' => 'La Mente Innamorata #1: Human Design e Amore',
        'seo_desc'  => 'Come la mente influenza le relazioni affettive secondo Human Design. Prima parte della serie: mente, amore e il meccanismo del desiderio consapevole.',
    ],
    '04.blog/la-mente-innamorata-2/item.md' => [
        'seo_title' => 'La Mente Innamorata #2: Decondizionamento HD',
        'seo_desc'  => 'Il decondizionamento nelle relazioni secondo Human Design: come liberarsi dai pattern inconsci per scegliere partner e dinamiche con piena consapevolezza.',
    ],
    '04.blog/la-mente-innamorata-3/item.md' => [
        'seo_title' => 'La Mente Innamorata #3: HD e Desiderio',
        'seo_desc'  => 'Desiderio, mente e disegno energetico: le forze nascoste che guidano le nostre scelte romantiche. Terza parte della serie Human Design e amore.',
    ],
    '04.blog/potere-creativo-malinconia/item.md' => [
        'seo_title' => 'Malinconia e Creatività nel Human Design',
        'seo_desc'  => 'La malinconia nei centri definiti del Human Design non è tristezza: è potenziale creativo inesplorato. Scopri come trasformarla in energia e ispirazione.',
    ],
    '04.blog/relazioni-bisogni-emotivi/item.md' => [
        'seo_title' => 'Relazioni e Bisogni nel Human Design',
        'seo_desc'  => "L'importanza dei bisogni emotivi nelle relazioni intime secondo Human Design. Ombre, canale 37 e come la consapevolezza trasforma le dinamiche di coppia.",
    ],
    '05.aziende/aziende_home.md' => [
        'seo_title' => 'BG5® Consulting per Aziende e Team',
        'seo_desc'  => 'Consulenza BG5® certificata per aziende, CEO e team leader. Team engineering, analisi partnership e leadership autentica basata sul Design umano.',
    ],
    '05.aziende/01.servizi/aziende_servizi.md' => [
        'seo_title' => 'BG5® Corporate: Analisi Team e Leadership',
        'seo_desc'  => 'Percorsi BG5® per CEO, soci e HR: analisi partnership, team dynamics, selezione collaboratori. Aumenta il fatturato con il design dei tuoi talenti.',
    ],
    '05.aziende/02.blog/blog_list.md' => [
        'seo_title' => 'Blog BG5® per Aziende e Imprenditori',
        'seo_desc'  => 'Articoli su BG5®, team engineering, leadership e business design. Risorse per imprenditori e manager che usano Human Design in azienda.',
    ],
    '05.aziende/02.blog/bg5-attirare-clienti-ideali-coerenza-personal-branding/item.md' => [
        'seo_title' => 'BG5®: come attrarre i clienti ideali',
        'seo_desc'  => 'Perché con il BG5 attiri certi clienti? La coerenza tra identità autentica e personal branding trasforma la qualità della tua clientela da libero professionista.',
    ],
    '05.aziende/02.blog/potere-del-penta/item.md' => [
        'seo_title' => 'Penta BG5®: Ingegneria dei Team Aziendali',
        'seo_desc'  => 'Come il Penta BG5® costruisce team ad alta performance: 3-5 persone allineate eliminano attriti e aumentano fatturato. Analisi team per aziende italiane.',
    ],
    '05.aziende/03.contatti/contact_form.md' => [
        'seo_title' => 'Candidatura Corporate BG5® — Contatti',
        'seo_desc'  => 'Richiedi una consulenza BG5® per la tua azienda. Analisi partnership, team dynamics e selezione collaboratori con il sistema Human Design.',
    ],
    '06.contatti/contact_form.md' => [
        'seo_title' => 'Prenota la tua Consulenza Human Design',
        'seo_desc'  => 'Contatta Valentina Russo per una lettura Human Design o BG5®. Sessioni individuali per carriera, relazioni e amor proprio. Prenota la tua seduta.',
    ],
    '09.genera-carta/default.md' => [
        'seo_title' => 'Genera la tua Carta Human Design Gratuita',
        'seo_desc'  => 'Calcola gratis la tua carta Human Design inserendo data, ora e luogo di nascita. Scopri il tuo Tipo, Profilo e Autorità interna in pochi secondi.',
    ],
    '10.archivio/default.md' => [
        'seo_title' => 'Archivio Articoli Human Design e BG5®',
        'seo_desc'  => 'Tutti gli articoli di Valentina Russo su Human Design e BG5®. Esplora blog privati e aziende per trovare i contenuti più utili al tuo percorso.',
    ],
];

// ── Funzione patcher ──────────────────────────────────────────────────────────

/**
 * Patcha il frontmatter YAML di un file .md
 * Aggiunge/sovrascrive SOLO seo_title e seo_desc nel blocco ---
 * Non tocca MAI il body del file
 */
function patchFrontmatter(string $filePath, array $newFields): array {
    $content = file_get_contents($filePath);
    if ($content === false) return ['error' => 'Impossibile leggere il file'];

    // Estrai frontmatter
    if (!preg_match('/^---\s*\n(.*?)\n---\s*(\n|$)/s', $content, $m)) {
        return ['error' => 'Nessun frontmatter trovato'];
    }

    $frontmatter = $m[1];
    $body        = substr($content, strlen($m[0]));
    $changes     = [];

    foreach ($newFields as $key => $value) {
        $quoted  = "'" . str_replace("'", "''", $value) . "'";
        $pattern = '/^(' . preg_quote($key, '/') . '\s*:).*$/m';

        if (preg_match($pattern, $frontmatter)) {
            // Campo esiste — aggiorna solo se diverso
            preg_match($pattern, $frontmatter, $existing);
            $existingVal = trim(preg_replace('/^' . preg_quote($key, '/') . '\s*:\s*/', '', $existing[0]));
            // Normalizza (rimuovi quote)
            $existingNorm = trim($existingVal, "'\"");
            if ($existingNorm === $value) {
                $changes[$key] = 'UNCHANGED';
                continue;
            }
            $frontmatter = preg_replace($pattern, $key . ': ' . $quoted, $frontmatter);
            $changes[$key] = 'UPDATED';
        } else {
            // Campo mancante — aggiungi alla fine del frontmatter
            $frontmatter .= "\n" . $key . ': ' . $quoted;
            $changes[$key] = 'ADDED';
        }
    }

    $newContent = "---\n" . $frontmatter . "\n---\n" . $body;
    if (file_put_contents($filePath, $newContent) === false) {
        return ['error' => 'Impossibile scrivere il file'];
    }

    return $changes;
}

// ── Esecuzione ────────────────────────────────────────────────────────────────

$dryRun  = isset($_GET['dry']) && $_GET['dry'] === '1';
$results = [];
$errors  = 0;

foreach ($SEO_MAP as $relPath => $fields) {
    $absPath = PAGES_DIR . '/' . $relPath;
    if (!file_exists($absPath)) {
        $results[$relPath] = ['error' => 'File non trovato'];
        $errors++;
        continue;
    }
    if ($dryRun) {
        $results[$relPath] = ['dry-run' => 'Nessuna modifica applicata'];
        continue;
    }
    $results[$relPath] = patchFrontmatter($absPath, $fields);
    foreach ($results[$relPath] as $v) {
        if (str_starts_with((string)$v, 'error')) $errors++;
    }
}

// ── Output HTML ───────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>SEO Patcher — valentinarussobg5.com</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:900px;margin:0 auto}
h1{color:#38bdf8}h2{color:#94a3b8;font-size:.9rem;margin-top:2rem}
table{width:100%;border-collapse:collapse;font-size:.82rem}
th{background:#1e293b;padding:6px 10px;text-align:left;color:#94a3b8}
td{padding:5px 10px;border-bottom:1px solid #1e293b;vertical-align:top}
.added{color:#4ade80}.updated{color:#facc15}.unchanged{color:#475569}
.error{color:#f87171}.ok{color:#4ade80}
.dry{color:#a78bfa}
pre{background:#1e293b;padding:10px;border-radius:4px;overflow-x:auto;font-size:.75rem}
</style>
</head>
<body>
<h1>🔧 SEO Patcher — <?= $dryRun ? 'DRY RUN (nessuna modifica)' : 'Eseguito' ?></h1>
<p>Pagine elaborate: <?= count($SEO_MAP) ?> &nbsp;|&nbsp; Errori: <?= $errors ?></p>
<?php if ($dryRun): ?>
<p style="color:#a78bfa">⚠️ Dry-run attivo. Aggiungi <code>&dry=0</code> (o rimuovi <code>&dry=1</code>) per applicare le modifiche.</p>
<?php endif; ?>
<h2>Risultati</h2>
<table>
<tr><th>File</th><th>seo_title</th><th>seo_desc</th><th>Note</th></tr>
<?php foreach ($results as $path => $res): ?>
<tr>
  <td><?= htmlspecialchars($path) ?></td>
  <?php if (isset($res['error'])): ?>
    <td colspan="2" class="error">❌ <?= htmlspecialchars($res['error']) ?></td><td></td>
  <?php elseif (isset($res['dry-run'])): ?>
    <td colspan="2" class="dry">DRY RUN</td><td></td>
  <?php else: ?>
    <td class="<?= strtolower($res['seo_title'] ?? 'unchanged') ?>"><?= htmlspecialchars($res['seo_title'] ?? '—') ?></td>
    <td class="<?= strtolower($res['seo_desc'] ?? 'unchanged') ?>"><?= htmlspecialchars($res['seo_desc'] ?? '—') ?></td>
    <td></td>
  <?php endif; ?>
</tr>
<?php endforeach; ?>
</table>
<h2>Legenda</h2>
<ul>
  <li class="added">ADDED — campo aggiunto (non era presente)</li>
  <li class="updated">UPDATED — campo sovrascritto (era presente ma diverso)</li>
  <li class="unchanged">UNCHANGED — già corretto, nessuna modifica</li>
  <li class="error">error — errore (file non trovato o non scrivibile)</li>
</ul>
<?php if (!$dryRun && $errors === 0): ?>
<p class="ok">✅ Tutto OK. Puoi eliminare questo file dal server quando vuoi.</p>
<?php endif; ?>
</body>
</html>
