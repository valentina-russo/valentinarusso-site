<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

// R22: il player chiama questo endpoint poco prima che il token del video
// scada, per riceverne uno nuovo senza ricaricare la pagina. Stesso
// controllo di accesso di lezione.php: mai fidarsi del solo id lezione.

$user = corsoRequireStudent();
$lessonId = (int)($_GET['lesson'] ?? 0);

$stmt = hdDb()->prepare('SELECT bunny_video_id, cohort_id FROM lessons WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson || !$lesson['bunny_video_id']) {
    http_response_code(404);
    header('Content-Type: application/json');
    die(json_encode(['ok' => false]));
}

corsoRequireEnrollment((int)$user['id'], (int)$lesson['cohort_id']);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'embed_url' => bunnySignedEmbedUrl($lesson['bunny_video_id'])]);
