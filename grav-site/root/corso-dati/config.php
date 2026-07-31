<?php
/**
 * Corso BG5 — Catalogo prodotto
 * Usato da checkout.php, dati.php, invia.php
 *
 * NOTA 31/07: nome prodotto "BG5 Foundation" e claim "Accreditato IACET / CEU"
 * ritirati su indicazione diretta di Valentina — il corso non è professionalizzante
 * e lei non rilascia crediti formativi. Nome definitivo TBD (Marco/Valentina).
 * Prezzo a semestri (proposta Valentina 31/07): S1 da solo 700€, S2 da solo 700€
 * (totale 1.400€ se separati), oppure tutto insieme a 1.200€ (risparmio 200€).
 * Pagamento a rate: gestito da Klarna una volta attivato lato Dashboard
 * Stripe (Impostazioni -> Metodi di pagamento). Il checkout non impone
 * payment_method_types, quindi Klarna compare automaticamente appena
 * abilitato sull'account, senza modifiche a questo codice.
 */

declare(strict_types=1);

const COURSE_CATALOG = [
    'bg5-foundation' => [
        'name'        => 'Corso BG5 — Percorso Completo',
        'description' => '20 lezioni online dal vivo via Zoom, 2 semestri da 10 lezioni (2 ore ciascuna). Semestre 1: Personal Operating Style. Semestre 2: Creative Operating Style. Percorso non professionalizzante, nessuna certificazione rilasciata.',
        'amount'      => 120000, // €1.200,00 — pacchetto completo, risparmio 200€ vs semestri separati
    ],
    'bg5-foundation-s1' => [
        'name'        => 'Corso BG5 — Semestre 1',
        'description' => '10 lezioni online dal vivo via Zoom (2 ore ciascuna) — Personal Operating Style. Percorso non professionalizzante, nessuna certificazione rilasciata.',
        'amount'      => 70000, // €700,00
    ],
    'bg5-foundation-s2' => [
        'name'        => 'Corso BG5 — Semestre 2',
        'description' => '10 lezioni online dal vivo via Zoom (2 ore ciascuna) — Creative Operating Style. Consigliato dopo il Semestre 1. Percorso non professionalizzante, nessuna certificazione rilasciata.',
        'amount'      => 70000, // €700,00
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
