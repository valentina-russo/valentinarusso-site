<?php
// Temporary cache clear — auto-delete dopo l'uso
if (($_GET['t'] ?? '') !== 'vr2026') { http_response_code(403); die(); }

// Grav cache directory
$cacheDir = dirname(__DIR__) . '/cache/';
if (!is_dir($cacheDir)) {
    // Try alternate paths
    $cacheDir = __DIR__ . '/cache/';
}

function rmRecursive(string $dir): int {
    $count = 0;
    if (!is_dir($dir)) return 0;
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        $count++;
    }
    return $count;
}

$n = rmRecursive($cacheDir);
echo "Cache svuotata — {$n} oggetti rimossi. Cancella questo file.";
