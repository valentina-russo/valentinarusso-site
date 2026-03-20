# PROJECT RESUME: valentinarussobg5.com
**Data ultimo aggiornamento:** 12/03/2026
**Status:** V5.7 Platinum - Produzione Ottimizzata & Forensic Fix Completato

## 📋 Panoramica
Sito web professionale per Valentina Russo. Infrastruttura ibrida: Grav CMS per il blog e le pagine dinamiche, Vite/HTML per le landing page statiche.

## 🛠️ Stack Tecnologico & Deploy
- **Infrastruttura:** Ibrido Vite (MPA) + Grav CMS (PHP).
- **Hosting:** Aruba FTP.
- **Workflow Deploy:** GitHub Actions (Deploy automatico di `grav-site/user/`).
    - **CRITICO:** La cartella `user/pages/` è GITIGNORED per proteggere i contenuti dell'admin. Le modifiche ai form nelle pagine vanno forzate via script server-side (`force_email_fix.php`).

## ✅ Milestone Recentemente Completate
1.  **Griglia 8pt & Usability:** Implementato sistema di spacing rigoroso su tutte le card e sezioni basato su variabili CSS (`--grid-md`, ecc.).
2.  **Fix Regressioni Visuali:** Risolti problemi di elementi sovrapposti, icone disallineate e padding inconsistenti nelle card workshop.
3.  **Forensic Email Fix (V5.7):** 
    *   Tutte le email della landing (statiche) e del CMS (dinamiche) sono state condensate su `info@valentinarussobg5.com`.
    *   Sostituito il `mailer.php` nella root di Aruba (era hardcodato con la vecchia email).
    *   Forzata sincronizzazione email su tutti i file `.md` tramite script PHP bypassando le restrizioni di deploy.

## 🚀 Come riprendere il lavoro
- **Prompt di Resume:** Usa il file `RESUME_PROMPT.md` per una nuova sessione.
- **Modifiche CSS:** La fonte di verità è `src/style.css`, che viene compilata e deployata in `user/themes/valentina/css/style.css`.
- **Cache:** Dopo ogni deploy o modifica strutturale, pulire sempre la cache da Grav Admin.

## 📌 Task Pendenti / Prossimi Step
- Pulizia definitiva dei file ZIP e script temporanei nella root (`search_email.php`, ecc.).
- Verifica ricezione testuale delle email per ambo i form (Privati/Aziende).
