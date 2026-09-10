<?php
/**
 * Rinvio una tantum della conferma a chi non l'ha mai ricevuta.
 *
 * Fino al 10/09/2026 la conferma partiva con le intestazioni separate da
 * a-capo semplici invece che da CRLF, quindi il Content-Type multipart non
 * veniva letto e il messaggio finiva scartato o nello spam. Nessuno degli
 * iscritti aveva il link Zoom. Questo attrezzo lo manda a tutto il registro.
 *
 * L'elenco dei serviti e' nuovo (rinviati-crlf.txt): i tre indirizzi segnati
 * nel vecchio elenco l'8 settembre erano stati "serviti" con la mail rotta.
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

$serviti = __DIR__ . '/rinviati-crlf.txt';
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
