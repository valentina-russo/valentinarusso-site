<?php
/**
 * Corso Base Human Design - Correzione nome visualizzato dell'admin
 * Access: https://valentinarussobg5.com/corso/fix-nome.php?token=corso_nome_2026_n0m3
 *
 * L'account admin ereditava il nome "Unicorno" dal vecchio HD Account
 * System: e il nome che le allieve vedono accanto alle correzioni nel forum.
 * Self-deleting.
 */

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('corso_nome_2026_n0m3', $_GET['token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'error' => 'Forbidden']));
}

$configPath = __DIR__ . '/../hd-db-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'hd-db-config.php mancante']));
}
require_once $configPath;

$log = [];
$errors = [];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', HD_DB_HOST, HD_DB_NAME),
        HD_DB_USER, HD_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $stmt = $pdo->prepare('UPDATE hd_users SET name = ? WHERE email = ?');
    $stmt->execute(['Valentina Russo', 'valentinebers@gmail.com']);
    $log[] = $stmt->rowCount() > 0
        ? 'Nome aggiornato in "Valentina Russo"'
        : 'Nessuna modifica necessaria (nome gia corretto)';

} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

if (empty($errors)) {
    unlink(__FILE__);
    $log[] = 'Autoeliminato OK';
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
