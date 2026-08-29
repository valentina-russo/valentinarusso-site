<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

// Foto profilo: visibile a chi e' loggato nel corso e condivide una classe
// con la persona (compare nel forum), oppure e' l'ammnistratrice, oppure e'
// la foto propria. Mai un link diretto al file (stesso principio di materiale.php).
$user = corsoRequireStudent();
$uid  = (int)$user['id'];
$isAdmin = corsoIsAdmin($uid);

$targetId = (int)($_GET['id'] ?? 0);

if ($targetId !== $uid && !$isAdmin) {
    $stmt = hdDb()->prepare(
        'SELECT 1 FROM course_enrollments e1
         JOIN course_enrollments e2 ON e2.cohort_id = e1.cohort_id
         WHERE e1.user_id = ? AND e2.user_id = ? LIMIT 1'
    );
    $stmt->execute([$uid, $targetId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit('Non consentito.');
    }
}

$stmt = hdDb()->prepare('SELECT avatar_path FROM hd_users WHERE id = ?');
$stmt->execute([$targetId]);
$path = $stmt->fetchColumn();

if (!$path || !file_exists($path)) {
    http_response_code(404);
    exit('Non trovato.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
