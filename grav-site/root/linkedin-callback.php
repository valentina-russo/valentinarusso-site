<?php
/**
 * LinkedIn OAuth 2.0 Callback
 *
 * Flow:
 * 1. Admin clicks "Connetti LinkedIn" → redirects to LinkedIn auth
 * 2. User authorizes → LinkedIn redirects here with ?code=...
 * 3. This file exchanges code for access token
 * 4. Stores token in linkedin-token.json
 * 5. Redirects back to admin
 */

// Config (credentials in separate file, not committed to git)
require_once __DIR__ . '/linkedin-config.php';

header('Content-Type: text/html; charset=utf-8');

// Step 1: If no code param, redirect to LinkedIn authorization
if (!isset($_GET['code']) && !isset($_GET['error'])) {
    $state = bin2hex(random_bytes(16));
    setcookie('li_oauth_state', $state, time() + 600, '/', '', true, true);

    $authUrl = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => LI_CLIENT_ID,
        'redirect_uri'  => LI_REDIRECT_URI,
        'state'         => $state,
        'scope'         => 'openid profile w_member_social'
    ]);

    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Handle error from LinkedIn
if (isset($_GET['error'])) {
    echo '<h2>Errore LinkedIn</h2>';
    echo '<p>' . htmlspecialchars($_GET['error_description'] ?? $_GET['error']) . '</p>';
    echo '<p><a href="/admin/">Torna all\'admin</a></p>';
    exit;
}

// Step 3: Exchange authorization code for access token
$code = $_GET['code'];

$ch = curl_init('https://www.linkedin.com/oauth/v2/accessToken');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'client_id'     => LI_CLIENT_ID,
        'client_secret' => LI_CLIENT_SECRET,
        'redirect_uri'  => LI_REDIRECT_URI,
    ]),
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);

if ($httpCode !== 200 || !isset($data['access_token'])) {
    echo '<h2>Errore nel token exchange</h2>';
    echo '<pre>' . htmlspecialchars($resp) . '</pre>';
    echo '<p><a href="/admin/">Torna all\'admin</a></p>';
    exit;
}

// Step 4: Get user profile (URN) for posting
$ch = curl_init('https://api.linkedin.com/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $data['access_token'],
    ],
]);
$profileResp = curl_exec($ch);
curl_close($ch);
$profile = json_decode($profileResp, true);

$personUrn = $profile['sub'] ?? null;

// Step 5: Save token
$tokenData = [
    'access_token'  => $data['access_token'],
    'expires_in'    => $data['expires_in'] ?? 5184000,
    'expires_at'    => time() + ($data['expires_in'] ?? 5184000),
    'person_urn'    => $personUrn,
    'created_at'    => date('c'),
];
file_put_contents(LI_TOKEN_FILE, json_encode($tokenData, JSON_PRETTY_PRINT));

// Step 6: Redirect back to admin with success
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>LinkedIn Connesso</title></head><body>';
echo '<div style="max-width:400px;margin:80px auto;text-align:center;font-family:Outfit,sans-serif;">';
echo '<div style="font-size:3rem;">&#10004;</div>';
echo '<h2 style="color:#0a66c2;">LinkedIn Connesso!</h2>';
echo '<p style="color:#666;">Token salvato. Scade il ' . date('d/m/Y', $tokenData['expires_at']) . '.</p>';
echo '<p style="color:#666;">Person URN: ' . htmlspecialchars($personUrn ?? 'non disponibile') . '</p>';
echo '<a href="/admin/" style="display:inline-block;margin-top:1rem;padding:10px 24px;background:#0a66c2;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Torna all\'Admin</a>';
echo '</div></body></html>';
