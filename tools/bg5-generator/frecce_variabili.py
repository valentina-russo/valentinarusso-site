# -*- coding: utf-8 -*-
"""Immagini della guida alle Variabili: le quattro frecce sopra la testa.

Nel bodygraph stanno a coppie: a sinistra il Design (rosso), a destra la
Personalita' (nero). In alto Determinazione e Prospettiva, in basso Ambiente
e Motivazione. La direzione (destra o sinistra) cambia da persona a persona,
quindi qui si mostrano solo le posizioni, con accesa quella della pagina.

    py tools/bg5-generator/frecce_variabili.py
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFont

RADICE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
BASE = os.path.join(RADICE, "grav-site", "user", "pages", "human-design", "07.variabili")

SPENTO = (200, 194, 188, 255)
PERSONALITA = (26, 26, 46, 255)
DESIGN = (192, 57, 43, 255)
INK = (58, 53, 50, 255)
MUTO = (150, 143, 137, 255)

L, A = 900, 760
LARG, ALT, GAP_X, GAP_Y = 300, 118, 70, 96


def _font(dim, grassetto=True):
    for nome in (("seguisb.ttf", "segoeuib.ttf", "arialbd.ttf") if grassetto
                 else ("segoeui.ttf", "arial.ttf")):
        try:
            return ImageFont.truetype(nome, dim)
        except OSError:
            continue
    return ImageFont.load_default()


# posizione: (colonna, riga, etichetta, colore acceso)
SLOT = {
    "determinazione": (0, 0, "Determinazione", DESIGN),
    "ambiente":       (0, 1, "Ambiente", DESIGN),
    "prospettiva":    (1, 0, "Prospettiva", PERSONALITA),
    "motivazione":    (1, 1, "Motivazione", PERSONALITA),
}


def frecce(accesa=None):
    img = Image.new("RGBA", (L, A), (255, 255, 255, 0))
    d = ImageDraw.Draw(img)
    f_et = _font(27)
    f_col = _font(23)

    tot_l = 2 * LARG + GAP_X
    tot_a = 2 * ALT + GAP_Y
    x0 = (L - tot_l) // 2
    y0 = (A - tot_a) // 2 + 26

    d.text((x0 + LARG // 2, y0 - 52), "DESIGN", font=f_col, anchor="mm", fill=MUTO)
    d.text((x0 + LARG + GAP_X + LARG // 2, y0 - 52), "PERSONALITÀ", font=f_col, anchor="mm", fill=MUTO)

    for nome, (col, riga, etichetta, colore) in SLOT.items():
        x = x0 + col * (LARG + GAP_X)
        y = y0 + riga * (ALT + GAP_Y)
        acceso = nome == accesa
        d.rounded_rectangle([x, y, x + LARG, y + ALT], radius=18,
                            fill=colore if acceso else SPENTO)
        d.text((x + LARG // 2, y + ALT + 30), etichetta, font=f_et, anchor="mm",
               fill=INK if acceso else MUTO)
    return img


CARTE = [
    (".", "le-variabili-human-design", None),
    ("01.determinazione", "variabile-determinazione", "determinazione"),
    ("02.ambiente", "variabile-ambiente", "ambiente"),
    ("03.prospettiva", "variabile-prospettiva", "prospettiva"),
    ("04.motivazione", "variabile-motivazione", "motivazione"),
]


def main():
    for cartella, nome, accesa in CARTE:
        d = os.path.join(BASE, cartella)
        os.makedirs(d, exist_ok=True)
        f = os.path.join(d, nome + ".png")
        frecce(accesa).save(f, format="PNG", optimize=True)
        print("%-20s %-34s accesa: %-16s %s kB"
              % (cartella, nome + ".png", accesa or "nessuna", os.path.getsize(f) // 1024))


if __name__ == "__main__":
    main()
