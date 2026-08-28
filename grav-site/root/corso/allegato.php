<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

// Serve gli allegati del forum solo a chi e nella classe: mai un link diretto
// al file, che sarebbe scaricabile da chiunque ne indovinasse il nome.

$user = corsoRequireStudent();
$id   = (int)($_GET['id'] ?? 0);

$stmt = hdDb()->prepare('SELECT a.*, p.cohort_id FROM forum_attachments a
                         JOIN forum_posts p ON p.id = a.post_id WHERE a.id = ?');
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) { http_response_code(404); exit('Non trovato.'); }

corsoRequireEnrollment((int)$user['id'], (int)$file['cohort_id']);

if (!is_file($file['path'])) { http_response_code(404); exit('File non disponibile.'); }

$allowed = corsoAllegatiMime();
$mime = isset($allowed[$file['mime']]) ? $file['mime'] : 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file['path']));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['orig_name']) . '"');
header('Cache-Control: private, max-age=3600');
readfile($file['path']);
