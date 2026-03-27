<?php
// ============================================================
// HD Account System — Configurazione Database
// QUESTO FILE È UN ESEMPIO. Copia in hd-db-config.php
// e compila con le credenziali reali Aruba.
// hd-db-config.php è in .gitignore — NON committare credenziali.
// ============================================================

define('HD_DB_HOST', 'localhost');
define('HD_DB_NAME', 'nome_database_aruba');
define('HD_DB_USER', 'utente_database_aruba');
define('HD_DB_PASS', 'password_database_aruba');
define('HD_DB_PORT', 3306);

// Pepper per rafforzare l'hashing delle password.
// Genera con: php -r "echo bin2hex(random_bytes(32));"
// CONSERVA questo valore in un posto sicuro — se lo perdi
// tutti gli utenti dovranno reimpostare la password.
define('HD_PEPPER', 'SOSTITUISCI_CON_32_BYTE_HEX_RANDOM');

// URL base del sito (senza slash finale)
define('HD_BASE_URL', 'https://valentinarussobg5.com');

// Email mittente per comunicazioni account
define('HD_FROM_EMAIL', 'noreply@valentinarussobg5.com');
define('HD_FROM_NAME',  'Valentina Russo');
