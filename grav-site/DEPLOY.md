# Istruzioni Deploy Grav CMS su Aruba

## Requisiti
- Hosting Aruba con PHP 7.4+ (già configurato)
- Accesso FTP o cPanel File Manager
- Client FTP (es. FileZilla) o accesso cPanel

---

## Passo 1 — Scarica Grav Admin Skeleton

1. Vai su **https://getgrav.org/downloads/skeletons**
2. Scarica **"Grav Core + Admin Plugin"** (ZIP)
   Esempio: `grav-admin-v1.7.x.zip`

---

## Passo 2 — Prepara i file in locale

1. Estrai lo ZIP di Grav in una cartella temporanea, es. `C:\temp\grav-admin\`
2. Copia la nostra cartella `grav-site/user/` **sopra** la cartella `user/` estratta dallo ZIP
   → Sostituisce completamente la cartella `user/` del Grav di default

3. **Copia le immagini** (vedi sezione Immagini sotto)

---

## Passo 3 — Configura la password SMTP Aruba

Apri il file `user/config/plugins/email.yaml` e inserisci la password della casella email Aruba:

```yaml
mailer:
    smtp:
        server: smtps.aruba.it
        port: 465
        encryption: ssl
        user: info@valentinarussobg5.it
        password: 'LA-TUA-PASSWORD-EMAIL-ARUBA'
