# 🤖 Handoff Prompt — valentinarussomentaladvisor.it

Questo documento contiene tutto il contesto necessario per riprendere il lavoro su questo progetto in qualsiasi ambiente LLM.

---

## 📌 Identità del progetto

Sito web di **Valentina Russo**, consulente **BG5 / Human Design**.
- **Prod URL**: https://rare-north.surge.sh
- **Dominio futuro**: valentinarussobg5.com (non ancora configurato su Surge)
- **GitHub repo**: `valentina-russo/valentinarusso-site` (repo pubblico)
- **Cartella locale**: `D:\valentinarussomentaladvisor.it`

> ⚠️ Valentina Russo è una **consulente BG5 / Human Design** — NON una psicologa, NON una terapeuta.
> BG5 è una derivazione business del sistema Human Design applicata a team e organizzazioni.

---

## 🏗️ Stack tecnico

| Componente | Tecnologia |
|-----------|-----------|
| Frontend | HTML5 + CSS3 + **Vanilla JS** (niente framework) |
| Build tool | **Vite 7** — Multi-Page Application (MPA) |
| Hosting | **Surge.sh** → `rare-north.surge.sh` |
| CMS | **Sveltia CMS** (non Decap/Netlify CMS) |
| Deploy | **GitHub Actions** → auto-deploy su push a `main` |
| Blog | Hybrid SPA: JSON index + fetch Markdown |

---

## 📁 Struttura file chiave

```
D:\valentinarussomentaladvisor.it\
│
├── src/
│   ├── blog.js              ← export async function initBlog(categoryFilter?)
│   ├── article-render.js    ← export async function renderArticle()
│   └── main.js              ← script generico per pagine non-blog
│
├── tools/
│   └── generate_blog_index.cjs  ← genera public/blog_index.json dai .md
│
├── content/
│   ├── blog-privati/        ← articoli .md per area privati (persone)
│   └── blog-aziende/        ← articoli .md per area business (BG5/aziende)
│
├── public/
│   ├── admin/
│   │   └── config.yml       ← config CMS PRODUZIONE (copiato in dist/ da Vite)
│   ├── blog_index.json      ← generato a build-time, servito staticamente
│   ├── placeholder.svg      ← fallback immagine blog card
│   └── assets/
│       ├── placeholder.jpg  ← fallback articolo singolo
│       └── blog/            ← immagini caricate via CMS
│
├── admin/
│   ├── index.html           ← entry Vite → dist/admin/index.html (carica Sveltia CMS CDN)
│   └── config.yml           ← config CMS LOCALE (local_backend: true, solo dev)
│
├── .github/workflows/
│   └── deploy.yml           ← build + surge deploy su push a main
│
├── vite.config.js           ← MPA config, elenca tutti gli HTML entry point
├── package.json
└── HANDOFF.md               ← questo file
```

---

## ⚙️ Script npm

```bash
npm run dev        # genera blog_index.json + avvia Vite dev server
npm run build      # genera blog_index.json + build Vite in dist/
npm run preview    # anteprima della build
npm run blog:build # genera solo blog_index.json (senza build Vite)
```

---

## 🔄 Architettura Blog — dettaglio

### Pagine blog e loro ruolo

| Pagina | Funzione |
|--------|---------|
| `blog.html` | Blog area **privati** (persone) — mostra tutti gli articoli privati |
| `aziende-blog.html` | Blog area **business/BG5** — mostra solo articoli aziende |
| `articolo.html` | Render articolo singolo (per entrambe le categorie) |

### Come funziona il listing

```html
<!-- blog.html: tutti gli articoli privati -->
<div id="blog-container">

<!-- aziende-blog.html: solo articoli business -->
<div id="blog-container" data-filter="aziende">
```

`src/blog.js` → `initBlog()`:
1. Legge `dataset.filter` dal container (se presente)
2. Fetch `/blog_index.json`
3. Filtra per categoria se `data-filter` è settato
4. Renderizza le card HTML con LD+JSON per SEO/AEO

### Come funziona l'articolo singolo

```
Link card → /articolo.html?id=SLUG&category=privati|aziende
```

`src/article-render.js` → `renderArticle()`:
1. Legge `?id=` e `?category=` dalla URL
2. Fetch `/content/{blog-privati|blog-aziende}/{id}.md`
3. Parsing frontmatter via regex (gray-matter non disponibile client-side)
4. Fetch `/blog_index.json` per i metadati (titolo, immagine, SEO)
5. Inietta LD+JSON nell'`<head>` per SEO/AEO
6. Renderizza markdown via `marked`

### Come viene generato blog_index.json

`tools/generate_blog_index.cjs`:
- Legge tutti i `.md` da `content/blog-privati/` e `content/blog-aziende/`
- **Filtra solo `status: published`** — i draft vengono esclusi
- Ordina per data decrescente
- Output: `public/blog_index.json`

### Frontmatter degli articoli `.md`

```yaml
---
title: "Titolo visibile"
date: 2026-03-07T00:00:00.000Z
status: draft          # oppure: published
featured_image: /assets/blog/nome-immagine.jpg
description: "Sottotitolo / excerpt breve"
seo_title: "Titolo per Google"           # opzionale
seo_desc: "Meta description (160 car.)"  # opzionale
geo_location: "Milano, Italia"           # opzionale
aeo_answer: "Risposta breve 40-50 parole ottimizzata per AI search"  # opzionale
---

Corpo dell'articolo in **Markdown**.
```

---

## 🖊️ CMS — Sveltia CMS

