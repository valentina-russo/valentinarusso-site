<?php
/**
 * Attrezzo esaurito.
 *
 * L'8 settembre 2026 questo script ha rimandato la conferma, con dentro il
 * link Zoom e l'evento del calendario, ai tre indirizzi che si erano iscritti
 * prima che la mail li contenesse. Fatto quello non serve piu'.
 *
 * Il deploy via FTP non cancella i file dal server, quindi non basta toglierlo
 * dal repository: il file resta li'. Per questo ora non fa piu' niente, e il
 * segreto che lo attivava e' stato rimosso dalla configurazione.
 *
 * La versione che funzionava e' nella storia di git, al commit 52379b8.
 */
declare(strict_types=1);

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo "non trovato\n";
