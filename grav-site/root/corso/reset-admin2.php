<?php
/**
 * Corso Base Human Design - Reset password admin (one-time, self-deletes)
 * Access: https://valentinarussobg5.com/corso/reset-admin2.php?token=corso_reset2_2026_p4w8t1
 */

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!hash_equals('corso_reset2_2026_p4w8t1', $token)) {
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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $adminEmail = 'valentinebers@gmail.com';
    $newPassword = bin2hex(random_bytes(8));
    $peppered = hash_hmac('sha256', $newPassword, HD_PEPPER);
    $passwordHash = defined('PASSWORD_ARGON2ID')
        ? password_hash($peppered, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 2])
        : password_hash($peppered, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare('UPDATE hd_users SET password_hash = ?, session_ver = session_ver + 1 WHERE email = ?');
    $stmt->execute([$passwordHash, $adminEmail]);

    if ($stmt->rowCount() === 0) {
        $errors[] = 'Nessun utente trovato con questa email.';
    } else {
        $log[] = 'Password resettata per ' . $adminEmail;
        $log[] = 'PASSWORD: ' . $newPassword;
    }
} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

if (empty($errors)) {
    unlink(__FILE__);
    $log[] = 'Autoeliminato OK';
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
