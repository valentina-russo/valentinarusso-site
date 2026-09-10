# -*- coding: utf-8 -*-
"""Guida il Firefox di Marco (quello vero, gia' aperto) da riga di comando.

Prerequisiti, in ordine:
  1. Firefox avviato con l'opzione -marionette (porta 2828)
  2. geckodriver.exe --connect-existing --marionette-port 2828 --port 4444
  3. py ff.py serve            (tiene aperta UNA sessione Selenium e risponde su 127.0.0.1:4455)

Poi, da qualunque shell:
  py ff.py tabs                       elenco schede (indice, titolo, url)
  py ff.py use <indice>               passa a una scheda
  py ff.py goto <url>                 apre un url nella scheda corrente
  py ff.py text [max]                 testo visibile della pagina
  py ff.py find <testo>               elementi cliccabili che contengono il testo (con indice)
  py ff.py click <testo|css=...>      clicca il primo elemento che contiene il testo (o un selettore css)
  py ff.py type <css> <testo>         scrive in un campo
  py ff.py key <tasto>                ENTER, ESCAPE, TAB
  py ff.py js <espressione>           esegue js e stampa il risultato
  py ff.py shot [file]                screenshot su disco
  py ff.py stop                       chiude il server (Firefox resta aperto)

Il server non crea mai una scheda: lavora su quelle che ci sono. Non tocca
mai password: se una pagina chiede l'accesso, lo fa Marco a mano nel browser.
"""
import json
from pathlib import Path
import sys
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
import time
import urllib.request
from http.server import BaseHTTPRequestHandler, HTTPServer

PORTA = 4455
GECKO = "http://127.0.0.1:4444"

CLICCABILI = "a, button, [role='button'], [role='link'], [role='menuitem'], [role='tab'], [role='option'], input[type='submit'], label, summary"


# --------------------------------------------------------------------------
# server: una sola sessione Selenium, comandi in JSON
# --------------------------------------------------------------------------
def _driver():
    from selenium import webdriver
    from selenium.webdriver.firefox.options import Options
    opts = Options()
    return webdriver.Remote(command_executor=GECKO, options=opts)


def _visibili(d, css):
    return [e for e in d.find_elements("css selector", css) if e.is_displayed()]


def _etichetta(e):
    t = (e.text or "").strip()
    if not t:
        t = e.get_attribute("aria-label") or e.get_attribute("title") or e.get_attribute("placeholder") or ""
    return " ".join(t.split())[:90]


