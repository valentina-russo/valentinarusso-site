<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

// Serve PDF, audio e registrazioni autenticati - mai un link diretto al file
// (R11: nessun accesso senza controllo server-side, anche conoscendo il path)

$user = corsoRequireStudent();
$lessonId = (int)($_GET['lesson'] ?? 0);
$type = $_GET['type'] ?? '';
$download = isset($_GET['scarica']); // Scarica forza il download, Visualizza lo apre inline

if (!in_array($type, ['slide', 'exercise', 'audio', 'video'], true)) {
    http_response_code(400);
    exit('Richiesta non valida.');
}

$stmt = hdDb()->prepare('SELECT cohort_id, pdf_slide_path, pdf_exercise_path, audio_path, bunny_video_id FROM lessons WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Non trovato.');
}

corsoRequireEnrollment((int)$user['id'], (int)$lesson['cohort_id']);

$path = match ($type) {
    'slide'    => $lesson['pdf_slide_path'],
    'exercise' => $lesson['pdf_exercise_path'],
    'audio'    => $lesson['audio_path'],
    // Una registrazione tenuta sul nostro disco. corsoVideoSorgente accetta
    // solo un nome di file dentro video/, quindi da qui non si esce dalla
    // cartella nemmeno provandoci.
    'video'    => (($v = corsoVideoSorgente($lesson['bunny_video_id'])) && $v['tipo'] === 'file')
                    ? __DIR__ . '/video/' . $v['id']
                    : null,
};
if (!$path || !file_exists($path)) {
    http_response_code(404);
    exit('File non disponibile.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'pdf' => 'application/pdf',
    'mp3' => 'audio/mpeg',
    'm4a' => 'audio/mp4',
    'wav' => 'audio/wav',
    'mp4', 'm4v' => 'video/mp4',
    'webm' => 'video/webm',
    default => 'application/octet-stream',
};

$size = filesize($path);
$disposition = ($download ? 'attachment' : 'inline') . '; filename="' . basename($path) . '"';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition);
header('X-Content-Type-Options: nosniff');

// Audio e video devono poter scorrere avanti/indietro senza riscaricare il
// file intero: serve il supporto vero alle richieste Range, non solo l'header
// che lo annuncia.
if (!in_array($type, ['audio', 'video'], true)) {
    header('Content-Length: ' . $size);
    readfile($path);
    exit;
}

header('Accept-Ranges: bytes');
$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range === '' || !preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
    header('Content-Length: ' . $size);
    readfile($path);
    exit;
}

$start = $m[1] === '' ? 0 : (int)$m[1];
$end   = $m[2] === '' ? $size - 1 : (int)$m[2];
if ($start > $end || $end >= $size) {
    http_response_code(416);
    header('Content-Range: bytes */' . $size);
    exit;
}

http_response_code(206);
header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
header('Content-Length: ' . ($end - $start + 1));

$fh = fopen($path, 'rb');
fseek($fh, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fh)) {
    $chunk = min(8192, $remaining);
    echo fread($fh, $chunk);
    $remaining -= $chunk;
}
fclose($fh);
