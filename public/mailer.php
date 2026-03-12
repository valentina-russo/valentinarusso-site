<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $name = strip_tags(trim($_POST["name"] ?? $_POST["nome"] ?? "N/A"));
    $email = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
    $company = strip_tags(trim($_POST["azienda"] ?? "N/A"));
    $message = strip_tags(trim($_POST["message"] ?? $_POST["messaggio"] ?? "N/A"));
    $type = strip_tags(trim($_POST["type"] ?? "privato"));

    // Recipient
    $to = "info@valentinarussobg5.com";

    // Subject
    $subject = "Nuovo messaggio da valentinarussobg5.com ($type): $name";

    // Email content
    $email_content = "Hai ricevuto un nuovo messaggio dal sito web.\n\n";
    $email_content .= "Tipo: $type\n";
    $email_content .= "Nome: $name\n";
    if ($type === "corporate") {
        $email_content .= "Azienda: $company\n";
    }
    $email_content .= "Email: $email\n\n";
    $email_content .= "Messaggio:\n$message\n";

    // headers
    $headers = "From: info@valentinarussobg5.com\r\n";
    $headers .= "Reply-To: $email\r\n";

    // Send email
    if (mail($to, $subject, $email_content, $headers)) {
        echo "success";
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        echo "error";
    }
} else {
    header("HTTP/1.1 403 Forbidden");
    echo "Accesso negato.";
}
?>