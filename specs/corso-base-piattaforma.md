# SPEC — Piattaforma Corso Base Human Design (custom, no Esmerise)
> Created: 28/08/2026
> Status: APPROVED

## Goal
Dashboard custom per il "Corso Base Human Design" (€1.200, live via Zoom, 2 semestri × 10 lezioni): ogni corsista vede solo il proprio corso (video registrazione + materiali + compiti), Valentina (admin) gestisce tutti i corsi/corsisti da un'unica vista. Sostituisce l'ipotesi piattaforma esterna (Esmerise) per non pagare canone/fee ricorrenti.

## Context
- Sito: Grav CMS (PHP/Twig) su `grav-site/`, ma i tool "applicativi" (non contenuti editoriali) vivono come PHP standalone in `grav-site/root/` (pattern esistente: `admin.php`+`admin_auth.php` per il CMS blog, `hd-db.php`+`hd-setup.php` per un sistema account già costruito).
- **Riuso obbligatorio**: `grav-site/root/hd-db.php` è un sistema di autenticazione completo e già sicuro (PDO/MySQL, password Argon2id+pepper, CSRF token, rate limiting login, sessioni hardened con idle timeout). È stato disattivato il 06/08/2026 per motivi GDPR (`hd-setup.php:1-12`) ma il codice resta valido — va riattivato e riusato, non riscritto da zero.
- DB esistente su Aruba/SupportHost: `valenti7_hd` con tabelle `hd_users`, `hd_charts`, `hd_login_attempts` (schema in `hd-setup.php`). `hd_charts` non serve per il corso — resta inutilizzata o va rimossa in uno step separato, fuori da questa spec.
- Pagamento del corso resta sul sito principale via Stripe/Klarna (`corso-base-human-design.html`), NON dentro questa piattaforma — l'iscrizione all'area corso è un passo manuale successivo fatto da Valentina/Marco dopo il pagamento (non serve integrazione automatica pagamento→accesso in questa fase).
- Corso NON evergreen: un solo cohort alla volta, nessun sistema di rivendita/riacquisto continuo.
- **Video hosting — DECISO 28/08: Bunny Stream** (bunny.net), non YouTube unlisted (nessun vero controllo accessi) né Vimeo (protezione a password condivisa, piani con privacy avanzata da ~20€/mese con prezzi in aumento nel 2026). Costo stimato Bunny: $1/mese minimo + $0.01/GB storage + $0.005/GB banda — per un corso di poche decine di studenti, pochi dollari/mese. Serve un account Bunny.net + API key (nuova dipendenza esterna, da creare).

## Non-goals
- Nessun NUOVO sistema di pagamento — le letture si prenotano/acquistano linkando il checkout Stripe gia esistente (letture-dati/checkout.php), non duplicato
- Nessun hosting video self-managed su Aruba (bandwidth/storage shared hosting insufficiente per ore di registrazioni)
- Nessuna app mobile
- Nessun sistema multi-cohort/rivendita automatica (corso singolo per ora)
- Nessuna migrazione/riattivazione di `hd_charts` (fuori scope)
- Nessun editor rich-text avanzato per il forum (testo semplice + eventuale allegato basta)

## Requirements

### Funzionali — Corsista
- R1: Il corsista fa login con email+password (riuso `hdLoginSession`/`hdGetCurrentUser` di hd-db.php).
- R2: Il corsista vede solo il/i corso/i a cui è iscritto — la visibilità è determinata da un record di iscrizione (tabella enrollment), non da un flag globale.
- R3: Ogni corso è un elenco ordinato di "classi" (lezioni); ogni classe mostra: titolo, video (Bunny Stream, vedi R14), PDF slide, PDF esercizio, tutti scaricabili/visualizzabili inline.
- R4: Ogni classe ha un thread forum dove il corsista posta il compito settimanale (testo + eventuale link/allegato) e vede le risposte di Valentina.
- R5: Un corsista con zero corsi assegnati vede uno stato vuoto con contatto di supporto, non un errore.

### Funzionali — Admin (Valentina/Marco)
- R6: L'admin vede una dashboard con tutti i corsi attivi e tutti i corsisti iscritti a ciascuno.
- R7: L'admin crea manualmente un account corsista (email) e lo iscrive a un corso specifico (nessun self-signup pubblico).
- R8: L'admin aggiunge/modifica una classe (titolo, upload video su Bunny Stream, upload PDF slide, upload PDF esercizio) sul corso.
- R9: L'admin risponde ai post del forum di qualunque corso/corsista.
- R10: Il ruolo admin è un flag esplicito sull'utente (es. `hd_users.role`), verificato lato server ad ogni richiesta — non basta essere loggati.

