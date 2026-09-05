# -*- coding: utf-8 -*-
"""Immagini della guida alle Linee e ai Profili: l'esagramma a sei righe.

Il Profilo non dipende dai centri ma dalla riga occupata dal Sole, quindi qui
il bodygraph non spiegherebbe niente. Si disegna l'esagramma: sei righe
numerate dal basso, con evidenziate quelle di cui parla la pagina.

Nero = personalita' (la parte conscia), rosso = design (quella inconscia),
gli stessi colori del bodygraph.

    py tools/bg5-generator/esagramma.py
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFont

RADICE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

SPENTO = (200, 194, 188, 255)
PERSONALITA = (26, 26, 46, 255)
DESIGN = (192, 57, 43, 255)

L, A = 900, 900
MARGINE_X, RIGA_H, SPAZIO = 150, 74, 46
NUMERO_X = 62


def _font(dim):
    for nome in ("seguisb.ttf", "segoeuib.ttf", "arialbd.ttf"):
        try:
            return ImageFont.truetype(nome, dim)
        except OSError:
            continue
    return ImageFont.load_default()


def esagramma(accese):
    """accese: {numero riga (1 in basso): colore}"""
    img = Image.new("RGBA", (L, A), (255, 255, 255, 0))
    d = ImageDraw.Draw(img)
    f = _font(38)
    alt_tot = 6 * RIGA_H + 5 * SPAZIO
    y0 = (A - alt_tot) // 2
    for n in range(6, 0, -1):
        i = 6 - n                      # 0 in alto
        y = y0 + i * (RIGA_H + SPAZIO)
        colore = accese.get(n, SPENTO)
        d.rounded_rectangle([MARGINE_X, y, L - MARGINE_X, y + RIGA_H],
                            radius=RIGA_H // 2, fill=colore)
        acceso = n in accese
        d.text((NUMERO_X, y + RIGA_H // 2), str(n), font=f, anchor="mm",
               fill=(58, 53, 50, 255) if acceso else (150, 143, 137, 255))
    return img


CARTE = [
    ("05.linee", ".",           "le-6-linee-human-design",  {}),
    ("05.linee", "01.linea-1",  "linea-1-investigatore",    {1: PERSONALITA}),
    ("05.linee", "02.linea-2",  "linea-2-eremita",          {2: PERSONALITA}),
    ("05.linee", "03.linea-3",  "linea-3-martire",          {3: PERSONALITA}),
    ("05.linee", "04.linea-4",  "linea-4-opportunista",     {4: PERSONALITA}),
    ("05.linee", "05.linea-5",  "linea-5-eretico",          {5: PERSONALITA}),
    ("05.linee", "06.linea-6",  "linea-6-modello-di-ruolo", {6: PERSONALITA}),
]


def main():
    for sezione, cartella, nome, accese in CARTE:
        d = os.path.join(RADICE, "grav-site", "user", "pages", "human-design", sezione, cartella)
        os.makedirs(d, exist_ok=True)
        f = os.path.join(d, nome + ".png")
        esagramma(accese).save(f, format="PNG", optimize=True)
        print("%-14s %-32s righe accese: %-10s %s kB"
              % (cartella, nome + ".png", ", ".join(map(str, accese)) or "nessuna",
                 os.path.getsize(f) // 1024))


if __name__ == "__main__":
    main()
