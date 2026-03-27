<?php
/**
 * HD Account System — CRUD Carte
 * Endpoint: POST /hd-charts.php  GET /hd-charts.php?action=list|get
 * Richiede autenticazione.
 */

require_once __DIR__ . '/hd-db.php';

header('Access-Control-Allow-Origin: https://valentinarussobg5.com');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

hdSessionStart();
$user = hdRequireAuth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'list') handleList($user);
    elseif ($action === 'get') handleGet($user, (int)($_GET['id'] ?? 0));
    else hdErr('Azione non valida.', 404);
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    switch ($action) {
        case 'save':        handleSave($user, $body);               break;
        case 'delete':      handleDelete($user, $body);             break;
        case 'rename':      handleRename($user, $body);             break;
        default:            hdErr('Azione non valida.', 404);
    }
}

hdErr('Metodo non consentito.', 405);

// ── Handlers ─────────────────────────────────────────────────────────────────

function handleList(array $user): void {
    $stmt = hdDb()->prepare(
        'SELECT id, chart_name, birth_day, birth_month, birth_year,
                birth_city, created_at
         FROM hd_charts
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 100'
    );
    $stmt->execute([$user['id']]);
    hdOk($stmt->fetchAll());
}

function handleGet(array $user, int $chartId): void {
    if (!$chartId) hdErr('ID carta mancante.');

    $stmt = hdDb()->prepare(
        'SELECT id, chart_name, birth_day, birth_month, birth_year,
                birth_hour, birth_min, birth_city, birth_tz, chart_json, created_at
         FROM hd_charts
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$chartId, $user['id']]);
    $chart = $stmt->fetch();

    if (!$chart) hdErr('Carta non trovata.', 404);

    // Decodifica JSON per validazione, poi ritorna come oggetto
    $chart['chart_data'] = json_decode($chart['chart_json'], true);
    unset($chart['chart_json']);
    hdOk($chart);
}

function handleSave(array $user, array $b): void {
    if (!hdCsrfVerify($b['csrf'] ?? '', 'save_chart')) hdErr('Token di sicurezza non valido.');

    // Limite massimo carte per utente
    $count = (int)hdDb()->prepare('SELECT COUNT(*) FROM hd_charts WHERE user_id = ?')
                         ->execute([$user['id']]) ?: 0;
    $countStmt = hdDb()->prepare('SELECT COUNT(*) FROM hd_charts WHERE user_id = ?');
    $countStmt->execute([$user['id']]);
    if ((int)$countStmt->fetchColumn() >= 50) {
        hdErr('Hai raggiunto il limite di 50 carte salvate. Elimina una carta per continuarne a salvare.');
    }

    // Valida chart_json
    $chartJson = $b['chart_json'] ?? '';
    if (!$chartJson || json_decode($chartJson) === null) hdErr('Dati carta non validi.');
    if (strlen($chartJson) > 300000) hdErr('Dati carta troppo grandi.');

    $day   = (int)($b['birth_day']   ?? 0);
    $month = (int)($b['birth_month'] ?? 0);
    $year  = (int)($b['birth_year']  ?? 0);
    $hour  = (int)($b['birth_hour']  ?? 0);
    $min   = (int)($b['birth_min']   ?? 0);
    $city  = substr(trim($b['birth_city'] ?? ''), 0, 150);
    $tz    = substr(trim($b['birth_tz']   ?? 'Europe/Rome'), 0, 60);
    $name  = substr(trim($b['chart_name'] ?? 'Carta senza nome'), 0, 100);

    if (!$day || !$month || !$year) hdErr('Data di nascita non valida.');

    $stmt = hdDb()->prepare(
        'INSERT INTO hd_charts
            (user_id, chart_name, birth_day, birth_month, birth_year,
             birth_hour, birth_min, birth_city, birth_tz, chart_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'], $name, $day, $month, $year,
        $hour, $min, $city, $tz, $chartJson
    ]);

    hdOk(['id' => (int)hdDb()->lastInsertId(), 'message' => 'Carta salvata!']);
}

function handleDelete(array $user, array $b): void {
    if (!hdCsrfVerify($b['csrf'] ?? '', 'delete_chart')) hdErr('Token di sicurezza non valido.');

    $chartId = (int)($b['chart_id'] ?? 0);
    if (!$chartId) hdErr('ID carta mancante.');

    $stmt = hdDb()->prepare('DELETE FROM hd_charts WHERE id = ? AND user_id = ?');
    $stmt->execute([$chartId, $user['id']]);

    if (!$stmt->rowCount()) hdErr('Carta non trovata.', 404);
    hdOk(['message' => 'Carta eliminata.']);
}

function handleRename(array $user, array $b): void {
    if (!hdCsrfVerify($b['csrf'] ?? '', 'rename_chart')) hdErr('Token di sicurezza non valido.');

    $chartId = (int)($b['chart_id'] ?? 0);
    $newName = substr(trim($b['chart_name'] ?? ''), 0, 100);
    if (!$chartId || !$newName) hdErr('Dati mancanti.');

    $stmt = hdDb()->prepare('UPDATE hd_charts SET chart_name = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$newName, $chartId, $user['id']]);

    if (!$stmt->rowCount()) hdErr('Carta non trovata.', 404);
    hdOk(['message' => 'Nome aggiornato.']);
}
