<?php
/**
 * Handler form richiesta workshop centri olistici.
 * Invia sempre a staff@valentinarussobg5.com.
 * Chiamato via fetch() dal template workshop_proposta.html.twig.
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"]);
    exit;
}

header("Content-Type: application/json");

// Leggi e sanifica i campi
$centro      = strip_tags(trim($_POST["centro"]      ?? ""));
$referente   = strip_tags(trim($_POST["referente"]   ?? ""));
$telefono    = strip_tags(trim($_POST["telefono"]    ?? ""));
$fascia      = strip_tags(trim($_POST["fascia"]      ?? ""));
$giorni_raw  = $_POST["giorni"] ?? [];

// Validazione minima
if (!$centro || !$referente || !$telefono) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Campi obbligatori mancanti"]);
    exit;
}

// Sanifica array giorni
$giorni_allowed = ["Lunedì","Martedì","Mercoledì","Giovedì","Venerdì","Sabato","Domenica"];
$giorni = [];
foreach ((array)$giorni_raw as $g) {
    $g = strip_tags(trim($g));
    if (in_array($g, $giorni_allowed)) {
        $giorni[] = $g;
    }
}
$giorni_str = $giorni ? implode(", ", $giorni) : "Non specificati";

// Destinatario fisso
$to      = "staff@valentinarussobg5.com";
$subject = "Richiesta workshop — " . $centro;

$body  = "Nuova richiesta workshop da valentinarussobg5.com\n";
$body .= "================================================\n\n";
$body .= "Centro:          " . $centro . "\n";
$body .= "Referente:       " . $referente . "\n";
$body .= "Telefono:        " . $telefono . "\n";
$body .= "Fascia oraria:   " . ($fascia ?: "Non specificata") . "\n";
$body .= "Giorni preferiti: " . $giorni_str . "\n";
$body .= "\n================================================\n";
$body .= "Inviato: " . date("d/m/Y H:i") . "\n";

$headers = "From: noreply@valentinarussobg5.com\r\n";
$headers .= "Reply-To: noreply@valentinarussobg5.com\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (mail($to, $subject, $body, $headers)) {
    echo json_encode(["ok" => true]);
} else {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Errore invio email"]);
}
