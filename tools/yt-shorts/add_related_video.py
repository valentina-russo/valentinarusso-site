"""
Aggiunge "video correlato" a uno Short via Chrome con remote debugging (CDP).

Chrome viene avviato con un profilo dedicato e porta DevTools 9223.
Prima esecuzione: accedi a YouTube Studio nel browser che si apre.
Esecuzioni successive: sessione gia' salvata nel profilo, accesso automatico.

Flusso UI scoperto empiricamente (Studio IT, maggio 2025):
  1. Apri editor video -> clicca "Mostra altro"
  2. Il campo "Video correlato" e' un ytcp-text-dropdown-trigger#linked-video-editor-link
  3. Cliccando apre un dialog "Scegli un video specifico" con una search box
  4. Cerchi per titolo -> selezioni l'option -> salvi

Uso: python add_related_video.py <short_video_id> <original_video_title_or_search>
     python add_related_video.py <short_video_id> <original_video_url>

Il secondo argomento puo' essere:
  - Un URL YouTube (es. https://youtu.be/D769ntt4Djg) -> cerca per ID
  - Un titolo o query di ricerca (es. "HUMAN DESIGN VS BG5")
"""
from __future__ import annotations

import socket
import subprocess
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent

# Percorso Chrome sistema (Windows default)
CHROME_EXE = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
# Profilo dedicato per l'automazione (gitignored, persiste la sessione YouTube)
CHROME_PROFILE = HERE / "chrome_profile"
# Porta DevTools — usa una porta non standard per non confliggere
CDP_PORT = 9223
DEBUG_SCREENSHOT = HERE / "studio_debug.png"


# ── Gestione Chrome ────────────────────────────────────────────────────────────

def _is_cdp_up(port: int = CDP_PORT) -> bool:
    """Verifica se Chrome DevTools e' gia' in ascolto sulla porta."""
    try:
        with socket.create_connection(("localhost", port), timeout=1):
            return True
    except OSError:
        return False


def _start_chrome() -> subprocess.Popen | None:
    """
    Avvia Chrome con profilo dedicato e remote debugging.
    Se e' gia' in ascolto sulla porta, non fa nulla.
    Returns il processo lanciato (o None se era gia' attivo).
    """
    if _is_cdp_up():
        print(f"[studio] Chrome gia' attivo su porta {CDP_PORT}")
        return None

    CHROME_PROFILE.mkdir(exist_ok=True)
    print(f"[studio] avvio Chrome (profilo: {CHROME_PROFILE.name})")
    proc = subprocess.Popen([
        CHROME_EXE,
        f"--remote-debugging-port={CDP_PORT}",
        f"--user-data-dir={CHROME_PROFILE}",
        "--no-first-run",
        "--no-default-browser-check",
        "--start-maximized",
    ])

    print("[studio] attendo che Chrome sia pronto... ", end="", flush=True)
    for _ in range(40):
        time.sleep(0.5)
        if _is_cdp_up():
            print("ok")
            return proc
    proc.kill()
    raise RuntimeError("Chrome non ha risposto in 20s. Controlla che sia installato in "
                       f"{CHROME_EXE}")


def _extract_video_id(url_or_query: str) -> str | None:
    """
    Se url_or_query e' un URL YouTube, estrae l'ID video.
    Altrimenti restituisce None (e' una query di ricerca).
    """
    import re
    patterns = [
        r"youtu\.be/([A-Za-z0-9_-]{11})",
        r"youtube\.com/(?:watch\?v=|shorts/)([A-Za-z0-9_-]{11})",
        r"v=([A-Za-z0-9_-]{11})",
    ]
    for pat in patterns:
        m = re.search(pat, url_or_query)
        if m:
            return m.group(1)
    return None


def _get_video_title(video_id: str) -> str | None:
    """
    Recupera il titolo del video tramite YouTube Data API v3.
    Usa youtube_token.pickle se disponibile.
    """
    try:
        import pickle
        from google.auth.transport.requests import Request
        from googleapiclient.discovery import build

        token_path = HERE / "youtube_token.pickle"
        if not token_path.exists():
            return None
        with open(token_path, "rb") as f:
            creds = pickle.load(f)
        if creds.expired and creds.refresh_token:
            creds.refresh(Request())
        yt = build("youtube", "v3", credentials=creds, cache_discovery=False)
        resp = yt.videos().list(part="snippet", id=video_id).execute()
        items = resp.get("items", [])
        if items:
            title = items[0]["snippet"]["title"]
            print(f"[studio] titolo video originale: {title!r}")
            return title
    except Exception as e:
        print(f"[studio] get_video_title failed: {e}")
    return None


# ── Core ───────────────────────────────────────────────────────────────────────

