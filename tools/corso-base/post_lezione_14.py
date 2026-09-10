# -*- coding: utf-8 -*-
"""Le grafiche per annunciare la lezione gratuita del 14 settembre 2026.

Due slide quadrate per il post community su YouTube, nella veste navy e ottone
gia' approvata, piu' un post per Facebook nella veste della Storia (fondo a
fantasia coi colori del logo, scheda crema).

La prima slide annuncia, la seconda spiega: la critica a un annuncio secco e'
che non fa capire niente di Human Design a chi non lo conosce, e quindi non da'
un motivo per esserci.

    py tools/corso-base/post_lezione_14.py
"""
import sys
from pathlib import Path

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
sys.path.insert(0, str(Path(__file__).resolve().parent))

from PIL import Image, ImageDraw, ImageFilter

from grafiche_corso import (
    CARTA, MUTO, NAVY, NEAR_W, OTTONE, OTTONE_SCURO,
    OUT_BOLD, OUT_MED, OUT_REG, OUT_SEMI, PF_BOLD, PF_BOLD_IT,
    a_capo, blocco, centrato, f, filetto, occhiello, pillola, salva,
)

L = 1080
SITO = "valentinarussobg5.com/lezione-gratuita-human-design"
FONDO = (r"D:/Download/Firefly_Gemini Flash_Abstract composition in a vertical "
         r"portrait frame of long overlapping wave bands cros 128581.png")


def cornice_navy():
    im = Image.new("RGB", (L, L), NAVY)
    d = ImageDraw.Draw(im)
    d.rectangle([60, 60, L - 60, L - 60], outline=OTTONE, width=2)
    return im, d


def slide_annuncio():
    """Slide 1: l'annuncio. E' l'immagine gia' approvata l'8 settembre."""
    im, d = cornice_navy()

    righe = ["Corso Base", "Human Design"]
    for dim in range(132, 70, -2):
        f_tit = f(PF_BOLD, dim)
        if max(d.textlength(r, font=f_tit) for r in righe) <= L - 220:
            break
    ih = int(dim * 1.10)

    y = 196
    for r in righe:
        centrato(d, r, f_tit, y, CARTA, L / 2)
        y += ih
    y += 46
    filetto(d, L / 2, y, larg=120)
    y += 42
    centrato(d, "1ª lezione gratuita", f(PF_BOLD_IT, 66), y, OTTONE, L / 2)
    y += 116
    y = pillola(d, "lunedì 14 settembre · ore 20:30", f(OUT_BOLD, 34), L / 2, y, OTTONE, NAVY) + 44

    fs = f(OUT_REG, 30)
    testo = "Su Zoom. Presento il programma delle venti lezioni e rispondo a tutte le domande."
    blocco(d, a_capo(d, testo, fs, L - 280), fs, 0, y, NEAR_W, 1.36, cx=L / 2)

    centrato(d, "Iscriviti dal link qui sotto", f(OUT_SEMI, 28), L - 196, OTTONE, L / 2)
    centrato(d, SITO, f(OUT_MED, 26), L - 150, MUTO, L / 2)
    salva(im, "lezione14-yt-1.jpg")


