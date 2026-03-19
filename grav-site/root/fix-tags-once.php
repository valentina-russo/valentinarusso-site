<?php
// Debug + fix: legge il frontmatter di un articolo
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

// Estrae solo il frontmatter (tra i due ---)
preg_match('/^---\s*\n(.*?)\n---/s', $content, $fm);
$frontmatter = $fm[1] ?? 'FRONTMATTER NON TROVATO';

echo '<pre>' . htmlspecialchars($frontmatter) . '</pre>';
