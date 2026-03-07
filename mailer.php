<?php
// Imposta l'email di destinazione
$to = "info@valentinarussobg5.com";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = strip_tags(trim($_POST["name"] ?? $_POST["nome"] ?? ""));
    $email = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
    $service = strip_tags(trim($_POST["service"] ?? ""));
    $message = trim($_POST["message"] ?? $_POST["messaggio"] ?? "");
    $type = strip_tags(trim($_POST["type"] ?? "privati"));
    $azienda = strip_tags(trim($_POST["azienda"] ?? ""));

    if (empty($name) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        if ($type === 'corporate') {
            echo "Si prega di completare tutti i campi correttamente.";
        } else {
            echo json_encode(["status" => "error", "message" => "Si prega di completare tutti i campi correttamente."]);
        }
        exit;
    }

    $subject = $type === 'corporate' ? "MAIL DA AZIENDE DA PARTE DI: $name" : "MAIL DA PRIVATI DA PARTE DI: $name";
    $email_content = "Nome: $name\n";
    $email_content .= "Email: $email\n";
    if ($type === 'corporate') {
        $email_content .= "Azienda: $azienda\n\n";
    } else {
        $email_content .= "Servizio di interesse: $service\n\n";
    }
    $email_content .= "Messaggio:\n$message\n";

    $email_headers = "From: $name <$email>";

    if (mail($to, $subject, $email_content, $email_headers)) {
        http_response_code(200);
        if ($type === 'corporate') {
            echo "success";
        } else {
            echo json_encode(["status" => "success", "message" => "Grazie! Il tuo messaggio è stato inviato."]);
        }
    } else {
        http_response_code(500);
        if ($type === 'corporate') {
            echo "Oops! Qualcosa è andato storto.";
        } else {
            echo json_encode(["status" => "error", "message" => "Oops! Qualcosa è andato storto, non siamo riusciti ad inviare il tuo messaggio."]);
        }
    }
} else {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "C'è stato un problema con la tua richiesta, per favore riprova."]);
}
?>