### Perché Sveltia e non Decap/Netlify CMS
- Decap CMS non supporta GitHub PKCE — usa `api.netlify.com` come proxy OAuth → broken su siti non-Netlify
- **Sveltia CMS** supporta login via **Personal Access Token (PAT)** GitHub nativamente, zero infrastruttura

### Accesso CMS
- **URL**: https://rare-north.surge.sh/admin/
- **Login**: clicca **"Sign In with GitHub Using Token"**
- **Token richiesto**: GitHub PAT con scope `repo`
  - Generare su: GitHub → Settings → Developer Settings → Personal Access Tokens → Tokens (classic)

### ⚠️ Se il CMS dà "Failed to fetch" al salvataggio
La sessione OAuth precedente è scaduta (token temporaneo ~8h). Soluzione:
1. Fare **logout** dal CMS (icona account in alto a destra)
2. Fare **re-login con PAT** ("Sign In with GitHub Using Token")

### File config CMS
- `admin/index.html` → carica `https://unpkg.com/@sveltia/cms/dist/sveltia-cms.js`
- `public/admin/config.yml` → config produzione (backend github, repo, branch: main, media_folder, collections)
- `admin/config.yml` → config locale con `local_backend: true` (solo per `npm run dev`)

### Collections CMS
| Collection | Cartella | Slug format |
|-----------|---------|------------|
| Articoli Privati | `content/blog-privati/` | `{{slug}}` |
| Articoli Aziende | `content/blog-aziende/` | `aziende-{{slug}}` |

- Media upload → `public/assets/blog/` → URL pubblico `/assets/blog/`

---

## 🚀 Deploy

### Automatico (workflow normale)
```bash
git add .
git commit -m "messaggio"
git push origin main
# → GitHub Action esegue: npm run build → surge dist rare-north.surge.sh
```

### Manuale (emergenza)
```bash
npm run build
npx surge dist rare-north.surge.sh
```

### ⚠️ Push rifiutato — CMS ha committato nel frattempo
```bash
git pull --rebase origin main
git push origin main
```
Questo succede frequentemente: il CMS committe direttamente su GitHub quando l'utente salva un articolo.

### GitHub Actions — segreti necessari
- `secrets.SURGE_TOKEN` → già configurato nel repo

---

## 🔧 jcodemunch — Setup obbligatorio ad ogni sessione

### Prima cosa da fare all'inizio di ogni sessione
```
mcp__jcodemunch__index_folder(
  path="D:\\valentinarussomentaladvisor.it",
  incremental=True,
  use_ai_summaries=False,
  extra_ignore_patterns=[
    "node_modules", "dist", ".git", "*.map",
    "tools/activate_netlify.cjs", "tools/add_domain*.cjs",
    "tools/check_netlify*.cjs", "tools/force_netlify*.cjs",
    "tools/netlify_auto.cjs", "tools/verify_netlify*.cjs",
    "tools/debug*.cjs", "tools/test_puppeteer.cjs"
  ]
)
```

### Repo identifier (fisso, non cambia)
```
local/valentinarussomentaladvisor.it
```

### Simboli estratti

| Symbol ID | Firma | Descrizione |
|-----------|-------|-------------|
| `src/blog.js::initBlog#function` | `async function initBlog(categoryFilter = null)` | Carica e renderizza card blog |
| `src/article-render.js::renderArticle#function` | `async function renderArticle()` | Renderizza articolo singolo da Markdown |

### Tool jcodemunch — uso pratico

```
# Simboli di un file
get_file_outline(repo="local/valentinarussomentaladvisor.it", file_path="src/blog.js")

# Codice completo di una funzione
get_symbol(repo="local/valentinarussomentaladvisor.it", symbol_id="src/blog.js::initBlog#function")

# Ricerca testo libero
search_text(repo="local/valentinarussomentaladvisor.it", query="blog_index")

# Ricerca per nome funzione
search_symbols(repo="local/valentinarussomentaladvisor.it", query="initBlog")
```

### ⚠️ NON indicizzato da jcodemunch → usare Read/Grep
- File `.html`
- `tools/*.cjs`
- `vite.config.js`
- `public/admin/config.yml`

---

## 🐛 Problemi noti e soluzioni

| Sintomo | Causa | Soluzione |
|---------|-------|-----------|
| CMS "Failed to fetch" al salvataggio | Sessione OAuth scaduta (8h) | Logout CMS → re-login con PAT |
| Articolo non appare sul blog | `status: draft` nel frontmatter | Cambiare in `status: published` nel CMS |
| Push rifiutato da GitHub | CMS ha committato prima | `git pull --rebase origin main && git push` |
| Immagini rotte nelle card | File non committato in repo | `git add public/assets/blog/ && git commit && git push` |
| blog_index.json vuoto/mancante | Build non eseguita | `npm run build` o `npm run blog:build` |

---

## 📊 Stato del progetto (2026-03-07)

### Funzionalità attive ✅
- Blog listing dinamico separato (privati / aziende/BG5)
- Articolo singolo con rendering Markdown
- SEO completo: LD+JSON, meta, geo, AEO answer
- CMS Sveltia con login PAT
- GitHub Actions auto-deploy su Surge
- jcodemunch indicizzato (2 simboli estratti)

### In sospeso ⏳
- Dominio `valentinarussobg5.com` da puntare su Surge (serve CNAME DNS → surge.sh)
- Contenuti reali da scrivere (gli articoli attuali sono test)

---

## 💡 Regola d'oro

> Il CMS (Sveltia) committe **direttamente su GitHub** quando salva un articolo.
> Prima di ogni `git push` locale → fare sempre **`git pull --rebase origin main`**.
