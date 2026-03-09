# Setup Grav in locale (Windows)

## Cosa installare

1. **Laragon Full** → https://laragon.org/download/ (versione Full con Apache + PHP 8.2)
2. **Grav + Admin ZIP** → https://getgrav.org/ → "Download Grav + Admin"

---

## Passi dopo l'installazione

### 1. Installa Laragon
- Esegui l'installer, tutto default
- Laragon si avvia automaticamente

### 2. Estrai Grav
- Apri il ZIP scaricato da getgrav.org
- Estrai tutto in `C:\laragon\www\valentina\`
  (crea la cartella `valentina` se non esiste)

### 3. Collega la cartella user/ del repo
Apri un terminale (cmd o bash) nella root del repo e lancia:
```
tools/setup_grav_local.sh
```
Questo collega automaticamente `grav-site/user/` all'installazione locale.

### 4. Avvia
- Apri Laragon → Start All
- Visita: **http://valentina.test** (Laragon crea il virtual host automaticamente)
- Prima visita: Grav chiede di creare l'account admin

### 5. Aggiorna launch.json
Dopo aver verificato la versione PHP installata da Laragon:
- Apri `C:\laragon\bin\php\`
- Nota la cartella (es. `php8.2.26`)
- Aggiorna il path in `.claude/launch.json`

---

## Workflow quotidiano

1. Laragon in tray → Start (se non già avviato)
2. Modifica file in `grav-site/user/` → vedi subito in http://valentina.test
3. Quando sei soddisfatto → `git push main` → deploy automatico su Aruba

**Nota:** le modifiche da admin locale (nuovi articoli, immagini) NON vanno su Aruba automaticamente.
Devi fare `git add grav-site/user/pages/... && git commit && git push`.
