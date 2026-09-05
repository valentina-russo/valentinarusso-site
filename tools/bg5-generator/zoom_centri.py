# -*- coding: utf-8 -*-
"""Genera le immagini della guida ai 9 Centri: uno zoom del bodygraph vero
sul centro di cui parla la pagina, piu' la carta intera per la pagina indice.

    py tools/bg5-generator/zoom_centri.py
"""
import io
import os
import sys

from PIL import Image

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import bodygraph_svg as bg

RADICE_PAGINE = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    "grav-site", "user", "pages", "human-design", "01.centri",
)

# Porte di ogni centro, come sono disegnate nel chart.svg
PORTE = {
    "HEAD":   [64, 61, 63],
    "AJNA":   [47, 24, 4, 11, 43, 17],
    "THROAT": [62, 23, 56, 35, 12, 45, 33, 8, 31, 20, 16],
    "G":      [1, 2, 7, 13, 10, 15, 25, 46],
    "HEART":  [21, 26, 40, 51],
    "SPLEEN": [48, 57, 44, 50, 32, 28, 18],
    "SACRAL": [34, 5, 14, 29, 59, 9, 3, 42, 27],
    "SOLAR":  [6, 22, 30, 36, 37, 49, 55],
    "ROOT":   [58, 38, 54, 53, 60, 52, 19, 39, 41],
}

# cartella della pagina -> (codice centro, slug del file)
PAGINE = [
    ("01.sacrale",       "SACRAL", "sacrale"),
    ("02.gola",          "THROAT", "gola"),
    ("03.plesso-solare", "SOLAR",  "plesso-solare"),
    ("04.milza",         "SPLEEN", "milza"),
    ("05.radice",        "ROOT",   "radice"),
    ("06.cuore",         "HEART",  "cuore"),
    ("07.g",             "G",      "g"),
    ("08.ajna",          "AJNA",   "ajna"),
    ("09.testa",         "HEAD",   "testa"),
]

LARGHEZZA_RENDER = 2400   # render pieno, poi si ritaglia
LATO_MAX = 1100           # lato lungo dell'immagine finale
PADDING = 0.34            # aria attorno al centro


def trasparente(img):
    """Le immagini vanno sulla pagina senza scheda: lo sfondo resta vuoto."""
    return img.convert("RGBA")


def ridimensiona(img, lato_max=LATO_MAX):
    w, h = img.size
    if max(w, h) <= lato_max:
        return img
    k = lato_max / max(w, h)
    return img.resize((round(w * k), round(h * k)), Image.LANCZOS)


def zoom_centro(codice):
    """Ritaglio quadrato con il centro sempre in mezzo.

    Non uso crop_center_png perche' clampa il riquadro ai bordi della tela:
    Milza e Plesso Solare stanno sul bordo del bodygraph e finirebbero
    schiacciati contro il lato dell'immagine. Qui il quadrato puo' uscire
    dalla tela e lo spazio in piu' resta bianco.
    """
    porte = PORTE[codice]
    png = bg.render_bodygraph_png(
        {"p_gates": set(porte), "d_gates": set(), "defined_centers": {codice}},
        width=LARGHEZZA_RENDER,
    )
    img = trasparente(Image.open(io.BytesIO(png)))
    larg, alt = img.size
    sx = larg / bg.SVG_VIEWBOX_W
    sy = alt / bg.SVG_VIEWBOX_H

    x0, y0, x1, y1 = bg.CENTER_BBOX_SVG[codice]
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    lato = max(x1 - x0, y1 - y0) * (1 + 2 * PADDING) / 2

    riquadro = (
        round((cx - lato) * sx), round((cy - lato) * sy),
        round((cx + lato) * sx), round((cy + lato) * sy),
    )
    tela = Image.new("RGBA", (riquadro[2] - riquadro[0], riquadro[3] - riquadro[1]), (255, 255, 255, 0))
    tela.paste(img, (-riquadro[0], -riquadro[1]))
    return ridimensiona(tela)


def carta_intera():
    tutte = set(range(1, 65))
    png = bg.render_bodygraph_png(
        {"p_gates": tutte, "d_gates": set(), "defined_centers": set(PORTE)},
        width=1200,
    )
    return ridimensiona(trasparente(Image.open(io.BytesIO(png))), 1400)


def salva(img, percorso):
    img.save(percorso, format="PNG", optimize=True)
    return os.path.getsize(percorso) // 1024


def main():
    for cartella, codice, slug in PAGINE:
        d = os.path.join(RADICE_PAGINE, cartella)
        nuovo = os.path.join(d, "centro-%s-human-design.png" % slug)
        peso = salva(zoom_centro(codice), nuovo)
        print("%-18s %-7s %s  %s kB" % (cartella, codice, os.path.basename(nuovo), peso))

    hub = os.path.join(RADICE_PAGINE, "i-9-centri-human-design.png")
    print("indice             TUTTI   %s  %s kB" % (os.path.basename(hub), salva(carta_intera(), hub)))


if __name__ == "__main__":
    main()
