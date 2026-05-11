"""
Aggiunge valentinebers@gmail.com al profilo Chrome usato da add_related_video.py.

Apre Chrome con il profilo yt-shorts, naviga al flow "Aggiungi account" di Google,
pre-compila l'email valentinebers@gmail.com e clicca Avanti.
L'utente deve inserire solo la password nel browser che si apre.
Quando il login è completato il profilo è pronto per l'automazione.
"""
import socket
import subprocess
import sys
import time
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

HERE = Path(__file__).resolve().parent
CHROME_EXE = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
CHROME_PROFILE = HERE / "chrome_profile"
CDP_PORT = 9223
TARGET_EMAIL = "valentinebers@gmail.com"


def _is_cdp_up(port: int = CDP_PORT) -> bool:
    try:
        with socket.create_connection(("localhost", port), timeout=1):
            return True
    except OSError:
        return False


def _clear_locks() -> None:
    for name in ("SingletonLock", "SingletonSocket", "SingletonCookie"):
        lf = CHROME_PROFILE / name
        if lf.exists():
            try:
                lf.unlink()
                print(f"[login] rimosso lock: {name}")
            except Exception:
                pass
    ldb = CHROME_PROFILE / "Default" / "LOCK"
    if ldb.exists():
        try:
            ldb.unlink()
            print("[login] rimosso Default/LOCK")
        except Exception:
            pass


def main() -> None:
    from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

    # ── Avvia Chrome se non è già attivo ──────────────────────────────────────
    if _is_cdp_up():
        print(f"[login] Chrome già attivo su porta {CDP_PORT}")
    else:
        CHROME_PROFILE.mkdir(exist_ok=True)
        _clear_locks()
        print(f"[login] avvio Chrome (profilo: {CHROME_PROFILE.name})")
        subprocess.Popen([
            CHROME_EXE,
            f"--remote-debugging-port={CDP_PORT}",
            f"--user-data-dir={CHROME_PROFILE}",
            "--no-first-run",
            "--no-default-browser-check",
            "--start-maximized",
        ])
        print("[login] attendo Chrome... ", end="", flush=True)
        for _ in range(40):
            time.sleep(0.5)
            if _is_cdp_up():
                print("ok")
                break
        else:
            print("TIMEOUT")
            sys.exit(1)

    with sync_playwright() as p:
        browser = p.chromium.connect_over_cdp(f"http://localhost:{CDP_PORT}")
        ctx = browser.contexts[0] if browser.contexts else browser.new_context()
        page = ctx.new_page()

        # ── Naviga al flow "Aggiungi account" ─────────────────────────────────
        add_url = (
            "https://accounts.google.com/AddSession"
            "?continue=https%3A%2F%2Fstudio.youtube.com%2F"
            "&hl=it"
        )
        print(f"[login] navigo a: {add_url}")
        try:
            page.goto(add_url, wait_until="domcontentloaded", timeout=30000)
            page.wait_for_timeout(2000)
        except Exception as e:
            print(f"[login] WARN navigazione: {e}")

        # Se siamo già su Studio, il login era già fatto
        # NOTA: l'URL di AddSession contiene studio.youtube.com come param continue=
        # → serve controllare che l'hostname sia effettivamente su studio.youtube.com
        if page.url.startswith("https://studio.youtube.com") or page.url.startswith("https://myaccount.google.com"):
            print(f"[login] gia' loggato su Studio: {page.url}")
            page.close()
            return

        # ── Inserisci email ───────────────────────────────────────────────────
        email_filled = False
        for sel in (
            "#identifierId",
            "input[type='email']",
            "input[name='identifier']",
        ):
            try:
                inp = page.wait_for_selector(sel, timeout=8000, state="visible")
                if inp:
                    inp.click(click_count=3)
                    page.wait_for_timeout(200)
                    inp.type(TARGET_EMAIL, delay=60)
                    print(f"[login] email inserita: {TARGET_EMAIL}")
                    email_filled = True
                    break
            except PWTimeout:
                continue
            except Exception as e:
                print(f"[login] WARN email input ({sel}): {e}")

        if email_filled:
            # Clicca Avanti
            page.wait_for_timeout(300)
            for sel in (
                "#identifierNext",
                "button:has-text('Avanti')",
                "button:has-text('Next')",
            ):
                btn = page.query_selector(sel)
                if btn and btn.is_visible():
                    btn.click()
                    print("[login] cliccato Avanti")
                    break
            page.wait_for_timeout(2000)
        else:
            print("[login] WARN: campo email non trovato — il browser è aperto, procedi manualmente")

        # ── Attendi che l'utente completi il login ────────────────────────────
        print()
        print("=" * 60)
        print(f"  Inserisci la password per {TARGET_EMAIL}")
        print("  nel browser Chrome aperto.")
        print("  Attendo il completamento (max 5 minuti)...")
        print("=" * 60)

        try:
            page.wait_for_url(
                lambda url: (
                    url.startswith("https://studio.youtube.com")
                    or url.startswith("https://myaccount.google.com")
                    or (url.startswith("https://www.youtube.com") and "accounts.google.com" not in url)
                ),
                timeout=300_000,
            )
            print(f"[login] login completato. URL finale: {page.url}")
            print("[login] profilo aggiornato — ora add_related_video.py funzionerà.")
        except PWTimeout:
            print("[login] timeout (5 min) — login non completato.")

        try:
            page.close()
        except Exception:
            pass
        print("[login] Chrome rimane aperto con la sessione salvata nel profilo.")


if __name__ == "__main__":
    main()
