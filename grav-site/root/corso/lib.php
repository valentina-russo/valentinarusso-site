<?php
declare(strict_types=1);

require_once __DIR__ . '/../hd-db.php';
require_once __DIR__ . '/bunny-config.php';

// ── Ruoli ────────────────────────────────────────────────────────────────────

// Come hdRequireAuth() ma redirige al login invece di rispondere JSON 401
// (hdRequireAuth e pensata per endpoint API, qui servono pagine HTML)
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
    if ($stmt->fetchColumn() !== 'admin') {
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

// ── Classi (coorti) ─────────────────────────────────────────────────────────
// Una "classe" e un gruppo di allieve che segue lo stesso corso in un dato
// periodo. Le lezioni e le iscrizioni appartengono alla classe, non al corso.

const CORSO_MAX_CLASSI = 3; // tetto voluto: oltre diventa ingestibile

function corsoCohorts(int $courseId, bool $includeArchived = false): array {
    $sql = 'SELECT * FROM cohorts WHERE course_id = ?';
    if (!$includeArchived) $sql .= ' AND archived_at IS NULL';
    $sql .= ' ORDER BY position ASC, id ASC';
    $stmt = hdDb()->prepare($sql);
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

function corsoCohort(int $cohortId): ?array {
    $stmt = hdDb()->prepare('SELECT co.*, c.title AS course_title, c.slug AS course_slug
                             FROM cohorts co JOIN courses c ON c.id = co.course_id
                             WHERE co.id = ?');
    $stmt->execute([$cohortId]);
    return $stmt->fetch() ?: null;
}

function corsoCanAddCohort(int $courseId): bool {
    return count(corsoCohorts($courseId)) < CORSO_MAX_CLASSI;
}

// ── Iscrizioni (R2, R11) ───────────────────────────────────────────────────

function corsoIsEnrolled(int $userId, int $cohortId): bool {
    $stmt = hdDb()->prepare('SELECT 1 FROM course_enrollments WHERE user_id = ? AND cohort_id = ?');
    $stmt->execute([$userId, $cohortId]);
    return (bool)$stmt->fetchColumn();
}

function corsoRequireEnrollment(int $userId, int $cohortId): void {
    if (corsoIsAdmin($userId)) return;
    if (!corsoIsEnrolled($userId, $cohortId)) {
        http_response_code(403);
        exit('Non sei iscritta a questa classe.');
    }
}

// ── Compiti in attesa di correzione ─────────────────────────────────────────
// Un post di un'allieva e "in attesa" se dopo di esso non c'e nessun post
// dell'admin nello stesso thread (= nella stessa lezione).

function corsoPendingHomework(?int $cohortId = null): array {
    $sql = 'SELECT p.id, p.lesson_id, p.body, p.created_at,
                   u.name AS student_name, u.email AS student_email,
                   l.title AS lesson_title, l.position AS lesson_position,
                   c.title AS course_title, co.name AS cohort_name, co.id AS cohort_id
            FROM forum_posts p
            JOIN hd_users u ON u.id = p.user_id
            JOIN lessons  l ON l.id = p.lesson_id
            JOIN cohorts co ON co.id = l.cohort_id
            JOIN courses  c ON c.id = co.course_id
            WHERE u.role = ?
              AND l.deleted_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM forum_posts r
                  JOIN hd_users ru ON ru.id = r.user_id
                  WHERE r.lesson_id = p.lesson_id
                    AND ru.role = ?
                    AND r.created_at > p.created_at
              )';
    $params = ['student', 'admin'];
    if ($cohortId !== null) { $sql .= ' AND co.id = ?'; $params[] = $cohortId; }
    $sql .= ' ORDER BY p.created_at ASC';
    $stmt = hdDb()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function corsoPendingCount(?int $cohortId = null): int {
    $sql = 'SELECT COUNT(*) FROM forum_posts p
            JOIN hd_users u ON u.id = p.user_id
            JOIN lessons  l ON l.id = p.lesson_id
            JOIN cohorts co ON co.id = l.cohort_id
            WHERE u.role = ? AND l.deleted_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM forum_posts r
                  JOIN hd_users ru ON ru.id = r.user_id
                  WHERE r.lesson_id = p.lesson_id AND ru.role = ?
                    AND r.created_at > p.created_at
              )';
    $params = ['student', 'admin'];
    if ($cohortId !== null) { $sql .= ' AND co.id = ?'; $params[] = $cohortId; }
    $stmt = hdDb()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
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
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return ['ok' => false, 'error' => 'cURL error: ' . $err];
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'data' => json_decode($resp, true)];
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

// URL firmato per il player embed (R22: TTL lungo, copre lezioni da 2h+)
function bunnySignedEmbedUrl(string $videoGuid, int $ttlSeconds = 14400): string {
    $expires = time() + $ttlSeconds;
    $token = hash('sha256', BUNNY_TOKEN_KEY . $videoGuid . $expires);
    return sprintf(
        'https://iframe.mediadelivery.net/embed/%s/%s?token=%s&expires=%d',
        BUNNY_LIBRARY_ID, $videoGuid, $token, $expires
    );
}

// ── Materiali PDF (R18: MIME + magic bytes, non solo estensione) ────────────

function corsoValidatedPdfUpload(string $fieldName, string $destDir, string $destBasename): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;
    $tmp  = $_FILES[$fieldName]['tmp_name'];
    if ($_FILES[$fieldName]['size'] > 20 * 1024 * 1024) return null;

    if (mime_content_type($tmp) !== 'application/pdf') return null;

    $handle = fopen($tmp, 'rb');
    $magic  = fread($handle, 5);
    fclose($handle);
    if ($magic !== '%PDF-') return null;

    if (!is_dir($destDir)) mkdir($destDir, 0750, true); // SEC-CORSO-004: non world-readable
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

// ── UI ───────────────────────────────────────────────────────────────────────

function corsoHtmlHead(string $title): void {
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' &middot; Corso Base Human Design</title>';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">';
    echo <<<'CSS'
<style>
:root{
  --rosa:#B68397; --teal:#5DAEB1; --oro:#E6A756; --navy:#1A2332;
  --crema:#FAF7F5; --surface:#EAE5E1; --ink:#2D2926; --ink-soft:#6B6560;
  --urgente:#9C5A70; --white:#fff;
  --f-head:'Playfair Display',Georgia,serif;
  --f-body:'Outfit',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
  --r:14px;
  --shadow:0 1px 2px rgba(26,35,50,.04),0 4px 16px rgba(26,35,50,.05);
  --shadow-lift:0 2px 6px rgba(26,35,50,.07),0 14px 30px rgba(26,35,50,.10);
  --ease:cubic-bezier(.65,0,.35,1);
}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;background:var(--crema);color:var(--ink);font-family:var(--f-body);
  font-size:16px;line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:var(--navy);text-decoration-color:rgba(93,174,177,.55);text-underline-offset:3px}
a:hover{text-decoration-color:var(--teal)}
h1,h2,h3{margin:0 0 .5rem}

/* ── Header ───────────────────────────────────────────── */
.site-head{background:var(--navy);color:var(--white)}
.site-head-in{max-width:840px;margin:0 auto;padding:.9rem 1.25rem;
  display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.brand{font-family:var(--f-head);font-style:italic;font-weight:700;font-size:1.0625rem;
  color:var(--white);text-decoration:none;white-space:nowrap}
.nav{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}
.nav a{color:rgba(255,255,255,.82);text-decoration:none;font-size:.875rem;font-weight:600;
  padding:.45rem .7rem;border-radius:8px;transition:background .18s var(--ease),color .18s var(--ease);
  display:inline-flex;align-items:center;gap:.4rem;min-height:38px}
.nav a:hover,.nav a:focus-visible{background:rgba(255,255,255,.12);color:var(--white)}
.nav a[aria-current="page"]{background:rgba(255,255,255,.16);color:var(--white)}
.nav .count{background:var(--oro);color:var(--navy);font-size:.6875rem;font-weight:800;
  min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;
  align-items:center;justify-content:center;line-height:1}

/* onda organica: continuita col sito pubblico */
.wave{display:block;width:100%;height:22px;color:var(--navy)}

/* ── Layout ───────────────────────────────────────────── */
.wrap{max-width:840px;margin:0 auto;padding:2rem 1.25rem 4rem}
.eyebrow{font-size:.8125rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase;
  color:var(--rosa);margin:0 0 .4rem}
.hero{font-family:var(--f-head);font-style:italic;font-weight:700;
  font-size:clamp(1.875rem,5vw,2.5rem);line-height:1.15;color:var(--navy);margin:0 0 .4rem}
.hero-sub{color:var(--ink-soft);margin:0 0 2rem;font-size:1.0625rem}
h1.page{font-family:var(--f-head);font-weight:700;font-size:clamp(1.5rem,4vw,1.75rem);
  color:var(--navy);line-height:1.2}
h2.sect{font-size:.8125rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase;
  color:var(--ink-soft);margin:2.25rem 0 .85rem}

/* ── Card ─────────────────────────────────────────────── */
.card{background:var(--white);border-radius:var(--r);padding:1.35rem 1.4rem;
  margin-bottom:.9rem;box-shadow:var(--shadow);border:1px solid rgba(26,35,50,.05)}
.card h3{font-size:1.125rem;font-weight:600;color:var(--navy);margin:0 0 .3rem}
a.card{display:block;text-decoration:none;color:inherit;
  transition:transform .2s var(--ease),box-shadow .2s var(--ease)}
/* specificita: a.card (0,1,1) batterebbe .card-row (0,1,0) e annullerebbe il flex */
a.card.card-row,a.card.task{display:flex}
a.card:hover,a.card:focus-visible{transform:translateY(-2px);box-shadow:var(--shadow-lift)}
.card-row{display:flex;align-items:center;gap:1rem}
.card-row .grow{min-width:0;flex:1}

/* numero classe: tipografia al posto delle thumbnail */
.num{flex-shrink:0;width:44px;height:44px;border-radius:50%;background:var(--crema);
  border:1.5px solid var(--surface);display:flex;align-items:center;justify-content:center;
  font-family:var(--f-head);font-size:1.125rem;font-weight:700;color:var(--rosa)}

/* ── Bottoni (testo navy su rosa/oro/teal, mai bianco: WCAG AA) ─────────── */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;
  min-height:46px;padding:.7rem 1.35rem;border-radius:11px;border:none;cursor:pointer;
  font-family:var(--f-body);font-size:.9375rem;font-weight:600;text-decoration:none;
  background:var(--oro);color:var(--navy);
  box-shadow:0 2px 0 rgba(26,35,50,.14);
  transition:transform .15s var(--ease),box-shadow .15s var(--ease),filter .15s var(--ease)}
.btn:hover,.btn:focus-visible{filter:brightness(1.05);transform:translateY(-1px);
  box-shadow:0 4px 0 rgba(26,35,50,.14)}
.btn:active{transform:translateY(1px);box-shadow:0 1px 0 rgba(26,35,50,.14)}
.btn.dark{background:var(--navy);color:var(--white);box-shadow:0 2px 0 rgba(26,35,50,.25)}
.btn.ghost{background:transparent;color:var(--navy);border:1.5px solid var(--surface);box-shadow:none}
.btn.ghost:hover{border-color:var(--rosa);background:var(--white)}
.btn.danger{background:var(--urgente);color:var(--white)}
.btn.full{width:100%}
.btn[disabled]{opacity:.5;cursor:not-allowed;transform:none;filter:none}
:focus-visible{outline:2.5px solid var(--teal);outline-offset:2px}

/* ── Form ─────────────────────────────────────────────── */
label{display:block;font-weight:600;font-size:.875rem;color:var(--navy);margin:0 0 .3rem}
input,textarea,select{width:100%;padding:.75rem .85rem;border:1.5px solid var(--surface);
  border-radius:10px;font-family:inherit;font-size:1rem;color:var(--ink);background:var(--white);
  margin:0 0 1rem;min-height:46px;transition:border-color .16s var(--ease),box-shadow .16s var(--ease)}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--teal);
  box-shadow:0 0 0 3px rgba(93,174,177,.18)}
