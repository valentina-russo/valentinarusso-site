<?php
// VERSION: 2026-03-11-AI-BOLD-V1
require_once 'admin_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

$body = trim($_POST['body'] ?? '');
$tags = trim($_POST['tags'] ?? '');

if (mb_strlen($body) < 50) {
    echo json_encode(['error' => 'Testo troppo breve.']);
    exit;
}

$apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
if (!$apiKey || $apiKey === 'YOUR_KEY_HERE') {
    echo json_encode(['error' => 'Chiave API Claude non configurata.']);
    exit;
}

$bodyTruncated = mb_substr($body, 0, 6000);
$tagsInfo = $tags ? "Le parole chiave principali dell'articolo sono: {$tags}." : "Non sono indicate parole chiave specifiche.";

$prompt  = "Sei un esperto SEO e copywriter italiano per il blog di Valentina Russo (Human Design e BG5).\n\n";
$prompt .= "Il tuo compito è mettere in grassetto markdown (**parola**) le parole e frasi chiave nel testo dell'articolo.\n\n";
$prompt .= "{$tagsInfo}\n\n";
$prompt .= "Regole OBBLIGATORIE:\n";
$prompt .= "1. Metti in grassetto i termini tecnici/chiave (es: Human Design, BG5, Proiettore, Autorità Emotiva, ecc.) e le parole direttamente collegate al tema dell'articolo.\n";
$prompt .= "2. Metti in grassetto anche parole semanticamente vicine al tema principale (sinonimi chiave, concetti correlati importanti).\n";
$prompt .= "3. NON mettere in grassetto tutta la frase — solo le parole o espressioni chiave.\n";
$prompt .= "4. NON mettere in grassetto più di 8-12 occorrenze in totale. Scegli le più rilevanti per SEO e lettura.\n";
$prompt .= "5. NON toccare titoli (righe che iniziano con #), immagini (![ ]), link ([ ]( )), codice.\n";
$prompt .= "6. NON modificare NULLA ALTRO del testo — solo aggiungere ** attorno alle parole scelte.\n";
$prompt .= "7. Rispondi SOLO con il testo markdown modificato, nessuna spiegazione prima o dopo.\n\n";
$prompt .= "TESTO ARTICOLO:\n{$bodyTruncated}";

$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 4000,
    'messages'   => [
        ['role' => 'user', 'content' => $prompt]
    ]
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);

$result    = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Errore di connessione: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    $errBody = json_decode($result, true);
    $errMsg  = $errBody['error']['message'] ?? ('HTTP ' . $httpCode);
    echo json_encode(['error' => 'Errore API Claude: ' . $errMsg]);
    exit;
}

$response = json_decode($result, true);
$text     = $response['content'][0]['text'] ?? '';

if (!$text) {
    echo json_encode(['error' => 'Risposta AI vuota. Riprova.']);
    exit;
}

echo json_encode(['body' => $text], JSON_UNESCAPED_UNICODE);
