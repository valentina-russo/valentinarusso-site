<?php
// ---------------------------------------------------------------------------
// MIGRAZIONE ESEGUITA (29/08/2026) — script neutralizzato di proposito.
// Ha aggiunto: lessons.description, lessons.audio_path, hd_users.phone,
// hd_users.avatar_path, hd_users.email_notifications, tabella lesson_notes.
// Mai riattivare questo script: se serve una nuova migrazione, scriverne
// una nuova con un token nuovo.
// ---------------------------------------------------------------------------
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Migrazione già eseguita'], JSON_UNESCAPED_UNICODE);
exit;
