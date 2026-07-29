<?php
/**
 * Letture Human Design/BG5 — Stripe Checkout
 * Chiamato da: /letture-dati/checkout.php?reading=coppia|figlio|ombra|croce|...
 *
 * Dopo il pagamento -> /letture-dati/dati.php?session_id={CHECKOUT_SESSION_ID}&reading=...
 * In caso di annullamento -> /servizi
 *
 * BOZZA — non ancora deployata/linkata pubblicamente. Vedi note prezzo in config.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
letture_load_env();

$STRIPE_KEY = getenv('STRIPE_SECRET_KEY') ?: '';
$BASE_URL   = 'https://valentinarussobg5.com';

$reading = strtolower(trim($_GET['reading'] ?? ''));
$product = letture_get($reading);

if (!$product) {
    http_response_code(404);
    echo 'Lettura non trovata.';
    exit;
}

if (empty($STRIPE_KEY)) {
    http_response_code(503);
    echo <<<HTML
<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><title>Pagamento temporaneamente non disponibile</title>
<style>body{font-family:sans-serif;text-align:center;padding:4rem 1rem;color:#2D2926}h1{font-size:1.5rem}a{color:#C9768A}</style>
</head><body>
<h1>Pagamento temporaneamente non disponibile</h1>
<p>Contatta Valentina direttamente: <a href="mailto:info@valentinarussobg5.com">info@valentinarussobg5.com</a></p>
</body></html>
HTML;
    exit;
}

$successUrl = $BASE_URL . '/letture-dati/dati.php?session_id={CHECKOUT_SESSION_ID}&reading=' . urlencode($reading);
$cancelUrl  = $BASE_URL . '/servizi';

$fields = [
    'mode'                                                   => 'payment',
    'success_url'                                            => $successUrl,
    'cancel_url'                                              => $cancelUrl,
    'locale'                                                  => 'it',
    'allow_promotion_codes'                                   => 'true',
    'payment_intent_data[description]'                        => $product['name'],
    'line_items[0][price_data][currency]'                     => 'eur',
    'line_items[0][price_data][product_data][name]'           => $product['name'],
    'line_items[0][price_data][product_data][description]'    => $product['description'],
    'line_items[0][price_data][unit_amount]'                  => (string)$product['amount'],
    'line_items[0][quantity]'                                 => '1',
    'metadata[reading]'                                       => $reading,
    'metadata[source]'                                        => 'valentinarussobg5.com/servizi',
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => $STRIPE_KEY . ':',
    CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    CURLOPT_POSTFIELDS     => http_build_query($fields),
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    error_log('[letture-checkout] cURL error: ' . $curlErr);
    header('Location: ' . $cancelUrl . '?error=network');
    exit;
}

$data = json_decode($response, true);

if ($httpCode === 200 && !empty($data['url'])) {
    // Allowlist Stripe redirect host (stesso pattern di libretto-dati)
    $u = parse_url($data['url']);
    $allowedHosts = ['checkout.stripe.com', 'buy.stripe.com'];
    if (
        !is_array($u) || empty($u['host']) || ($u['scheme'] ?? '') !== 'https'
        || !in_array(strtolower($u['host']), $allowedHosts, true)
    ) {
        error_log('[letture-checkout] Unexpected redirect host: ' . ($data['url'] ?? '(empty)'));
        header('Location: ' . $cancelUrl . '?error=payment');
        exit;
    }
    header('Location: ' . $data['url']);
    exit;
} else {
    $errMsg = $data['error']['message'] ?? 'Errore sconosciuto';
    error_log('[letture-checkout] Stripe error HTTP ' . $httpCode . ': ' . $errMsg);
    header('Location: ' . $cancelUrl . '?error=payment');
    exit;
}
