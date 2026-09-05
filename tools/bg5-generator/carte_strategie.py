# -*- coding: utf-8 -*-
"""Immagini della guida alle Strategie: una configurazione possibile del
bodygraph per ciascuna Tipologia.

Come per le Autorita', la Tipologia dipende dalla carta intera e non da un
singolo centro, quindi si mostra il bodygraph completo.

    py tools/bg5-generator/carte_strategie.py
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from carte_autorita import carta

RADICE = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    "grav-site", "user", "pages", "human-design", "03.strategia",
)

CARTE = [
    (".",                       "le-strategie-human-design", ["SACRAL", "THROAT"]),
    ("01.rispondere",           "strategia-rispondere",      ["SACRAL"]),
    ("02.rispondere-informare", "strategia-rispondere-informare", ["SACRAL", "THROAT"]),
    ("03.aspettare-invito",     "strategia-aspettare-invito", ["AJNA", "THROAT"]),
    ("04.informare",            "strategia-informare",       ["HEART", "THROAT"]),
    ("05.ciclo-lunare",         "strategia-ciclo-lunare",    []),
]


def main():
    for cartella, nome, centri in CARTE:
        d = os.path.join(RADICE, cartella)
        os.makedirs(d, exist_ok=True)
        f = os.path.join(d, nome + ".png")
        carta(centri).save(f, format="PNG", optimize=True)
        print("%-24s %-36s %s  %s kB"
              % (cartella, nome + ".png", ", ".join(centri) or "nessun centro",
                 os.path.getsize(f) // 1024))


if __name__ == "__main__":
    main()
