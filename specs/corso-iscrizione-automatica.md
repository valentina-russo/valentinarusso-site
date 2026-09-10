# Dal pagamento all'accesso: iscrizione automatica al corso

Stato: in implementazione, 10/09/2026.
Superficie: pagamenti, credenziali, sessioni. Passata da `rafter-secure-design`
(blocco auth + capostipite STRIDE) prima di scrivere codice.

## Il problema

Le due metà esistono e funzionano separatamente.

Pagamento: `corso-base-human-design.html` -> `corso-dati/checkout.php` -> Stripe
Checkout -> `corso-dati/dati.php` -> `corso-dati/invia.php`, che **verifica presso
Stripe che la sessione sia `paid`** prima di accettare, manda due email e scrive
`logs/iscrizioni.log`.

Accesso: `corso/admin/iscrivi.php`, dove Valentina scrive email e nome a mano e il
sistema le mostra **una password generata, visibile una volta sola a schermo**.
Nessuna email parte: deve copiarla e mandarla lei.

Fra le due non passa niente. Dopo il pagamento la piattaforma non sa che qualcuno
ha pagato, e non sa in quale classe metterlo.

## Decisioni di sicurezza

### D1 — Identita' e primitiva di autenticazione: si tiene quella che c'e'
Utenti umani, non servizi ne' agenti. Nessuna federazione (SSO/OIDC): la
piattaforma ha gia' password proprie con sessioni PHP lato server e
`hd_users.session_ver` per invalidarle. Introdurre un identity provider per una
trentina di allieve sarebbe sproporzionato. **Rifiutato**: JWT, che qui non
servirebbe a niente e renderebbe la revoca piu' difficile di adesso.

### D2 — Hashing: `hdHashPassword()`, invariato
Argon2id con pepper, fallback bcrypt cost 12. Nessun hashing artigianale.

### D3 — La password non viaggia mai: link di attivazione monouso
Al posto della password generata da noi, la mail di conferma porta un link a
`/corso/attiva.php?t=<token>` dove l'allieva **sceglie da se'** la password.

