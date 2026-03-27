<?php
/**
 * HD Account System — Autenticazione
 * Endpoint: POST /hd-auth.php con campo JSON `action`
 * Actions: register | login | logout | me | verify | forgot | reset
 */

require_once __DIR__ . '/hd-db.php';

header('Access-Control-Allow-Origin: https://valentinarussobg5.com');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

hdSessionStart();

$method = $_SERVER['REQUEST_METHOD'];

// GET: verify email (link da email), me (check sessione), csrf (token per form)
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'verify') {
        handleVerifyEmail($_GET['token'] ?? '');
    } elseif ($action === 'me') {
        handleMe();
    } elseif ($action === 'csrf') {
        handleCsrf($_GET['for'] ?? 'default');
    } else {
        hdErr('Azione non valida.', 404);
    }
}

// POST: tutte le altre azioni
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    switch ($action) {
        case 'register': handleRegister($body); break;
        case 'login':    handleLogin($body);    break;
        case 'logout':   handleLogout();         break;
        case 'forgot':   handleForgot($body);   break;
        case 'reset':    handleReset($body);     break;
        default:         hdErr('Azione non valida.', 404);
    }
}

hdErr('Metodo non consentito.', 405);

// ── Handlers ─────────────────────────────────────────────────────────────────

function handleCsrf(string $for): void {
    hdSessionStart();
    // Azioni consentite per CSRF token
    $allowed = ['login', 'register', 'forgot', 'reset', 'save_chart', 'delete_chart',
                'rename_chart', 'change_password', 'delete_account'];
    if (!in_array($for, $allowed, true)) hdErr('Azione CSRF non valida.');
    hdOk(['token' => hdCsrfToken($for), 'for' => $for]);
}

function handleMe(): void {
    $user = hdGetCurrentUser();
    if (!$user) hdOk(null);
    hdOk([
        'id'    => $user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
    ]);
}

function handleRegister(array $b): void {
    // CSRF
    if (!hdCsrfVerify($b['csrf'] ?? '', 'register')) hdErr('Token di sicurezza non valido. Ricarica la pagina.');

    $email   = strtolower(trim($b['email'] ?? ''));
    $password = $b['password'] ?? '';
    $name    = trim($b['name'] ?? '');
    $gdpr    = !empty($b['gdpr']);
    $age     = !empty($b['age']);

    // Validazioni
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) hdErr('Email non valida.');
    if (strlen($email) > 254)                        hdErr('Email troppo lunga.');
    if ($passErr = hdValidatePassword($password))    hdErr($passErr);
    if (!$gdpr)  hdErr('Devi accettare la Privacy Policy per registrarti.');
    if (!$age)   hdErr('Devi avere almeno 16 anni per registrarti.');

    // Rate limiting
    if (hdIsRateLimited($email, hdGetIp())) {
        sleep(2);
        hdErr('Troppi tentativi. Riprova tra qualche minuto.', 429);
    }

    $db = hdDb();

    // Verifica se email già esiste — risponde sempre uguale (no enumeration)
    $existing = $db->prepare('SELECT id FROM hd_users WHERE email = ?');
    $existing->execute([$email]);

    if ($existing->fetch()) {
        // Email già registrata: invia email di notifica all'utente
        hdSendMail($email, 'Tentativo di registrazione', "Qualcuno ha tentato di registrarsi con questa email su valentinarussobg5.com.\nSe non eri tu, ignora questo messaggio.\nSe hai dimenticato la password, vai su: " . HD_BASE_URL . "/account");
        // Risposta identica a quella di successo
        hdOk(['message' => 'Controlla la tua email per completare la registrazione.']);
    }

    // Crea utente
    $hash    = hdHashPassword($password);
    $token   = bin2hex(random_bytes(32));
    $stmt    = $db->prepare(
        'INSERT INTO hd_users (email, password_hash, name, verify_token, gdpr_consent, gdpr_date)
         VALUES (?, ?, ?, ?, 1, NOW())'
    );
    $stmt->execute([$email, $hash, $name, $token]);

    // Email di verifica
    $link = HD_BASE_URL . '/hd-auth.php?action=verify&token=' . urlencode($token);
    $body = "Ciao" . ($name ? " $name" : "") . ",\n\n"
          . "Benvenuta/o su valentinarussobg5.com!\n\n"
          . "Clicca il link qui sotto per verificare la tua email:\n$link\n\n"
          . "Il link scade tra 24 ore.\n\n"
          . "Se non ti sei registrata/o, ignora questo messaggio.\n\n"
          . "— Valentina Russo";
    hdSendMail($email, 'Conferma la tua email — valentinarussobg5.com', $body);

    hdOk(['message' => 'Controlla la tua email per completare la registrazione.']);
}

