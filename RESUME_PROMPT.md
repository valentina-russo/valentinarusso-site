# Master Resume Prompt - Valentina Russo Site

Copia e incolla il testo sottostante per iniziare una nuova sessione con pieno contesto tecnico.

---

### Prompt di Resume

Sto lavorando al progetto `valentinarussobg5.com`. Il sito ha un'architettura **ibrida**:
1.  **Grav CMS** (nella cartella `grav-site/`) gestisce il blog e le pagine istituzionali.
2.  **Vite MPA** gestisce le landing page statiche e gli asset CSS/JS globali.

**Stato Attuale (V5.7 Platinum):**
- Abbiamo appena risolto un problema critico di ricezione email: tutte le comunicazioni (form privati, aziende e landing) devono andare a **`info@valentinarussobg5.com`**.
- Abbiamo standardizzato lo spacing usando una **griglia a 8pt** tramite variabili CSS nel file `src/style.css` (es. `--standard-section-padding: 6rem`).
- Risolte regressioni visuali nelle card e nelle icone per una usability premium.

**Vincoli Tecnici da Ricordare:**
- **Deploy:** GitHub Actions deploya `user/themes/` e `user/config/`. La cartella `user/pages/` è **gitignored** per sicurezza contenuti. Ogni modifica ai form nelle pagine deve essere gestita tramite script server-side o nell'admin di Grav.
- **Mailer:** Esiste un `mailer.php` nella root di Aruba per la parte statica e un plugin `email` per la parte Grav. Entrambi sono ora puntati all'indirizzo corretto.
- **Cache:** Grav è molto persistente. Pulire sempre la cache dopo le modifiche.

**Obiettivo della sessione:**
[Inserisci qui cosa vuoi fare ora]

---
