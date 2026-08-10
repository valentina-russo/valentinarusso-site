<?php
// Test temporaneo — da cancellare subito dopo l'esecuzione.
// Replica esatta della chiamata mail() usata in invia.php per verificare
// che il server recapiti correttamente a due destinatari separati da virgola.

$ADMIN_EMAIL = 'consulenza@marcomunich.com, consulenze@valentinarussobg5.com';
$FROM_EMAIL  = 'info@valentinarussobg5.com';
$FROM_NAME   = 'Valentina Russo — Letture HD/BG5';
$nowIt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i');

$subject = '🔮 TEST — verifica notifica prenotazione (' . $nowIt . ')';
$body = <<<TEXT
Questa è una email di TEST per verificare che le notifiche di prenotazione
arrivino correttamente sia a Marco che a Valentina.

Se ricevi questa email, il fix funziona.

Inviata: {$nowIt}
TEXT;

$headersArray = [
    'MIME-Version'              => '1.0',
    'Content-Type'              => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => '8bit',
    'From'                      => $FROM_NAME . ' <' . $FROM_EMAIL . '>',
    'X-Mailer'                  => 'PHP/' . PHP_VERSION,
];

$ok = mail($ADMIN_EMAIL, $subject, $body, $headersArray);

header('Content-Type: text/plain; charset=UTF-8');
echo $ok ? "OK: mail() ha restituito true per: {$ADMIN_EMAIL}" : "FAIL: mail() ha restituito false";
