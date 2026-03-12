# 🏗️ ARCHITETTURA - valentinarussobg5.com

**Data**: 2026-03-12
**Versione**: V5.6 Platinum
**Status**: IN PRODUZIONE

---

## 📋 INDICE
1. [Stack Tecnologico](#stack-tecnologico)
2. [Struttura del Progetto](#struttura-del-progetto)
3. [Deploy & Workflow](#deploy--workflow)
4. [Flussi Dati Principali](#flussi-dati-principali)
5. [File Critici](#file-critici)
6. [Pagine Principali](#pagine-principali)
7. [CMS Admin](#cms-admin)
8. [Schema.org & SEO](#schemaorg--seo)

---

## 🔧 STACK TECNOLOGICO

| Layer | Tecnologia | Ruolo |
|-------|-----------|-------|
| **Frontend** | HTML5 / CSS3 / JS Vanilla | 20+ pagine statiche + interattività |
| **Build Tool** | Vite 7 MPA | Bundle assets + static copy |
| **CMS Live** | Grav CMS | Rendering dinamico (produzione) |
| **Template** | Twig | Templating backend |
| **Backend API** | PHP | blog_api.php, admin.php, mailer.php, ai_bold.php |
| **Database** | File-based (.md) | Articoli in `content/blog-*/*.md` |
| **Hosting** | Aruba | FTP deploy via GitHub Actions |
| **CDN** | Google Fonts | Outfit, Playfair Display |
| **AI** | Claude API (Haiku) | Bold keywords, meta generation |
| **CI/CD** | GitHub Actions | Auto-deploy grav-site/user/ |

---

## 📂 STRUTTURA DEL PROGETTO

```
D:\valentinarussomentaladvisor.it/
├── index.html, servizi.html, blog.html ... (20+ entry points Vite)
├── src/
│   ├── style.css (CSS globale — FONTE DI VERITÀ per produzione)
│   ├── blog.js (infinite scroll blog)
│   ├── article-render.js (render articolo singolo)
│   └── main.js
├── public/
│   ├── mailer.php (contact form email)
│   ├── blog_api.php (API blog — legge .md, ritorna JSON)
│   ├── admin.php (CMS editor articoli)
│   ├── admin_auth.php (password-based auth)
│   ├── ai_bold.php (Claude API — bold keywords)
│   ├── ai_meta.php (Claude API — meta generation)
│   └── assets/ (immagini, favicon)
├── content/
│   ├── blog-privati/ (articoli markdown)
│   └── blog-aziende/ (articoli markdown)
├── grav-site/ (← LIVE IN PRODUZIONE SU ARUBA)
│   ├── user/
│   │   ├── themes/valentina/
│   │   │   ├── templates/
│   │   │   │   ├── partials/
│   │   │   │   │   ├── base.html.twig (master layout)
│   │   │   │   │   ├── header.html.twig
│   │   │   │   │   └── footer.html.twig
│   │   │   │   ├── home.html.twig (privati hero)
│   │   │   │   ├── aziende_home.html.twig (aziende hero)
│   │   │   │   ├── servizi.html.twig
│   │   │   │   ├── chi-sono.html.twig
│   │   │   │   ├── blog_list.html.twig
│   │   │   │   ├── default.html.twig (articoli + genera-carta special case)
│   │   │   │   └── item.html.twig (blog articoli)
│   │   │   ├── css/
│   │   │   │   ├── style.css (CSS globale — deployato da GitHub Actions)
│   │   │   │   ├── hero-privati.css (privati hero)
│   │   │   │   └── hero-aziende.css (aziende hero)
│   │   │   ├── js/
│   │   │   │   └── main.js
│   │   │   ├── images/
│   │   │   │   ├── logo.png
│   │   │   │   └── bg5-certified.png
│   │   │   └── valentina.yaml (config tema)
│   │   ├── pages/ (GITIGNORED — non deployato)
│   │   │   └── assets/ (immagini articoli)
│   │   ├── plugins/
│   │   │   ├── admin/ (Grav admin panel)
│   │   │   ├── form/ (form plugin)
│   │   │   ├── email/ (email plugin)
│   │   │   └── valentina-admin/ (custom plugin — sidebar articoli)
│   │   └── config/
│   │       ├── security.yaml
│   │       └── ...
│   └── public/ (← FTP root su Aruba)
│       └── index.php (Grav bootstrap)
├── vite.config.js (build config — 23 entry points + static copy)
├── package.json (dependencies)
├── .github/workflows/ (GitHub Actions deploy)
└── ARCHITECTURE.md (questo file)
```

---

## 🚀 DEPLOY & WORKFLOW

### **Flusso di Deploy**

```
Modifica locale (src/ o grav-site/user/themes/)
    ↓
git push origin main
    ↓
GitHub Actions triggered
    ↓
Deploy: grav-site/user/ → Aruba FTP
    ✓ Deploya: themes/, root/, plugins/
    ✗ Esclude: pages/** (GITIGNORED), accounts/**
    ↓
Live su https://valentinarussobg5.com
```

### **Versioning CSS**

- **Versione attuale**: `v=20260311d` (in `base.html.twig`)
- File: `grav-site/user/themes/valentina/css/style.css`
- Quando modificare: Aggiorna il suffisso nel `<link>` per forzare cache invalidation

---

## 📡 FLUSSI DATI PRINCIPALI

### **1. Blog Articles**

```
content/blog-privati/*.md (frontmatter + markdown)
    ↓ (via Vite static copy)
dist/content/blog-*/
    ↓ (fetch browser)
src/blog.js → blog_api.php
    ↓
blog_api.php (legge .md, estrae frontmatter YAML via regex, ritorna JSON)
    ↓
src/blog.js (infinite scroll, rendering card)
    ↓
articolo.html → src/article-render.js
    ↓
src/article-render.js (fetch singolo articolo da blog_api.php)
    ↓
Renderizza articolo completo
```

**Scheduling**: Solo post con `status: published|scheduled` AND `date <= now()` sono visibili.

### **2. CMS Admin (PHP-based)**

```
public/admin.php (EasyMDE editor + form)
    ↓
Salva in content/blog-privati/*.md (PHP write)
    ↓
Refresh automatico blog_api.php
    ↓
Frontend aggiorna articoli
```

**Funzionalità**:
- Editor Markdown (EasyMDE)
- Upload immagini → `assets/blog/`
- Frontmatter fields: title, date, status, tags, seo_title, seo_desc, geo_location, faq (JSON), ecc.
- Button "Grassetto Keyword" → `public/ai_bold.php` (Claude API)

### **3. AI Features**

```
admin.php → Button "Grassetto Keyword"
    ↓
POST → public/ai_bold.php (Claude Haiku)
    ↓
Claude API: reads tags + body, bolds keywords + semantically related terms
    ↓
Returns: modified markdown with **bold** applied
    ↓
Editor aggiorna
```

**Altro**:
- `ai_meta.php` (genera SEO meta da articolo body)

---

## 🔑 FILE CRITICI

| File | Scopo | Posizione |
|------|-------|-----------|
| `vite.config.js` | Build config — 23 entry points + static copy targets | root |
| `base.html.twig` | Master layout (schema.org + header/footer) | grav-site/user/themes/valentina/templates/partials/ |
| `style.css` | CSS globale + mobile nav fix (transform: translateX) | grav-site/user/themes/valentina/css/ |
| `hero-privati.css` | Hero privati styling | grav-site/user/themes/valentina/css/ |
| `blog_api.php` | API blog — legge .md, ritorna JSON | public/ |
| `admin.php` | CMS editor articoli + AI buttons | public/ |
| `admin_auth.php` | Password-based auth (NON GitHub PAT) | public/ |
| `ai_bold.php` | Claude API endpoint per bold keywords | public/ |
| `src/blog.js` | Infinite scroll blog (fetch → rendering) | src/ |
| `src/article-render.js` | Render singolo articolo | src/ |

---

## 📄 PAGINE PRINCIPALI

### **Privati** (Default)

| URL | Template | Tipo |
|-----|----------|------|
| `/` | `home.html.twig` | Hero privati (blueprint home) |
| `/servizi` | `servizi.html.twig` | Servizi (percorso 1-2-3) |
| `/chi-sono` | `chi-sono.html.twig` | Bio Valentina + foto |
| `/blog` | `blog_list.html.twig` | Infinite scroll articoli privati |
| `/articolo?id=...` | `default.html.twig` (+ `item.html.twig` per blog) | Articolo singolo |
| `/genera-carta` | `default.html.twig` (special case) | Generator carta Human Design |
| `/contatti` | `contact_form.html.twig` | Contact form |
| `/privacy`, `/terms` | default | Static pages |

### **Aziende**

| URL | Template | Tipo |
|-----|----------|------|
| `/aziende` | `aziende_home.html.twig` | Hero aziende |
| `/aziende-servizi` | `aziende_servizi.html.twig` | Servizi aziende |
| `/aziende-blog` | `blog_list.html.twig` (+ filter="aziende") | Blog aziende |
| `/aziende-contatti` | `contact_form.html.twig` | Contact form aziende |

---

## 🛠️ CMS ADMIN

### **Frontmatter YAML** (ogni articolo.md)

```yaml
title: "Titolo Articolo"
date: 2026-03-12
status: published|scheduled|draft
category: privati|aziende
tags: [tag1, tag2, tag3]
featured_image: /assets/blog/articolo-slug.jpg
image_focus: "center|left|right"
image_title: "Titolo immagine"
image_caption: "Caption immagine"
image_alt: "Alt text"
description: "Meta description (160 chars)"
seo_title: "SEO Title (60 chars)"
seo_desc: "SEO description (160 chars)"
geo_location: "Milan, Italy"
aeo_answer: "Answer for Answers Engines"
faq:
  - question: "Domanda 1?"
    answer: "Risposta 1"
  - question: "Domanda 2?"
    answer: "Risposta 2"
```

### **Password Auth**

- File: `public/admin_auth.php`
- Password: **[configurato in admin_auth.php]**
- No GitHub PAT required

### **Campi Disponibili**

- Title, Date, Status
- Tags, Category (privati|aziende)
- Featured image, Image metadata (focus, title, caption, alt)
- Description (meta)
- SEO title & description
- Geo location (per GEO SEO)
- FAQ (JSON array)

---

## 📊 SCHEMA.ORG & SEO

### **Schemi Implementati** (in `base.html.twig`)

1. **WebSite Schema** — identifica il sito
2. **Person Schema** — Valentina Russo (BG5 Consultant)
   - name, image, jobTitle, qualifications, knowsAbout
   - sameAs: Instagram, etc.
3. **Organization Schema** — Valentina Russo org + BG5 memberships
4. **Service Schema** — Servizi principali (Prima Lettura, BG5® Business, ecc.)
5. **LocalBusiness Schema** — Consulente Italia
6. **FAQPage Schema** — FAQ principali per AI search engines
   - Questions: Che cos'è BG5®? Che cos'è Human Design? Chi è Valentina? Quali servizi?

### **Trust Signals Aggiunti**

- ✅ BG5® Business Institute link
- ✅ Certified logo (bg5-certified.png)
- ✅ Instagram verified
- ✅ Qualifications & credentials

### **GEO/SEO Score**

**Problema risolto** (commit f0827c3):
- ✅ Entity Clarity: Person + Organization schemas
- ✅ Direct Answers: FAQPage schema
- ✅ Trust Signals: Link + certificazioni + Organization memberships

---

## 🔄 ULTIME MODIFICHE

### **Commit f0827c3** (Brand Font Consistency)
- Allineato brand-name: Playfair Display (tutte pagine)
- Allineato brand-tagline: Outfit + colore mauve (tutte pagine)

### **Commit before** (Mobile Nav Fix)
- Fixed `#main-nav`: `transform: translateX(100%)` (no horizontal scroll)

### **Commit before** (Schema.org + Analytics)
- Added Person + Organization + LocalBusiness + Service + FAQPage schemas
- Removed Plausible, kept GA4

---

## 📝 CONVENTION & RULES

### **CSS Variables** (in `style.css`)

```css
--primary-color: #B68397;      /* Old Rose */
--secondary-color: #5DAEB1;    /* Teal */
--accent-color: #E6A756;       /* Gold */
--bg-color: #FAF7F5;           /* Off-white */
--text-color: #2D2926;         /* Dark */
--soft-gray: #EAE5E1;          /* Light gray */
--mauve: #7a5c6e;              /* Mauve (tagline) */
```

### **Fonts**

- **Heading**: Playfair Display (serif)
- **Body**: Outfit (sans-serif)
- **Weight**: 800 for bold headings, 600 for strong text

### **Markdown Blockers in Content**

❌ **NEVER use `---` (horizontal rule) in markdown content**
- Conflicts with YAML frontmatter delimiter
- Causes preg_replace errors in Grav processing

### **Git Workflow**

```bash
# Setup
npm install
npm run dev          # Local development (Vite)
npm run build        # Build dist/

# Deployment
git push origin main → GitHub Actions → Aruba FTP
```

---

## 🎯 NEXT STEPS / TODO

- [ ] GEO/SEO: Aggiungere link autorevoli (BG5 Institute, ecc.)
- [ ] GEO/SEO: Ottimizzare score (current: 0/100 → target: 60+)
- [ ] Workshop proposta: Implementazione ottimale (hold for now)
- [ ] Blog: Aggiungere articoli privati/aziende
- [ ] Admin: Testare AI bold keywords in production

---

**Last Updated**: 2026-03-12 14:59
**Maintained by**: Claude Haiku 4.5
