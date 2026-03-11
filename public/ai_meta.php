<?php
// VERSION: 2026-03-11-AI-META-V1
require_once 'admin_auth.php';

header('Content-Type: application/json; charset=utf-8');

// Solo admin autenticati
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

$body     = trim($_POST['body'] ?? '');
$title    = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? 'privati');

if (mb_strlen($body) < 80) {
    echo json_encode(['error' => 'Testo articolo troppo breve. Scrivi almeno qualche paragrafo prima di generare i metadati.']);
    exit;
}

// API key check
$apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
if (!$apiKey || $apiKey === 'YOUR_KEY_HERE') {
    echo json_encode(['error' => 'Chiave API Claude non configurata. Inserisci CLAUDE_API_KEY in admin_auth.php.']);
    exit;
}

// Tronca il body per non sprecare token (max ~4000 chars)
$bodyTruncated = mb_substr($body, 0, 4000);
$categoryLabel = ($category === 'aziende')
    ? 'business/aziendale — BG5 per team, leadership e imprenditori'
    : 'percorso individuale — BG5 e Human Design personale';

$prompt  = "Sei un esperto SEO e copywriter per Valentina Russo, consulente BG5 e Human Design.\n";
$prompt .= "Analizza il seguente articolo del blog (categoria: {$categoryLabel}) e genera ESCLUSIVAMENTE i metadati tecnici richiesti.\n";
$prompt .= "NON riscrivere né modificare il testo dell'articolo.\n\n";
if ($title) {
    $prompt .= "TITOLO ARTICOLO: {$title}\n\n";
}
$prompt .= "TESTO ARTICOLO:\n{$bodyTruncated}\n\n";
$prompt .= "---\n";
$prompt .= "Genera i seguenti metadati in italiano.\n";
$prompt .= "Rispondi SOLO con un oggetto JSON valido, senza testo prima o dopo.\n\n";
$prompt .= "{\n";
$prompt .= "  \"description\": \"Estratto breve e coinvolgente per l'anteprima lista articoli (max 180 caratteri)\",\n";
$prompt .= "  \"seo_title\": \"Titolo SEO ottimizzato per Google (max 60 caratteri, include la parola chiave principale)\",\n";
$prompt .= "  \"seo_desc\": \"Meta description per Google (max 155 caratteri, include call to action)\",\n";
$prompt .= "  \"geo_location\": \"Posizione geografica se l'articolo è locale (es: Milano, Italia), altrimenti stringa vuota\",\n";
$prompt .= "  \"aeo_answer\": \"Risposta sintetica e autorevole alla domanda principale dell'articolo (2-4 frasi, adatta a Google AI Overview e ChatGPT)\",\n";
$prompt .= "  \"faq\": [\n";
$prompt .= "    {\"question\": \"Domanda frequente 1?\", \"answer\": \"Risposta completa 1\"},\n";
$prompt .= "    {\"question\": \"Domanda frequente 2?\", \"answer\": \"Risposta completa 2\"},\n";
$prompt .= "    {\"question\": \"Domanda frequente 3?\", \"answer\": \"Risposta completa 3\"}\n";
$prompt .= "  ],\n";
$prompt .= "  \"image_title\": \"Titolo breve per l'immagine di copertina (es: Valentina Russo - Human Design)\",\n";
$prompt .= "  \"image_alt\": \"Testo alternativo accessibile e SEO per l'immagine (max 100 caratteri)\",\n";
$prompt .= "  \"image_caption\": \"Didascalia breve sotto l'immagine (1 frase, tono caldo e professionale)\",\n";
$prompt .= "  \"image_desc\": \"Descrizione estesa dell'immagine per i motori di ricerca (2-3 frasi)\"\n";
$prompt .= "}\n";

$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 1500,
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

// Estrai il JSON dalla risposta
if (preg_match('/\{[\s\S]*\}/m', $text, $m)) {
    $meta = json_decode($m[0], true);
    if ($meta) {
        // Converti faq array → stringa JSON per la textarea
        if (isset($meta['faq']) && is_array($meta['faq'])) {
            $meta['faq_json'] = json_encode($meta['faq'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            unset($meta['faq']);
        }
        echo json_encode($meta, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => 'Risposta AI non parsabile (JSON malformato). Riprova.']);
    }
} else {
    echo json_encode(['error' => 'Formato risposta AI inatteso. Riprova.']);
}
