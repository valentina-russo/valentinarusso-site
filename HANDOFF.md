# 🤖 Handoff Prompt — valentinarussomentaladvisor.it

Questo documento contiene tutto il contesto necessario per riprendere il lavoro su questo progetto in qualsiasi ambiente LLM.

---

## 📌 Identità del progetto

Sito web di **Valentina Russo**, consulente **BG5 / Human Design**.
- **Prod URL**: https://rare-north.surge.sh
- **Dominio futuro**: valentinarussobg5.com (non ancora configurato)
- **GitHub repo**: `valentina-russo/valentinarusso-site` (repo pubblico)
- **Cartella locale**: `D:\valentinarussomentaladvisor.it`

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
│   ├── blog-privati/        ← articoli .md per area privati
│   └── blog-aziende/        ← articoli .md per area business
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
│   ├── index.html           ← entry Vite → dist/admin/index.html
│   └── config.yml           ← config CMS LOCALE (local_backend: true, solo dev)
│
├── .github/workflows/
│   └── deploy.yml           ← build + surge deploy su push a main
│
├── vite.config.js           ← MPA config, elenca tutti gli HTML entry point
└── package.json
```

---

## ⚙️ Script npm

```bash
npm run dev        # genera blog_index.json + avvia Vite dev server
npm run build      # genera blog_index.json + build Vite in dist/
npm run preview    # anteprima della build
npm run blog:build # genera solo blog_index.json (senza build)
```

---

## 🔄 Architettura Blog — dettaglio

### Come funziona il blog listing

```
blog.html
  <div id="blog-container">               ← TUTTI gli articoli (privati + aziende)

aziende-blog.html
  <div id="blog-container" data-filter="aziende">  ← solo articoli aziende
```

`src/blog.js` → `initBlog()`:
1. Legge `dataset.filter` dal container (se presente)
2. Fetch `/blog_index.json`
3. Filtra per categoria se `data-filter` è settato
4. Renderizza le card HTML

### Come funziona l'articolo singolo

```
Link card → /articolo.html?id=SLUG&category=privati|aziende
```

`src/article-render.js` → `renderArticle()`:
1. Legge `?id=` e `?category=` dalla URL
2. Fetch `/content/{blog-privati|blog-aziende}/{id}.md`
3. Parsing frontmatter via regex (non gray-matter, non disponibile client-side)
4. Fetch `/blog_index.json` per i metadati (titolo, immagine, SEO)
5. Inietta LD+JSON nell'`<head>` per SEO/AEO
6. Renderizza markdown via `marked`

### blog_index.json — come viene generato

`tools/generate_blog_index.cjs`:
- Legge tutti i `.md` da `content/blog-privati/` e `content/blog-aziende/`
- **Filtra solo `status: published`** (i draft vengono esclusi)
- Ordina per data decrescente
- Output: `public/blog_index.json`

### Frontmatter articoli

```yaml
---
title: "Titolo visibile"
date: 2026-03-07T00:00:00.000Z
status: draft          # oppure: published
featured_image: /assets/blog/nome-immagine.jpg
description: "Sottotitolo / excerpt"
seo_title: "Titolo per Google"        # opzionale
seo_desc: "Meta description"          # opzionale
geo_location: "Milano, Italia"        # opzionale
aeo_answer: "Risposta breve 40-50 parole per AI"  # opzionale
---

Corpo dell'articolo in **Markdown**.
```

---

## 🖊️ CMS — Sveltia CMS

### Perché Sveltia e non Decap/Netlify CMS
- Decap CMS (ex Netlify CMS) non supporta GitHub PKCE — usa `api.netlify.com` come proxy OAuth → broken su siti non-Netlify
- **Sveltia CMS** supporta login via **Personal Access Token (PAT)** GitHub nativamente

### Accesso CMS
- **URL**: https://rare-north.surge.sh/admin/
- **Login**: clicca **"Sign In with GitHub Using Token"**
- **Token richiesto**: GitHub PAT con scope `repo`
  - Generare su: GitHub → Settings → Developer Settings → Personal Access Tokens → Tokens (classic)

### File config CMS
- `admin/index.html` → carica Sveltia CMS da CDN: `https://unpkg.com/@sveltia/cms/dist/sveltia-cms.js`
- `public/admin/config.yml` → config di produzione (backend: github, repo, branch: main, media_folder, collections)
- `admin/config.yml` → config locale con `local_backend: true` (solo per `npm run dev`)

