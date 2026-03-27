<?php
/**
 * HD Account System — Core: DB, Sessione, CSRF, Auth
 * valentinarussobg5.com
 *
 * NON accessibile direttamente — solo via require_once dagli altri endpoint.
 */

if (basename($_SERVER['PHP_SELF'] ?? '') === 'hd-db.php') {
    http_response_code(403);
    exit('Forbidden');
}

// ── Configurazione ────────────────────────────────────────────────────────────

$configFile = __DIR__ . '/hd-db-config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    exit(json_encode(['ok' => false, 'error' => 'Servizio non disponibile (config mancante).']));
}
require_once $configFile;

// ── Sessione sicura ───────────────────────────────────────────────────────────

function hdSessionStart(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.use_strict_mode',  '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid',    '0');
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_secure',    '1');
    ini_set('session.cookie_samesite',  'Lax');
    ini_set('session.gc_maxlifetime',   '7200');   // 2h idle
    ini_set('session.cookie_lifetime',  '0');       // session cookie
    ini_set('session.name',             'hdsid');

    session_start();
}

// ── PDO ───────────────────────────────────────────────────────────────────────

function hdDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4;port=%d',
        HD_DB_HOST, HD_DB_NAME, HD_DB_PORT
    );
    $pdo = new PDO($dsn, HD_DB_USER, HD_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    return $pdo;
}

// ── Password ──────────────────────────────────────────────────────────────────

function hdHashPassword(string $password): string {
    $peppered = hash_hmac('sha256', $password, HD_PEPPER);
    // Prova Argon2id, fallback bcrypt
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($peppered, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 3,
            'threads'     => 2,
        ]);
    }
    return password_hash($peppered, PASSWORD_BCRYPT, ['cost' => 12]);
}

function hdVerifyPassword(string $password, string $hash): bool {
    $peppered = hash_hmac('sha256', $password, HD_PEPPER);
    return password_verify($peppered, $hash);
}

function hdValidatePassword(string $password): ?string {
    $len = strlen($password);
    if ($len < 8)   return 'La password deve essere di almeno 8 caratteri.';
    if ($len > 128) return 'La password è troppo lunga (max 128 caratteri).';
    return null;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

function hdCsrfToken(string $action = 'default'): string {
    hdSessionStart();
    $key = 'csrf_' . $action;
    if (empty($_SESSION[$key]) || ($_SESSION[$key]['exp'] ?? 0) < time()) {
        $_SESSION[$key] = [
            'tok' => bin2hex(random_bytes(32)),
            'exp' => time() + 3600,
        ];
    }
    return $_SESSION[$key]['tok'];
}

function hdCsrfVerify(string $submitted, string $action = 'default'): bool {
    hdSessionStart();
    $key   = 'csrf_' . $action;
    $stored = $_SESSION[$key] ?? null;
    if (!$stored || ($stored['exp'] ?? 0) < time()) return false;
    $valid = hash_equals($stored['tok'], $submitted);
    if ($valid) unset($_SESSION[$key]);
    return $valid;
}

// ── Auth ─────────────────────────────────────────────────────────────────────

function hdGetCurrentUser(): ?array {
    hdSessionStart();
    if (empty($_SESSION['hd_user_id'])) return null;

    $userId  = (int)$_SESSION['hd_user_id'];
    $sesVer  = (int)($_SESSION['hd_session_ver'] ?? 0);

    $stmt = hdDb()->prepare('SELECT id, email, name, session_ver, verified_at FROM hd_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) { hdLogout(); return null; }

    // Sessione invalidata da cambio password / reset
    if ((int)$user['session_ver'] !== $sesVer) { hdLogout(); return null; }

    // Idle timeout: 2h
    if ((time() - ($_SESSION['hd_last_act'] ?? 0)) > 7200) { hdLogout(); return null; }

    $_SESSION['hd_last_act'] = time();
    return $user;
}

function hdRequireAuth(): array {
    $user = hdGetCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Non autenticato.']);
        exit;
    }
    return $user;
}

function hdLoginSession(array $user): void {
    hdSessionStart();
    session_regenerate_id(true);
    $_SESSION['hd_user_id']    = $user['id'];
    $_SESSION['hd_session_ver'] = (int)$user['session_ver'];
    $_SESSION['hd_last_act']   = time();
}

function hdLogout(): void {
    hdSessionStart();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Rate Limiting ─────────────────────────────────────────────────────────────

function hdIsRateLimited(string $email, string $ip): bool {
    $db      = hdDb();
    $emailH  = hash('sha256', strtolower(trim($email)));
    $ipH     = hash('sha256', $ip);
    $window  = 900;  // 15 min
    $maxEmail = 5;
    $maxIp    = 15;

    // Pulizia occasionale
    if (random_int(1, 30) === 1) {
        $db->prepare('DELETE FROM hd_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)')->execute();
    }

    $q = 'SELECT COUNT(*) FROM hd_login_attempts WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)';
    $s = $db->prepare($q);

    $s->execute([$emailH, $window]);
    if ((int)$s->fetchColumn() >= $maxEmail) return true;

    $s->execute([$ipH, $window]);
    if ((int)$s->fetchColumn() >= $maxIp)    return true;

    return false;
}

function hdRecordFailedAttempt(string $email, string $ip): void {
    $db     = hdDb();
    $emailH = hash('sha256', strtolower(trim($email)));
    $ipH    = hash('sha256', $ip);
    $stmt   = $db->prepare('INSERT INTO hd_login_attempts (identifier) VALUES (?)');
    $stmt->execute([$emailH]);
    $stmt->execute([$ipH]);
}

function hdGetIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ── Response helpers ──────────────────────────────────────────────────────────

function hdJson(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

function hdOk(mixed $data = null): void {
    hdJson(['ok' => true, 'data' => $data]);
}

function hdErr(string $msg, int $status = 400): void {
    hdJson(['ok' => false, 'error' => $msg], $status);
}

// ── Email ─────────────────────────────────────────────────────────────────────

function hdSendMail(string $to, string $subject, string $body): bool {
    $headers  = "From: " . HD_FROM_NAME . " <" . HD_FROM_EMAIL . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP\r\n";
    return mail($to, $subject, $body, $headers);
}

// ── Security headers ──────────────────────────────────────────────────────────

function hdSecHeaders(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header_remove('X-Powered-By');
}

hdSecHeaders();
