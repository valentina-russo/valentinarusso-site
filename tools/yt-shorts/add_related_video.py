"""
Aggiunge "video correlato" a uno Short via Chrome CDP + storage_state backup.

Strategia sessione (doppio livello):
  1. Chrome profile (chrome_profile/) — Chrome salva i cookie su disco in SQLite
     continuamente; sopravvive a reboot normali.
  2. studio_session.json — storage_state Playwright salvato a fine ogni run;
     backup esplicito in caso Chrome venga killato di forza senza flush SQLite.

Prima esecuzione: accedi a YouTube Studio nel browser che si apre.
Esecuzioni successive: sessione ripristinata automaticamente, nessun re-login.

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

import io
import json
import socket
import subprocess
import sys
import time
from pathlib import Path

# Fix encoding su Windows (cp1252 non supporta frecce e altri caratteri Unicode)
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

HERE = Path(__file__).resolve().parent

CHROME_EXE = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
CHROME_PROFILE = HERE / "chrome_profile"
CDP_PORT = 9223
SESSION_FILE = HERE / "studio_session.json"   # backup cookie store (gitignored)
DEBUG_SCREENSHOT = HERE / "studio_debug.png"

# ── Account Valentina ──────────────────────────────────────────────────────────
# REGOLA CRITICA: il chrome_profile contiene piu' account Google (es. darkofiu@gmail.com).
# Prima di qualsiasi azione su YouTube Studio bisogna verificare di essere
# sul canale @valentinarussobg5. Se non lo si e', si switcha via authuser.
VALENTINA_CHANNEL_ID = "UCIW4aZwPaYirGVWAg5uTXBA"   # @valentinarussobg5


# ── Chrome / CDP ───────────────────────────────────────────────────────────────

def _is_cdp_up(port: int = CDP_PORT) -> bool:
    try:
        with socket.create_connection(("localhost", port), timeout=1):
            return True
    except OSError:
        return False


def _clear_chrome_locks() -> None:
    """
    Rimuove i lock file che Chrome lascia dopo un kill forzato (reboot/taskkill).
    Senza questa pulizia Chrome si rifiuta di avviarsi sul profilo.
    """
    for name in ("SingletonLock", "SingletonSocket", "SingletonCookie"):
        lf = CHROME_PROFILE / name
        if lf.exists():
            try:
                lf.unlink()
                print(f"[studio] rimosso lock: {name}")
            except Exception:
                pass
    # LevelDB lock nella cartella Default
    ldb_lock = CHROME_PROFILE / "Default" / "LOCK"
    if ldb_lock.exists():
        try:
            ldb_lock.unlink()
            print("[studio] rimosso Default/LOCK")
        except Exception:
            pass


def _start_chrome() -> None:
    """
    Avvia Chrome con profilo dedicato e remote debugging.
    Se CDP e' gia' attivo, non fa nulla.
    """
    if _is_cdp_up():
        print(f"[studio] Chrome gia' attivo su porta {CDP_PORT}")
        return

    CHROME_PROFILE.mkdir(exist_ok=True)
    _clear_chrome_locks()

    print(f"[studio] avvio Chrome (profilo: {CHROME_PROFILE.name})")
    subprocess.Popen([
        CHROME_EXE,
        f"--remote-debugging-port={CDP_PORT}",
        f"--user-data-dir={CHROME_PROFILE}",
        "--no-first-run",
        "--no-default-browser-check",
        "--start-maximized",
    ])

    print("[studio] attendo Chrome... ", end="", flush=True)
    for _ in range(40):
        time.sleep(0.5)
        if _is_cdp_up():
            print("ok")
            return
    raise RuntimeError(f"Chrome non ha risposto in 20s ({CHROME_EXE})")


def _page_has_auth_error(page) -> bool:
    """
    Ritorna True se Studio mostra la pagina di autorizzazione negata.
    Cerca sia l'apostrofo ASCII (') sia quello curvo Unicode (') usato da Studio IT.
    """
    for sel in (
        "text=/non hai l.autorizzazione/i",   # regex: . matcha entrambi gli apostrofi
        "text=/don.t have permission/i",
        "text=/not authorized/i",
        "text=/Cambia account/",               # bottone presente solo sull'auth-error page
    ):
        try:
            if page.query_selector(sel):
                return True
        except Exception:
            pass
    return False


def _click_brand_account_in_switcher(page) -> bool:
    """
    Apre il channel switcher UI di YouTube Studio e clicca @valentinarussobg5.

    TECNICA: @valentinarussobg5 e' un YouTube Brand Account — non e' un account
    Google separato. authuser=N switcha solo canali personali (account Google),
    non i Brand Account ad essi collegati. Per accedere al Brand Account bisogna
    usare il menu canali dentro YouTube Studio.

    Returns True se il switch e' avvenuto con successo.
    """
    from playwright.sync_api import TimeoutError as PWTimeout

    # 1. Apri il menu canali:
    #    - #avatar-btn sul topbar normale di Studio
    #    - "Cambia account" = bottone presente sulla pagina auth-error di Studio
    clicked = False
    for sel in (
        "#avatar-btn",
        "[aria-label*='canale' i]",
        "[aria-label*='channel' i]",
        "ytcp-account-section-renderer #avatar-btn",
        "#channel-avatar-section",
        "button:has-text('Cambia account')",
        "a:has-text('Cambia account')",
    ):
        try:
            btn = page.wait_for_selector(sel, timeout=3000, state="visible")
            if btn:
                btn.click()
                page.wait_for_timeout(1500)
                clicked = True
                print(f"[studio] menu canali aperto ({sel})")
                break
        except PWTimeout:
            continue
        except Exception:
            continue

    if not clicked:
        print("[studio] WARN: avatar/channel switcher non trovato")
        return False

    # Screenshot del menu aperto (debug)
    try:
        page.screenshot(path=str(HERE / "studio_switcher_menu.png"))
    except Exception:
        pass

    # 2. Cerca @valentinarussobg5 / "Valentina Russo" nel menu e cliccalo
    for sel in (
        f"[data-channel-id='{VALENTINA_CHANNEL_ID}']",
        f"[href*='{VALENTINA_CHANNEL_ID}']",
        "text=/valentina russo/i",
        "text=/@valentinarussobg5/i",
        "[role='menuitem']:has-text('Valentina')",
        "[role='option']:has-text('Valentina')",
        "yt-formatted-string:has-text('Valentina Russo')",
        "a:has-text('Valentina Russo')",
    ):
        try:
            el = page.query_selector(sel)
            if not el:
                continue
            try:
                visible = el.is_visible()
            except Exception:
                visible = False
            if not visible:
                continue
            print(f"[studio] trovato canale Valentina: {sel}")
            el.click()
            page.wait_for_timeout(3000)
            # Verifica diretta sull'URL corrente
            if (VALENTINA_CHANNEL_ID in page.url
                    and not _page_has_auth_error(page)
                    and not _is_error_page(page)):
                print("[studio] switch via UI riuscito")
                return True
            # Il browser interno potrebbe aver navigato; naviga esplicitamente al canale
            try:
                page.goto(
                    f"https://studio.youtube.com/channel/{VALENTINA_CHANNEL_ID}",
                    wait_until="domcontentloaded", timeout=15000,
                )
                page.wait_for_timeout(2000)
                if (VALENTINA_CHANNEL_ID in page.url
                        and not _page_has_auth_error(page)
                        and not _is_error_page(page)):
                    print("[studio] switch confermato via navigazione diretta")
                    return True
            except Exception:
                pass
        except Exception:
            continue

    # 3. "Passa ad account" / "Switch account" puo' aprire un submenu con i canali
    for sw_sel in ("text=/passa ad account/i", "text=/switch account/i", "text=/cambia account/i"):
        try:
            sw_btn = page.query_selector(sw_sel)
            if sw_btn and sw_btn.is_visible():
                print(f"[studio] apro submenu canali: {sw_sel}")
                sw_btn.click()
                page.wait_for_timeout(1500)
                for sub_sel in (
                    "text=/valentina russo/i",
                    f"[data-channel-id='{VALENTINA_CHANNEL_ID}']",
                    "[role='menuitem']:has-text('Valentina')",
                ):
                    sub_el = page.query_selector(sub_sel)
                    if sub_el and sub_el.is_visible():
                        sub_el.click()
                        page.wait_for_timeout(3000)
                        try:
                            page.goto(
                                f"https://studio.youtube.com/channel/{VALENTINA_CHANNEL_ID}",
                                wait_until="domcontentloaded", timeout=15000,
                            )
                            page.wait_for_timeout(2000)
                            if (VALENTINA_CHANNEL_ID in page.url
                                    and not _page_has_auth_error(page)
                                    and not _is_error_page(page)):
                                print("[studio] switch via submenu riuscito")
                                return True
                        except Exception:
                            pass
                break
        except Exception:
            continue

    # 4. JavaScript fallback: cerca nel DOM visibile qualsiasi nodo con "Valentina"
    try:
        result = page.evaluate(f"""() => {{
            const cid = '{VALENTINA_CHANNEL_ID}';
            let el = document.querySelector('[data-channel-id="' + cid + '"]')
                  || document.querySelector('[href*="' + cid + '"]');
            if (!el) {{
                for (const e of document.querySelectorAll(
                        '[role="menuitem"],[role="option"],a,button,span')) {{
                    if (e.offsetParent !== null && e.textContent && (
                            e.textContent.toLowerCase().includes('valentina russo') ||
                            e.textContent.trim() === '@valentinarussobg5')) {{
                        el = e; break;
                    }}
                }}
            }}
            if (el) {{ el.click(); return 'clicked: ' + el.textContent.trim().slice(0, 60); }}
            const items = [];
            document.querySelectorAll('[role="menuitem"],[role="option"]').forEach(e => {{
                if (e.offsetParent !== null && e.textContent)
                    items.push(e.textContent.trim().slice(0, 50));
            }});
            return items.length ? 'menu_items: ' + items.join(' || ') : 'no_menu_items';
        }}""")
        print(f"[studio] JS switcher: {result}")
        if result and result.startswith("clicked:"):
            page.wait_for_timeout(3000)
            try:
                page.goto(
                    f"https://studio.youtube.com/channel/{VALENTINA_CHANNEL_ID}",
                    wait_until="domcontentloaded", timeout=15000,
                )
                page.wait_for_timeout(2000)
                if (VALENTINA_CHANNEL_ID in page.url
                        and not _page_has_auth_error(page)
                        and not _is_error_page(page)):
                    print("[studio] switch via JS riuscito")
                    return True
            except Exception:
                pass
    except Exception as e:
        print(f"[studio] JS fallback errore: {e}")

    return False


def _ensure_valentina_account(page) -> bool:
    """
    Assicura che YouTube Studio sia attivo sul Brand Account @valentinarussobg5.

    TECNICA CRITICA: @valentinarussobg5 e' un YouTube Brand Account gestito da
    valentinebers@gmail.com. NON e' un account Google separato.
    - authuser=N switcha solo tra account Google (canali personali)
    - Per il Brand Account bisogna usare il channel switcher UI di Studio

    Flusso:
      1. Studio home -> gia' su Valentina -> OK
      2. Channel switcher UI dalla pagina corrente
      3. authuser=0..4: per ognuno si prova di nuovo il channel switcher

    REGOLA ASSOLUTA: chiamare sempre come PRIMO passo prima di qualsiasi
    azione su YouTube Studio.
    """
    print("[studio] controllo account attivo...")
    try:
        page.goto("https://studio.youtube.com/", wait_until="domcontentloaded", timeout=20000)
        page.wait_for_timeout(2500)
    except Exception as e:
        print(f"[studio] WARN: Studio home fallita: {e}")
        return False

    current = page.url
    if (VALENTINA_CHANNEL_ID in current
            and not _page_has_auth_error(page)
            and not _is_error_page(page)):
        print("[studio] gia' su @valentinarussobg5")
        return True

    print(f"[studio] account sbagliato (URL: {current})")
    print("[studio] @valentinarussobg5 e' un Brand Account — uso channel switcher UI")

    # Tentativo 1: channel switcher dalla pagina corrente
    if _click_brand_account_in_switcher(page):
        return True

    # Tentativo 2: itera authuser=0..4 (per trovare valentinebers@gmail.com)
    # e per ognuno riprova il channel switcher
    for n in range(5):
        try:
            page.goto(f"https://studio.youtube.com/?authuser={n}",
                     wait_until="domcontentloaded", timeout=15000)
            page.wait_for_timeout(2000)

            if "accounts.google.com" in page.url or "signin" in page.url.lower():
                print(f"[studio] authuser={n}: non loggato — saltato")
                continue

            current_n = page.url
            print(f"[studio] authuser={n}: {current_n}")

            if (VALENTINA_CHANNEL_ID in current_n
                    and not _page_has_auth_error(page)
                    and not _is_error_page(page)):
                print(f"[studio] gia' su Valentina con authuser={n}")
                return True

            if _click_brand_account_in_switcher(page):
                return True

        except Exception as e:
            print(f"[studio] authuser={n}: errore ({e})")
            continue

    print("[studio] ERRORE: switch a @valentinarussobg5 fallito.")
    print("         Controlla che valentinebers@gmail.com sia loggato in chrome_profile")
    print("         e gestisca il canale @valentinarussobg5.")
    return False


def _save_session(ctx) -> None:
    """
    Salva cookies + localStorage in studio_session.json.
    Backup esplicito: se Chrome viene killato senza flush SQLite,
    i cookie sono comunque su file e vengono ripristinati al prossimo run.
    """
    try:
        state = ctx.storage_state()
        SESSION_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2))
        print(f"[studio] sessione salvata -> {SESSION_FILE.name}")
    except Exception as e:
        print(f"[studio] WARN: impossibile salvare sessione: {e}")


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

        # Se il contesto e' vuoto (prima run o Chrome appena avviato) e abbiamo
        # un backup dei cookie, li ripristiniamo per evitare il re-login.
        if SESSION_FILE.exists():
            try:
                cookies = json.loads(SESSION_FILE.read_text())
                existing = ctx.cookies()
                if not any(c.get("name", "").startswith("SAPISID") for c in existing):
                    state = json.loads(SESSION_FILE.read_text())
                    if "cookies" in state:
                        ctx.add_cookies(state["cookies"])
                        print(f"[studio] sessione ripristinata da {SESSION_FILE.name}")
            except Exception as e:
                print(f"[studio] WARN: restore sessione fallito: {e}")

        page = ctx.new_page()

        try:
            # ── PRIMO PASSO: verifica account ─────────────────────────────────
            # Il chrome_profile puo' avere piu' account Google (es. darkofiu@gmail.com).
            # DOBBIAMO essere su @valentinarussobg5 prima di fare qualsiasi cosa.
            account_ok = _ensure_valentina_account(page)
            if not account_ok:
                print("[studio] STOP: account sbagliato, impossibile procedere.")
                return False

            # ── Login check (per prima esecuzione) ────────────────────────────
            if "accounts.google.com" in page.url or "signin" in page.url.lower():
                print("[studio] non loggato — accedi come valentinebers@gmail.com nel browser.")
                print("[studio] attendo login (max 5 min)...")
                page.wait_for_url("*studio.youtube.com*", timeout=300_000)
                page.wait_for_timeout(3000)
                # Ri-verifica account dopo login
                _ensure_valentina_account(page)

            # ── Naviga all'editor del video ───────────────────────────────────
            # Screenshot post-switch: conferma visiva del canale attivo
            page.screenshot(path=str(HERE / "studio_post_switch.png"))
            print(f"[studio] URL dopo switch: {page.url}")

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
            # Salva la sessione su file JSON (backup esplicito).
            # Poi chiudi solo la pagina — NON il browser, Chrome resta attivo
            # con la sessione Google intatta per i run successivi.
            _save_session(ctx)
            try:
                page.close()
            except Exception:
                pass


# ── Helpers ───────────────────────────────────────────────────────────────────

def _is_error_page(page) -> bool:
    """Ritorna True se Studio mostra la pagina generica di errore."""
    return bool(page.query_selector(
        "text=/si è verificato un errore|something went wrong/i"
    ))


def _studio_editor_ready(page, timeout_ms: int = 12000) -> bool:
    """
    Verifica che il video editor sia visibile (non solo ytcp-app che esiste ovunque).
    Usa selettori specifici dell'editor — NON ytcp-app come fallback.
    """
    from playwright.sync_api import TimeoutError as PWTimeout
    for sel in ("ytcp-video-metadata-editor", "ytcp-tabs", "#details"):
        try:
            page.wait_for_selector(sel, timeout=timeout_ms)
            return True
        except PWTimeout:
            continue
    return False


def _wait_for_studio(page, url: str, max_retries: int = 6) -> None:
    """
    Attendi che YouTube Studio carichi correttamente il video editor.

    Strategia a due fasi:
      Fase 1 — reload loop (max_retries volte, 5s tra l'uno e l'altro)
      Fase 2 — warm navigation: canale Valentina -> video URL
                IMPORTANTE: usare il canale di Valentina (non Studio home) per non
                perdere il contesto di channel switch gia' fatto da _ensure_valentina_account.
    """
    from playwright.sync_api import TimeoutError as PWTimeout

    # ── Fase 1: reload loop ────────────────────────────────────────────────────
    for attempt in range(1, max_retries + 1):
        if _studio_editor_ready(page, timeout_ms=10000) and not _is_error_page(page):
            print(f"[studio] editor pronto al tentativo {attempt}")
            page.wait_for_timeout(1000)
            return

        print(f"[studio] pagina errore/timeout (tentativo {attempt}/{max_retries}) — refresh...")
        # Invece di reload (che puo' perdere il contesto canale), re-naviga alla URL del video
        page.goto(url, wait_until="domcontentloaded", timeout=20000)
        page.wait_for_timeout(5000)

    # ── Fase 2: warm navigation ────────────────────────────────────────────────
    # Passa PRIMA per il canale di Valentina per mantenere il contesto corretto,
    # poi vai al video. NON usare Studio home: resetterebbe darkofiu come canale attivo.
    valentina_channel = f"https://studio.youtube.com/channel/{VALENTINA_CHANNEL_ID}"
    print(f"[studio] warm navigation: canale Valentina -> video URL...")
    try:
        page.goto(valentina_channel, wait_until="domcontentloaded", timeout=20000)
        page.wait_for_timeout(3000)
    except Exception:
        pass

    page.goto(url, wait_until="domcontentloaded", timeout=30000)
    page.wait_for_timeout(3000)

    # Ultima chance: aspetta specificamente il video editor (20s)
    if _studio_editor_ready(page, timeout_ms=20000) and not _is_error_page(page):
        print("[studio] editor pronto dopo warm navigation")
        page.wait_for_timeout(2000)
        return

    if _is_error_page(page):
        print("[studio] WARN: pagina errore persistente — procedo comunque")
    page.wait_for_timeout(2000)


def _click_show_more(page) -> None:
    """
    Espande la sezione avanzata di Studio cliccando 'Mostra altro' / 'Show more'.
    Il campo 'Video correlato' si trova in questa sezione nascosta.
    Prima controlla se #linked-video-editor-link è già visibile (sezione già espansa).
    """
    from playwright.sync_api import TimeoutError as PWTimeout

    # Se il trigger è già presente la sezione è già espansa — non cliccare "Mostra altro"
    already_open = page.query_selector("#linked-video-editor-link")
    if already_open:
        print("[studio] sezione avanzata già espansa — salto 'Mostra altro'")
        return

    for sel in (
        "ytcp-button:has-text('Mostra altro')",
        "ytcp-button:has-text('Show more')",
        "[aria-label='Mostra altro']",
        "[aria-label='Show more']",
    ):
        try:
            # wait_for_selector con timeout breve: se non c'è passiamo al prossimo
            btn = page.wait_for_selector(sel, timeout=3000, state="visible")
            if btn:
                btn.click()
                page.wait_for_timeout(1500)
                print(f"[studio] espanso: {sel}")
                return
        except PWTimeout:
            continue
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

    # Attendi attivamente #linked-video-editor-link (non query_selector istantaneo).
    # Su Chrome freddo Angular impiega 5-15s per renderizzare la sezione avanzata.
    try:
        trigger = page.wait_for_selector("#linked-video-editor-link", timeout=15000)
        if trigger:
            trigger.click()
            page.wait_for_timeout(2000)
            print("[studio] picker Video correlato aperto")
            return True
    except PWTimeout:
        pass

    # Fallback: cerca per testo
    for sel in (
        "ytcp-text-dropdown-trigger:has-text('Video correlato')",
        "ytcp-text-dropdown-trigger:has-text('Related video')",
        "ytcp-shorts-content-links-picker",
    ):
        try:
            el = page.wait_for_selector(sel, timeout=5000)
        except PWTimeout:
            el = None
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
