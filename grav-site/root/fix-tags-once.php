<?php
// One-time: converte geo_content array YAML -> stringa
// ELIMINARE SUBITO DOPO L'USO

define('TOKEN', 'fix-tags-2026-ok');
if (($_GET['token'] ?? '') !== TOKEN) { http_response_code(403); die('No.'); }

$gravPagesDir = __DIR__ . '/user/pages/';
if (!is_dir($gravPagesDir)) { $gravPagesDir = __DIR__ . '/../user/pages/'; }
if (!is_dir($gravPagesDir)) { die('Pages dir not found. __DIR__=' . __DIR__); }

$slug = $_GET['slug'] ?? '';
if (!$slug || strpos($slug, '..') !== false) { die('Slug mancante o invalido.'); }

$found = null;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($gravPagesDir));
foreach ($it as $file) {
    if ($file->getFilename() !== 'item.md') continue;
    if (strpos($file->getPathname(), $slug) !== false) { $found = $file->getPathname(); break; }
}
if (!$found) { die('File non trovato per slug: ' . htmlspecialchars($slug)); }

$content = file_get_contents($found);
$lines   = explode("\n", $content);
$fixed   = [];

foreach ($lines as $i => $line) {
    // Rileva riga geo_content: [...]  oppure  geo_content: ["..."]
    if (preg_match('/^(geo_content):\s*(\[.+\])\s*$/', $line, $m)) {
        // Decodifica il JSON array
        $arr = json_decode($m[2], true);
        if (is_array($arr)) {
            // Unisce in un'unica stringa, sostituisce ' con ''
            $joined  = implode(' ', $arr);
            $escaped = str_replace("'", "''", $joined);
            $fixed[] = "geo_content: '" . $escaped . "'";
            continue;
        }
    }
    $fixed[] = $line;
}

$newContent = implode("\n", $fixed);

if ($newContent === $content) {
    die('Nessuna modifica: geo_content non e\' un array JSON su una riga, o non trovato.');
}

file_put_contents($found, $newContent);
echo '<pre>OK - geo_content convertito da array a stringa singola.</pre>';
