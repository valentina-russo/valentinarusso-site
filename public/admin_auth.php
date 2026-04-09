<?php
// SEC-013: Harden session cookie before session_start()
session_set_cookie_params([
    'lifetime' => 0,          // Expires when browser closes
    'path'     => '/',
    'secure'   => true,       // HTTPS only — Aruba forces HTTPS in production
    'httponly' => true,       // Not accessible via JS
    'samesite' => 'Strict',   // CSRF mitigation
]);
session_start();

// SEC-012: Read password from environment variable — set ADMIN_PASSWORD on Aruba hosting panel.
// Fallback to hardcoded value only for local dev (remove fallback once env var is set on server).
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'valentina2026');

// Chiave API Claude per generazione metadati AI
// Ottienila su: https://console.anthropic.com/
define('CLAUDE_API_KEY', 'YOUR_KEY_HERE');

function isAdmin()
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function checkAuth()
{
    if (!isAdmin()) {
        header('Location: admin.php');
        exit;
    }
}

if (isset($_POST['login'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        // SEC-013: Regenerate session ID on login to prevent session fixation (CWE-384)
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = "Password errata!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>