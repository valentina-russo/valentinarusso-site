# Task Board

## BG5 Blueprint — Piano approvato (12/04)
Piano completo: `.claude/plans/keen-finding-cocke.md`

### FASE A — Generator production-ready ✅ COMPLETA
- [x] A1: Estrarre 19 prompt in `prompts.py` con template
- [x] A2: calc_chart_ephem.py — calcolo carta HD via ephem
- [x] A3: Collegare `rebuild_pdfs.build_pdf()` a `generator.py`
- [x] A4: Aggiornare generator da 7 a 19 sezioni

### FASE B — Landing page + Stripe (BLOCCO: account Stripe Valentina)
- [x] B1: Template Twig landing page `/bg5-blueprint` + deploy fix
- [ ] B2: Stripe Checkout integration
- [ ] B3: Configurare webhook Stripe su Aruba

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
- [ ] Commit pdf_theme.py + rebuild_pdfs.py Essenziale redesign (fatto in locale, non pushato)
- [ ] Valutare feature "inserimento gates manuali" nel Tool HD Relazionale
- [ ] Croci traduzione — validare con Valentina

## This Week
- [ ] Croci BG5 — validare traduzione con Valentina
- [ ] Wikidata QID — creare voce per Valentina Russo (GEO-C02)

## Backlog
- [ ] A2: Implementare calculate_hd_chart() in Python (ephem + tabelle gate)
- [ ] Social Generator Fase 2: input vocale (Groq Whisper) + pubblicazione Instagram
- [ ] Social Generator Fase 3: Video Clip da YouTube → Reel 9:16
- [ ] Dream Rave Chart, Mammalian Chart, Alpha One
- [ ] Backlink building (directory, guest post, citazioni)
- [ ] SEC-002 (rate limiting) e SEC-006 (CSRF)
- [ ] /workshop-proposta: aggiungere social proof Vicenza

## Done
- [x] Fase A completa: generator 19 sezioni, prompts.py, calc_chart_ephem.py (14/04)
- [x] Landing page bg5-blueprint: template + deploy fix (14/04)
- [x] Essenziale PDF redesign: teal palette, cover semplificata, part dividers chiari (14/04)
- [x] Search Console review: diagnosi dominio nuovo, 4 settimane dati (14/04)
- [x] BG5 PDF: fix pagine bianche, orfani, duplicati, titoli dinamici (12/04)
- [x] Piano completo Blueprint approvato (5 fasi A-E) (12/04)
- [x] sec-review: SEC-001,003,010,011,012,013 (09/04)
- [x] GEO/SEO/AEO full audit + fix (03/04)
