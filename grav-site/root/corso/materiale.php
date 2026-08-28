<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

// Serve i PDF autenticati - mai un link diretto al file (R11: nessun accesso
// senza controllo server-side, anche conoscendo il path)

$user = corsoRequireStudent();
$lessonId = (int)($_GET['lesson'] ?? 0);
$type = $_GET['type'] ?? '';

if (!in_array($type, ['slide', 'exercise'], true)) {
    http_response_code(400);
    exit('Richiesta non valida.');
}

$stmt = hdDb()->prepare('SELECT course_id, pdf_slide_path, pdf_exercise_path FROM lessons WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Non trovato.');
}

corsoRequireEnrollment((int)$user['id'], (int)$lesson['course_id']);

$path = $type === 'slide' ? $lesson['pdf_slide_path'] : $lesson['pdf_exercise_path'];
if (!$path || !file_exists($path)) {
    http_response_code(404);
    exit('File non disponibile.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