def add_related_video(short_id: str, original_url_or_title: str) -> bool:
    """
    Apre YouTube Studio, trova il campo 'Video correlato', seleziona il video
    originale dal picker e salva.

    original_url_or_title: URL YouTube oppure titolo/query da cercare nel picker.
    Returns True se salvato con successo.
    """
    from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

    studio_url = f"https://studio.youtube.com/video/{short_id}/edit"
    print(f"[studio] target: {studio_url}")

    # Ricava query di ricerca: preferisci titolo (piu' affidabile nel picker)
    vid_id = _extract_video_id(original_url_or_title)
    if vid_id:
        # Prova a risolvere il titolo per una ricerca piu' precisa
        title = _get_video_title(vid_id)
        search_query = title if title else vid_id
    else:
        search_query = original_url_or_title
    print(f"[studio] search query: {search_query!r}")

    _start_chrome()

    with sync_playwright() as p:
        browser = p.chromium.connect_over_cdp(f"http://localhost:{CDP_PORT}")
        ctx = browser.contexts[0] if browser.contexts else browser.new_context()
        # Sempre nuova pagina: evita stato stale da navigazioni precedenti.
        page = ctx.new_page()

        try:
            page.goto(studio_url, wait_until="domcontentloaded", timeout=30000)
            page.wait_for_timeout(1500)

            # ── Login check ────────────────────────────────────────────────────
            current = page.url
            if "accounts.google.com" in current or "signin" in current.lower():
                print("[studio] non loggato — accedi con @valentinarussobg5 nel browser aperto.")
                print("[studio] attendo login (max 5 min)...")
                page.wait_for_url("*studio.youtube.com*", timeout=300_000)
                page.wait_for_timeout(3000)
                if short_id not in page.url:
                    page.goto(studio_url, wait_until="domcontentloaded", timeout=30000)
                    page.wait_for_timeout(1500)

            # ── Attendi editor Studio ──────────────────────────────────────────
            _wait_for_studio(page, studio_url)

            page.screenshot(path=str(DEBUG_SCREENSHOT))
            print(f"[studio] screenshot: {DEBUG_SCREENSHOT.name}")

            # ── Espandi sezione avanzata ───────────────────────────────────────
            _click_show_more(page)

            # ── Apri picker Video correlato ────────────────────────────────────
            opened = _open_video_picker(page)
            if not opened:
                print("[studio] picker Video correlato non aperto.")
                return False

            # ── Cerca e seleziona video ────────────────────────────────────────
            selected = _search_and_select(page, search_query)
            if not selected:
                print(f"[studio] video non trovato per query: {search_query!r}")
                return False

            # ── Salva ──────────────────────────────────────────────────────────
            saved = _click_save(page)
            page.wait_for_timeout(2500)
            page.screenshot(path=str(HERE / "studio_after_save.png"))

            if saved:
                print("[studio] video correlato impostato e salvato.")
            else:
                print("[studio] avviso: pulsante Salva non trovato — salva tu manualmente.")

            return saved

        except Exception as exc:
            print(f"[studio] errore: {exc}")
            try:
                page.screenshot(path=str(HERE / "studio_error.png"))
                print("[studio] screenshot errore salvato.")
            except Exception:
                pass
            return False

        finally:
            try:
                # Chiudi la pagina ma NON il browser.
                # Chrome rimane in esecuzione con la sessione Google intatta:
                # al prossimo run _is_cdp_up() = True e riusiamo lo stesso
                # processo Chrome gia' autenticato (nessun re-login necessario).
                page.close()
            except Exception:
                pass
            # NON chiamare browser.close(): via CDP chiuderebbe Chrome
            # e perderemmo la sessione Google a ogni invocation.


# ── Helpers ───────────────────────────────────────────────────────────────────

def _wait_for_studio(page, url: str, max_retries: int = 3) -> None:
    """
    Attendi che YouTube Studio carichi correttamente.
    Se appare la pagina di errore generica, fa refresh (max_retries volte).
    """
    from playwright.sync_api import TimeoutError as PWTimeout

    for attempt in range(1, max_retries + 1):
        loaded = False
        for sel in ("ytcp-video-metadata-editor", "ytcp-tabs", "#details", "ytcp-app"):
            try:
                page.wait_for_selector(sel, timeout=10000)
                loaded = True
                break
            except PWTimeout:
                continue

        page.wait_for_timeout(2000)

        error_text = page.query_selector("text=/si è verificato un errore|something went wrong/i")
        if error_text or not loaded:
            print(f"[studio] pagina errore/timeout (tentativo {attempt}/{max_retries}) — refresh...")
            page.reload(wait_until="domcontentloaded", timeout=20000)
            page.wait_for_timeout(3000)
            continue

        return

    print("[studio] retry navigazione completa...")
    page.goto(url, wait_until="domcontentloaded", timeout=30000)
    page.wait_for_timeout(4000)


