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
- [ ] **Stripe live**: Valentina autorizza esplicitamente switch a sk_live_ (chiave da Dashboard o via CLI con permessi espliciti) → gh secret set → push → live
- [ ] **PRE go-live blocking**: `/sec-review grav-site/root/libretto-dati/checkout.php` (Security Default — gate da completare prima del live)
- [ ] **PRE go-live blocking**: `/legal-review libretto-istruzioni` (Legal Default — IVA, T&C, privacy policy + Stripe)
- [ ] Rigenerare PDF Valentina con pipeline v5 — cover mostra "22 april 2026" invece di "21 giugno 1988". Usare `generate_blueprint.py --chart D:/Download/hd-valentina-corrected.json`
- [ ] Fix variable_arrows encoding in calc_chart_ephem.py (caratteri corrotti UTF-8)
- [ ] Verifica esterna dati Marco su myhumandesign.com (19/01/1983, 01:45, Vicenza)

## This Week
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
