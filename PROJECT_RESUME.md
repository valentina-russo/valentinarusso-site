# PROJECT RESUME: valentinarussomentaladvisor.it
**Data ultimo aggiornamento:** 07/03/2026
**Status:** Infrastruttura CMS Pronta / UI Ottimizzata

## 📋 Panoramica
Sito web professionale per Valentina Russo (Mental Advisor & Consulente BG5).
- **Area Privati:** Focus su Human Design, decondizionamento e relazioni.
- **Area Corporate:** Focus su Team Engineering (Penta), Leadership e Fatturato.

## 🛠️ Stack Tecnologico
- **Frontend:** HTML5 / CSS3 / JS Vanilla (No framework).
- **Build System:** Vite 7 (Multi-page app).
- **Hosting Pubblico:** Surge ([rare-north.surge.sh](https://rare-north.surge.sh)).
- **CMS & Auth:** Netlify + Decap CMS ([comforting-sawine-6d1e1c.netlify.app/admin/](https://comforting-sawine-6d1e1c.netlify.app/admin/)).
- **Database (Content):** GitHub ([valentina-russo/valentinarusso-site](https://github.com/valentina-russo/valentinarusso-site)).
- **Automation:** Puppeteer (installato), jcodemunch (MCP Server installato in `C:\Users\marco\AppData\Local\Programs\Python\Python312\Scripts\jcodemunch-mcp.exe`).

## ✅ Milestone Completate
- **Design System:** Stile "Mercury" (gradienti fluidi), no sottolineature sui link, titoli antracite/blu.
- **Mobile Optimization:**
    - Foto Valentina circolare (240px) e centrata sopra il testo.
    - Pulsanti XL full-width.
    - Footer bianco e centrato solo su mobile.
    - Inversione ordine Hero (Immagine -> Titolo -> CTA).
- **Area Business:** 
    - Divisione in due blu (Imprenditore vs Azienda).
    - Iconografia dedicata (👤/🏢) e "blob" di luce decorativi.
    - Timeline del percorso (Step 1-2-3) e card "Prima Lettura" premium.
- **CMS & Blog:**
    - Integrazione Decap CMS con supporto **Foto in Evidenza**.
    - Campi metadati avanzati: **SEO** (Title/Desc), **GEO** (Località), **AEO** (Featured Snippet per AI).
    - Supporto per articoli in Markdown caricati su GitHub.
- **Contatti:** Email `info@valentinarussobg5.com` e Whatsapp `+39 379 103 7653` aggiornati ovunque.

## 🚀 Come riprendere il lavoro
1. **Verifica UI:** Controllare sempre `rare-north.surge.sh`.
2. **Gestione Contenuti:** Accedere a `/admin` tramite il link Netlify per caricare nuovi articoli.
3. **Sincronizzazione:** Se si cambia l'header, usare `node tools/sync_headers.cjs`.
4. **Deploy:** `npm run build && npx surge dist rare-north.surge.sh`.

## 📌 Task Pendenti / Futuri
- Implementazione rendering dinamico del blog (script per generare HTML da Markdown).
- Collegamento dominio definitivo `valentinarussomentaladvisor.it` all'istanza Netlify.
- Test SEO/AEO con Puppeteer.
