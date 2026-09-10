<?php
/**
 * Segue la lezione gratuita: manda la brochure del Corso Base a chi era
 * iscritto alla lezione e non ha ancora comprato il corso.
 *
 * Come rinvia.php: si attiva solo col segreto BROCHURE_TOKEN, che vive nel
 * file di configurazione sul server. Ogni indirizzo riceve una volta sola,
 * l'elenco dei serviti sta in brochure-inviati.txt.
 *
 * Chiamate:
 *   ?t=SEGRETO&elenco=1    mostra a chi scriverebbe, senza mandare niente
 *   ?t=SEGRETO&prova=INDIRIZZO   manda solo a quell'indirizzo, per vedere il testo
 *   ?t=SEGRETO&vai=1       manda a tutti gli aventi diritto
 *
 * ATTREZZO TEMPORANEO: va disarmato togliendo il segreto dopo l'uso.
 */
declare(strict_types=1);

require __DIR__ . '/iscrivi.php';   // ambiente(), registra_invio(), REGISTRO

const BROCHURE_URL = 'https://valentinarussobg5.com/corso-base-human-design/brochure?da=brochure';
const SERVITI      = __DIR__ . '/brochure-inviati.txt';

/** Indirizzi nostri: non sono iscritti veri, non vanno serviti. */
const INTERNI = [
    'consulenze@valentinarussobg5.com',
    'info@valentinarussobg5.com',
    'consulenza@marcomunich.com',
    'marcomunich@gmail.com',
    'valentinebers@gmail.com',
];

header('Content-Type: text/plain; charset=UTF-8');

$atteso = ambiente('BROCHURE_TOKEN');
$dato   = (string)($_GET['t'] ?? '');
if ($atteso === '' || !hash_equals($atteso, $dato)) {
    http_response_code(404);
    echo "non trovato\n";
    exit;
}

/**
 * Chi ha gia' pagato il corso: l'indirizzo sta in course_payments.
 * Se la banca dati non risponde si ferma tutto: meglio non mandare niente
 * che mandare la brochure a chi ha appena pagato.
 */
function paganti(): array {
    require_once __DIR__ . '/../hd-db.php';
    $st = hdDb()->query('SELECT DISTINCT LOWER(email) AS e FROM course_payments');
    return array_column($st->fetchAll(), 'e');
}

function manda_brochure(string $nome, string $email): bool {
    // Le righe delle intestazioni si separano con CRLF: con a-capo semplici
    // il messaggio parte senza mittente leggibile e finisce nello spam.
    $ac = chr(13) . chr(10);

    $righe = [
        "Ciao {$nome},",
        "",
        "grazie per essere stata alla lezione gratuita del 14 settembre.",
        "",
        "Se il Corso Base di Human Design ti sta girando in testa e vuoi vederlo tutto",
        "nero su bianco, cos'\u{e8} lo Human Design, le venti lezioni una per una, come",
        "funziona e quanto costa, ho messo la brochure completa qui:",
        "",
        BROCHURE_URL,
        "",
        "Sono sei pagine, si leggono in cinque minuti. Da l\u{ec} la puoi anche scaricare in PDF.",
        "",
        "Se preferisci parlarne prima, il mio numero \u{e8} +39 379 103 7653: chiamami o",
        "scrivimi su WhatsApp quando vuoi, rispondo io.",
        "",
        "Le lezioni partono luned\u{ec} 12 ottobre, ogni luned\u{ec} alle 18 su Zoom.",
        "",
        "A presto,",
        "Valentina Russo",
        "Consulente Human Design / BG5 (Business Group 5)",
        "https://valentinarussobg5.com",
    ];
    $corpo = implode($ac, $righe) . $ac;

    $mittente = 'Valentina Russo <' . DA_EMAIL . '>';
    $intestazioni = "From: " . $mittente . $ac
        . "Reply-To: " . DA_EMAIL . $ac
        . "Content-Type: text/plain; charset=UTF-8" . $ac
        . "Content-Transfer-Encoding: 8bit" . $ac
        . "MIME-Version: 1.0";

    $oggetto = '=?UTF-8?B?' . base64_encode('La brochure del Corso Base di Human Design') . '?=';
    $riuscito = @mail($email, $oggetto, $corpo, $intestazioni);
    registra_invio($email, $riuscito ? 'brochure ok' : 'brochure FALLITA');
    return $riuscito;
}

/** Il registro della lezione, ripulito: nome + indirizzo, senza duplicati. */
function registro(): array {
    if (!is_file(REGISTRO)) { return []; }
    $fuori = array_map('strtolower', array_merge(INTERNI, paganti()));
    $serviti = is_file(SERVITI)
        ? array_map('strtolower', array_filter(array_map('trim', file(SERVITI, FILE_IGNORE_NEW_LINES))))
        : [];
    $fuori = array_merge($fuori, $serviti);

    $out = [];
    $h = fopen(REGISTRO, 'r');
    $prima = true;
    while (($r = fgetcsv($h)) !== false) {
        if ($prima) { $prima = false; continue; }
        $nome  = ltrim(trim((string)($r[1] ?? '')), "'");
        $email = ltrim(trim((string)($r[2] ?? '')), "'");
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { continue; }
        $chiave = strtolower($email);
        if (in_array($chiave, $fuori, true) || isset($out[$chiave])) { continue; }
        $out[$chiave] = ['nome' => $nome !== '' ? $nome : 'ciao', 'email' => $email];
    }
    fclose($h);
    return array_values($out);
}

$prova = trim((string)($_GET['prova'] ?? ''));
if ($prova !== '') {
    $email = filter_var($prova, FILTER_VALIDATE_EMAIL);
    if ($email === false) { echo "indirizzo di prova non valido\n"; exit; }
    echo manda_brochure('Marco', $email) ? "prova mandata a {$email}\n" : "prova NON riuscita\n";
    exit;
}

try {
    $destinatari = registro();
} catch (Throwable $e) {
    http_response_code(500);
    echo "elenco dei paganti non leggibile: nessuna mail mandata
";
    exit;
}

if (isset($_GET['elenco'])) {
    echo "aventi diritto: " . count($destinatari) . "\n";
    foreach ($destinatari as $d) { echo $d['nome'] . ' | ' . $d['email'] . "\n"; }
    exit;
}

if (!isset($_GET['vai'])) {
    echo "aventi diritto: " . count($destinatari) . "\n";
    echo "aggiungi &elenco=1 per vederli, &vai=1 per mandare\n";
    exit;
}

$fatte = 0;
$fallite = 0;
foreach ($destinatari as $d) {
    if (manda_brochure($d['nome'], $d['email'])) {
        file_put_contents(SERVITI, $d['email'] . "\n", FILE_APPEND | LOCK_EX);
        $fatte++;
    } else {
        $fallite++;
    }
}
echo "mandate: {$fatte}\nnon riuscite: {$fallite}\n";
