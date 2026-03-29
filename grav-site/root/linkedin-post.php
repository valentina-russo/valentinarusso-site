<?php
/**
 * LinkedIn Post API
 *
 * Receives POST with: text, articleUrl
 * Posts to LinkedIn using stored OAuth token
 * Returns JSON: { ok: true, postId: "..." } or { ok: false, error: "..." }
 */

header('Content-Type: application/json; charset=utf-8');

// Auth check
$pass = $_POST['pass'] ?? '';
if ($pass !== 'ValeAdmin2026') {
    echo json_encode(['ok' => false, 'error' => 'Non autorizzato']);
    exit;
}

define('LI_TOKEN_FILE', __DIR__ . '/linkedin-token.json');

// Load token
if (!file_exists(LI_TOKEN_FILE)) {
    echo json_encode(['ok' => false, 'error' => 'LinkedIn non connesso. Vai su /linkedin-callback.php per autorizzare.', 'needsAuth' => true]);
    exit;
}

$tokenData = json_decode(file_get_contents(LI_TOKEN_FILE), true);
if (!$tokenData || !isset($tokenData['access_token'])) {
    echo json_encode(['ok' => false, 'error' => 'Token LinkedIn non valido. Riconnetti.', 'needsAuth' => true]);
    exit;
}

// Check expiry
if (isset($tokenData['expires_at']) && time() > $tokenData['expires_at']) {
    echo json_encode(['ok' => false, 'error' => 'Token LinkedIn scaduto. Riconnetti su /linkedin-callback.php', 'needsAuth' => true]);
    exit;
}

$accessToken = $tokenData['access_token'];
$personUrn   = $tokenData['person_urn'] ?? null;

if (!$personUrn) {
    echo json_encode(['ok' => false, 'error' => 'Person URN mancante. Riconnetti LinkedIn.', 'needsAuth' => true]);
    exit;
}

// Get post data
$text       = $_POST['text'] ?? '';
$articleUrl = $_POST['articleUrl'] ?? '';

if (!$text) {
    echo json_encode(['ok' => false, 'error' => 'Testo del post mancante']);
    exit;
}

// Build LinkedIn post payload
$payload = [
    'author'          => 'urn:li:person:' . $personUrn,
    'commentary'      => $text,
    'visibility'      => 'PUBLIC',
    'distribution'    => [
        'feedDistribution'            => 'MAIN_FEED',
        'targetEntities'              => [],
        'thirdPartyDistributionChannels' => []
    ],
    'lifecycleState'  => 'PUBLISHED',
];

// If article URL provided, add as article content
if ($articleUrl) {
    $payload['content'] = [
        'article' => [
            'source'      => $articleUrl,
            'title'       => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
        ]
    ];
}

// Call LinkedIn API
$ch = curl_init('https://api.linkedin.com/rest/posts');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0',
        'LinkedIn-Version: 202503',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);

$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

// LinkedIn returns 201 Created with x-restli-id header for success
if ($httpCode === 201 || $httpCode === 200) {
    echo json_encode([
        'ok'      => true,
        'message' => 'Post pubblicato su LinkedIn!',
        'httpCode' => $httpCode,
    ]);
} else {
    $errorData = json_decode($resp, true);
    $errorMsg  = $errorData['message'] ?? $resp ?? 'Errore sconosciuto';
    echo json_encode([
        'ok'       => false,
        'error'    => 'LinkedIn API error (' . $httpCode . '): ' . $errorMsg,
        'httpCode' => $httpCode,
        'raw'      => $resp,
    ]);
}
