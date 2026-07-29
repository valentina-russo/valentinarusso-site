<?php
/**
 * Letture Human Design/BG5 — Catalogo prodotti condiviso
 * Usato da checkout.php, dati.php, invia.php
 *
 * data_mode:
 *   single  -> un solo set di dati di nascita (il cliente)
 *   couple  -> due set di dati di nascita (persona A + persona B)
 *   child   -> dati di nascita del figlio/a + contatto del genitore
 *
 * NB PREZZI: tutti confermati esplicitamente da Marco. Foundation 210,
 * Bimbo 210, Coppia 300, Ritorno Solare 150, tutte le altre 250.
 */

declare(strict_types=1);

const LETTURE_CATALOG = [
    'foundation' => [
        'name'        => 'Lettura Foundation (Prima Lettura)',
        'description' => 'Decodifica Tipologia Energetica, Strategia, Autorità Interna e Profilo. Il punto di ingresso obbligato.',
        'amount'      => 21000, // €210,00 — CONFERMATO
        'data_mode'   => 'single',
        'duration'    => '120 minuti',
    ],
    'coppia' => [
        'name'        => 'Lettura di Coppia',
        'description' => 'Analisi del grafico di connessione tra due disegni: canali elettromagnetici, centri definiti a vicenda, tema energetico della coppia.',
        'amount'      => 30000, // €300,00 — CONFERMATO
        'data_mode'   => 'couple',
        'duration'    => '90 minuti',
    ],
    'figlio' => [
        'name'        => 'Lettura per il Figlio/a',
        'description' => 'Lettura del disegno di tuo figlio prima che il condizionamento si sedimenti, con indicazioni pratiche per non trasmettere gli schemi del Non-Sé.',
        'amount'      => 21000, // €210,00 — CONFERMATO
        'data_mode'   => 'child',
        'duration'    => '60 minuti',
    ],
    'ombra' => [
        'name'        => 'Lettura dei Lati Ombra',
        'description' => 'Mappatura del tuo tema del Non-Sé specifico e costruzione di un sistema di riconoscimento in tempo reale.',
        'amount'      => 25000, // €250,00 — CONFERMATO ("l'ombra")
        'data_mode'   => 'single',
        'duration'    => '90 minuti',
    ],
    'croce' => [
        'name'        => 'Lettura della Croce di Incarnazione',
        'description' => 'La tua Croce di Incarnazione: da dove nasce, cosa rappresenta, cosa sei venuto a incarnare in questa vita.',
        'amount'      => 25000, // €250,00 — CONFERMATO
        'data_mode'   => 'single',
        'duration'    => '90 minuti',
    ],
    'ritorno-solare' => [
        'name'        => 'Lettura del Ritorno Solare',
        'description' => 'Check-in annuale: quali porte si accendono nei prossimi dodici mesi, vicino al tuo compleanno.',
        'amount'      => 15000, // €150,00 — CONFERMATO
        'data_mode'   => 'single',
        'duration'    => '60 minuti',
    ],
    'ritorno-saturno' => [
        'name'        => 'Lettura del Ritorno di Saturno',
        'description' => 'Il passaggio alla piena responsabilità della vita adulta, intorno ai 29 anni, letto sulla tua carta.',
        'amount'      => 25000, // €250,00 — CONFERMATO ("i ritorni")
        'data_mode'   => 'single',
        'duration'    => '90 minuti',
    ],
    'ritorno-chirone' => [
        'name'        => 'Lettura del Ritorno di Chirone',
        'description' => 'La ferita che porti dall\'infanzia, intorno ai 50 anni, e come trasformarla nel tuo contributo più grande.',
        'amount'      => 25000, // €250,00 — CONFERMATO ("i ritorni")
        'data_mode'   => 'single',
        'duration'    => '90 minuti',
    ],
    'opposizione-urano' => [
        'name'        => 'Lettura dell\'Opposizione di Urano',
        'description' => 'Il passaggio dal tema del Nodo Sud al Nodo Nord, tra i 38 e i 42 anni: la mezza età letta sulla tua carta.',
        'amount'      => 25000, // €250,00 — CONFERMATO ("le opposizioni")
        'data_mode'   => 'single',
        'duration'    => '90 minuti',
    ],
];

function letture_get(string $key): ?array {
    return LETTURE_CATALOG[$key] ?? null;
}

function letture_load_env(): void {
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
