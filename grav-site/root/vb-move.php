<?php
/**
 * vb-move.php — sposta un articolo tra sezioni (privati ↔ aziende)
 * Chiamato dal plugin valentina-admin via fetch POST.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

define('ADMIN_PASS', 'ValeAdmin2026');
define('PAGES_DIR',  __DIR__ . '/user/pages');
define('CACHE_DIR',  __DIR__ . '/cache');

/* ── AUTH ── */
if (empty($_SESSION['ai_auth'])) {
    $pass = $_POST['pass'] ?? '';
    if ($pass !== ADMIN_PASS) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Non autorizzato']);
        exit;
    }
    $_SESSION['ai_auth'] = true;
}

/* ── INPUT ── */
$oldParent = trim($_POST['old_parent'] ?? '', '/');
$newParent = trim($_POST['new_parent'] ?? '', '/');
$slug      = trim($_POST['slug']       ?? '');

if (!$oldParent || !$newParent || !$slug) {
    echo json_encode(['ok' => false, 'error' => 'Parametri mancanti']);
    exit;
}

/* ── HELPERS ── */
function findFolder($dir, array $segments, $slug) {
    if (empty($segments)) {
        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
            $clean = preg_replace('/^\d+\./', '', basename($d));
            if ($clean === $slug) return $d;
        }
        return null;
    }
    $target = array_shift($segments);
    foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
        $clean = preg_replace('/^\d+\./', '', basename($d));
        if ($clean === $target) {
            $result = findFolder($d, $segments, $slug);
            if ($result !== null) return $result;
        }
    }
    return null;
}

function findDir($dir, array $segments) {
    if (empty($segments)) return $dir;
    $target = array_shift($segments);
    foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
        $clean = preg_replace('/^\d+\./', '', basename($d));
        if ($clean === $target) {
            return findDir($d, $segments);
        }
    }
    return null;
}

/* ── TROVA SORGENTE E DESTINAZIONE ── */
$oldFolder    = findFolder(PAGES_DIR, explode('/', $oldParent), $slug);
$newParentDir = findDir(PAGES_DIR, explode('/', $newParent));

if (!$oldFolder) {
    echo json_encode(['ok' => false, 'error' => "Articolo '$slug' non trovato in '$oldParent'"]);
    exit;
}

if (!$newParentDir) {
    echo json_encode(['ok' => false, 'error' => "Cartella destinazione '$newParent' non trovata"]);
    exit;
}

$newFolder = $newParentDir . '/' . basename($oldFolder);

if (file_exists($newFolder)) {
    echo json_encode(['ok' => false, 'error' => "Articolo '$slug' gia' presente in '$newParent'"]);
    exit;
}

/* ── SPOSTA ── */
if (!rename($oldFolder, $newFolder)) {
    echo json_encode(['ok' => false, 'error' => 'Impossibile spostare la cartella (permessi?)']);
    exit;
}

/* ── SVUOTA CACHE GRAV ── */
if (is_dir(CACHE_DIR)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $f) {
        if ($f->isFile()) @unlink($f->getPathname());
    }
}

echo json_encode(['ok' => true, 'slug' => $slug, 'from' => $oldParent, 'to' => $newParent]);