textarea{min-height:120px;resize:vertical;line-height:1.55}
.hint{font-size:.875rem;color:var(--ink-soft);margin:-.6rem 0 1rem}

/* ── Messaggi ─────────────────────────────────────────── */
.msg{padding:.85rem 1rem;border-radius:11px;margin-bottom:1.25rem;font-size:.9375rem;
  border-left:3px solid}
.msg.err{background:#FBEAE8;border-color:var(--urgente);color:#7E3B4C}
.msg.ok{background:#E8F3F0;border-color:var(--teal);color:#1F5A54}

/* ── Badge ────────────────────────────────────────────── */
.badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;font-weight:600;
  padding:.22rem .6rem;border-radius:99px;background:var(--surface);color:var(--navy);white-space:nowrap}
.badge.teal{background:rgba(93,174,177,.18)}
.badge.oro{background:rgba(230,167,86,.24)}
.meta{font-size:.875rem;color:var(--ink-soft)}

/* ── Compiti da correggere: urgenza sul bordo ─────────── */
.task{display:flex;align-items:center;gap:.9rem;border-left:4px solid var(--teal)}
.task.media{border-left-color:var(--oro)}
.task.alta{border-left-color:var(--urgente)}
.task .who{font-weight:600;font-size:1rem;color:var(--navy)}
.task .snippet{color:var(--ink-soft);font-size:.9375rem;white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis}
.dot{flex-shrink:0;width:10px;height:10px;border-radius:50%;background:var(--teal)}
.task.media .dot{background:var(--oro)}
.task.alta .dot{background:var(--urgente)}

/* ── Forum ────────────────────────────────────────────── */
.post{border-top:1px solid var(--surface);padding-top:1rem;margin-top:1rem}
.post:first-of-type{border-top:none;padding-top:0;margin-top:0}
.post .author{font-weight:600;color:var(--navy)}
.post .author.is-admin{color:var(--rosa)}
.post .body{margin-top:.35rem;white-space:pre-wrap;overflow-wrap:anywhere}
.post.from-admin{background:rgba(182,131,151,.07);margin-left:-.6rem;margin-right:-.6rem;
  padding:1rem .6rem .1rem;border-radius:10px;border-top-color:transparent}

/* ── Video / stato vuoto ──────────────────────────────── */
iframe.video{width:100%;aspect-ratio:16/9;border:none;border-radius:var(--r);
  background:var(--navy);display:block}
.empty{text-align:center;padding:3rem 1.25rem;color:var(--ink-soft)}
.empty .mark{margin:0 auto 1rem;display:block}

/* ── Password rivelata una volta sola ─────────────────── */
.reveal{background:var(--navy);color:var(--white);border-radius:var(--r);padding:1.75rem 1.5rem;
  text-align:center;margin-bottom:1.25rem}
.reveal .pw{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:1.5rem;
  font-weight:600;letter-spacing:.05em;margin:.75rem 0 1.15rem;word-break:break-all;color:var(--white)}
.reveal .warn{font-size:.875rem;color:#E4C4D0;margin:1rem 0 0}
.reveal .btn.ghost{border-color:rgba(255,255,255,.35);color:var(--white)}
.reveal .btn.ghost:hover{background:rgba(255,255,255,.12);border-color:var(--white)}

/* ── Signature: il segno di spunta che si disegna ─────── */
.mark{width:44px;height:44px}
.mark circle{fill:none;stroke:var(--surface);stroke-width:2}
.mark path{fill:none;stroke:var(--teal);stroke-width:2.5;stroke-linecap:round;
  stroke-linejoin:round;stroke-dasharray:30;stroke-dashoffset:30}
.mark.drawn path{animation:draw .55s var(--ease) forwards}
@keyframes draw{to{stroke-dashoffset:0}}
@media (prefers-reduced-motion:reduce){
  *{animation-duration:.01ms!important;transition-duration:.01ms!important}
  .mark path{stroke-dashoffset:0}
}
@media(max-width:560px){
  .wrap{padding:1.5rem 1rem 3rem}
  .site-head-in{padding:.75rem 1rem}
  .nav a{font-size:.8125rem;padding:.4rem .55rem}
}
</style>
CSS;
    echo '</head><body>';
}

// Segno di spunta disegnato a mano (signature moment, CSS puro)
function corsoCheckMark(bool $drawn = true): string {
    $cls = $drawn ? 'mark drawn' : 'mark';
    return '<svg class="' . $cls . '" viewBox="0 0 44 44" aria-hidden="true">'
         . '<circle cx="22" cy="22" r="20"/>'
         . '<path d="M13.5 22.5 L19.5 28.5 L31 17"/></svg>';
}

/**
 * Header + navigazione. Massimo 3 voci, per scelta esplicita di Valentina
 * ("entri, c'e il corso e il forum, punto") -- niente sidebar da LMS.
 */
function corsoNav(array $user, bool $isAdmin, string $current = ''): void {
    $p = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? '../' : '';
    $cur = fn(string $k) => $current === $k ? ' aria-current="page"' : '';

    echo '<header class="site-head"><div class="site-head-in">';
    echo '<a class="brand" href="' . ($isAdmin ? $p . 'admin/index.php' : $p . 'index.php') . '">Corso Base Human Design</a>';
    echo '<nav class="nav">';
    if ($isAdmin) {
        $pending = corsoPendingCount();
        echo '<a href="' . $p . 'admin/index.php"' . $cur('corsi') . '>Corsi</a>';
        echo '<a href="' . $p . 'admin/compiti.php"' . $cur('compiti') . '>Da correggere';
        if ($pending > 0) echo ' <span class="count">' . $pending . '</span>';
        echo '</a>';
    } else {
        echo '<a href="' . $p . 'index.php"' . $cur('corsi') . '>Il mio corso</a>';
        echo '<a href="' . $p . 'letture.php"' . $cur('letture') . '>Prenota una lettura</a>';
    }
    echo '<a href="' . $p . 'logout.php">Esci</a>';
    echo '</nav></div></header>';
    // onda organica: stesso linguaggio visivo del sito pubblico
    echo '<svg class="wave" viewBox="0 0 1200 22" preserveAspectRatio="none" aria-hidden="true">'
       . '<path d="M0,0 H1200 V6 C900,22 600,-4 300,10 C180,16 80,14 0,8 Z" fill="currentColor"/></svg>';
}

function corsoHtmlFoot(): void {
    echo '</body></html>';
}

function corsoCsrfField(string $action = 'default'): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(hdCsrfToken($action)) . '">';
}