- Token da `random_bytes(32)`, 64 caratteri esadecimali.
- **A riposo si salva l'impronta**, non il token: `hash('sha256', $token)` in
  `hd_users.reset_token` (VARCHAR(64), la colonna esiste gia'). Chi leggesse il
  database non ottiene link utilizzabili.
- Scadenza in `reset_expires`: **7 giorni**. Chi paga di sera e attiva nel
  weekend non resta fuori; oltre, Valentina rigenera dal pannello.
- Monouso: all'attivazione riuscita `reset_token` e `reset_expires` vanno a NULL,
  `verified_at` a NOW(), `session_ver` +1 (chiude eventuali sessioni aperte).
- La pagina di attivazione **non rivela se un indirizzo esista**: un token
  sbagliato, scaduto o gia' speso da' sempre lo stesso messaggio.
- Tentativi limitati per IP con `hdIsRateLimited()` / `hdRecordFailedAttempt()`,
  gli stessi del login.

### D4 — Quale classe: mappatura esplicita, mai indovinata
Nuova tabella `cohort_products (cohort_id, catalog_key)`: dice quale prodotto del
catalogo serve una classe. Impostata a caselle dalla pagina della classe in admin.
Il percorso completo (`bg5-foundation`) puo' mappare su due classi, i semestri su
una ciascuno.

`invia.php` iscrive nelle classi **non archiviate** mappate al prodotto comprato.
Se non ne trova nessuna **non tira a indovinare**: l'iscrizione resta registrata e
la mail a Valentina porta in testa una riga `AZIONE RICHIESTA`. Un pagamento non
si perde mai per colpa di una mappatura mancante.

### D5 — Un pagamento, un'iscrizione: `course_payments.session_id` UNIQUE
Il `session_id` di Stripe compare nell'URL di ritorno e quindi e' noto a chi ha
pagato. Senza difesa si potrebbe rimandare il form piu' volte con indirizzi
diversi e ottenere piu' account da un solo pagamento. La tabella
`course_payments` ha `session_id` UNIQUE: il secondo tentativo viene rifiutato,
e resta a registro.

### D6 — Si iscrive solo su pagamento verificato da Stripe
`invia.php` ha un ramo di ripiego che, se `STRIPE_SECRET_KEY` manca, accetta il
prodotto dal parametro della richiesta. Quel ramo **non** iscrive nessuno: senza
conferma di Stripe si mandano solo le email, come oggi. Nessun account nasce da
una richiesta non verificata.

### D7 — Nessuna scalata di privilegi
L'account nuovo nasce sempre con `role = 'student'`. Se l'indirizzo appartiene
gia' a un amministratore, non si tocca niente e si avvisa: e' lo stesso guardiano
che protegge il reset password in `admin/classe.php`.

### D8 — Chi ha gia' un account non riceve link di attivazione
Chi compra il Semestre 2 dopo aver fatto il primo viene solo iscritto alla nuova
classe, con la password che usa gia'. Nessun token generato, nessuna sessione
invalidata. Come si comporta gia' il form manuale.

### D9 — Lo schema si crea da se', senza script one-time
Le due tabelle nuove nascono con `CREATE TABLE IF NOT EXISTS` da
`corsoEnsureIscrizioneSchema()`, chiamata solo dai due percorsi che le usano.
**Rifiutato**: un altro script di migrazione con token, come quelli che sono
serviti ad agosto e che poi sono stati neutralizzati uno per uno perche' il
deploy FTP non li cancella dal server.

### D10 — Autorizzazione: invariata
L'accesso alle lezioni resta `course_enrollments` + i controlli lato server per
endpoint (`corsoRequireEnrollment`), verificati il 29/08 con un account non
iscritto. Questo lavoro aggiunge righe a quella tabella, non cambia chi puo'
leggere cosa.

## STRIDE, in breve

| | |
|---|---|
| Spoofing | Account solo dopo `payment_status = paid` confermato da Stripe (D6). |
| Tampering | L'indirizzo viene dal form di chi ha pagato: e' il suo acquisto. Il riuso del `session_id` e' chiuso da D5. |
| Repudiation | `course_payments` tiene sessione, indirizzo, classi e data. |
| Information disclosure | Nessuna password nelle email (D3), token in chiaro mai a riposo, pagina di attivazione muta sull'esistenza degli indirizzi. |
| Denial of service | Attivazione limitata per IP con gli stessi contatori del login. |
| Elevation of privilege | Ruolo fisso `student`, guardiano sugli amministratori (D7). |

## Cosa resta fuori da questo lavoro

- Il pannello per **rigenerare** un link scaduto: per ora Valentina usa il reset
  password che c'e' gia'. Da aggiungere se capita davvero.
- Nessun webhook Stripe: la conferma avviene alla lettura sincrona della sessione.
  Se l'allieva chiude il browser prima di compilare il form, il pagamento resta
  registrato su Stripe ma l'iscrizione non parte. Valentina lo vede dalla
  dashboard Stripe e iscrive a mano. Un webhook chiuderebbe anche questo caso ed
  e' il prossimo passo naturale.

## Come si prova, senza toccare il server

Il progetto non aveva modo di eseguire PHP in locale, quindi questa modifica al
gestore dei pagamenti sarebbe finita in produzione senza mai essere stata
eseguita. Ora si prova cosi':

1. PHP portatile con `pdo_mysql` attivo (`php.ini` con `extension=pdo_mysql`).
2. Un MariaDB qualunque, anche portatile, su una porta libera.
3. `tools/corso-prove/schema-di-prova.sql` crea le tabelle come in produzione,
   comprese le colonne che il pannello si aspetta (`courses.slug`,
   `hd_users.avatar_path`) e la forma vera di `hd_login_attempts`, che ha una
   sola colonna `identifier` con l'impronta di email o IP.
4. `grav-site/root/hd-db-config.php` (in .gitignore) punta al database locale.
5. `php tools/corso-prove/prova_iscrizione.php` esegue 34 controlli sul percorso
   completo: nascita delle tabelle, mappatura, primo acquisto, riuso della
   sessione di pagamento, secondo acquisto sullo stesso indirizzo, prodotto senza
   classi, indirizzo di amministratore, attivazione, token scaduto.

Il resto si e' provato con il server interno di PHP: la pagina di attivazione
lungo tutto il giro (CSRF, password debole, password diverse, attivazione,
riuso del link) e il pannello della classe (rende le caselle, salva, e scarta
una chiave di prodotto inventata).

## Cosa questo lavoro NON prova

Il giro vero con Stripe. Le chiavi sono in modalita' live e una prova reale
costerebbe da 700 a 1.200 euro, quindi `corsoIscriviDaPagamento` e' stata
verificata contro un MySQL vero ma con un identificativo di sessione finto. Il
primo acquisto reale va guardato: la mail a Valentina dice sempre com'e' andata
l'iscrizione, e `course_payments` tiene la riga con l'esito.

## Trovato per strada, non sistemato qui

`hdSecHeaders()` esiste in `hd-db.php` ma nessuna pagina della piattaforma la
chiama: tutta l'area del corso viaggia senza intestazioni di sicurezza. E' un
buco che precede questo lavoro e riguarda ogni pagina, non solo le due nuove,
quindi va chiuso in un intervento suo invece che di straforo qui.
