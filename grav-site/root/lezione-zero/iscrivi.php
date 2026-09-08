<?php
/**
 * Iscrizione alla lezione gratuita del 14 settembre.
 * Scrive nel registro CSV (protetto da .htaccess) e avvisa Valentina via email.
 * Nessun servizio esterno: quando si sceglie la piattaforma di invio,
 * i contatti si esportano da qui.
 */
declare(strict_types=1);

const PAGINA   = '/lezione-gratuita-human-design';
const REGISTRO = __DIR__ . '/iscritti.csv';
const LIMITI   = __DIR__ . '/limiti.json';
const A_VALENTINA = 'consulenze@valentinarussobg5.com, consulenza@marcomunich.com';
const DA_EMAIL    = 'info@valentinarussobg5.com';

function ambiente(string $chiave): string {
    static $conf = null;
    if ($conf === null) {
        $conf = [];
        $f = __DIR__ . '/' . '.' . 'env';
        if (is_file($f)) {
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $riga) {
                $riga = trim($riga);
                if ($riga === '' || $riga[0] === '#' || !str_contains($riga, '=')) { continue; }
                [$k, $v] = explode('=', $riga, 2);
                $conf[trim($k)] = trim($v);
            }
        }
    }
    return $conf[$chiave] ?? '';
}

/**
 * Manda il contatto a Brevo. Se la chiave manca o la chiamata non riesce non
 * succede nulla di grave: l'iscrizione e' gia' nel registro locale, che resta
 * la fonte di verita'.
 */
function a_brevo(string $nome, string $email, string $origine): void {
    $chiave = ambiente('BREVO_API_KEY');
    if ($chiave === '' || !function_exists('curl_init')) { return; }
    $lista = (int)ambiente('BREVO_LIST_ID');
    $corpo = [
        'email' => $email,
        'attributes' => ['NOME' => $nome, 'ORIGINE' => $origine, 'EVENTO' => 'lezione-14-settembre'],
        'updateEnabled' => true,
    ];
    if ($lista > 0) { $corpo['listIds'] = [$lista]; }

    $ch = curl_init('https://api.brevo.com/v3/contacts');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $chiave],
        CURLOPT_POSTFIELDS => json_encode($corpo, JSON_UNESCAPED_UNICODE),
    ]);
    $risposta = curl_exec($ch);
    $codice = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($codice < 200 || $codice >= 300) {
        @file_put_contents(__DIR__ . '/brevo.log',
            date('c') . " HTTP {$codice} " . substr((string)$risposta, 0, 300) . "
",
            FILE_APPEND | LOCK_EX);
    }
}

/**
 * Excel e LibreOffice eseguono come formula ogni cella che inizia con = + - @
 * o con un tabulatore. Si antepone un apostrofo, che i fogli di calcolo
 * leggono come "questo e' testo".
 */
function scudo(string $v): string {
    return ($v !== '' && str_contains("=+-@	
", $v[0])) ? "'" . $v : $v;
}

function rimanda(string $esito): never {
    header('Location: ' . PAGINA . '?esito=' . $esito, true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { rimanda('metodo'); }

session_start();
$inviato = (string)($_POST['csrf_token'] ?? '');
$atteso  = (string)($_SESSION['csrf_lezione'] ?? '');
if ($atteso === '' || !hash_equals($atteso, $inviato)) { rimanda('sessione'); }

// campo esca: i moduli compilati dai bot lo riempiono, le persone no
if (trim((string)($_POST['citta'] ?? '')) !== '') { rimanda('ok'); }

$nome    = trim((string)($_POST['nome'] ?? ''));
$email   = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$consenso = isset($_POST['consenso']);

if ($nome === '' || mb_strlen($nome) > 80 || $email === false || !$consenso) {
    rimanda('dati');
}
$nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome);
$origine = substr(preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['origine'] ?? '')), 0, 40);

// non piu' di 5 iscrizioni per indirizzo IP in un'ora
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$chiave = hash('sha256', $ip);
$ora = time();
$fh = fopen(LIMITI, 'c+');
if ($fh !== false) {
    flock($fh, LOCK_EX);
    $grezzo = stream_get_contents($fh);
    $conteggi = $grezzo ? (json_decode($grezzo, true) ?: []) : [];
    $conteggi = array_filter($conteggi, static fn($v) => ($v['t'] ?? 0) > $ora - 3600);
    $mio = $conteggi[$chiave] ?? ['n' => 0, 't' => $ora];
    if ($mio['n'] >= 5) { flock($fh, LOCK_UN); fclose($fh); rimanda('troppi'); }
    $conteggi[$chiave] = ['n' => $mio['n'] + 1, 't' => $ora];
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($conteggi));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

// registro: una riga per iscritto, niente duplicati sullo stesso indirizzo
$gia = false;
if (is_file(REGISTRO) && ($h = fopen(REGISTRO, 'r')) !== false) {
    while (($r = fgetcsv($h)) !== false) {
        if (isset($r[2]) && strcasecmp($r[2], $email) === 0) { $gia = true; break; }
    }
    fclose($h);
}
if (!$gia) {
    $nuovo = !is_file(REGISTRO);
    if (($h = fopen(REGISTRO, 'a')) !== false) {
        if (flock($h, LOCK_EX)) {
            if ($nuovo) { fputcsv($h, ['data_iscrizione', 'nome', 'email', 'consenso', 'origine']); }
            fputcsv($h, [date('c'), scudo($nome), scudo($email), 'si', scudo($origine)]);
            fflush($h);
            flock($h, LOCK_UN);
        }
        fclose($h);
    }
}

a_brevo($nome, $email, $origine);

$quando = 'lunedi 14 settembre 2026, ore 20:30';

if (!$gia) {
    $oggetto = 'Nuova iscrizione lezione gratuita: ' . $nome;
    $corpo = "NOME  : {$nome}\nEMAIL : {$email}\nQUANDO: {$quando}\nORIGINE: "
           . ($origine !== '' ? $origine : '-') . "\nDATA  : " . date('d/m/Y H:i') . "\n";
    @mail(A_VALENTINA, $oggetto, $corpo,
        "From: Valentina Russo <" . DA_EMAIL . ">\r\nContent-Type: text/plain; charset=UTF-8");
}

if (!$gia) {
    $saluto = "Ciao {$nome},\n\n"
        . "ti ho segnato per la lezione gratuita di {$quando}, su Zoom.\n\n"
        . "Ti scrivo io con il link qualche giorno prima. Se nel frattempo vuoi arrivare preparata o preparato, "
        . "calcola intanto il tuo Bodygraph qui: https://valentinarussobg5.com/genera-carta\n\n"
        . "A presto,\nValentina Russo\nAnalista BG5 / Human Design\n";
    @mail($email, 'Sei iscritto alla lezione gratuita del 14 settembre', $saluto,
        "From: Valentina Russo <" . DA_EMAIL . ">\r\nContent-Type: text/plain; charset=UTF-8");
}

rimanda('ok');
