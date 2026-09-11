# Task Board

## BG5 Blueprint — Piano approvato (12/04)
Piano completo: `.claude/plans/keen-finding-cocke.md`

### FASE A — Generator production-ready ✅ COMPLETA
- [x] A1: Estrarre 19 prompt in `prompts.py` con template
- [x] A2: calc_chart_ephem.py — calcolo carta HD via ephem
- [x] A3: Collegare `rebuild_pdfs.build_pdf()` a `generator.py`
- [x] A4: Aggiornare generator da 7 a 19 sezioni

### FASE B — Landing page + Stripe ✅ TEST MODE COMPLETO
- [x] B1: Template Twig landing page `/bg5-blueprint` + deploy fix
- [x] B2: Stripe Checkout integration — account VRBG5, sk_test_ via GitHub Secret + workflow .env, end-to-end verificato (28/04)
- [ ] B3: Configurare webhook Stripe su Aruba (post go-live)
- [x] B4: Switch a sk_live_ — **FATTO 20/07**. Bug scoperto durante il primo tentativo: al primo giro Marco aveva copiato la chiave perdendo il primo carattere ("k_live_" invece di "sk_live_"), causa HTTP 401 diagnosticata via error_log su cPanel File Manager. Corretto al secondo giro (copia via icona invece di selezione manuale). Verificato live via curl: entrambi i tier (base/avanzato) reindirizzano a `cs_live_`.

### FASE C — Deploy generator
- [ ] C1: GitHub Actions workflow `generate-blueprint.yml`
- [ ] C2: Email conferma + consegna

### FASE D — Review UI
- [ ] D1: Completare review.php (PDF inline, approva/invia)

### FASE E — Polish + go-live
- [ ] E1: Test end-to-end Stripe test mode
- [ ] E2: Monitoring + error handling

---

## Today (prossima sessione)

> **Priorita' proposte al wrap-up 11/09** (in ordine):
> 1. **Bunny: ricarica e chiave.** E' l'unica cosa che blocca i video del corso, e le lezioni partono il 12 ottobre. Marco mette i 20 euro con la carta e passa la chiave API dell'account; il resto lo faccio io (libreria, MP4 fallback, secret, video di prova).
> 2. **Leggere lo spazio libero** in Oggi -> Stato del server. Se oltre 13 GB, la strada dei video sul nostro disco resta aperta come riserva.
> 3. **Contenuti del corso da Valentina**: 10 lezioni del semestre 2, nome e date definitivi, video di presentazione. Attesi il 29/08, mai arrivati, e mancano quattro settimane all'inizio.
> 4. Se avanza tempo: mettere in vista Autorita', Definizione e Profili dentro le pagine dei Centri (le prime hanno meno di 6 letture, i Centri 15-18).