function handleLogin(array $b): void {
    if (!hdCsrfVerify($b['csrf'] ?? '', 'login')) hdErr('Token di sicurezza non valido. Ricarica la pagina.');

    $email    = strtolower(trim($b['email'] ?? ''));
    $password = $b['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$password) hdErr('Email o password mancante.');

    if (hdIsRateLimited($email, hdGetIp())) {
        sleep(2);
        hdErr('Troppi tentativi. Riprova tra 15 minuti.', 429);
    }

    $stmt = hdDb()->prepare('SELECT id, email, name, password_hash, session_ver, verified_at FROM hd_users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !hdVerifyPassword($password, $user['password_hash'])) {
        hdRecordFailedAttempt($email, hdGetIp());
        // Delay progressivo
        sleep(1);
        hdErr('Email o password errati.', 401);
    }

    // Email non verificata
    if (empty($user['verified_at'])) {
        hdErr('Verifica prima la tua email. Controlla la casella di posta (incluso lo spam).', 403);
    }

    hdLoginSession($user);
    hdOk([
        'id'    => $user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
    ]);
}

function handleLogout(): void {
    hdLogout();
    hdOk(null);
}

function handleVerifyEmail(string $token): void {
    if (!$token) {
        http_response_code(400);
        header('Location: ' . HD_BASE_URL . '/account?error=token_invalido');
        exit;
    }

    $stmt = hdDb()->prepare(
        'SELECT id FROM hd_users WHERE verify_token = ? AND verified_at IS NULL'
    );
    $stmt->execute([trim($token)]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ' . HD_BASE_URL . '/account?error=token_invalido');
        exit;
    }

    hdDb()->prepare(
        'UPDATE hd_users SET verified_at = NOW(), verify_token = NULL WHERE id = ?'
    )->execute([$user['id']]);

    header('Location: ' . HD_BASE_URL . '/account?verified=1');
    exit;
}

function handleForgot(array $b): void {
    $start = microtime(true);

    if (!hdCsrfVerify($b['csrf'] ?? '', 'forgot')) hdErr('Token di sicurezza non valido.');

    $email = strtolower(trim($b['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) hdErr('Email non valida.');

    $stmt = hdDb()->prepare('SELECT id, name FROM hd_users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires   = date('Y-m-d H:i:s', time() + 3600);

        hdDb()->prepare(
            'UPDATE hd_users SET reset_token = ?, reset_expires = ? WHERE id = ?'
        )->execute([$tokenHash, $expires, $user['id']]);

        $link = HD_BASE_URL . '/account?action=reset&token=' . urlencode($rawToken);
        $body = "Ciao" . ($user['name'] ? " {$user['name']}" : "") . ",\n\n"
              . "Hai richiesto il reset della password su valentinarussobg5.com.\n\n"
              . "Clicca il link per impostare una nuova password:\n$link\n\n"
              . "Il link scade tra 1 ora.\n\n"
              . "Se non hai fatto questa richiesta, ignora questo messaggio.\n\n"
              . "— Valentina Russo";
        hdSendMail($email, 'Reset password — valentinarussobg5.com', $body);
    }

    // Equalizza tempo di risposta (anti-enumeration)
    $elapsed = microtime(true) - $start;
    if ($elapsed < 0.5) usleep((int)((0.5 - $elapsed) * 1_000_000));

    hdOk(['message' => "Se l'email è registrata, riceverai un link a breve."]);
}

function handleReset(array $b): void {
    if (!hdCsrfVerify($b['csrf'] ?? '', 'reset')) hdErr('Token di sicurezza non valido.');

    $rawToken = trim($b['token'] ?? '');
    $password = $b['password'] ?? '';

    if (!$rawToken) hdErr('Token mancante.');
    if ($passErr = hdValidatePassword($password)) hdErr($passErr);

    $tokenHash = hash('sha256', $rawToken);
    $db        = hdDb();

    $stmt = $db->prepare(
        'SELECT id FROM hd_users WHERE reset_token = ? AND reset_expires > NOW()'
    );
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    if (!$user) hdErr('Link di reset non valido o scaduto.');

    $newHash = hdHashPassword($password);
    $db->prepare(
        'UPDATE hd_users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, session_ver = session_ver + 1 WHERE id = ?'
    )->execute([$newHash, $user['id']]);

    hdOk(['message' => 'Password aggiornata. Ora puoi accedere.']);
}
