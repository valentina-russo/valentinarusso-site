<?php
/**
 * vb-set-title.php — aggiorna il campo title: in item.md di una pagina Grav
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
$parentPath = trim($_POST['parent_path'] ?? '', '/');
$slug       = trim($_POST['slug']        ?? '');
$newTitle   = trim($_POST['new_title']   ?? '');

if (!$slug || !$newTitle) {
    echo json_encode(['ok' => false, 'error' => 'Parametri mancanti (slug, new_title)']);
    exit;
}

/* ── TROVA CARTELLA ── */
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

$segments = $parentPath ? explode('/', $parentPath) : [];
$folder   = findFolder(PAGES_DIR, $segments, $slug);

if (!$folder) {
    echo json_encode(['ok' => false, 'error' => "Cartella '$slug' non trovata in '$parentPath'"]);
    exit;
}

$file = $folder . '/item.md';
if (!file_exists($file)) {
    echo json_encode(['ok' => false, 'error' => 'item.md non trovato in: ' . $folder]);
    exit;
}

/* ── AGGIORNA TITLE ── */
$content = file_get_contents($file);
$lines   = explode("\n", $content);
$found   = false;

foreach ($lines as $i => $line) {
    if (preg_match('/^title:\s*/i', $line)) {
        $escaped   = str_replace('"', '\\"', $newTitle);
        $lines[$i] = 'title: "' . $escaped . '"';
        $found     = true;
        break;
    }
}

if (!$found) {
    // Inserisce dopo il primo --- del frontmatter
    foreach ($lines as $i => $line) {
        if (trim($line) === '---' && $i > 0) {
            array_splice($lines, $i, 0, ['title: "' . str_replace('"', '\\"', $newTitle) . '"']);
            $found = true;
            break;
        }
    }
}

if (!$found) {
    echo json_encode(['ok' => false, 'error' => 'Impossibile trovare o inserire il campo title nel file']);
    exit;
}

file_put_contents($file, implode("\n", $lines));

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

echo json_encode(['ok' => true, 'title' => $newTitle]);
