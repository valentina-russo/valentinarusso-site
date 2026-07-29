<?php
/**
 * Corso BG5 Foundation — Catalogo prodotto
 * Usato da checkout.php, dati.php, invia.php
 *
 * Prezzo confermato da Marco: 1200 EUR pagamento unico.
 * Pagamento a rate: gestito da Klarna una volta attivato lato Dashboard
 * Stripe (Impostazioni -> Metodi di pagamento). Il checkout non impone
 * payment_method_types, quindi Klarna compare automaticamente appena
 * abilitato sull'account, senza modifiche a questo codice.
 */

declare(strict_types=1);

const COURSE_CATALOG = [
    'bg5-foundation' => [
        'name'        => 'Corso BG5 Foundation',
        'description' => '20 lezioni online dal vivo via Zoom, 2 semestri da 10 lezioni (2 ore ciascuna). Semestre 1: Personal Operating Style. Semestre 2: Creative Operating Style. Accreditato IACET.',
        'amount'      => 120000, // €1.200,00 — CONFERMATO
    ],
];

function course_get(string $key): ?array {
    return COURSE_CATALOG[$key] ?? null;
}

function course_load_env(): void {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}
