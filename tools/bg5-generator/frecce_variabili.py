# -*- coding: utf-8 -*-
"""Immagini della guida alle Variabili: le quattro frecce sopra la testa.

Nel bodygraph stanno a coppie: a sinistra il Design (rosso), a destra la
Personalita' (nero). In alto Determinazione e Prospettiva, in basso Ambiente
e Motivazione.

Ogni freccia punta a destra oppure a sinistra, e la direzione cambia da
persona a persona: per questo ogni posizione ne mostra due, una per verso.
Quella di cui parla la pagina e' colorata, le altre restano grigie.

    py tools/bg5-generator/frecce_variabili.py
"""
import os

from PIL import Image, ImageDraw, ImageFont

RADICE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
BASE = os.path.join(RADICE, "grav-site", "user", "pages", "human-design", "07.variabili")

SPENTO = (206, 200, 194, 255)
PERSONALITA = (43, 45, 66, 255)
DESIGN = (198, 60, 46, 255)
INK = (58, 53, 50, 255)
MUTO = (145, 138, 132, 255)

L, A = 900, 700
LARG, ALT, SPAZIO_FRECCE, GAP_X, GAP_Y = 300, 62, 16, 84, 118


def _font(dim, grassetto=True):
    nomi = ("seguisb.ttf", "segoeuib.ttf", "arialbd.ttf") if grassetto else ("segoeui.ttf", "arial.ttf")
    for n in nomi:
        try:
            return ImageFont.truetype(n, dim)
        except OSError:
            continue
    return ImageFont.load_default()


def freccia(d, x, y, larg, alt, verso, colore):
    """Pentagono con la punta, come nel bodygraph. verso: 'destra' o 'sinistra'."""
    p = int(alt * 0.62)
    if verso == "destra":
        punti = [(x, y), (x + larg - p, y), (x + larg, y + alt / 2),
                 (x + larg - p, y + alt), (x, y + alt)]
    else:
        punti = [(x + larg, y), (x + p, y), (x, y + alt / 2),
                 (x + p, y + alt), (x + larg, y + alt)]
    d.polygon(punti, fill=colore)


# posizione: (colonna, riga, etichetta, colore acceso)
SLOT = {
    "determinazione": (0, 0, "Determinazione", DESIGN),
    "ambiente":       (0, 1, "Ambiente", DESIGN),
    "prospettiva":    (1, 0, "Prospettiva", PERSONALITA),
    "motivazione":    (1, 1, "Motivazione", PERSONALITA),
}


def frecce(accesa=None):
    """accesa: il nome di una Variabile, oppure un insieme di nomi."""
    accese = set() if accesa is None else ({accesa} if isinstance(accesa, str) else set(accesa))
    img = Image.new("RGBA", (L, A), (255, 255, 255, 0))
    d = ImageDraw.Draw(img)
    f_et = _font(26)
    f_col = _font(21)
    f_verso = _font(15, grassetto=False)

    alt_slot = 2 * ALT + SPAZIO_FRECCE
    tot_l = 2 * LARG + GAP_X
    tot_a = 2 * alt_slot + GAP_Y
    x0 = (L - tot_l) // 2
    y0 = (A - tot_a) // 2 + 22

    d.text((x0 + LARG // 2, y0 - 46), "DESIGN", font=f_col, anchor="mm", fill=MUTO)
    d.text((x0 + LARG + GAP_X + LARG // 2, y0 - 46), "PERSONALITÀ", font=f_col, anchor="mm", fill=MUTO)

    for nome, (col, riga, etichetta, colore) in SLOT.items():
        x = x0 + col * (LARG + GAP_X)
        y = y0 + riga * (alt_slot + GAP_Y)
        acceso = nome in accese
        c = colore if acceso else SPENTO

        freccia(d, x, y, LARG, ALT, "sinistra", c)
        freccia(d, x, y + ALT + SPAZIO_FRECCE, LARG, ALT, "destra", c)

        if acceso:
            d.text((x + LARG - 20, y + ALT / 2), "sinistra", font=f_verso, anchor="rm", fill=(255, 255, 255, 235))
            d.text((x + 20, y + ALT + SPAZIO_FRECCE + ALT / 2), "destra", font=f_verso, anchor="lm", fill=(255, 255, 255, 235))

        d.text((x + LARG // 2, y + alt_slot + 32), etichetta, font=f_et, anchor="mm",
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
