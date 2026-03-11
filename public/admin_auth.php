<?php
session_start();

// Password semplice per il pannello (modificabile dall'utente)
define('ADMIN_PASSWORD', 'valentina2026');

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