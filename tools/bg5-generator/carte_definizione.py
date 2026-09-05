# -*- coding: utf-8 -*-
"""Immagini della guida alla Definizione: quanti gruppi separati formano i
centri colorati.

Qui non basta accendere dei centri: serve che si vedano i canali completi che
li tengono insieme, e i vuoti che li separano. Percio' si passano coppie di
porte, una per canale, invece di interi centri.

    py tools/bg5-generator/carte_definizione.py
"""
import io
import os
import sys

from PIL import Image

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import bodygraph_svg as bg
from zoom_centri import trasparente, ridimensiona

RADICE = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    "grav-site", "user", "pages", "human-design", "04.definizione",
)

# canale: (porta, porta, centro, centro)
CANALI = {
    "34-20": (34, 20, "SACRAL", "THROAT"),
    "17-62": (17, 62, "AJNA", "THROAT"),
    "64-47": (64, 47, "HEAD", "AJNA"),
    "26-44": (26, 44, "HEART", "SPLEEN"),
    "19-49": (19, 49, "ROOT", "SOLAR"),
}

CARTE = [
    (".",            "la-definizione-human-design", ["34-20", "64-47"]),
    ("01.singola",   "definizione-singola",         ["34-20", "17-62"]),
    ("02.doppia",    "definizione-doppia",          ["34-20", "64-47"]),
    ("03.tripla",    "definizione-tripla",          ["34-20", "64-47", "26-44"]),
    ("04.quadrupla", "definizione-quadrupla",       ["34-20", "64-47", "26-44", "19-49"]),
    ("05.nessuna",   "definizione-nessuna",         []),
]


def carta(canali):
    porte, centri = set(), set()
    for c in canali:
        a, b, c1, c2 = CANALI[c]
        porte |= {a, b}
        centri |= {c1, c2}
    png = bg.render_bodygraph_png(
        {"p_gates": porte, "d_gates": set(), "defined_centers": centri},
        width=1100,
    )
    return ridimensiona(trasparente(Image.open(io.BytesIO(png))), 1300)


def main():
    for cartella, nome, canali in CARTE:
        d = os.path.join(RADICE, cartella)
        os.makedirs(d, exist_ok=True)
        f = os.path.join(d, nome + ".png")
        carta(canali).save(f, format="PNG", optimize=True)
        print("%-16s %-32s canali: %-28s %s kB"
              % (cartella, nome + ".png", ", ".join(canali) or "nessuno",
                 os.path.getsize(f) // 1024))


if __name__ == "__main__":
    main()
