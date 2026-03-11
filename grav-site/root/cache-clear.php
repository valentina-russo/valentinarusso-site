<?php
/**
 * Grav cache-clear endpoint — chiamato automaticamente dal deploy GitHub Actions.
 * Protetto da token segreto (GitHub Secret: GRAV_CACHE_TOKEN).
 */

$token = $_GET['token'] ?? '';
$expected = getenv('GRAV_CACHE_TOKEN');

// Fallback: legge da file .cache-token nella stessa cartella (non in git)
if (!$expected && file_exists(__DIR__ . '/.cache-token')) {
    $expected = trim(file_get_contents(__DIR__ . '/.cache-token'));
}

if (!$expected || !hash_equals($expected, $token)) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

$cleared = 0;
$errors  = [];

$cacheDir = __DIR__ . '/cache/';

if (is_dir($cacheDir)) {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isFile()) {
            if (@unlink($item->getRealPath())) {
                $cleared++;
            } else {
                $errors[] = $item->getRealPath();
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status'        => 'ok',
    'files_cleared' => $cleared,
    'errors'        => count($errors),
]);
