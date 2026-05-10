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
- [ ] B4: Switch a sk_live_ quando Valentina completa verifica identità + IBAN

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
- [ ] **Spot-check security su `tools/yt-shorts/`**: path injection in `repurpose.py` argparse, command injection in ffmpeg/yt-dlp subprocess args, no-token-in-log su `youtube_publisher.py`. Auditor 08/05 ha flaggato MISSED su 8 file Python (low-risk, tooling interno).
- [ ] **Primo run reale `/yt-shorts`** su `D:/Download/Valentina Russo/Vale BG5 nuovo.mp4` (5-8 min Whisper IT + AI segment pick + scelta titolo + cover Valentina + conferma publish unlisted)
- [ ] **Commit pipeline `tools/yt-shorts/`** (untracked) — orchestrator, OAuth, cover_templates, find_youtube_url. NON committare `client_secret.json` né `youtube_token.pickle` (gitignored).
- [ ] **Commit fix tools/bg5-generator/**: `generate_blueprint.py` (MAX_TOKENS 8000, MODEL claude-opus-4-7) + `build_blueprint_v5.py` (CondPageBreak, no centri-gruppi). Attualmente untracked.
- [ ] **Test E2E Stripe test mode**: pagamento → form → 2 email partite (admin + cliente)
- [ ] **Rigenerare PDF Valentina v5** con build_blueprint_v5 corrente (capitoli scritti da me, zero API)
- [ ] **Spot-check writing-rules su Beatrice cliente reale**: grep "non.*ma\|Non è.*è\|è importante\|è cruciale\|è fondamentale\|, [a-z]+, [a-z]+,? e " sui suoi 7 .md cached + fix violazioni (6 Pag.7 + cap6 Pag.7). Poi rebuild PDF finale.

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
- [x] **Pipeline `/yt-shorts` configurata e validata end-to-end** — 8 file in `tools/yt-shorts/`: cover_templates (8 design, Valentina ha scelto `6-photo-bottom-dark--square`), transcribe (faster-whisper IT), select_segment (rule-based 60-120s), generate_titles (10+desc+hashtag), render_video (ffmpeg 9:16 + drawtext + libass karaoke + watermark), youtube_publisher (OAuth + upload + thumbnail), find_youtube_url (ffprobe + channel match + Whisper Jaccard), repurpose (orchestrator). OAuth Google completato (channel `@valentinarussobg5` UCIW4aZwPaYirGVWAg5uTXBA). Test upload riuscito poi privato. Alias `/yt-shorts.md` creato (08/05)
- [x] **No Manual Fallback Default** aggiunta a CLAUDE.md (user override esplicito) — mai proporre passi manuali all'utente come fallback (08/05)
- [x] **Libretto AVANZATO Beatrice Rachela** (cliente reale €147) — Manifestatore 1/3 Plesso Solare. PDF v3 finale, 4 pagine appendice (no più centri sup/cen/inf). 6 sezioni completate + 1 scritta intera (cap6 Pag.7) post-correzione max_tokens. Costo finale: €2.66 (sprecato sul 1° run con API), poi rebuild zero costo (06/05)
- [x] **Libretto AVANZATO Marco** — Generatore 3/5 Vicenza 19/01/1983 01:45. Croce delle Leggi 4. 49 sezioni scritte da Claude Code interno, zero API. PDF 11.3 MB (06/05)
- [x] **Libretto AVANZATO Fabio Stivanello** — Generatore Manifestante 3/5 Arzignano 23/09/1984 14:10. Croce dell'Esperienza 3. 49 sezioni scritte da Claude Code interno, zero API. PDF 11.3 MB (06/05)
- [x] **Fix critici generator** — MAX_TOKENS 4500→8000 (no più tagli mid-frase), MODEL Opus 4-6→4-7 (no più overload), CondPageBreak(90mm) intra-capitolo (no più pagine sparse), rimosse pagine "Centri sup/cen/inf" da appendice (non esistono in HD canon). File untracked in attesa di commit (06/05)
- [x] **SEO `/genera-carta`** copertura per query "come calcolare il proprio human design": H2 dedicato + lista 4-step + 5 FAQ strutturate (frontmatter `faq:`) + FAQPage JSON-LD nel template + aeo_answer. Triple coverage (HTML, structured data, AI answer engines). Commit c8ff332, deploy live verificato (04/05)
- [x] **/genera-carta-beta bodygraph composito** completo — 6 bug risolti: (1) loop MGP obsoleto rimosso, (2) COL_A/B nuovi colori per evitare collisione con verde Ajna, (3) wrap cream per visibilità su page bg viola, (4) inactive gates COL_EMPTY scurito da #c8c2bc a #9c948c per visibilità su cream, (5) inactive centers COL_CENTER_EMPTY #b8b1a8, (6) **fix critico**: pattern split AB aveva patternContentUnits di default 'userSpaceOnUse' → rect width=0.5 interpretato come 0.5 PIXEL (invisibile); aggiunto patternContentUnits='objectBoundingBox'. Verificato con render empty skeleton: tutti i 64 gate + 9 centri presenti (02/05)
- [x] **/sec-review libretto-dati** completo — 8 fix applicati (SEC-LIB-002..008): Stripe redirect allowlist, server-side session verification, no GET pre-fill, regex session_id, .htaccess hardening, escapeshellarg, null-byte regex, $ok1&&$ok2 (02/05)
- [x] **/legal-review libretto-istruzioni** completo — 11 fix applicati (4 CRITICAL + 3 HIGH + 4 MEDIUM): T&C vendita conformi D.Lgs. 206/2005, privacy aggiornata Stripe+Anthropic, email conferma art. 51 c.7, info precontrattuali landing, AI disclosure, regime forfettario (02/05)
- [x] GA4 event tracking PDF download su /genera-carta — `pdf_carta_scaricato` (02/05)
- [x] Landing `/libretto-istruzioni`: pricing migrata da navy a parchment — elimina alla radice 6 tentativi falliti di divider navy↔parchment. Rimossi bp-fade-to-navy + ornamento ceralacca + wave. Card Avanzato resta dark drama come accent intenzionale (01/05)
- [x] Landing `/libretto-istruzioni` mobile polish completo: hero gap, fan grid, testo centrato, parallax fix (30/04)
- [x] Navbar rimossa dalla landing libretto con Twig block override (30/04)
- [x] Rosa rimosso completamente: btn Avanzato gold, footer link gold, section-dark cream (30/04)
- [x] Content: "Voce di Gola" → "la tua voce" (2 occorrenze indice + pricing) (30/04)
- [x] Email contatto `info@valentinarussobg5.com` su pagina successo + errore + email conferma cliente (29/04)
- [x] Stripe CLI installata (winget) + login OAuth account VRBG5 — pronta per switch live (29/04)
- [x] Stripe Checkout integrato end-to-end test mode: account VRBG5, GitHub Secret + workflow .env, payment_method_types[]=card, .htaccess block .env access (28/04)
- [x] Anti-AI-slop pass su landing libretto-istruzioni: ~20 em-dash, 8 triplette, 4 pattern "non X — Y" rimossi — commit 63cd878 (28/04)
- [x] Bio Valentina: foto cerchio reale 340×340 (overflow:hidden + height esplicita) + FAQ "sessione live" riscritta (28/04)
- [x] Pass anti-AI-slop su 5 articoli HD/BG5 (body + frontmatter) — commit cf77c68 (24/04)
- [x] Audit PDF `valentina-blueprint-v4.pdf` — Croce Amore 2 · Q2 · 15/10 & 25/46 · cover date issue (24/04)
- [x] Doppia CTA via item.html.twig (privati + aziende) — commits 2af33c3 + 879e273 (24/04)
- [x] Audit PDF `valentina-completo-v2.pdf` — mismatch profilo/croce identificato (22/04)
- [x] C1: GitHub Actions workflow `generate-blueprint.yml` creato (21/04)
- [x] D1: review.php completato — PDF iframe + editor sezioni + approva (21/04)
- [x] generator.py: auto-calc chart da birth data (timezonefinder) quando non presente nel job (21/04)
- [x] stripe-webhook.php: aggiunto repository_dispatch → GitHub Actions dopo job creato (21/04)
- [x] bodygraph_svg.py: fix path hardcoded Windows → relativo (cross-platform) (21/04)
- [x] requirements.txt creato per GitHub Actions (21/04)
- [x] Wikidata QID: dati preparati per creazione manuale (vedi Daily Notes/042126.md) (21/04)
- [x] valentina_voice.md creato da 8 video YouTube (~147k chars trascritti) (20/04)
- [x] PDF refactor: 18 → 13 sezioni, voice Valentina integrata nel system prompt (20/04)
- [x] Section subtitles dinamici aggiunti al PDF (Tipologia: Proiettore · Tipo non-energetico, ecc.) (20/04)
- [x] PDF generato con data corretta: 1988-06-21, Proiettore Mentale 2/4 Singola (20/04)
- [x] Data nascita Valentina corretta: 21/06/1988 (era 21/09/1988) (20/04)
- [x] sec-review: SRI html2pdf, XSS innerHTML→textContent, Nominatim→Photon (18/04)
- [x] design spot-check standalone HD calculator: WCAG AA fix, bodygraph, h2 (18/04)
- [x] BG5 PDF test generato — Valentina Russo, Projector 2/4 (18/04)
- [x] ZIP package standalone HD calculator (18/04)
