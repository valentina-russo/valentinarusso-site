<?php
// One-time script: converte tags stringa -> lista YAML in qualsiasi articolo
// ELIMINARE SUBITO DOPO L'USO

define('TOKEN', 'fix-tags-2026-ok');
if (($_GET['token'] ?? '') !== TOKEN) { http_response_code(403); die('No.'); }

$gravPagesDir = __DIR__ . '/user/pages/';
if (!is_dir($gravPagesDir)) {
    $gravPagesDir = __DIR__ . '/../user/pages/';
}
if (!is_dir($gravPagesDir)) {
    die('Pages dir not found. __DIR__=' . __DIR__);
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

if (!$found) { die('File non trovato per slug: ' . htmlspecialchars($slug) . ' in ' . $gravPagesDir); }

$content = file_get_contents($found);

// Trova riga tags: nel frontmatter (linea per linea, nessun preg_replace multiline)
$lines  = explode("\n", $content);
$tagsLineIdx = null;
$tagsRaw     = null;
foreach ($lines as $i => $line) {
    if (preg_match('/^tags:\s*(.+)$/', $line, $m)) {
        $tagsLineIdx = $i;
        $tagsRaw     = trim($m[1], " '\"\r");
        break;
    }
    // se tags è già lista YAML (riga "tags:" senza valore)
    if (preg_match('/^tags:\s*$/', $line)) {
        die('Tags gia\' in formato lista YAML. Nessuna modifica necessaria.');
    }
}

if ($tagsLineIdx === null) {
    die('Campo tags non trovato. Contenuto inizio file:<br><pre>' . htmlspecialchars(substr($content, 0, 500)) . '</pre>');
}

// Costruisce le righe lista
$tags     = array_filter(array_map('trim', explode(',', $tagsRaw)));
$newLines = ["tags:"];
foreach ($tags as $t) {
    $escaped  = str_replace("'", "''", $t);
    $newLines[] = "    - '" . $escaped . "'";
}

// Sostituisce la riga tags: con il blocco lista
array_splice($lines, $tagsLineIdx, 1, $newLines);
$newContent = implode("\n", $lines);

file_put_contents($found, $newContent);

echo '<pre>';
echo "OK Fix applicato!\n";
echo "File: " . htmlspecialchars($found) . "\n\n";
echo "Tags originali: " . htmlspecialchars($tagsRaw) . "\n\n";
echo "Nuovo blocco:\n" . htmlspecialchars(implode("\n", $newLines)) . "\n";
echo '</pre>';
