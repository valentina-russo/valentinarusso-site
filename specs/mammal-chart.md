# SPEC — Mammal Chart (Human Design per cani)
> Created: 11/08/2026
> Status: DRAFT

## Goal
Calcolatore gratuito, analogo a `/calcolo-human-design`, che genera la carta Human Design ("Mammal Chart") di un cane a partire da data/ora/luogo di nascita (o adozione), riusando il motore astronomico esistente (`swe-hd.js` + SwissEphemeris WASM) e adattando testi/etichette al contesto animale.

## Context
- Motore esistente: `grav-site/user/themes/valentina/js/swe-hd.js` (1129 righe, wrapper SwissEphemeris) + `swisseph-browser.js` / `swisseph.wasm`, calcolo client-side, nessun dato inviato a server.
- Pagina di riferimento: `grav-site/user/pages/09.calcolo-human-design/default.md`, template `genera_carta_beta`, gratuito, no login (`hd_account_enabled: false`).
- Meccanica HD standard: 9 centri, 64 porte, 36 canali, doppia foto astronomica (nascita + ~88 giorni prima = "Design"). La Mammal Chart usa la STESSA meccanica astronomica/gate — non esiste un motore di calcolo alternativo per animali nel sistema HD classico. Cambiano interpretazione e naming (niente tema "Not-Self" umano, niente linguaggio su "carriera/relazioni umane").
- **Assunzione da validare con Valentina (nessuna fonte HD autorevole consultata in questa spec)**: le etichette Tipo/Profilo/Autorità per animali potrebbero necessitare denominazioni diverse da quelle umane (es. niente "Manifestatore Costruttore" in senso di carriera). Questo va confermato prima di scrivere i testi definitivi — R9 lo isola come rischio esplicito.

## Non-goals
- Non include un motore di calcolo astronomico alternativo: si riusa SwissEphemeris così com'è.
- Non include vendita di un prodotto PDF a pagamento in questa fase (solo tool gratuito standalone, come l'equivalente umano).
- Non include carta di coppia/composito per due animali (solo carta singola in questa fase).
- Non copre specie diverse dal cane in questa fase (no gatti, cavalli, ecc. — riferimento futuro).

## Requirements

### Functional
- R1: Il sistema deve accettare in input nome del cane (opzionale, per personalizzare l'output), data di nascita, ora di nascita (opzionale), luogo di nascita.
- R2: Se l'ora di nascita non è nota, il sistema deve permettere il calcolo con un'ora di default (mezzogiorno) mostrando un avviso che Tipo/Profilo/Autorità potrebbero non essere accurati — stesso pattern già usato nella pagina umana.
- R3: Il sistema deve permettere, in alternativa alla data di nascita sconosciuta, l'inserimento della data di adozione come proxy, con un avviso esplicito che il risultato non è la vera Mammal Chart ma un'approssimazione.
- R4: Il calcolo deve produrre bodygraph con 9 centri, 64 porte, 36 canali, Tipo, Profilo, Autorità — stessa struttura dati dell'endpoint umano, generata da `swe-hd.js`.
- R5: I testi di interpretazione (Tipo/Strategia/Autorità) devono essere riscritti in chiave comportamentale canina (no linguaggio su carriera/relazioni romantiche umane), NON riusare 1:1 i testi della pagina umana.
- R6: Il risultato deve essere visualizzabile nel browser senza invio dati a server esterni (stesso vincolo privacy della pagina umana).
- R7: La pagina deve avere URL e slug dedicati (es. `/mammal-chart` o `/carta-human-design-cane`), non sovrascrivere `/calcolo-human-design`.

### Edge Cases & Error Handling
- R8: Se luogo di nascita non è geocodificabile (comune/città inesistente), il sistema deve mostrare un errore chiaro invece di calcolare con coordinate sbagliate — stesso comportamento della pagina umana da verificare e riusare.
- R9: **Rischio di contenuto non validato**: prima della pubblicazione, i testi di interpretazione Tipo/Profilo/Autorità per cani devono essere rivisti e approvati da Valentina (fonte HD autorevole sulla Mammal Chart) — non pubblicare con testi placeholder/assunti da Claude senza validazione umana esperta.
- R10: Se data di nascita è nel futuro o palesemente non valida (es. anno < 1990 per un cane vivo), il sistema deve rifiutare l'input con messaggio d'errore.

### Security
- R11: Nessun dato inserito dall'utente (nome cane, data, luogo) deve essere inviato a server esterni — stesso vincolo privacy-first della pagina umana, calcolo 100% client-side.

### Performance
- R12: Il calcolo deve completarsi in browser in meno di 5 secondi su connessione media (stesso target implicito della pagina "carta in 30 secondi", il calcolo vero e proprio è quasi istantaneo una volta caricato il WASM).

## Acceptance Criteria
The implementation is complete when:
- [ ] R1–R12 verificati
- [ ] Testi di interpretazione approvati da Valentina (R9) — BLOCCANTE per la pubblicazione, non per lo sviluppo
- [ ] Test manuale con almeno 3 date/ore/luoghi diversi confrontando output con la pagina umana per verificare che gate/centri/canali risultino identici a parità di dati astronomici (stesso motore, stesso birthdata umano vs canino deve produrre stesso identico bodygraph strutturale)

## Out of scope for this spec
- Prodotto PDF "Libretto Cane" a pagamento (eventuale fase 2, come per il Libretto umano)
- Carta di coppia cane+cane o cane+persona (Penta/composito)
- Altre specie (gatti, cavalli)
- SEO/GEO dedicato alla nuova pagina (verrà fatto come pass separato prima del delivery, per policy CLAUDE.md esistente)

## Log conferme
- [11/08/2026] Valentina conferma: gli input richiesti (coordinate/data/ora/luogo) sono gli stessi già usati per il Dream Rave Chart — R1 confermato senza modifiche. Non risolve R9 (interpretazione testuale Tipo/Profilo/Autorità per animali), che resta aperto.