def _click_show_more(page) -> None:
    """
    Espande la sezione avanzata di Studio cliccando 'Mostra altro' / 'Show more'.
    Il campo 'Video correlato' si trova in questa sezione nascosta.
    """
    for sel in (
        "ytcp-button:has-text('Mostra altro')",
        "ytcp-button:has-text('Show more')",
        "[aria-label='Mostra altro']",
        "[aria-label='Show more']",
    ):
        try:
            btn = page.query_selector(sel)
            if btn and btn.is_visible():
                btn.click()
                page.wait_for_timeout(1500)
                print(f"[studio] espanso: {sel}")
                return
        except Exception:
            continue
    print("[studio] 'Mostra altro' non trovato — sezione gia' espansa o layout diverso")


def _open_video_picker(page) -> bool:
    """
    Clicca il trigger 'Video correlato' per aprire il dialog di selezione video.
    Il trigger e' ytcp-text-dropdown-trigger#linked-video-editor-link.
    Returns True se il dialog e' aperto.
    """
    from playwright.sync_api import TimeoutError as PWTimeout

    # Playwright query_selector pierces shadow DOM
    trigger = page.query_selector("#linked-video-editor-link")
    if trigger:
        trigger.click()
        page.wait_for_timeout(2000)
        print("[studio] picker Video correlato aperto")
        return True

    # Fallback: cerca per testo
    for sel in (
        "ytcp-text-dropdown-trigger:has-text('Video correlato')",
        "ytcp-text-dropdown-trigger:has-text('Related video')",
        "ytcp-shorts-content-links-picker",
    ):
        el = page.query_selector(sel)
        if el:
            el.click()
            page.wait_for_timeout(2000)
            print(f"[studio] picker aperto via: {sel}")
            return True

    return False


def _search_and_select(page, search_query: str) -> bool:
    """
    Nella dialog 'Scegli un video specifico', cerca il video e clicca il risultato.
    Returns True se il video e' stato selezionato.
    """
    from playwright.sync_api import TimeoutError as PWTimeout

    # Trova input di ricerca nel dialog (Playwright pierces shadow DOM)
    search_inp = None
    for sel in (
        "input[placeholder*='tuoi video' i]",
        "input[placeholder*='your video' i]",
        "input[placeholder*='search' i][type='text']",
        "ytcp-dialog input",
    ):
        el = page.query_selector(sel)
        if el and el.is_visible():
            search_inp = el
            break

    if not search_inp:
        print("[studio] search input nel dialog non trovato")
        return False

    # Usa ElementHandle.type() — dispatcha keyboard events direttamente
    # senza dipendere dal focus globale (non usa page.keyboard).
    # Tronca a 50 caratteri per evitare query troppo lunghe che non matchano.
    query = search_query[:50]
    search_inp.type(query, delay=50)
    page.wait_for_timeout(3000)
    print(f"[studio] cercato: {query!r}")

    # Seleziona il primo risultato (role=option)
    option = page.query_selector("[role='option']")
    if not option:
        # Prova altri selettori comuni per item del picker
        for sel in (
            "ytcp-video-section-item",
            "ytcp-video-picker-item",
            "ytcp-thumbnail-with-info",
            "[data-video-id]",
        ):
            option = page.query_selector(sel)
            if option:
                break

    if not option:
        print("[studio] nessun risultato trovato nel picker")
        return False

    option_text = ""
    try:
        option_text = option.inner_text()[:80]
    except Exception:
        pass
    print(f"[studio] seleziono: {option_text!r}")
    option.click()
    page.wait_for_timeout(1500)
    return True


def _click_save(page) -> bool:
    """Clicca il pulsante Salva in YouTube Studio."""
    from playwright.sync_api import TimeoutError as PWTimeout

    for sel in (
        "#save-button:not([disabled])",
        "ytcp-button#save-button",
        "ytcp-button:has-text('Salva')",
        "ytcp-button:has-text('Save')",
        "[aria-label='Salva']:not([disabled])",
        "[aria-label='Save']:not([disabled])",
    ):
        try:
            btn = page.wait_for_selector(sel, timeout=2000, state="visible")
            if btn and btn.is_enabled():
                btn.click()
                return True
        except PWTimeout:
            continue
    return False


# ── CLI ────────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("uso: python add_related_video.py <short_video_id> <original_video_url_or_title>")
        print("  es: python add_related_video.py 8XoCYS0-xzk https://youtu.be/D769ntt4Djg")
        print("  es: python add_related_video.py 8XoCYS0-xzk 'HUMAN DESIGN VS BG5'")
        sys.exit(1)

    ok = add_related_video(sys.argv[1], sys.argv[2])
    sys.exit(0 if ok else 1)