def esegui(d, cmd, args):
    from selenium.webdriver.common.keys import Keys
    if cmd == "tabs":
        out = []
        att = d.current_window_handle
        for i, h in enumerate(d.window_handles):
            d.switch_to.window(h)
            out.append("%d%s %s | %s" % (i, "*" if h == att else " ", d.title[:70], d.current_url[:100]))
        d.switch_to.window(att)
        return "\n".join(out)
    if cmd == "use":
        d.switch_to.window(d.window_handles[int(args[0])])
        return "%s | %s" % (d.title, d.current_url)
    if cmd == "goto":
        d.get(args[0])
        time.sleep(2)
        return "%s | %s" % (d.title, d.current_url)
    if cmd == "url":
        return "%s | %s" % (d.title, d.current_url)
    if cmd == "text":
        n = int(args[0]) if args else 6000
        return d.find_element("css selector", "body").text[:n]
    if cmd == "find":
        q = " ".join(args).lower()
        righe = []
        for i, e in enumerate(_visibili(d, CLICCABILI)):
            et = _etichetta(e)
            if q in et.lower():
                righe.append("%d  <%s> %s" % (i, e.tag_name, et))
        return "\n".join(righe) or "(niente)"
    if cmd == "click":
        q = " ".join(args)
        if q.startswith("css="):
            e = _visibili(d, q[4:])[0]
        elif q.startswith("n="):
            e = _visibili(d, CLICCABILI)[int(q[2:])]
        else:
            cand = [e for e in _visibili(d, CLICCABILI) if q.lower() in _etichetta(e).lower()]
            if not cand:
                return "NON TROVATO: " + q
            # preferisce la corrispondenza esatta
            esatti = [e for e in cand if _etichetta(e).lower() == q.lower()]
            e = (esatti or cand)[0]
        d.execute_script("arguments[0].scrollIntoView({block:'center'})", e)
        time.sleep(0.3)
        try:
            e.click()
        except Exception:
            d.execute_script("arguments[0].click()", e)
        time.sleep(1.5)
        return "cliccato: " + _etichetta(e)
    if cmd == "type":
        css, testo = args[0], " ".join(args[1:])
        e = _visibili(d, css)[0]
        e.click()
        e.clear()
        e.send_keys(testo)
        return "scritto in " + css
    if cmd == "scrivi":
        # Nota dura: mai selectAll qui. Nel compositore di Facebook la
        # selezione ingloba anche la foto allegata e insertText la sostituisce
        # col testo, cosi' il post esce senza immagine. Il cursore va messo in
        # coda al contenuto e il testo aggiunto.
        # Inserisce testo su piu' righe in un campo contenteditable (i
        # compositori di Facebook e YouTube lo sono). Passa da un file per non
        # perdere gli a-capo sulla riga di comando, e usa insertText invece di
        # assegnare innerText: solo insertText genera gli eventi che React
        # ascolta, altrimenti il testo appare e il pulsante Pubblica resta
        # spento perche' il campo per lui e' ancora vuoto.
        css, percorso = args[0], args[1]
        # Indice facoltativo come terzo argomento: React lascia nel DOM il
        # vecchio campo editabile dopo aver aggiunto una foto, e scrivere nel
        # primo mette il testo in un nodo che non e' piu' quello a schermo.
        # "ultimo" prende l'ultimo, che di norma e' quello vivo.
        quale = args[2] if len(args) > 2 else "0"
        testo = Path(percorso).read_text(encoding="utf-8")
        campi = _visibili(d, css)
        if not campi:
            return "NESSUN CAMPO per " + css
        e = campi[-1] if quale == "ultimo" else campi[int(quale)]
        # Niente click di Selenium: nel compositore di Facebook il paragrafo
        # segnaposto copre il campo e il click viene intercettato. Il focus da
        # JS basta, e insertText scrive dove sta il cursore.
        d.execute_script("arguments[0].scrollIntoView({block:'center'})", e)
        time.sleep(0.4)
        d.execute_script(
            "const e=arguments[0], t=arguments[1];"
            "e.focus();"
            "const sel=window.getSelection(); sel.removeAllRanges();"
            "const r=document.createRange(); r.selectNodeContents(e);"
            "r.collapse(false); sel.addRange(r);"
            "document.execCommand('insertText', false, t);", e, testo)
        time.sleep(1.5)
        return "scritti %d caratteri nel campo %s di %s (ne ho visti %d)" % (
            len(testo), quale, css, len(campi))
    if cmd == "upload":
        # Carica un file in un input[type=file]. Non si puo' fare da JS: il
        # valore di un input file e' scrivibile solo dal browser, quindi passa
        # da send_keys di Selenium, che funziona anche se l'input e' nascosto
        # (Facebook e YouTube li tengono nascosti dietro un pulsante).
        percorso = args[-1]
        css = args[0] if len(args) > 1 else "input[type=file]"
        campi = d.find_elements("css selector", css)
        if not campi:
            return "NESSUN CAMPO FILE per " + css
        campo = campi[0]
        d.execute_script(
            "arguments[0].style.display='block';"
            "arguments[0].style.visibility='visible';"
            "arguments[0].style.opacity=1;"
            "arguments[0].style.height='1px';"
            "arguments[0].style.width='1px';", campo)
        # geckodriver in --connect-existing rifiuta l'endpoint di upload
        # remoto ("HTTP method not allowed"): va spento il rilevatore di file
        # locali, cosi' send_keys manda il percorso e basta, che e' quello che
        # serve dato che Firefox gira sulla stessa macchina.
        try:
            campo._upload = lambda percorso_locale: percorso_locale
        except Exception:
            pass
        campo.send_keys(percorso)
        time.sleep(3)
        return "caricato in %s: %s" % (css, percorso)
    if cmd == "key":
        k = {"ENTER": Keys.ENTER, "ESCAPE": Keys.ESCAPE, "TAB": Keys.TAB,
             "BACKSPACE": Keys.BACKSPACE, "DOWN": Keys.ARROW_DOWN,
             "UP": Keys.ARROW_UP}[args[0].upper()]
        d.switch_to.active_element.send_keys(k)
        time.sleep(1)
        return "premuto " + args[0]
    if cmd == "js":
        return json.dumps(d.execute_script("return (" + " ".join(args) + ")"), ensure_ascii=False)[:6000]
    if cmd == "shotel":
        # Schermata di un solo elemento: non dipende dallo scorrimento, che
        # su pagine con animazioni di comparsa non regge fra un comando e
        # l'altro. Selenium lo porta in vista da solo e ritaglia.
        css, f = args[0], args[1]
        e = _visibili(d, css)[0]
        d.execute_script("arguments[0].scrollIntoView({block:'start'})", e)
        time.sleep(1.2)
        e.screenshot(f)
        return "salvato " + f
    if cmd == "shot":
        f = args[0] if args else "ff-shot.png"
        d.save_screenshot(f)
        return "salvato " + f
    return "comando sconosciuto: " + cmd


class Handler(BaseHTTPRequestHandler):
    driver = None

    def log_message(self, *a):
        pass

    def do_POST(self):
        n = int(self.headers.get("Content-Length", 0))
        req = json.loads(self.rfile.read(n).decode("utf-8"))
        try:
            if req["cmd"] == "stop":
                out = "server chiuso"
                self._rispondi(out)
                raise SystemExit
            out = esegui(Handler.driver, req["cmd"], req.get("args", []))
        except SystemExit:
            raise
        except Exception as e:
            out = "ERRORE %s: %s" % (type(e).__name__, str(e)[:400])
        self._rispondi(out)

    def _rispondi(self, out):
        b = json.dumps({"out": out}, ensure_ascii=False).encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(b)))
        self.end_headers()
        self.wfile.write(b)


def serve():
    Handler.driver = _driver()
    print("sessione aperta:", Handler.driver.session_id, "| schede:", len(Handler.driver.window_handles), flush=True)
    srv = HTTPServer(("127.0.0.1", PORTA), Handler)
    try:
        srv.serve_forever()
    except SystemExit:
        pass


# --------------------------------------------------------------------------
# client
# --------------------------------------------------------------------------
def client(cmd, args):
    dati = json.dumps({"cmd": cmd, "args": args}).encode("utf-8")
    req = urllib.request.Request("http://127.0.0.1:%d/" % PORTA, data=dati, headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            print(json.loads(r.read().decode("utf-8"))["out"])
    except urllib.error.URLError:
        print("server non attivo: avvialo con  py ff.py serve")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(__doc__)
    elif sys.argv[1] == "serve":
        serve()
    else:
        client(sys.argv[1], sys.argv[2:])