def slide_spiegazione():
    """Slide 2: cos'e' lo Human Design e cosa insegni il corso."""
    im, d = cornice_navy()

    # Senza occhiello: la slide 1 non ce l'ha, e un titolo che dipende
    # dall'occhiello per avere senso non e' un titolo.
    righe = ["Cos'è lo", "Human Design"]
    for dim in range(112, 70, -2):
        f_tit = f(PF_BOLD, dim)
        if max(d.textlength(r, font=f_tit) for r in righe) <= L - 240:
            break
    ih = int(dim * 1.10)

    y = 232
    for r in righe:
        centrato(d, r, f_tit, y, CARTA, L / 2)
        y += ih
    y += 40
    filetto(d, L / 2, y, larg=120)
    y += 56

    dim_c = 30
    fs = f(OUT_REG, dim_c)
    paragrafi = [
        "Lo Human Design descrive come sei fatto: dove prendi le decisioni, "
        "come assorbi l'energia di chi ti sta intorno, cosa ti stanca e cosa "
        "ti rimette in moto. Si calcola da data, ora e luogo di nascita.",
        "Il corso insegna a leggerlo: venti lezioni dal vivo, due ore ciascuna, "
        "dal 12 ottobre ogni lunedì alle 18.",
    ]
    for testo_p in paragrafi:
        righe_p = a_capo(d, testo_p, fs, L - 260)
        blocco(d, righe_p, fs, 0, y, NEAR_W, 1.42, cx=L / 2)
        y += int(len(righe_p) * dim_c * 1.42) + 30

    # La chiusura sotto il testo, non a un'altezza fissa: con due paragrafi
    # lunghi una posizione fissa gli finisce addosso.
    y_chiusura = max(y + 34, L - 208)
    centrato(d, "Lunedì 14 la prima lezione è gratuita", f(OUT_SEMI, 30), y_chiusura, OTTONE, L / 2)
    centrato(d, SITO, f(OUT_MED, 26), y_chiusura + 52, MUTO, L / 2)
    salva(im, "lezione14-yt-2.jpg")


def post_facebook():
    """Facebook: la veste della Storia, quadrata."""
    sf = Image.open(FONDO).convert("RGB")
    sc = max(L / sf.width, L / sf.height)
    sf = sf.resize((round(sf.width * sc), round(sf.height * sc)), Image.LANCZOS)
    x = (sf.width - L) // 2
    y0 = (sf.height - L) // 2
    im = sf.crop((x, y0, x + L, y0 + L))

    m = 54
    ombra = Image.new("RGBA", (L, L), (0, 0, 0, 0))
    ImageDraw.Draw(ombra).rectangle([m + 4, m + 12, L - m + 4, L - m + 14], fill=(60, 48, 40, 105))
    im = Image.alpha_composite(im.convert("RGBA"),
                               ombra.filter(ImageFilter.GaussianBlur(22))).convert("RGB")
    d = ImageDraw.Draw(im)
    d.rectangle([m, m, L - m, L - m], fill="#FCFAF7")
    d.rectangle([m, m, L - m, L - m], outline=OTTONE_SCURO, width=3)
    d.rectangle([m + 13, m + 13, L - m - 13, L - m - 13], outline="#E3DCD2", width=2)

    occhiello(d, "LEZIONE 0 · GRATUITA", L / 2, m + 54, colore=OTTONE_SCURO)

    righe = ["Corso Base di", "Human Design"]
    for dim in range(96, 56, -2):
        f_tit = f(PF_BOLD, dim)
        if max(d.textlength(r, font=f_tit) for r in righe) <= L - 2 * m - 110:
            break
    ih = int(dim * 1.16)

    y = m + 150
    for r in righe:
        centrato(d, r, f_tit, y, "#3A3532", L / 2)
        y += ih
    y += 26
    filetto(d, L / 2, y, larg=110, colore=OTTONE_SCURO)
    y += 62

    centrato(d, "lunedì 14 settembre, ore 20:30", f(OUT_BOLD, 40), y, "#5E3A47", L / 2)
    y += 92

    fs = f(OUT_MED, 32)
    testo = ("Un'ora in diretta su Zoom per vedere come si legge un Bodygraph e cosa "
             "contengono le venti lezioni del corso. C'è spazio per le domande. "
             "Poi il corso parte il 12 ottobre, ogni lunedì alle 18.")
    blocco(d, a_capo(d, testo, fs, L - 2 * m - 130), fs, 0, y, "#6B625D", 1.44, cx=L / 2)

    centrato(d, SITO, f(OUT_SEMI, 27), L - m - 96, OTTONE_SCURO, L / 2)
    salva(im, "lezione14-facebook.jpg")


if __name__ == "__main__":
    slide_annuncio()
    slide_spiegazione()
    post_facebook()