### Autorizzazione e sicurezza
- R11: Un corsista non può vedere classi, video, PDF o thread forum di un corso a cui non è iscritto, anche indovinando/enumerando URL — controllo server-side su ogni richiesta (non solo nascosto lato client).
- R12: Ogni azione che modifica stato (post forum, iscrizione, upload materiali) richiede CSRF token valido (riuso `hdCsrfToken`/`hdCsrfVerify`).
- R13: Login protetto da rate limiting per email e per IP (riuso `hdIsRateLimited`/`hdRecordFailedAttempt`).
- R14: Nessun video ospitato come file grezzo su Aruba. Video su **Bunny Stream**, riprodotto solo tramite URL firmato con token temporaneo generato dal backend PHP DOPO aver verificato lato server che il corsista è iscritto a quel corso (join su tabella enrollment) — non un link segreto/unlisted, un vero controllo per-richiesta.
- R15: Password hashate con Argon2id+pepper (riuso `hdHashPassword`/`hdVerifyPassword`), mai testo in chiaro, mai in log.
- R16: Sessioni con timeout idle 2h e invalidazione su cambio password (riuso `session_ver`, già presente in hd-db.php).
- R21: Le credenziali/API key di Bunny Stream vanno in config server-side (stesso pattern di `hd-db-config.php`, mai committate in chiaro nel repo), mai esposte al client.

### Edge case e gestione errori
- R17: Submit di un post forum vuoto o oltre una lunghezza massima (es. 10.000 caratteri) viene rifiutato con messaggio chiaro, non silenziosamente troncato o accettato.
- R18: Upload PDF rifiutato se il file non è un PDF valido o supera una dimensione massima (es. 20MB) — validazione sia su estensione/mimetype sia su contenuto (magic bytes), non solo estensione.
- R19: Se l'admin elimina una classe con post forum già presenti, i post restano leggibili (nessuna cancellazione a cascata silenziosa) — coerente con la regola generale del progetto contro le cancellazioni permanenti non richieste esplicitamente.
- R20: Tentativo di login con email non esistente e con password errata restituiscono lo stesso messaggio generico ("credenziali non valide") — nessuna user enumeration.
- R22: IMPLEMENTATO E VERIFICATO 29/08. Se il token firmato Bunny scade mentre il corsista sta guardando il video, il player deve poter richiedere un nuovo token senza ricaricare l'intera pagina. Realizzato con video-token.php (endpoint autenticato, stesso controllo iscrizione di lezione.php) + Player.js lato client: 5 minuti prima della scadenza, il player chiede un nuovo URL firmato, lo carica nello stesso iframe e riprende dal secondo esatto in cui era, senza reload della pagina.

## Acceptance Criteria
L'implementazione è completa quando:
- [ ] R1–R22 verificati
- [ ] `/adversarial-review specs/corso-base-piattaforma.md [file implementati]` non trova CRITICAL o HIGH
- [ ] Un corsista di test non riesce ad accedere a un corso a cui non è iscritto anche modificando l'URL a mano
- [ ] Un corsista di test non riesce a riprodurre il video di un corso a cui non è iscritto anche copiando l'URL del player Bunny da un altro browser/sessione
- [ ] `legal-guardian` ha rivisto la privacy policy aggiornata per la raccolta email/password dei corsisti (riapre GDPR surface chiusa il 06/08/2026 — vedi Context)

### Funzionali — Prenotazione letture (aggiunto 28/08, richiesta esplicita)
- R23: La dashboard corsista mostra il catalogo delle letture (riuso di letture-dati/config.php::LETTURE_CATALOG, nessun catalogo duplicato) con link diretto a letture-dati/checkout.php?reading=X per acquistare — nessuna logica di pagamento nuova in questo modulo.
- R24: L'area /corso/ (studente e admin) non e collegata dalla navigazione del sito principale e non ha alcun form di login sulla homepage — e un link a parte che Valentina consegna direttamente ai corsisti. Gia soddisfatto dal design attuale (pagine con noindex, nessun link da valentinarussobg5.com), nessuna modifica di codice necessaria per questo punto salvo mantenerlo cosi.

## Out of scope per questa spec
- Riattivazione/rimozione di `hd_charts`
- Integrazione automatica pagamento → iscrizione
- Gamification, certificati, punti/reward
- Multi-cohort / vendita ricorrente dello stesso corso
- App mobile o notifiche push
