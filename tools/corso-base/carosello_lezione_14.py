# -*- coding: utf-8 -*-
"""
Carosello quadrato che introduce il Corso Base di Human Design e porta alla
lezione zero gratuita del 14 settembre 2026.

Stessa veste della Storia approvata il 09/09: fondo a fantasia con i colori del
logo, scheda crema con doppia cornice, Playfair per i titoli e Outfit per il
testo. Sette schede, 1080x1080, buone sia per il feed Instagram sia per un
Community Post su YouTube.

    py carosello_lezione_14.py
"""
from pathlib import Path
import sys

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
from PIL import Image, ImageDraw, ImageFont, ImageFilter

F = Path(r"D:/valentinarussomentaladvisor.it/tools/bg5-generator/fonts")
PF = str(F / "PlayfairDisplay-Bold.ttf")
PR = str(F / "PlayfairDisplay-Regular.ttf")
OB = str(F / "Outfit-Bold.ttf")
OM = str(F / "Outfit-Medium.ttf")

SCHEDA = "#FCFAF7"; INK = "#3A3532"; MUTO = "#6B625D"
LINEA = "#E3DCD2"; VINO_S = "#5E3A47"; OTTONE_S = "#8A5A12"

FONDO = (r"D:/Download/Firefly_Gemini Flash_Abstract composition in a vertical "
         r"portrait frame of long overlapping wave bands cros 128581.png")
USCITA = Path(r"D:/Download/post-youtube-da-pubblicare/carosello-lezione-14")

L = 46                       # margine della scheda
W = H = 1080
R = B = W - L
T = L

# kicker, titolo, corpo, coda (la coda va solo sull'ultima)
SCHEDE = [
    ("LEZIONE 0 \u00b7 GRATUITA",
     "Corso Base di\nHuman Design",
     "La prima lezione è gratuita",
     "lunedì 14 settembre, ore 20:30"),

    ("DA DOVE SI PARTE",
     "Cos'\u00e8 lo\nHuman Design",
     "Un sistema che descrive come sei fatto: dove prendi le decisioni, "
     "come assorbi l'energia di chi ti sta intorno, cosa ti stanca e cosa ti "
     "rimette in moto. Si calcola da data, ora e luogo di nascita.",
     None),

    ("LO STRUMENTO",
     "Il Bodygraph",
     "\u00c8 il disegno che esce dal calcolo. Nove centri, trentadue canali, "
     "sessantaquattro porte. Imparare a leggerlo vuol dire smettere di "
     "indovinare e cominciare a riconoscere quello che hai davanti.",
     None),

    ("SEMESTRE 1 \u00b7 10 LEZIONI",
     "Tipologia,\nStrategia e Autorit\u00e0",
     "Le basi del Bodygraph, i nove centri, le cinque Tipologie una per una. "
     "Poi come elabori le informazioni e i temi del Non-S\u00e9, i punti in cui "
     "ti allontani da te senza accorgertene.",
     None),

    ("SEMESTRE 2 \u00b7 10 LEZIONI",
     "Canali, Porte,\nProfilo e Linee",
     "I cinque circuiti che attraversano il disegno, dall'integrazione al "
     "tribale. Poi le sei linee e i dodici Profili, con la sintesi di due "
     "Bodygraph completi.",
     None),

    ("COME FUNZIONA",
     "Venti lezioni\ndal vivo",
     "Due ore ciascuna, su Zoom, ogni luned\u00ec alle 18 a partire dal 12 "
     "ottobre. Le lezioni sono registrate e disponibili su richiesta, cos\u00ec "
     "una serata in cui non ci sei non ti fa perdere il filo.",
     None),

    ("LEZIONE 0 \u00b7 GRATUITA",
     "Luned\u00ec 14 settembre\nore 20:30",
     "Un'ora su Zoom. Presento il programma delle venti lezioni e rispondo "
     "alle domande. Nessun impegno: se il corso non \u00e8 per te, finisce l\u00ec.",
     "valentinarussobg5.com/lezione-gratuita-human-design"),
]


def spazia(d, x, y, t, f, fill, s):
    for c in t:
        d.text((x, y), c, font=f, fill=fill)
        x += d.textlength(c, font=f) + s


def larghezza_spaziata(d, t, f, s):
    return sum(d.textlength(c, font=f) for c in t) + s * max(0, len(t) - 1)


def centra(d, y, t, f, fill):
    d.text(((W - d.textlength(t, font=f)) / 2, y), t, font=f, fill=fill)


def acapo(d, t, f, mw):
    fuori = []
    for pezzo in t.split("\n"):
        cur = ""
        for p in pezzo.split():
            prova = (cur + " " + p).strip()
            if d.textlength(prova, font=f) <= mw:
                cur = prova
            else:
                if cur:
                    fuori.append(cur)
                cur = p
        fuori.append(cur)
    return fuori


