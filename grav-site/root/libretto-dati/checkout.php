<?php
/**
 * Libretto d'Istruzioni Human Design — Stripe Checkout
 *
 * Crea una sessione Stripe Checkout e redirige il cliente.
 * Chiamato da: /libretto-dati/checkout.php?tier=base|avanzato
 *
 * Env vars necessari (impostare su Aruba):
 *   STRIPE_SECRET_KEY     → sk_live_... (oppure sk_test_... in test)
 *
 * Dopo il pagamento → /libretto-dati/dati.php?session_id={CHECKOUT_SESSION_ID}&tier=...
 * In caso di annullamento → /libretto-istruzioni
 */

declare(strict_types=1);

// ─── Config locale (non in git — caricata manualmente su Aruba via FTP) ──────
// Crea su Aruba: libretto-dati/stripe-config.php  (vedi stripe-config.example.php)
if (file_exists(__DIR__ . '/stripe-config.php')) {
    require_once __DIR__ . '/stripe-config.php';
}

$STRIPE_KEY = getenv('STRIPE_SECRET_KEY') ?: '';
$BASE_URL   = 'https://valentinarussobg5.com';

// ─── Validazione tier ─────────────────────────────────────────────────────────
$tier = strtolower(trim($_GET['tier'] ?? 'avanzato'));
if (!in_array($tier, ['base', 'avanzato'], true)) {
    $tier = 'avanzato';
}

// ─── Configurazione prodotti ──────────────────────────────────────────────────
$products = [
    'base' => [
        'name'        => "Libretto d'Istruzioni Human Design — Base",
        'description' => '4 capitoli · ~35 pagine · PDF personalizzato su carta Human Design · Consegnato in 2-4 giorni lavorativi',
        'amount'      => 9000,   // in centesimi (€90,00)
        'label'       => 'Base',
    ],
    'avanzato' => [
        'name'        => "Libretto d'Istruzioni Human Design — Avanzato",
        'description' => '7 capitoli · ~55 pagine · PDF personalizzato su carta Human Design · Consegnato in 2-4 giorni lavorativi',
        'amount'      => 14700,  // in centesimi (€147,00)
        'label'       => 'Avanzato',
    ],
];

$product = $products[$tier];

// ─── Fallback se Stripe non configurato ───────────────────────────────────────
if (empty($STRIPE_KEY)) {
    http_response_code(503);
    echo <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Pagamento temporaneamente non disponibile</title>
<style>
  body { font-family: sans-serif; text-align: center; padding: 4rem 1rem; color: #2D2926; }
  h1 { font-size: 1.5rem; margin-bottom: 1rem; }
  p { color: #6B6560; }
  a { color: #C9768A; }
</style>
</head>
<body>
  <h1>Pagamento temporaneamente non disponibile</h1>
  <p>Il sistema di pagamento è in configurazione. Contatta Valentina direttamente:</p>
  <p><a href="mailto:valentina@valentinarussobg5.com">valentina@valentinarussobg5.com</a></p>
</body>
</html>
HTML;
    exit;
}

// ─── Crea sessione Stripe Checkout via cURL ───────────────────────────────────
$successUrl = $BASE_URL . '/libretto-dati/dati.php?session_id={CHECKOUT_SESSION_ID}&tier=' . $tier;
$cancelUrl  = $BASE_URL . '/libretto-istruzioni';

$fields = [
    'mode'                                             => 'payment',
    'success_url'                                      => $successUrl,
    'cancel_url'                                       => $cancelUrl,
    'locale'                                           => 'it',
    'allow_promotion_codes'                            => 'true',
    'automatic_payment_methods[enabled]'               => 'true',
    'payment_intent_data[description]'                 => $product['name'],
    'line_items[0][price_data][currency]'              => 'eur',
    'line_items[0][price_data][product_data][name]'    => $product['name'],
    'line_items[0][price_data][product_data][description]' => $product['description'],
    'line_items[0][price_data][unit_amount]'           => (string)$product['amount'],
    'line_items[0][quantity]'                          => '1',
    'metadata[tier]'                                   => $tier,
    'metadata[source]'                                 => 'valentinarussobg5.com',
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

// ─── Gestione risposta ────────────────────────────────────────────────────────
if ($curlErr) {
    error_log('[libretto-checkout] cURL error: ' . $curlErr);
    header('Location: ' . $cancelUrl . '?error=network');
    exit;
}

$data = json_decode($response, true);

if ($httpCode === 200 && !empty($data['url'])) {
    // ── Redirige a Stripe Checkout ──────────────────────────────────────────
    header('Location: ' . $data['url']);
    exit;
} else {
    $errMsg = $data['error']['message'] ?? 'Errore sconosciuto';
    error_log('[libretto-checkout] Stripe error HTTP ' . $httpCode . ': ' . $errMsg);
    header('Location: ' . $cancelUrl . '?error=payment');
    exit;
}
