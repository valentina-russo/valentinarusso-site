<?php
/**
 * HD Account System — Gestione Account
 * Endpoint: POST /hd-account.php con campo JSON `action`
 * Actions: change_password | delete | export
 * Richiede autenticazione.
 */

require_once __DIR__ . '/hd-db.php';

header('Access-Control-Allow-Origin: https://valentinarussobg5.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

hdSessionStart();
$user = hdRequireAuth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    switch ($action) {
        case 'change_password': handleChangePassword($user, $body); break;
        case 'delete':          handleDelete($user, $body);         break;
        case 'export':          handleExport($user);                break;
        default:                hdErr('Azione non valida.', 404);
    }
}

hdErr('Metodo non consentito.', 405);

// ── Handlers ─────────────────────────────────────────────────────────────────

function handleChangePassword(array $user, array $b): void {
    if (!hdCsrfVerify($b['csrf'] ?? '', 'change_password')) hdErr('Token di sicurezza non valido.');

    $oldPassword = $b['old_password'] ?? '';
    $newPassword = $b['new_password'] ?? '';

    if (!$oldPassword) hdErr('Inserisci la password attuale.');
    if ($passErr = hdValidatePassword($newPassword)) hdErr($passErr);

    // Verifica password attuale
    $stmt = hdDb()->prepare('SELECT password_hash FROM hd_users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !hdVerifyPassword($oldPassword, $row['password_hash'])) {
        sleep(1);
        hdErr('Password attuale non corretta.', 401);
    }

    // Aggiorna password e invalida tutte le sessioni attive (session_ver++)
    $newHash = hdHashPassword($newPassword);
    hdDb()->prepare(
        'UPDATE hd_users SET password_hash = ?, session_ver = session_ver + 1 WHERE id = ?'
    )->execute([$newHash, $user['id']]);

    // Distruggi sessione corrente — l'utente dovrà ri-autenticarsi
    hdLogout();

    hdOk(['message' => 'Password aggiornata. Effettua di nuovo il login.']);
}

function handleDelete(array $user, array $b): void {
    if (!hdCsrfVerify($b['csrf'] ?? '', 'delete_account')) hdErr('Token di sicurezza non valido.');

    $password    = $b['password'] ?? '';
    $confirm     = $b['confirm'] ?? '';

    if (!$password) hdErr('Inserisci la tua password per confermare l\'eliminazione.');
    if ($confirm !== 'ELIMINA') hdErr('Digita ELIMINA nel campo di conferma.');

    // Verifica password
    $stmt = hdDb()->prepare('SELECT password_hash FROM hd_users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !hdVerifyPassword($password, $row['password_hash'])) {
        sleep(1);
        hdErr('Password non corretta.', 401);
    }

    $db = hdDb();
    $db->beginTransaction();
    try {
        // Elimina carte (anche senza CASCADE per sicurezza esplicita)
        $db->prepare('DELETE FROM hd_charts WHERE user_id = ?')->execute([$user['id']]);
        // Elimina tentativi di login tracciati per email (già hashed, ma pulizia GDPR)
        // Nota: hd_login_attempts usa identifier=hash(email), non user_id
        // Non possiamo collegarli direttamente — vengono puliti automaticamente dopo 24h
        // Elimina utente
        $db->prepare('DELETE FROM hd_users WHERE id = ?')->execute([$user['id']]);
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        hdErr('Errore durante l\'eliminazione. Riprova o contatta il supporto.', 500);
    }

    // Distruggi sessione
    hdLogout();

    hdOk(['message' => 'Account eliminato. Tutti i tuoi dati sono stati rimossi.']);
}

/**
 * GDPR Art. 20 — Portabilità dei dati.
 * Restituisce un JSON scaricabile con tutti i dati dell'utente.
 * Nessun CSRF richiesto (GET-like, non modifica dati), ma richiede auth.
 */
function handleExport(array $user): void {
    $db = hdDb();

    // Dati utente (esclude password_hash e token per sicurezza)
    $userStmt = $db->prepare(
        'SELECT id, email, name, created_at, verified_at,
                gdpr_consent, gdpr_date
         FROM hd_users WHERE id = ?'
    );
    $userStmt->execute([$user['id']]);
    $userData = $userStmt->fetch();

    // Carte salvate (con dati completi incluso chart_json)
    $chartsStmt = $db->prepare(
        'SELECT id, chart_name, birth_day, birth_month, birth_year,
                birth_hour, birth_min, birth_city, birth_tz,
                chart_json, created_at
         FROM hd_charts WHERE user_id = ?
         ORDER BY created_at DESC'
    );
    $chartsStmt->execute([$user['id']]);
    $charts = $chartsStmt->fetchAll();

    // Decodifica chart_json per output leggibile
    foreach ($charts as &$chart) {
        $chart['chart_data'] = json_decode($chart['chart_json'], true);
        unset($chart['chart_json']);
    }

    $export = [
        'export_date'    => date('Y-m-d\TH:i:s\Z'),
        'export_version' => '1.0',
        'service'        => 'valentinarussobg5.com',
        'user'           => $userData,
        'charts'         => $charts,
    ];

    $json     = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $filename = 'hd-export-' . date('Y-m-d') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}
