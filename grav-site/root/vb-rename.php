<?php
/**
 * vb-rename.php — rinomina la cartella di una pagina Grav
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
$parentPath = trim($_POST['parent_path'] ?? '', '/');  // es: "aziende/blog"
$oldSlug    = trim($_POST['old_slug'] ?? '');
$newSlug    = trim($_POST['new_slug'] ?? '');

if (!$parentPath || !$oldSlug || !$newSlug) {
    echo json_encode(['ok' => false, 'error' => 'Parametri mancanti']);
    exit;
}

if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $newSlug)) {
    echo json_encode(['ok' => false, 'error' => 'Slug non valido (usa solo lettere minuscole, numeri e trattini)']);
    exit;
}

/* ── TROVA CARTELLA ── */
/**
 * Cerca ricorsivamente la cartella 'slug' dentro la gerarchia 'segments'.
 * Gestisce prefissi numerici Grav (es: 05.aziende, 02.blog).
 */
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

$segments  = explode('/', $parentPath);
$oldFolder = findFolder(PAGES_DIR, $segments, $oldSlug);

if (!$oldFolder) {
    echo json_encode(['ok' => false, 'error' => "Cartella '$oldSlug' non trovata in '$parentPath'"]);
    exit;
}

$newFolder = dirname($oldFolder) . '/' . $newSlug;

if (file_exists($newFolder)) {
    echo json_encode(['ok' => false, 'error' => "Slug '$newSlug' già in uso"]);
    exit;
}

/* ── RINOMINA ── */
if (!rename($oldFolder, $newFolder)) {
    echo json_encode(['ok' => false, 'error' => 'Impossibile rinominare la cartella (permessi?)']);
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

echo json_encode(['ok' => true, 'old' => $oldSlug, 'new' => $newSlug]);
