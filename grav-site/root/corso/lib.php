<?php
declare(strict_types=1);

require_once __DIR__ . '/../hd-db.php';
require_once __DIR__ . '/bunny-config.php';

// ── Ruoli ────────────────────────────────────────────────────────────────────

// Come hdRequireAuth() ma redirige al login invece di rispondere JSON 401
// (hdRequireAuth e pensata per endpoint API, qui serve pagine HTML)
function corsoRequireLoggedIn(): array {
    $user = hdGetCurrentUser();
    if (!$user) {
        $prefix = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? '../' : '';
        header('Location: ' . $prefix . 'login.php');
        exit;
    }
    return $user;
}

function corsoRequireStudent(): array {
    return corsoRequireLoggedIn();
}

function corsoRequireAdmin(): array {
    $user = corsoRequireLoggedIn();
    $stmt = hdDb()->prepare('SELECT role FROM hd_users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $role = $stmt->fetchColumn();
    if ($role !== 'admin') {
        http_response_code(403);
        exit('Accesso negato.');
    }
    return $user;
}

function corsoIsAdmin(int $userId): bool {
    $stmt = hdDb()->prepare('SELECT role FROM hd_users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() === 'admin';
}

// ── Iscrizioni (R2, R11) ───────────────────────────────────────────────────

function corsoIsEnrolled(int $userId, int $courseId): bool {
    $stmt = hdDb()->prepare('SELECT 1 FROM course_enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$userId, $courseId]);
    return (bool)$stmt->fetchColumn();
}

function corsoRequireEnrollment(int $userId, int $courseId): void {
    if (corsoIsAdmin($userId)) return;
    if (!corsoIsEnrolled($userId, $courseId)) {
        http_response_code(403);
        exit('Non sei iscritto a questo corso.');
    }
}

// ── Bunny Stream — upload + URL firmati (R14, R21, R22) ────────────────────

function bunnyApiRequest(string $method, string $path, ?array $jsonBody = null, ?string $rawBody = null, ?string $contentType = null): array {
    $ch = curl_init('https://video.bunnycdn.com' . $path);
    $headers = ['AccessKey: ' . BUNNY_API_KEY];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody));
    } elseif ($rawBody !== null) {
        $headers[] = 'Content-Type: ' . ($contentType ?: 'application/octet-stream');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
    }
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return ['ok' => false, 'error' => 'cURL error: ' . $err];
    }
    $decoded = json_decode($resp, true);
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'data' => $decoded];
}

function bunnyCreateVideo(string $title): ?string {
    $res = bunnyApiRequest('POST', '/library/' . BUNNY_LIBRARY_ID . '/videos', ['title' => $title]);
    if (!$res['ok'] || empty($res['data']['guid'])) return null;
    return $res['data']['guid'];
}

function bunnyUploadVideoFile(string $videoGuid, string $tmpFilePath): bool {
    $body = file_get_contents($tmpFilePath);
    if ($body === false) return false;
    $res = bunnyApiRequest('PUT', '/library/' . BUNNY_LIBRARY_ID . '/videos/' . $videoGuid, null, $body, 'application/octet-stream');
    return $res['ok'];
}

// URL firmato per il player embed (scade dopo $ttlSeconds - R22: rigenerabile senza reload pagina)
function bunnySignedEmbedUrl(string $videoGuid, int $ttlSeconds = 14400): string {
    $expires = time() + $ttlSeconds; // default 4h, copre lezioni lunghe
    $token = hash('sha256', BUNNY_TOKEN_KEY . $videoGuid . $expires);
    return sprintf(
        'https://iframe.mediadelivery.net/embed/%s/%s?token=%s&expires=%d',
        BUNNY_LIBRARY_ID, $videoGuid, $token, $expires
    );
}

// ── Materiali PDF (R18: validazione MIME + magic bytes, non solo estensione) ─

function corsoValidatedPdfUpload(string $fieldName, string $destDir, string $destBasename): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;
    $tmp = $_FILES[$fieldName]['tmp_name'];
    $size = $_FILES[$fieldName]['size'];
    if ($size > 20 * 1024 * 1024) return null; // R18: max 20MB

    $mime = mime_content_type($tmp);
    if ($mime !== 'application/pdf') return null;

    $handle = fopen($tmp, 'rb');
    $magic = fread($handle, 5);
    fclose($handle);
    if ($magic !== '%PDF-') return null; // R18: magic bytes, non solo estensione

    if (!is_dir($destDir)) mkdir($destDir, 0750, true); // SEC-CORSO-004: non world-readable su hosting condiviso
    $destPath = $destDir . '/' . $destBasename . '.pdf';
    if (!move_uploaded_file($tmp, $destPath)) return null;
    return $destPath;
}

// ── Forum (R4, R9, R17) ──────────────────────────────────────────────────────

function corsoValidatePostBody(string $body): ?string {
    $body = trim($body);
    if ($body === '') return 'Il messaggio non puo essere vuoto.';
    if (mb_strlen($body) > 10000) return 'Il messaggio e troppo lungo (max 10.000 caratteri).';
    return null;
}

// ── UI helpers ────────────────────────────────────────────────────────────────

function corsoHtmlHead(string $title): void {
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' - Corso Base Human Design</title>';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<style>
        :root { --bg:#FAF7F5; --text:#2D2926; --primary:#B68397; --secondary:#5DAEB1; --accent:#E6A756; --white:#FFFFFF; --soft-gray:#EAE5E1; }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: "Outfit", -apple-system, sans-serif; margin: 0; padding: 2rem 1rem; line-height: 1.6; }
        h1, h2, h3 { font-family: "Playfair Display", Georgia, serif; }
        a { color: var(--secondary); }
        .container { max-width: 780px; margin: 0 auto; }
        .card { background: var(--white); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn { display: inline-block; background: var(--primary); color: var(--white); padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; border: none; cursor: pointer; font-size: 1rem; }
        .btn.secondary { background: var(--secondary); }
        .btn.danger { background: #C0554B; }
        input, textarea, select { width: 100%; padding: 0.6rem; border: 1px solid var(--soft-gray); border-radius: 8px; font-family: inherit; font-size: 1rem; margin-bottom: 0.75rem; }
        label { font-weight: 600; display: block; margin-bottom: 0.25rem; }
        .error { color: #C0554B; background: #FBEAE8; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .success { color: #2E7D32; background: #E8F5E9; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .muted { color: #77716C; font-size: 0.9rem; }
        .top-nav { display:flex; justify-content: space-between; align-items:center; max-width: 780px; margin: 0 auto 1.5rem; }
        .top-nav a { color: var(--text); text-decoration:none; font-size:0.9rem; }
        .post { border-top: 1px solid var(--soft-gray); padding-top: 0.75rem; margin-top: 0.75rem; }
        .post .author { font-weight: 600; }
        .post .author.admin-post { color: var(--primary); }
        video, iframe.video-embed { width: 100%; aspect-ratio: 16/9; border-radius: 8px; border: none; }
        .empty-state { text-align:center; padding: 3rem 1rem; color: #77716C; }
    </style></head><body>';
}

function corsoHtmlFoot(): void {
    echo '</body></html>';
}

function corsoCsrfField(string $action = 'default'): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(hdCsrfToken($action)) . '">';
}
