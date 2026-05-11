# YouTube Shorts Repurpose Pipeline

Repurpose un video YouTube esistente (o file locale) in un Reel verticale 9:16 da 1-2 minuti, con titolo burnt-in (15s), sottotitoli burnt-in safe zone bassa, e copertina brand-coerente.

## Workflow

```
input video → Whisper trascrizione → AI selezione segmento (1-2 min)
            → 10 titoli proposti → utente sceglie 1
            → 5 template cover → utente sceglie 1 (poi fisso)
            → ffmpeg crop 9:16 + burn-in titolo + sottotitoli + watermark
            → upload YouTube Short via API
```

## Setup

### 1. Dipendenze Python
```
pip install -r requirements.txt
```

### 2. ffmpeg
Installa ffmpeg (https://ffmpeg.org/download.html). Aggiungi al PATH.
Verifica: `ffmpeg -version`

### 3. Whisper model (italiano)
Al primo uso, faster-whisper scarica il modello automaticamente (~1.5GB per `medium`).

### 4. YouTube Data API v3 OAuth
Una volta sola:
1. Vai a https://console.cloud.google.com/apis/credentials
2. Abilita "YouTube Data API v3"
3. Crea OAuth 2.0 Client ID (Desktop App)
4. Scarica il JSON come `client_secret.json` in questa cartella
5. Esegui: `python youtube_publisher.py --auth`
6. Browser si aprirà → login con account Valentina → grant permessi `youtube.upload`
7. Token salvato in `youtube_token.pickle`

## Comandi

### Genera preview cover (per scegliere il template)
```bash
python cover_templates.py "D:/Download/yt-shorts-cover-previews" "Titolo di prova"
```

### Repurpose end-to-end
```bash
python repurpose.py <URL_YT | path/video.mp4> [--start MM:SS] [--end MM:SS]
```

Il flusso interattivo proporrà:
- 10 titoli (scegli 1 con numero)
- 1 template di cover già fisso (`COVER_TEMPLATE` nella .env)
- Preview finale → conferma per pubblicare

### Upload manuale (se non vuoi auto-publish)
```bash
python repurpose.py <video> --no-publish
# Output in D:/Download/yt-shorts/<slug>/
# Carica manualmente da app YouTube Studio
```

## Configurazione (.env)

```env
ANTHROPIC_API_KEY=sk-ant-...    # solo se usi API per titoli (default: usa Claude Code interno)
COVER_TEMPLATE=1-editorial-dark  # template fisso scelto
WATERMARK_TEXT=@valentinarussobg5
DEFAULT_PRIVACY=public           # public | unlisted | private
```

## File principali

| File | Funzione |
|---|---|
| `repurpose.py` | Orchestrator end-to-end |
| `cover_templates.py` | 5 design template per copertina |
| `transcribe.py` | Whisper italiano wrapper |
| `select_segment.py` | AI segment picker |
| `generate_titles.py` | 10 titoli + descrizione + hashtag |
| `render_video.py` | ffmpeg crop + burn-in pipeline |
| `youtube_publisher.py` | YT Data API upload |
| `client_secret.json` | OAuth credentials (NON committed) |
| `youtube_token.pickle` | Token cache (NON committed) |

## Output

`D:/Download/yt-shorts/{slug}/`
- `short.mp4` — video finale 1080×1920
- `cover.png` — copertina 1080×1920
- `titles.txt` — i 10 titoli proposti (quello scelto è marcato)
- `description.txt` — descrizione caricata su YT
- `subtitles.srt` — sottotitoli sorgente
- `metadata.json` — slug, durata, segmento, video ID YouTube uploadato

## Stato

- [x] cover_templates.py — 5 template Pillow funzionanti
- [ ] transcribe.py
- [ ] select_segment.py
- [ ] generate_titles.py
- [ ] render_video.py
- [ ] youtube_publisher.py (OAuth + upload)
- [ ] repurpose.py (orchestrator)
- [ ] Test E2E con video reale Valentina

## Note brand

- Font: Playfair Display Bold/Italic (titoli), Outfit Bold/Medium (etichette/brand), IM Fell English (eyebrows numerici)
- Palette: navy `#1E3A5F`, rosa `#B68397`, gold `#C48A3A`, cream `#FAF7F5`, ink `#1C1210`
- Tono titoli: rispetta writing rules Valentina (no em-dash, no triplette, no meta-frasi). Max 7 parole.
- Watermark video: `@valentinarussobg5` bottom-left fade-in ultimi 3 secondi
