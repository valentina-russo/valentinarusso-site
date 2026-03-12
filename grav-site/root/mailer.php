<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = strip_tags(trim($_POST["name"] ?? $_POST["nome"] ?? "N/A"));
    $email = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
    $company = strip_tags(trim($_POST["azienda"] ?? "N/A"));
    $message = strip_tags(trim($_POST["message"] ?? $_POST["messaggio"] ?? "N/A"));
    $type = strip_tags(trim($_POST["type"] ?? "privato"));

    // RECIPIENT - DEFINITIVE FIX
    $to = "info@valentinarussobg5.com";

    $subject = "Nuovo messaggio da valentinarussobg5.com ($type): $name";
    $email_content = "Hai ricevuto un nuovo messaggio dal sito web.\n\nType: $type\nNome: $name\nEmail: $email\n";
    if ($type === "corporate")
        $email_content .= "Azienda: $company\n";
    $email_content .= "\nMessaggio:\n$message\n";

    $headers = "From: info@valentinarussobg5.com\r\nReply-To: $email\r\n";

    if (mail($to, $subject, $email_content, $headers)) {
        echo "success";
    } else {
        header("HTTP/1.1 500 Internal Error");
        echo "error";
    }
} else {
    echo "Access Denied";
}
?>