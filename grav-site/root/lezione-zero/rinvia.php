<?php
/**
 * Rinvio una tantum della conferma a chi si era iscritto prima che la mail
 * contenesse il link Zoom e l'evento del calendario.
 *
 * Si attiva solo con il segreto RINVIO_TOKEN, che vive nel file di
 * configurazione sul server. Ogni indirizzo riceve una volta sola: chi e' gia'
 * stato servito finisce in rinviati.txt.
 *
 * ATTREZZO TEMPORANEO: va tolto dal deploy dopo l'uso.
 */
declare(strict_types=1);

require __DIR__ . '/iscrivi.php';   // definisce ambiente(), manda_conferma(), REGISTRO

header('Content-Type: text/plain; charset=UTF-8');

$atteso = ambiente('RINVIO_TOKEN');
$dato   = (string)($_GET['t'] ?? '');
if ($atteso === '' || !hash_equals($atteso, $dato)) {
    http_response_code(404);
    echo "non trovato\n";
    exit;
}

$serviti = __DIR__ . '/rinviati.txt';
$gia = is_file($serviti)
    ? array_filter(array_map('trim', file($serviti, FILE_IGNORE_NEW_LINES)))
    : [];

if (!is_file(REGISTRO)) {
    echo "registro vuoto: nessuno a cui scrivere\n";
    exit;
}

$fatti = 0;
$saltati = 0;
$h = fopen(REGISTRO, 'r');
$prima = true;
while (($r = fgetcsv($h)) !== false) {
    if ($prima) { $prima = false; continue; }          // intestazione
    $nome  = trim((string)($r[1] ?? ''));
    $email = trim((string)($r[2] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { continue; }
    if (in_array(strtolower($email), array_map('strtolower', $gia), true)) {
        $saltati++;
        continue;
    }
    manda_conferma(ltrim($nome, "'"), $email);
    file_put_contents($serviti, $email . "\n", FILE_APPEND | LOCK_EX);
    $gia[] = $email;
    $fatti++;
}
fclose($h);

echo "inviate: {$fatti}\ngia servite in precedenza: {$saltati}\n";
