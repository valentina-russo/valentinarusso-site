# -*- coding: utf-8 -*-
"""Immagini della guida alle 7 Autorita': il bodygraph intero con la
configurazione di centri che produce quell'Autorita'.

Un'Autorita' non e' una proprieta' di un centro, e' il risultato di quali
centri sono colorati e quali no: per questo qui si mostra la carta intera
e non lo zoom, che invece serve alle pagine dei Centri.

    py tools/bg5-generator/carte_autorita.py
"""
import io
import os
import sys

from PIL import Image

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import bodygraph_svg as bg
from zoom_centri import PORTE, trasparente, ridimensiona

RADICE = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    "grav-site", "user", "pages", "human-design", "02.autorita",
)

# cartella, nome del file, centri colorati
CARTE = [
    (".",                  "le-7-autorita-human-design", ["SOLAR", "SACRAL", "SPLEEN"]),
    ("01.emotiva",         "autorita-emotiva",           ["SOLAR"]),
    ("02.sacrale",         "autorita-sacrale",           ["SACRAL"]),
    ("03.splenica",        "autorita-splenica",          ["SPLEEN"]),
    ("04.ego",             "autorita-ego",               ["HEART"]),
    ("05.autoproiettata",  "autorita-autoproiettata",    ["G", "THROAT"]),
    ("06.mentale",         "autorita-mentale",           ["HEAD", "AJNA"]),
    ("07.lunare",          "autorita-lunare",            []),
]


def carta(centri):
    porte = set()
    for c in centri:
        porte |= set(PORTE[c])
    png = bg.render_bodygraph_png(
        {"p_gates": porte, "d_gates": set(), "defined_centers": set(centri)},
        width=1100,
    )
    return ridimensiona(trasparente(Image.open(io.BytesIO(png))), 1300)


def main():
    for cartella, nome, centri in CARTE:
        d = os.path.join(RADICE, cartella)
        os.makedirs(d, exist_ok=True)
        f = os.path.join(d, nome + ".png")
        carta(centri).save(f, format="PNG", optimize=True)
        print("%-20s %-32s centri colorati: %s  %s kB"
              % (cartella, nome + ".png", ", ".join(centri) or "nessuno",
                 os.path.getsize(f) // 1024))


if __name__ == "__main__":
    main()