def fondo_scalato():
    sf = Image.open(FONDO).convert("RGB")
    sc = max(W / sf.width, H / sf.height)
    return sf.resize((round(sf.width * sc), round(sf.height * sc)), Image.LANCZOS)


def finestra(sf, indice, totale):
    """Un pezzo diverso del fondo per ogni scheda: sfogliare sembra scorrere
    lungo un'unica immagine invece di rivedere sempre lo stesso ritaglio."""
    x = (sf.width - W) // 2
    corsa = max(0, sf.height - H)
    y = round(corsa * indice / max(1, totale - 1))
    return sf.crop((x, y, x + W, y + H))


def scheda(indice, kicker, titolo, corpo, coda, sfondo):
    img = sfondo

    ombra = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(ombra).rectangle([L + 4, T + 12, R + 4, B + 14], fill=(60, 48, 40, 105))
    img = Image.alpha_composite(img.convert("RGBA"),
                                ombra.filter(ImageFilter.GaussianBlur(22))).convert("RGB")
    d = ImageDraw.Draw(img)
    d.rectangle([L, T, R, B], fill=SCHEDA)
    d.rectangle([L, T, R, B], outline=OTTONE_S, width=3)
    d.rectangle([L + 13, T + 13, R - 13, B - 13], outline=LINEA, width=2)

    f_kick = ImageFont.truetype(OB, 25)
    f_corpo = ImageFont.truetype(OM, 38 if indice == 0 else 33)
    f_coda = ImageFont.truetype(OB, 27)
    f_num = ImageFont.truetype(OM, 23)

    # il titolo si rimpicciolisce finche' non sta nella scheda
    largo = R - L - 130
    for corpo_px in range(78, 43, -2):
        f_tit = ImageFont.truetype(PF, corpo_px)
        righe_tit = titolo.split("\n")
        if max(d.textlength(r, font=f_tit) for r in righe_tit) <= largo:
            break
    ih = int(corpo_px * 1.22)

    righe_corpo = acapo(d, corpo, f_corpo, largo)

    # kicker in una pillola sul bordo alto, come sulla Storia
    kw = larghezza_spaziata(d, kicker, f_kick, 6)
    d.rounded_rectangle([(W - kw) / 2 - 28, T - 26, (W + kw) / 2 + 28, T + 26], 26, fill=SCHEDA)
    spazia(d, (W - kw) / 2, T - 15, kicker, f_kick, OTTONE_S, 6)

    # blocco titolo + filetto + corpo, centrato nell'altezza utile
    alto = len(righe_tit) * ih + 30 + 44 + len(righe_corpo) * 46
    if coda:
        alto += 90 if indice == 0 else 74
    y = T + (B - T - alto) // 2 + 18

    for r in righe_tit:
        centra(d, y, r, f_tit, INK)
        y += ih
    y += 26
    d.line([(W / 2 - 74, y), (W / 2 + 74, y)], fill=OTTONE_S, width=3)
    y += 50

    for r in righe_corpo:
        centra(d, y, r, f_corpo, MUTO if indice else VINO_S)
        y += 46

    if coda and indice == 0:
        f_data = ImageFont.truetype(OB, 42)
        y += 24
        centra(d, y, coda, f_data, VINO_S)
    elif coda:
        y += 26
        cw = d.textlength(coda, font=f_coda)
        d.rounded_rectangle([(W - cw) / 2 - 26, y - 8, (W + cw) / 2 + 26, y + 44], 26,
                            outline=OTTONE_S, width=3)
        centra(d, y + 2, coda, f_coda, OTTONE_S)

    # numero di scheda in basso, discreto
    passo = f"{indice + 1} / {len(SCHEDE)}"
    nw = d.textlength(passo, font=f_num)
    d.text(((W - nw) / 2, B - 62), passo, font=f_num, fill=LINEA)
    return img


def main():
    USCITA.mkdir(parents=True, exist_ok=True)
    sf = fondo_scalato()
    for i, (k, t, c, coda) in enumerate(SCHEDE, start=0):
        img = scheda(i, k, t, c, coda, finestra(sf, i, len(SCHEDE)))
        nome = USCITA / f"{i + 1:02d}.jpg"
        img.save(nome, quality=94)
        print(nome.name, "|", t.replace("\n", " ")[:44])
    # provino unico per la revisione
    prov = Image.new("RGB", (W // 2 * 4, H // 2 * 2), SCHEDA)
    for i in range(len(SCHEDE)):
        p = Image.open(USCITA / f"{i + 1:02d}.jpg").resize((W // 2, H // 2), Image.LANCZOS)
        prov.paste(p, ((i % 4) * (W // 2), (i // 4) * (H // 2)))
    prov.resize((prov.width // 2, prov.height // 2), Image.LANCZOS).save(USCITA / "provino.jpg", quality=90)
    print("\nprovino:", USCITA / "provino.jpg")


if __name__ == "__main__":
    main()