```

---

## Passo 4 — Upload su Aruba

### Opzione A: cPanel File Manager
1. Accedi al cPanel Aruba → File Manager
2. Naviga nella cartella radice del sito (`public_html/` o simile)
3. Carica e decomprimi tutti i file di Grav
4. Assicurati che la struttura sia:
   ```
   public_html/
   ├── index.php      ← file di Grav
   ├── user/          ← la nostra cartella
   ├── system/
   ├── vendor/
   └── ...
   ```

### Opzione B: FTP (FileZilla)
1. Configura FileZilla con le credenziali FTP Aruba
2. Carica l'intera cartella Grav nella root del sito

---

## Passo 5 — Prima visita e setup

1. Visita **https://valentinarussobg5.com**
   Grav si auto-configura alla prima visita (crea cache, configura sessioni)

2. Visita **https://valentinarussobg5.com/admin**
   Crea l'account amministratore:
   - Username: (a scelta, es. `valentina`)
   - Password: (sicura, almeno 8 caratteri)
   - Email: `info@valentinarussobg5.it`

3. Nel pannello admin:
   - Vai su **Themes** → attiva il tema **Valentina**
   - Vai su **Plugins** → verifica che **Form** e **Email** siano attivi

---

## Passo 6 — Test completo

- [ ] Homepage carica con design corretto
- [ ] Menu funziona (incluso hamburger mobile)
- [ ] Blog privati lista articoli
- [ ] Blog aziende lista articoli (tema navy)
- [ ] Articolo singolo apre correttamente
- [ ] Form contatti invia email a `info@valentinarussobg5.com`
- [ ] Form aziende invia email
- [ ] Genera Carta mostra link Jovian Archive
- [ ] Admin `/admin` accessibile e funzionante
- [ ] Creazione nuovo articolo da admin
- [ ] Scheduling: articolo con data futura non appare nel blog

---

## Immagini

Le immagini del sito attuale sono in `public/assets/`. In Grav vanno in `user/images/`.

**Mapping:**
```
public/assets/blog-1.png          → user/images/blog/blog-1.png
public/assets/blog-2.png          → user/images/blog/blog-2.png
public/assets/blog-3.png          → user/images/blog/blog-3.png
public/assets/blog-5.png          → user/images/blog/blog-5.png
public/assets/logo.png            → user/themes/valentina/images/logo.png
public/assets/valentina-privati.jpg  → user/images/valentina-privati.jpg
public/assets/blog/               → user/images/blog/ (tutti i file)
```

**Script batch per copiarle** (esegui in PowerShell dal repo):
```powershell
$src = "public\assets"
$dst = "grav-site\user\images"
New-Item -ItemType Directory -Force -Path "$dst\blog"
Copy-Item "$src\blog-*.png" "$dst\blog\"
Copy-Item "$src\blog-*.jpg" "$dst\blog\"
Copy-Item "$src\blog\*" "$dst\blog\" -Recurse
Copy-Item "$src\valentina-privati.*" "$dst\"
```

Poi copia `logo.png` separatamente nel tema:
```powershell
Copy-Item "public\assets\logo.png" "grav-site\user\themes\valentina\images\"
```

---

## Aggiornare il dominio nei link interni

Dopo il deploy, alcune immagini potrebbero usare path assoluti con il vecchio dominio. Verifica in `/admin`:
- Vai su **Pages** → controlla gli articoli
- I path immagine dovrebbero essere relativi (`/user/images/...`) — già configurati correttamente

---

## Struttura finale cartella `user/`

```
user/
├── accounts/          ← creato automaticamente da Grav al primo login admin
├── config/
│   ├── site.yaml
│   ├── system.yaml
│   └── plugins/
│       ├── admin.yaml
│       ├── email.yaml      ← ricorda di inserire la password SMTP
│       └── form.yaml
├── images/
│   ├── blog/              ← immagini articoli
│   └── valentina-privati.jpg
├── pages/
│   ├── 01.home/
│   ├── 02.chi-sono/
│   ├── 03.servizi/
│   ├── 04.blog/
│   │   ├── blog_list.md
│   │   ├── esplosioni-emotive/
│   │   ├── relazioni-bisogni-emotivi/
│   │   ├── la-mente-innamorata-1/
│   │   ├── la-mente-innamorata-2/
│   │   ├── la-mente-innamorata-3/
│   │   ├── potere-creativo-malinconia/
│   │   ├── articolo-privati-2/
│   │   └── introduzione-human-design/
│   ├── 05.aziende/
│   │   ├── aziende_home.md
│   │   ├── 01.servizi/
│   │   ├── 02.blog/
│   │   │   ├── blog_list.md
│   │   │   ├── potere-del-penta/
│   │   │   ├── secondo-articolo-business/
│   │   │   └── aziende-test-3/
│   │   └── 03.contatti/
│   ├── 06.contatti/
│   ├── 07.privacy/
│   ├── 08.terms/
│   ├── 09.genera-carta/
│   └── 10.archivio/
└── themes/
    └── valentina/
        ├── blueprints.yaml
        ├── valentina.yaml
        ├── css/style.css
        ├── images/logo.png   ← da copiare
        ├── js/main.js
        └── templates/
            ├── partials/
            │   ├── base.html.twig
            │   ├── header.html.twig
            │   └── footer.html.twig
            ├── default.html.twig
            ├── home.html.twig
            ├── servizi.html.twig
            ├── blog_list.html.twig
            ├── item.html.twig
            ├── contact_form.html.twig
            ├── aziende_home.html.twig
            └── aziende_servizi.html.twig
```

---

## Creare un nuovo articolo da admin

1. Vai su `/admin` → **Pages**
2. Clicca **+ Add** → scegli la pagina parent (`04.blog` per privati, `05.aziende/02.blog` per aziende)
3. Scegli template **item**
4. Compila i campi:
   - **Title**: titolo articolo
   - **Date**: data pubblicazione
   - **Published**: spunta per pubblicare (o lascia deselezionato per salvare come bozza)
   - Aggiungi i campi extra nel frontmatter (tags, featured_image, description, ecc.)
5. Salva → l'articolo appare immediatamente nel blog

### Scheduling articoli
Per programmare la pubblicazione futura:
- In admin, imposta **Published: false**
- Nel frontmatter aggiungi `date: 2026-04-15 10:00:00`
- Quando vuoi renderlo visibile, cambia **Published: true**

Oppure usa il plugin **Scheduler** di Grav (installabile da admin → Plugins) per la pubblicazione automatica basata sulla data.

---

## Contatti e supporto
Per problemi tecnici con Grav: https://discourse.getgrav.org/
Documentazione: https://learn.getgrav.org/