### Collections CMS
- **Articoli Privati** → salva in `content/blog-privati/`, slug: `{{slug}}`
- **Articoli Aziende** → salva in `content/blog-aziende/`, slug: `aziende-{{slug}}`
- Media → `public/assets/blog/` → URL pubblico `/assets/blog/`

---

## 🚀 Deploy

### Automatico (normale workflow)
```bash
git add .
git commit -m "messaggio"
git push origin main
# → GitHub Action: build → surge deploy → https://rare-north.surge.sh
```

### Manuale (emergenza)
```bash
npm run build
npx surge dist rare-north.surge.sh
```

### ⚠️ Se push viene rifiettato (CMS ha committato nel frattempo)
```bash
git pull --rebase origin main
git push origin main
```

Questo succede spesso perché il CMS committe direttamente su GitHub quando l'utente salva un articolo.

---

## 🔧 jcodemunch — Setup obbligatorio ad ogni sessione

jcodemunch è il tool per l'esplorazione del codice. Va indicizzato **all'inizio di ogni sessione**.

### Comando di indicizzazione
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

### Repo identifier (fisso)
```
local/valentinarussomentaladvisor.it
```

### Simboli estratti (le funzioni principali del progetto)

| Symbol ID | Firma |
|-----------|-------|
| `src/blog.js::initBlog#function` | `async function initBlog(categoryFilter = null)` |
| `src/article-render.js::renderArticle#function` | `async function renderArticle()` |

### Tool jcodemunch — uso pratico

```
# Vedere i simboli di un file
get_file_outline(repo="local/valentinarussomentaladvisor.it", file_path="src/blog.js")

# Leggere il codice completo di una funzione
get_symbol(repo="local/valentinarussomentaladvisor.it", symbol_id="src/blog.js::initBlog#function")

# Cercare testo libero in tutti i file indicizzati
search_text(repo="local/valentinarussomentaladvisor.it", query="blog_index")

# Cercare per nome funzione
search_symbols(repo="local/valentinarussomentaladvisor.it", query="initBlog")
```

### ⚠️ Cosa NON è indicizzato da jcodemunch
- File HTML → usare `Grep` o `Read`
- `tools/*.cjs` → usare `Read` diretto
- `vite.config.js` → usare `Read` diretto
- `public/admin/config.yml` → usare `Read` diretto

---

## 🐛 Bug noti e soluzioni

| Sintomo | Causa | Soluzione |
|---------|-------|-----------|
| CMS "Failed to fetch" al salvataggio | Sessione OAuth scaduta (token 8h) | Logout CMS → re-login con PAT GitHub |
| Articolo non appare sul blog | `status: draft` nel frontmatter | Cambiare in `status: published` e salvare nel CMS |
| Push rifiutato da GitHub | CMS ha committato prima | `git pull --rebase origin main && git push origin main` |
| Immagini rotte nelle card | File non committato in `public/assets/blog/` | `git add public/assets/blog/ && git commit && git push` |
| Blog_index.json vuoto/mancante | Non è stato eseguito `npm run build` | `npm run build` o `npm run blog:build` |
| Articoli aziende appaiono su blog privati | Vecchio stato CMS/cache browser | Hard refresh (Ctrl+Shift+R) sulla pagina |

---

## 🌐 GitHub Actions — segreti necessari

Il workflow `.github/workflows/deploy.yml` usa:
- `secrets.SURGE_TOKEN` → token Surge per il deploy (già configurato nel repo)

---

## 📊 Stato attuale del progetto (2026-03-07)

### Articoli presenti
- **Privati** (`content/blog-privati/`): `articolo-test.md`
- **Aziende** (`content/blog-aziende/`): `articolo-aziende-test.md`, `aziende-secondo-articolo-business-2.md`, `aziende-test-3.md`

### Funzionalità attive
- ✅ Blog listing dinamico (privati + aziende separati)
- ✅ Articolo singolo con rendering Markdown
- ✅ SEO: LD+JSON, meta description, geo, AEO answer
- ✅ CMS Sveltia funzionante con login PAT
- ✅ GitHub Actions auto-deploy
- ✅ jcodemunch indicizzato (2 simboli estratti)

### In sospeso
- ⏳ Dominio `valentinarussobg5.com` da configurare su Surge
- ⏳ Articoli reali da scrivere (gli attuali sono test)

---

## 💡 Regola d'oro per questo progetto

> Quando il CMS (Sveltia) salva un articolo, committe **direttamente su GitHub**.
> Prima di ogni `git push` locale, fare sempre **`git pull --rebase origin main`**.
