"""
Aggiunge "video correlato" a uno Short via automazione Playwright di YouTube Studio.

Prima esecuzione: apre Chromium, attendere il login manuale a YouTube Studio,
poi premi INVIO. L'auth viene salvata in studio_auth.json per i run successivi.

Uso: python add_related_video.py <short_video_id> <original_video_url>
"""
from __future__ import annotations

import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
AUTH_STATE = HERE / "studio_auth.json"   # gitignored — contiene cookie di sessione
DEBUG_SCREENSHOT = HERE / "studio_debug.png"


def _ensure_auth(p) -> "BrowserContext":
    """
    Se studio_auth.json esiste: riusa le credenziali salvate (nessun login).
    Altrimenti: apre il browser, aspetta il login manuale, salva le credenziali.
    """
    browser = p.chromium.launch(headless=False, slow_mo=100)

    if AUTH_STATE.exists():
        print(f"[studio] carico auth da {AUTH_STATE.name}")
        return browser.new_context(storage_state=str(AUTH_STATE))

    # Prima volta: login manuale
    print("[studio] prima esecuzione — apertura Chromium per login manuale.")
    print("[studio] 1. Accedi a YouTube con l'account @valentinarussobg5")
    print("[studio] 2. Torna qui e premi INVIO.")
    ctx = browser.new_context()
    page = ctx.new_page()
    page.goto("https://studio.youtube.com", wait_until="domcontentloaded")
    input("[studio] Premi INVIO dopo aver completato il login... ")
    ctx.storage_state(path=str(AUTH_STATE))
    print(f"[studio] auth salvata in {AUTH_STATE.name} — non servira' piu' loggarsi.")
    return ctx


def add_related_video(short_id: str, related_url: str) -> bool:
    """
    Apre YouTube Studio, imposta il video correlato sullo Short, salva.
    Returns True se ok.
    """
    from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

    studio_url = f"https://studio.youtube.com/video/{short_id}/edit"
    print(f"[studio] apertura: {studio_url}")

    with sync_playwright() as p:
        ctx = _ensure_auth(p)
        page = ctx.new_page()

        try:
            page.goto(studio_url, wait_until="domcontentloaded", timeout=30000)

            # Attendi che l'editor Studio sia pronto
            try:
                page.wait_for_selector(
                    "ytcp-video-metadata-editor, #details, ytcp-tabs",
                    timeout=20000,
                )
            except PWTimeout:
                pass

            page.wait_for_timeout(2500)
            page.screenshot(path=str(DEBUG_SCREENSHOT))
            print(f"[studio] screenshot: {DEBUG_SCREENSHOT.name}")

            # ── Trova il campo "Video correlato" ──────────────────────────────
            related_input = _find_related_input(page)

            if related_input is None:
                print("[studio] campo 'Video correlato' non trovato.")
                print(f"         Controlla {DEBUG_SCREENSHOT.name} per vedere la UI.")
                # Aggiorna auth nel caso la sessione fosse scaduta
                ctx.storage_state(path=str(AUTH_STATE))
                ctx.close()
                return False

            # ── Inserisci URL ─────────────────────────────────────────────────
            related_input.triple_click()
            related_input.fill(related_url)
            page.wait_for_timeout(600)
            print(f"[studio] URL inserito: {related_url}")

            # ── Salva ─────────────────────────────────────────────────────────
            saved = _click_save(page)
            page.wait_for_timeout(2000)
            page.screenshot(path=str(HERE / "studio_after_save.png"))

            if saved:
                print("[studio] video correlato impostato e salvato.")
            else:
                print("[studio] avviso: salva manualmente (pulsante 'Salva' non trovato).")

            # Aggiorna auth state
            ctx.storage_state(path=str(AUTH_STATE))
            ctx.close()
            return saved

        except Exception as exc:
            print(f"[studio] errore: {exc}")
            try:
                page.screenshot(path=str(HERE / "studio_error.png"))
                print(f"[studio] screenshot errore salvato.")
            except Exception:
                pass
            try:
                ctx.close()
            except Exception:
                pass
            return False


def _find_related_input(page):
    """Prova piu' selettori per trovare il campo 'Video correlato'."""
    from playwright.sync_api import TimeoutError as PWTimeout

    strategies = [
        # Label italiana
        "ytcp-form-input-container:has-text('Video correlato') input",
        "ytcp-form-input-container:has-text('Video correlato') ytcp-text-input-field input",
        # Label inglese
        "ytcp-form-input-container:has-text('Related video') input",
        "ytcp-form-input-container:has-text('Related video') ytcp-text-input-field input",
        # Placeholder
        "input[placeholder*='correlato' i]",
        "input[placeholder*='related' i]",
        # Componente Shorts
        "ytcp-shorts-related-video-editor input",
        "#shorts-related-video input",
    ]

    for sel in strategies:
        try:
            el = page.wait_for_selector(sel, timeout=2000, state="visible")
            if el:
                print(f"[studio] campo trovato: {sel}")
                return el
        except PWTimeout:
            continue

    # Fallback: cerca per testo visibile nella pagina
    for label in ("Video correlato", "Related video"):
        try:
            section = page.locator(f"text=/{label}/i").first
            if section.count() > 0:
                inp = section.locator(
                    "xpath=ancestor::ytcp-form-input-container//input"
                ).first
                if inp.is_visible():
                    print(f"[studio] campo trovato via testo: {label!r}")
                    return inp
        except Exception:
            continue

    return None


def _click_save(page) -> bool:
    """Clicca il pulsante Salva."""
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


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("uso: python add_related_video.py <short_video_id> <original_video_url>")
        print("  es: python add_related_video.py 8XoCYS0-xzk https://youtu.be/D769ntt4Djg")
        sys.exit(1)

    ok = add_related_video(sys.argv[1], sys.argv[2])
    sys.exit(0 if ok else 1)