### Fatto l'11/09
- [x] **Brochure del corso online** (`/corso-base-human-design/brochure`): sei pagine impilate, colonna fissa con iscrizione e telefono, barra in fondo su telefono, tocco per ingrandire, PDF scaricabile. Attrezzo per la mail di follow-up pronto e disarmato.
- [x] **Password dimenticata** per le allieve, con link di due ore e risposta muta sull'esistenza dell'indirizzo. Spec `specs/corso-recupero-password.md`.
- [x] **Foto della docente riparata**: compariva rotta in tutto il forum perche' il controllo chiedeva una classe in comune e Valentina non e' iscritta a nessuna.
- [x] **Pannello "Oggi"**: compiti che aspettano, lezioni senza registrazione, allieve mai entrate col tasto per mandargli il link. L'elenco dei corsi e' diventato "Corsi".
- [x] **Da correggere scritto per esteso** nella pagina della classe, e il vecchio "Reset password" sostituito da "Manda link password" (niente piu' segreti copiati a mano).
- [x] **Mail all'allieva quando Valentina risponde** a un compito.
- [x] **Forum rifatto alla maniera classica**, con sezioni per argomento dentro la classe: Bacheca (solo docente, divieto verificato anche contro invio forzato), Compiti, Domande, Presentazioni. Colonne Risposte e Letture, messaggi numerati, autore nella colonna a sinistra.
- [x] **Aula rivestita**: schede con copertina per corsi e lezioni, video su palco pieno nella pagina della lezione.
- [x] **Video per link**: si incolla Bunny, Drive, YouTube, Vimeo o un file nostro, e tornano le anteprime. Piu' il tasto "Scarica la lezione" con indirizzo firmato che scade.
- [x] **Contatore delle visite per provenienza** sulla lezione gratuita, sul server e senza cookie, con l'attrezzo che mette in fila visite, iscritte e conversione.
- [x] **Search Console collegato e letto** (`tools/search_console.py`): l'account di servizio era gia' proprietario del sito.
- [x] **Primi due interventi SEO pubblicati**: cinque pagine Strategia col nome della Tipologia nel titolo, rimandi dagli articoli alla guida (5 + 12), titoli dei due articoli riscritti sulle ricerche vere.
- [x] **Messaggio per Valentina** sulla scelta dei video, con i costi per esteso.

## Google Business Profile (creato 12/06 — DA VERIFICARE)
- [ ] **🔴 Verifica GBP "Valentina Russo BG5"** — serve indirizzo postale reale Valentina (resta nascosto, blocker [indirizzo fisico]). Dashboard → "Esegui la verifica". Finché non verificato il profilo NON è pubblico.
- [ ] **Foto GBP** — caricare logo + ritratto (pool selfie / valentina.png) da dashboard editor "Foto" (wizard upload non funziona via automazione)
- [ ] **Aggiungere servizi GBP** — Prima Lettura €210, Vita e Relazioni €500, BG5 Business da €1.800, Libretto da €90, Analisi Team, Selezione personale BG5 (sezione "Modifica servizi")

## This Week
- [ ] **Stripe live**: Valentina autorizza switch a sk_live_ → gh secret set → push → live (dipende da verifica identità Valentina)
- [ ] **Sostituire `[INDIRIZZO FISICO COMPLETO]`** in 4 punti (privacy/terms/invia.php/bg5_blueprint) — bloccato su input Valentina, ma è blocker go-live
- [ ] Verifica esterna dati Marco su myhumandesign.com (19/01/1983, 01:45, Vicenza)
- [ ] M-04 (legal): retention policy `ordini.log` (90gg cron rotazione)
- [ ] M-03 (legal): pulsante recesso digitale art. 54-bis CdC (in vigore 19/06/2026)
- [ ] Standalone HD calculator — consegnare al cliente finlandese quando sito pronto
- [ ] Feature: input gates manuali nel Tool HD Relazionale (per carte senza data)

## Backlog
- [ ] BG5 Blueprint di Coppia (Composite): pipeline PDF con dati di due persone — sezioni compatibilità energetica, strategie di team, dinamiche decisionali
- [ ] A2: Implementare calculate_hd_chart() in Python (ephem + tabelle gate)
- [ ] Social Generator Fase 2: input vocale (Groq Whisper) + pubblicazione Instagram
- [ ] Social Generator Fase 3: Video Clip da YouTube → Reel 9:16
- [ ] Dream Rave Chart, Mammalian Chart, Alpha One
- [ ] Backlink building (directory, guest post, citazioni)
- [ ] SEC-002 (rate limiting) e SEC-006 (CSRF)
- [ ] /workshop-proposta: aggiungere social proof Vicenza

## Done

### 23/08
- [x] **`/yt-long` completo per "Non Avete Capito l'Autorità Interna"** — https://youtu.be/iDnAWYG6O9Y (unlisted, 33 min). Trascrizione chunked, concat con trim silenzi (offset capitoli 9.07s = 10.87 intro - 1.80 trim), cover con foto dal pool, 12 capitoli, descrizione teaser senza spoiler. Thumbnail verificata live.
- [x] **Story Instagram + Community Post** per lo stesso video, entrambi verificati. Il Community Post e' stato pubblicato per errore prima del previsto (tap su coordinate sbagliate) e poi corretto via Edit aggiungendo il link al video.
- [x] **Cover di `7QKY2KYiIB4` (Sacrale Definito e Non Definito) rifatta** con titolo in grassetto su 2 righe, coerente con la serie Generatori. Fix permanente collaterale in `cover_long.py`: la soglia di luminanza per l'eyebrow oro (0.35) catturava anche i colori `--type` saturi, portata a 0.25 cosi' il rosso Costruttore e il verde Guida tengono l'eyebrow bianca come le cover live.
- [x] **Story standalone "Conosci la tua Tipologia?"** — primo asset social non legato a un video, promuove il calcolatore gratuito. Template nuovo (quiz su crema con le 4 tipologie, terminologia HD su richiesta di Marco), sticker Link con testo personalizzato "Calcola la tua carta". Verificata in archivio.

### 21-22/08
- [x] **`/yt-long` completo per "L'Amore e i suoi Demoni" (intervista con Valeria Milan)**: trascrizione, concat intro/outro, 28 capitoli con offset corretto per il trim silenzio (10.87 - 2.29 = 8.58s), cover iterata su richiesta Marco, upload YouTube, Story Instagram (sticker verificato via Archive) e Community Post carosello con link.
- [x] **Cover aBKilWiD4nI ("Sentire il Corpo") rifatta in stile bianco/crema**, stessa foto esistente, solo lo stile della parte destra allineato alle cover recenti.
- [x] **Libretto Jailma (Essenziale + Completo) scritto interamente a mano** (no credito API, su richiesta esplicita Marco) via la pipeline ufficiale `prompts.py`+`rebuild_pdfs.py`, non i vecchi `spike_*.py`. Fix collaterale permanente: accenti mancanti in `calc_chart_ephem.py` (G/Sé, Comunità, ecc.), valeva per ogni cliente storico.
- [x] **Audit blog + guardian paralleli (marketing/geo/performance)** — marketing-guardian ha prodotto un falso positivo ("0 CTA" su /aziende, smentito da verifica sul render live).
- [x] **3 fix performance-guardian applicati**: compressione HTML, lazy-load html2pdf, preconnect font Google. Due bug self-inflitti nel farlo (doppia compressione gzip, rottura UMD html2pdf) diagnosticati e risolti in sessione.

### 16/08
- [x] **Backfill capitoli su tutti e 9 i long-form che ne erano privi**: ezxJF5wzu4M, 7QKY2KYiIB4, Y2OKN432uQE, M87lX2ziz70, phPmP_jMYRM, BWPPz7ZrW1k, aBKilWiD4nI, cRnqomggSq4, WS_snTUwVbY. Per gli ultimi due il sorgente locale non esisteva piu': audio scaricato dal video pubblicato con `_transcribe_batch.py --yt` (yt_dlp). aBKilWiD4nI ricomposto da 3 sorgenti con `--offset`.
- [x] **`/yt-long` completo per "2027: mi dissocio dallo Human Design. I proiettori non sono nati nel 1781"** (QOhAjCATRZI, public, 38:29): montaggio, cover con foto dal pool, descrizione, 20 capitoli, Story Instagram con sticker Link verificata in archivio, carosello Community 4 slide con link in didascalia.
- [x] **Sottotitolo cover iterato con Marco** fino alla versione dettata: "2027: proiettori esistono da sempre, la mia lettura, non quella di Ra". Cover e Story rigenerate con la stessa foto (`--photo` esplicito, mai il default).
- [x] **/session-reset** eseguito a meta' giornata con pressione a CRITICAL (1500 tool call).


<!-- Venerdi 14/08/26: Done list pulita (rule CLAUDE.md). Task fino all'08/08 archiviate nei Daily Notes. -->

### Completate 11-14/08
- [x] **🐶 Mammal Chart: calcolatore con motore corretto + pagina informativa** — `/mammal-chart` e `/blog/articoli/mammal-chart-cane` live (noindex). Matrice a 15 porte dalla fonte originale di Ra Uru Hu, bodygraph SVG dal layout reale, linee con keynote mammifere, citta'+fuso automatici. 3 bug trovati testando con un cane reale e corretti. Fix collaterale: il meta tag robots mancava nel tema, quindi `noindex: true` non aveva mai funzionato su nessuna pagina.
- [x] **🎬 /yt-long "Le Porte del Sacrale nel Quarto della Dualita': 29 e 59"** — https://youtu.be/BWPPz7ZrW1k (unlisted). Quarto verificato trascrivendo il video invece di dedurlo. Cover a due righe su richiesta Marco, Story verticale, playlist Generatori. OAuth rinnovato a meta' pipeline, retry dal montato.
- [x] **📱 IG Story + Community Post carosello pubblicati e verificati** per il video sopra. Sticker Link non centrale (risolto il trascinamento che era fallito prima: partivo da coordinate fuori dallo sticker). Carosello 3 slide pubblicato dall'app YouTube sull'emulatore, primo caso provato in autonomia.
- [x] **🔧 Selezione clip Shorts: causa trovata e fix applicato** — non usava mai l'AI, era punteggio lessicale. Aggiunto `select_ai.py` su Valentina e `curate_hooks_ai.py` su Hannele.
