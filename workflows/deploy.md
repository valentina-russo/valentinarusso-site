---
description: Build and Deploy the Website
---

# 🚀 Workflow: Build & Deploy 

Questo workflow definisce i passaggi standardizzati per compilare il sito e pubblicarlo online tramite Surge. 

## Fasi dell'Operazione

1. **Verifica Finale e Test Locale**
   - Assicurati che lo sviluppo sia completato.
   - Avvia il server locale di Vite per controllare:
     ```bash
     npm run dev
     ```
   - *Nota:* Controlla la corretta formattazione, tipografia e eventuali errori console (ispeziona il DOM).

2. **Clean Build**
   - Elimina manualmente la cartella `dist/` per evitare "Ghost Tracking" di vecchi file o versioni cachate.
   - Esegui la build del progetto:
     ```bash
     npm run build
     ```
   - *Nota:* Vite compilerà e raggrupperà tutti i file dentro una nuova directory `dist/`.

3. **Deployment su Surge**
   - Posizionati all'interno della cartella di distribuzione:
     ```bash
     cd dist
     ```
   - Lancia la pubblicazione automatizzata su Surge verso il dominio ufficiale:
     ```bash
     surge --domain valentinarussomentaladvisor.it
     ```
   - *Nota:* Controlla l'output per accertarti che il caricamento sia andato a buon fine, e attendi che il sito sia online.

4. **Verifica Post-Deploy**
   - Visita `https://valentinarussomentaladvisor.it` da un browser (preferibilmente in navigazione in incognito per evitare problemi di cache).
   - Verifica sia le pagine principali che le sezioni *Corporate* (es. `/aziende.html`).

---
*Blueprint WAT v6.0 - Tutti i comandi sono deterministici.*
