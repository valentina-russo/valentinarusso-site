#!/usr/bin/env python3
"""
Renderizza deck.html in PNG (una per slide) e in un PDF unico 1920x1080.

    py tools/deck-corso/build.py                 # PNG + PDF in tools/deck-corso/out/
    py tools/deck-corso/build.py --solo-png

Ogni <section class="slide"> diventa una pagina. Il nome del file viene da
data-nome, cosi' l'ordine su disco e' l'ordine del mazzo.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

QUI = Path(__file__).resolve().parent
SORGENTE = QUI / "deck.html"
USCITA = QUI / "out"

W, H = 1920, 1080


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--solo-png", action="store_true", help="salta il PDF")
    ap.add_argument("--solo-pdf", action="store_true", help="salta le PNG")
    a = ap.parse_args()

    if not SORGENTE.exists():
        print("manca", SORGENTE)
        return 1
    USCITA.mkdir(exist_ok=True)

    with sync_playwright() as p:
        br = p.chromium.launch()
        pg = br.new_page(viewport={"width": W, "height": H}, device_scale_factor=1)
        pg.goto(SORGENTE.as_uri())
        # I font sono file locali: senza questa attesa la prima slide esce in fallback.
        pg.wait_for_function("document.fonts.ready")
        pg.wait_for_timeout(400)

        if not a.solo_pdf:
            slides = pg.query_selector_all(".slide")
            for i, s in enumerate(slides, 1):
                nome = s.get_attribute("data-nome") or f"slide-{i:02d}"
                f = USCITA / f"{nome}.png"
                s.screenshot(path=str(f))
                print(f.name)

        if not a.solo_png:
            # In stampa le slide non devono avere lo stacco dell'anteprima.
            pg.add_style_tag(content=".slide + .slide{margin-top:0}")
            f = USCITA / "corso-base-hd.pdf"
            pg.pdf(path=str(f), width=f"{W}px", height=f"{H}px",
                   print_background=True, margin={"top": "0", "bottom": "0",
                                                  "left": "0", "right": "0"})
            print(f.name)

        br.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
