<?php
// One-time script: converte tags stringa -> lista YAML in qualsiasi articolo
// ELIMINARE SUBITO DOPO L'USO

define('TOKEN', 'fix-tags-2026-ok');
if (($_GET['token'] ?? '') !== TOKEN) { http_response_code(403); die('No.'); }

$gravPagesDir = __DIR__ . '/../grav-site/user/pages/';
if (!is_dir($gravPagesDir)) {
    $gravPagesDir = __DIR__ . '/user/pages/';
}
if (!is_dir($gravPagesDir)) {
    die('Pages dir not found. Tried: ' . $gravPagesDir);
}

$slug = $_GET['slug'] ?? '';
if (!$slug || strpos($slug, '..') !== false) { die('Slug mancante o invalido.'); }

// cerca ricorsivamente
$found = null;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($gravPagesDir));
foreach ($it as $file) {
    if ($file->getFilename() !== 'item.md') continue;
    if (strpos($file->getPathname(), $slug) !== false) {
        $found = $file->getPathname();
        break;
    }
}

if (!$found) { die('File non trovato per slug: ' . htmlspecialchars($slug)); }

$content = file_get_contents($found);

// Leggi il valore attuale di tags
if (!preg_match('/^tags:\s*(.+)$/m', $content, $m)) {
    die('Campo tags non trovato nel file: ' . htmlspecialchars($found));
}

$tagsRaw = trim($m[1], " '\"\r\n");

// Se è già una lista YAML (inizia con il trattino sulla riga dopo), skip
if (preg_match('/^tags:\s*\n\s+-/m', $content)) {
    die('Tags già in formato lista. Nessuna modifica necessaria. File: ' . htmlspecialchars($found));
}

// Splitta per virgola e genera lista YAML
$tags = array_filter(array_map('trim', explode(',', $tagsRaw)));
$listYaml = "tags:\n";
foreach ($tags as $t) {
    $escaped = str_replace("'", "''", $t);
    $listYaml .= "    - '" . $escaped . "'\n";
}
$listYaml = rtrim($listYaml);

// Sostituisce la riga tags: 'stringa'
$newContent = preg_replace('/^tags:\s*.+$/m', $listYaml, $content);

if ($newContent === $content) {
    die('Nessuna sostituzione effettuata. Controlla il formato del file.');
}

file_put_contents($found, $newContent);

echo '<pre>';
echo "✅ Fix applicato!\n";
echo "File: " . htmlspecialchars($found) . "\n\n";
echo "Tags originali: " . htmlspecialchars($m[1]) . "\n\n";
echo "Nuovo YAML:\n" . htmlspecialchars($listYaml) . "\n";
echo '</pre>';
