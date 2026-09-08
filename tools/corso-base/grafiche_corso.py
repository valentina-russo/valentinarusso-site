# -*- coding: utf-8 -*-
"""Grafiche per far conoscere il Corso Base Human Design.

Un formato per ogni canale: post e story Instagram, carosello, copertina
YouTube, community post, LinkedIn, immagine per WhatsApp, banner per il sito,
testata newsletter. Tutte nel mondo visivo della landing del corso (crema,
inchiostro scuro, ottone), con gli stessi caratteri del sito.

I fatti nelle grafiche vengono dalla landing: venti lezioni dal vivo su Zoom,
due semestri da dieci, due ore a lezione, 700 euro a semestre o 1.200 il
percorso completo, rateizzabile, curriculum ufficiale BG5 Business Institute.
Nessuna data e nessun numero di posti: non sono stati decisi.

    py tools/corso-base/grafiche_corso.py
"""
import os
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

RADICE = Path(__file__).resolve().parent.parent.parent
FONT = RADICE / "tools" / "bg5-generator" / "fonts"
ASSETS = RADICE / "grav-site" / "user" / "pages" / "assets"
CORSO = ASSETS / "corso-bg5-foundation"
LOGO = RADICE / "assets" / "logo.png"
YT = RADICE / "tools" / "yt-shorts"
OUT = Path(os.environ.get("GRAFICHE_OUT", r"D:/Download/corso-base/grafiche"))

CREMA = "#FAF7F5"
CARTA = "#FFFFFF"
INK = "#1A2332"
MUTO = "#6B625D"
OTTONE = "#C48A3A"
OTTONE_SCURO = "#8A5A12"
NAVY = "#1A2332"
NEAR_W = "#F4EFE9"

PF_BOLD = str(FONT / "PlayfairDisplay-Bold.ttf")
PF_BOLD_IT = str(FONT / "PlayfairDisplay-BoldItalic.ttf")
PF_IT = str(FONT / "PlayfairDisplay-Italic.ttf")
OUT_BOLD = str(FONT / "Outfit-Bold.ttf")
OUT_SEMI = str(FONT / "Outfit-SemiBold.ttf")
OUT_MED = str(FONT / "Outfit-Medium.ttf")
OUT_REG = str(FONT / "Outfit-Regular.ttf")

FOTO_HERO = CORSO / "hero-valentina.jpg"            # 760x950, verticale
FOTO_CONFRONTO = CORSO / "confronto-valentina.jpg"  # 700x933, verticale
FOTO_SEZIONE = CORSO / "sezione-valentina.jpg"      # 1400x612, orizzontale
FOTO_QUADRA = ASSETS / "valentina.jpg"              # 1080x1080

SITO = "valentinarussobg5.com"
DATA = "Dal 12 ottobre, ogni lunedì alle 18"
HANDLE = "@valentinarussobg5"


# --------------------------------------------------------------------------
# aiuti
# --------------------------------------------------------------------------
def f(path, size):
    return ImageFont.truetype(path, size)


def rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def a_capo(d, testo, font, larg):
    """Spezza il testo in righe che stanno in `larg` pixel."""
    righe, riga = [], ""
    for parola in testo.split():
        prova = (riga + " " + parola).strip()
        if d.textlength(prova, font=font) <= larg:
            riga = prova
        else:
            if riga:
                righe.append(riga)
            riga = parola
    if riga:
        righe.append(riga)
    return righe


def adatta(d, testo, path, larg, max_righe, da, a, passo=4):
    """Il carattere piu' grande fra `da` e `a` con cui il testo sta in max_righe."""
    for dim in range(da, a - 1, -passo):
        font = f(path, dim)
        righe = a_capo(d, testo, font, larg)
        if len(righe) <= max_righe:
            return font, righe
    font = f(path, a)
    return font, a_capo(d, testo, font, larg)[:max_righe]


def centrato(d, testo, font, y, colore, cx):
    l = d.textlength(testo, font=font)
    d.text((cx - l / 2, y), testo, font=font, fill=colore)


def blocco(d, righe, font, x, y, colore, interlinea=1.18, cx=None):
    """Disegna righe una sotto l'altra. Se cx e' dato, centra su cx. Torna la y finale."""
    alt = int(font.size * interlinea)
    for r in righe:
        if cx is None:
            d.text((x, y), r, font=font, fill=colore)
        else:
            centrato(d, r, font, y, colore, cx)
        y += alt
    return y


def foto_ritaglio(path, larg, alt, y_viso=0.22):
    """Ritaglia la foto per riempire larg x alt, tenendo il viso (in alto)."""
    im = Image.open(path).convert("RGB")
    pw, ph = im.size
    if pw / ph > larg / alt:
        nw = int(ph * larg / alt)
        x0 = (pw - nw) // 2
        im = im.crop((x0, 0, x0 + nw, ph))
    else:
        nh = int(pw * alt / larg)
        y0 = int((ph - nh) * y_viso)
        im = im.crop((0, y0, pw, y0 + nh))
    return im.resize((larg, alt), Image.LANCZOS)


def sfuma(im, colore, da_frazione, verso="basso"):
    """Sfumatura verso `colore` sull'ultimo tratto della foto.
    verso: 'basso', 'destra' oppure 'sinistra'."""
    im = im.convert("RGBA")
    w, h = im.size
    ov = Image.new("RGBA", im.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(ov)
    c = rgb(colore)
    if verso == "basso":
        n = int(h * (1 - da_frazione))
        y0 = h - n
        for i in range(n):
            t = i / max(1, n)
            d.line([(0, y0 + i), (w, y0 + i)], fill=(c[0], c[1], c[2], int(255 * t ** 1.5)))
    elif verso == "destra":
        n = int(w * (1 - da_frazione))
        x0 = w - n
        for i in range(n):
            t = i / max(1, n)
            d.line([(x0 + i, 0), (x0 + i, h)], fill=(c[0], c[1], c[2], int(255 * t ** 1.5)))
    else:
        n = int(w * (1 - da_frazione))
        for i in range(n):
            t = 1 - i / max(1, n)
            d.line([(i, 0), (i, h)], fill=(c[0], c[1], c[2], int(255 * t ** 1.5)))
    return Image.alpha_composite(im, ov).convert("RGB")


def pillola(d, testo, font, cx, y, fondo, colore, pad_x=34, pad_y=18):
    l = d.textlength(testo, font=font)
    h = font.size + pad_y * 2
    w = l + pad_x * 2
    d.rounded_rectangle([cx - w / 2, y, cx + w / 2, y + h], radius=h / 2, fill=fondo)
    d.text((cx - l / 2, y + pad_y - 2), testo, font=font, fill=colore)
    return y + h


def filetto(d, cx, y, larg=90, colore=OTTONE, spess=3):
    d.line([(cx - larg // 2, y), (cx + larg // 2, y)], fill=colore, width=spess)


def occhiello(d, testo, cx, y, colore=OTTONE, dim=26, x=None):
    """Etichetta in maiuscolo spaziato. Con x allinea a sinistra, altrimenti centra su cx.
    Pillow non ha la spaziatura fra lettere: si disegna un carattere alla volta."""
    font = f(OUT_BOLD, dim)
    t = testo.upper()
    sp = dim * 0.16
    largh = sum(d.textlength(ch, font=font) + sp for ch in t) - sp
    px = (cx - largh / 2) if x is None else x
    for ch in t:
        d.text((px, y), ch, font=font, fill=colore)
        px += d.textlength(ch, font=font) + sp
    return y + dim


def salva(im, nome, qualita=88):
    OUT.mkdir(parents=True, exist_ok=True)
    p = OUT / nome
    im.save(p, "JPEG", quality=qualita, optimize=True, progressive=True)
    print("%-30s %dx%d  %d kB" % (nome, im.size[0], im.size[1], p.stat().st_size // 1024))


# --------------------------------------------------------------------------
# 1. post Instagram, annuncio
# --------------------------------------------------------------------------
def ig_feed():
    W = H = 1080
    im = Image.new("RGB", (W, H), CREMA)
    fw = 470
    foto = sfuma(foto_ritaglio(FOTO_HERO, fw, H, 0.15), CREMA, 0.80, "destra")
    im.paste(foto, (0, 0))
    d = ImageDraw.Draw(im)
    x = fw + 46
    larg = W - x - 60
    y = 118
    y = occhiello(d, "Corso Base Human Design", 0, y, x=x, dim=22) + 34
    font, righe = adatta(d, "Impara a leggere la tua carta, e quella degli altri.", PF_BOLD_IT, larg, 4, 66, 44)
    y = blocco(d, righe, font, x, y, INK, 1.12) + 26
    d.line([(x, y), (x + 90, y)], fill=OTTONE, width=3)
    y += 34
    dett = f(OUT_MED, 27)
    for r in ("20 lezioni dal vivo, 2 semestri", "Su Zoom, 2 ore a lezione", DATA, "Anche a rate"):
        d.ellipse([x, y + 11, x + 9, y + 20], fill=OTTONE)
        d.text((x + 24, y), r, font=dett, fill=INK)
        y += 44
    y = H - 158
    d.text((x, y), "Docente: Valentina Russo", font=f(OUT_SEMI, 24), fill=INK)
    d.text((x, y + 34), "Analista Certificata BG5\u00ae", font=f(OUT_REG, 22), fill=MUTO)
    fs = f(OUT_BOLD, 22)
    d.text((W - 60 - d.textlength(SITO, font=fs), H - 52), SITO, font=fs, fill=OTTONE_SCURO)
    salva(im, "01-instagram-post.jpg")


# --------------------------------------------------------------------------
# 2. story Instagram, con il generatore del canale
# --------------------------------------------------------------------------
def ig_story():
    W, H = 1080, 1920
    im = Image.new("RGB", (W, H), CREMA)
    # la fascia bassa (circa 250px) e' coperta dai comandi di Instagram:
    # tutto quello che conta sta sopra
    fh = int(H * 0.54)
    foto = sfuma(foto_ritaglio(FOTO_HERO, W, fh, 0.10), CREMA, 0.62, "basso")
    im.paste(foto, (0, 0))
    d = ImageDraw.Draw(im)
    y = fh + 26
    y = occhiello(d, "Corso Base Human Design", W / 2, y, dim=28) + 24
    filetto(d, W / 2, y, larg=110, spess=4)
    y += 44
    font, righe = adatta(d, "Impara a leggere la tua carta, e quella degli altri.", PF_BOLD_IT, W - 160, 3, 92, 60)
    y = blocco(d, righe, font, 0, y, INK, 1.12, cx=W / 2) + 30
    fs = f(OUT_REG, 34)
    for r in a_capo(d, "Venti lezioni dal vivo in due semestri, su Zoom, dal 12 ottobre, ogni lunedì alle 18. Il curriculum ufficiale BG5 (Business Group 5), in termini di Human Design.", fs, W - 180):
        centrato(d, r, fs, y, MUTO, W / 2)
        y += 48
    y += 30
    y = pillola(d, "SCOPRI IL CORSO", f(OUT_BOLD, 28), W / 2, y, INK, CARTA, pad_x=40, pad_y=20)
    centrato(d, HANDLE, f(OUT_BOLD, 28), y + 36, MUTO, W / 2)
    salva(im, "02-instagram-story.jpg")


# --------------------------------------------------------------------------
# 3. carosello Instagram, 5 slide
# --------------------------------------------------------------------------
def _slide_base(numero, tot=5):
    W = H = 1080
    im = Image.new("RGB", (W, H), CREMA)
    d = ImageDraw.Draw(im)
    d.rectangle([48, 48, W - 48, H - 48], outline=OTTONE, width=2)
    d.text((72, 66), "CORSO BASE HUMAN DESIGN", font=f(OUT_BOLD, 20), fill=OTTONE)
    n = "%d / %d" % (numero, tot)
    fn = f(OUT_BOLD, 20)
    d.text((W - 72 - d.textlength(n, font=fn), 66), n, font=fn, fill=MUTO)
    fh = f(OUT_BOLD, 20)
    d.text((W / 2 - d.textlength(HANDLE, font=fh) / 2, H - 92), HANDLE, font=fh, fill=MUTO)
    return im, d


def _slide_elenco(numero, titolo, sotto, voci, nome):
    W = H = 1080
    im, d = _slide_base(numero)
    y = 190
    y = occhiello(d, sotto, 0, y, x=96, dim=22) + 26
    font, righe = adatta(d, titolo, PF_BOLD, W - 192, 2, 66, 46)
    y = blocco(d, righe, font, 96, y, INK, 1.1) + 22
    d.line([(96, y), (186, y)], fill=OTTONE, width=3)
    y += 44
    fv = f(OUT_REG, 33)
    fnum = f(PF_BOLD_IT, 32)
    for i, v in enumerate(voci, 1):
        d.text((96, y - 2), "%02d" % i, font=fnum, fill=OTTONE)
        righe = a_capo(d, v, fv, W - 192 - 70)
        y = blocco(d, righe, fv, 166, y, INK, 1.3) + 26
    salva(im, nome)


def carosello():
    W = H = 1080
    # 1. gancio
    im, d = _slide_base(1)
    cy = 470
    font, righe = adatta(d, "Vuoi leggere la tua carta da sola?", PF_BOLD_IT, W - 220, 3, 92, 60)
    y = cy - int(len(righe) * font.size * 1.12 / 2)
    y = blocco(d, righe, font, 0, y, INK, 1.12, cx=W / 2) + 30
    filetto(d, W / 2, y)
    y += 40
    fs = f(OUT_REG, 30)
    for r in a_capo(d, "Venti lezioni dal vivo, in due semestri, per leggere il tuo Bodygraph e quello di chi ti sta vicino. Dal 12 ottobre, ogni lunedì alle 18.", fs, W - 260):
        centrato(d, r, fs, y, MUTO, W / 2)
        y += 42
    centrato(d, "Scorri per vedere cosa impari  \u2192", f(OUT_SEMI, 24), H - 170, OTTONE_SCURO, W / 2)
    salva(im, "03-carosello-1.jpg")

    _slide_elenco(2, "Tipologia, Strategia e Autorit\u00e0", "Semestre 1 \u00b7 10 lezioni", [
        "I 9 Centri del Bodygraph e le 5 Tipologie che li attraversano",
        "La tua Autorit\u00e0 interiore e il modo in cui elabori le informazioni",
        "I temi del Non-S\u00e9, uno per uno, e la saggezza che nascondono",
        "Progetto finale: la sintesi completa del tuo Bodygraph",
    ], "03-carosello-2.jpg")

    _slide_elenco(3, "Canali, Porte, Profilo e Linee", "Semestre 2 \u00b7 10 lezioni", [
        "Le 64 Porte e i 36 Canali, circuito per circuito",
        "Le grandi tematiche di fondo che attraversano ogni carta",
        "Le 6 Linee e i 12 Profili, fra karma personale e transpersonale",
        "Progetto finale: la lettura di due Bodygraph completi",
    ], "03-carosello-3.jpg")

    _slide_elenco(4, "Per chi \u00e8, e per chi non \u00e8", "Onestamente", [
        "Per chi vuole leggere il proprio Bodygraph in autonomia, oltre la lettura individuale",
        "Per chi lavora con le persone: HR, coach, consulenti, manager",
        "Per chi ha gi\u00e0 fatto la Prima Lettura e vuole il metodo dietro quella sessione",
        "Non fa per chi non pu\u00f2 dedicare due ore a settimana, dal vivo, per venti settimane",
    ], "03-carosello-4.jpg")

    # 5. chiusura con foto, prezzo e rimando alla bio
    im, d = _slide_base(5)
    fh = 470
    foto = sfuma(foto_ritaglio(FOTO_HERO, W - 100, fh, 0.28), CREMA, 0.62, "basso")
    im.paste(foto, (50, 50))
    d = ImageDraw.Draw(im)
    d.rectangle([48, 48, W - 48, H - 48], outline=OTTONE, width=2)
    y = fh + 20
    centrato(d, "Docente: Valentina Russo", f(PF_BOLD, 40), y, INK, W / 2)
    y += 56
    centrato(d, "Analista Certificata BG5\u00ae, curriculum ufficiale BG5 Business Institute", f(OUT_REG, 24), y, MUTO, W / 2)
    y += 60
    filetto(d, W / 2, y)
    y += 34
    centrato(d, "700\u20ac a semestre \u00b7 1.200\u20ac il percorso", f(PF_BOLD_IT, 46), y, INK, W / 2)
    y += 66
    centrato(d, DATA + " · su Zoom, anche a rate", f(OUT_REG, 27), y, MUTO, W / 2)
    y += 74
    pillola(d, "IL LINK \u00c8 IN BIO", f(OUT_BOLD, 26), W / 2, y, OTTONE, CARTA)
    fh2 = f(OUT_BOLD, 20)
    d.text((W / 2 - d.textlength(HANDLE, font=fh2) / 2, H - 92), HANDLE, font=fh2, fill=MUTO)
    salva(im, "03-carosello-5.jpg")


# --------------------------------------------------------------------------
# 4. copertina YouTube, con lo strumento del canale (foto dal pool, eyebrow fisso)
# --------------------------------------------------------------------------
def youtube_cover():
    OUT.mkdir(parents=True, exist_ok=True)
    out = OUT / "04-youtube-copertina.jpg"
    subprocess.run([sys.executable, str(YT / "cover_long.py"),
                    "Il Corso Base Human Design", str(out),
                    "--eyebrow", "HUMAN DESIGN - BG5",
                    "--photo", str(FOTO_HERO),
                    "--subtitle", "Dal 12 ottobre, lunedì alle 18"],
                   check=True, cwd=str(YT))
    im = Image.open(out)
    print("%-30s %dx%d  %d kB" % (out.name, im.size[0], im.size[1], out.stat().st_size // 1024))


# --------------------------------------------------------------------------
# 5. community post YouTube: una lezione del programma, senza link
# --------------------------------------------------------------------------
def community_post():
    W = H = 1080
    im = Image.new("RGB", (W, H), NAVY)
    d = ImageDraw.Draw(im)
    d.rectangle([60, 60, W - 60, H - 60], outline=OTTONE, width=2)
    y = 150
    y = occhiello(d, "Dal programma del Corso Base", W / 2, y, dim=22) + 70
    centrato(d, "01", f(PF_BOLD_IT, 150), y, OTTONE, W / 2)
    y += 176
    centrato(d, "LEZIONE 1 DI 20", f(OUT_BOLD, 24), y, NEAR_W, W / 2)
    y += 66
    filetto(d, W / 2, y)
    y += 44
    font, righe = adatta(d, "Introduzione allo Human Design", PF_BOLD, W - 200, 2, 64, 44)
    y = blocco(d, righe, font, 0, y, CARTA, 1.12, cx=W / 2) + 26
    fs = f(OUT_REG, 30)
    for r in a_capo(d, "Le basi del Bodygraph: Personalit\u00e0 e Design, la parte conscia e quella inconscia della carta.", fs, W - 240):
        centrato(d, r, fs, y, NEAR_W, W / 2)
        y += 42
    centrato(d, "Venti lezioni dal vivo · " + DATA, f(OUT_SEMI, 24), H - 150, OTTONE, W / 2)
    salva(im, "05-community-post.jpg")


# --------------------------------------------------------------------------
# 5b. post YouTube per la lezione zero, quella gratuita su Zoom
# --------------------------------------------------------------------------
def community_lezione_zero():
    W = H = 1080
    im = Image.new("RGB", (W, H), NAVY)
    d = ImageDraw.Draw(im)
    d.rectangle([60, 60, W - 60, H - 60], outline=OTTONE, width=2)

    y = occhiello(d, "Corso Base Human Design", W / 2, 184, dim=24) + 66
    filetto(d, W / 2, y)
    y += 56

    font, righe = adatta(d, "Prima lezione gratuita", PF_BOLD, W - 220, 2, 92, 58)
    y = blocco(d, righe, font, 0, y, CARTA, 1.10, cx=W / 2) + 62

    y = pillola(d, "lunedì 14 settembre · ore 20:30",
                f(OUT_BOLD, 34), W / 2, y, OTTONE, NAVY) + 46

    fs = f(OUT_REG, 31)
    testo = ("Su Zoom. Presento il programma delle venti lezioni "
             "e rispondo a tutte le domande.")
    y = blocco(d, a_capo(d, testo, fs, W - 260), fs, 0, y, NEAR_W, 1.36, cx=W / 2)

    centrato(d, "Scrivimi per ricevere il link", f(OUT_SEMI, 28), H - 196, OTTONE, W / 2)
    centrato(d, HANDLE, f(OUT_MED, 26), H - 150, MUTO, W / 2)
    salva(im, "05b-community-lezione-zero.jpg")


# --------------------------------------------------------------------------
# 6. LinkedIn, per chi lavora con le persone
# --------------------------------------------------------------------------
def linkedin():
    W, H = 1200, 627
    im = Image.new("RGB", (W, H), NAVY)
    fw = 430
    foto = sfuma(foto_ritaglio(FOTO_HERO, fw, H, 0.12), NAVY, 0.70, "sinistra")
    im.paste(foto, (W - fw, 0))
    d = ImageDraw.Draw(im)
    x, larg = 80, W - fw - 40
    y = 96
    y = occhiello(d, "Formazione \u00b7 20 lezioni dal vivo", 0, y, x=x, dim=22) + 34
    font, righe = adatta(d, "Un linguaggio preciso per capire chi hai davanti.", PF_BOLD, larg, 3, 60, 42)
    y = blocco(d, righe, font, x, y, CARTA, 1.1) + 22
    d.line([(x, y), (x + 90, y)], fill=OTTONE, width=3)
    y += 30
    fs = f(OUT_REG, 24)
    for r in a_capo(d, "Corso Base Human Design, sul curriculum ufficiale BG5 (Business Group 5) Business Institute. Per HR, coach, consulenti e manager. Dal 12 ottobre, ogni lunedì alle 18.", fs, larg):
        d.text((x, y), r, font=fs, fill=NEAR_W)
        y += 34
    d.text((x, H - 90), "Valentina Russo \u00b7 Analista Certificata BG5\u00ae", font=f(OUT_SEMI, 22), fill=OTTONE)
    d.text((x, H - 56), SITO, font=f(OUT_REG, 20), fill=NEAR_W)
    salva(im, "06-linkedin.jpg")


# --------------------------------------------------------------------------
# 7. WhatsApp, per chi ha gia' fatto la Prima Lettura
# --------------------------------------------------------------------------
def whatsapp():
    W = H = 1080
    im = Image.new("RGB", (W, H), CREMA)
    d = ImageDraw.Draw(im)
    d.rectangle([56, 56, W - 56, H - 56], outline=OTTONE, width=3)
    d.rectangle([70, 70, W - 70, H - 70], outline=OTTONE, width=1)
    ft = foto_ritaglio(FOTO_HERO, 200, 200, 0.08)
    maschera = Image.new("L", (200, 200), 0)
    ImageDraw.Draw(maschera).ellipse([0, 0, 199, 199], fill=255)
    im.paste(ft, (W // 2 - 100, 140), maschera)
    d = ImageDraw.Draw(im)
    y = 380
    centrato(d, "Hai fatto la Prima Lettura?", f(OUT_SEMI, 30), y, MUTO, W / 2)
    y += 60
    font, righe = adatta(d, "Il passo dopo \u00e8 il metodo.", PF_BOLD_IT, W - 220, 2, 84, 56)
    y = blocco(d, righe, font, 0, y, INK, 1.1, cx=W / 2) + 22
    filetto(d, W / 2, y)
    y += 40
    fs = f(OUT_REG, 30)
    for r in a_capo(d, "Il Corso Base Human Design: venti lezioni dal vivo su Zoom, in due semestri, per leggere la tua carta e quella degli altri. Dal 12 ottobre, ogni lunedì alle 18.", fs, W - 240):
        centrato(d, r, fs, y, INK, W / 2)
        y += 42
    y += 30
    centrato(d, "700\u20ac a semestre \u00b7 1.200\u20ac il percorso, anche a rate", f(OUT_SEMI, 27), y, OTTONE_SCURO, W / 2)
    centrato(d, "Rispondi a questo messaggio e ti mando i dettagli.", f(OUT_REG, 26), H - 150, MUTO, W / 2)
    salva(im, "07-whatsapp.jpg")


# --------------------------------------------------------------------------
# 8. banner per il sito (risultato del calcolo, articoli del blog)
# --------------------------------------------------------------------------
def banner_sito():
    W, H = 1600, 500
    im = Image.new("RGB", (W, H), CREMA)
    fw = 620
    foto = sfuma(foto_ritaglio(FOTO_HERO, fw, H, 0.12), CREMA, 0.72, "sinistra")
    im.paste(foto, (W - fw, 0))
    d = ImageDraw.Draw(im)
    x, larg = 90, W - fw - 40
    y = 86
    y = occhiello(d, "Corso Base Human Design", 0, y, x=x, dim=22) + 30
    font, righe = adatta(d, "Hai appena calcolato la tua carta. Vuoi imparare a leggerla?", PF_BOLD_IT, larg, 2, 58, 40)
    y = blocco(d, righe, font, x, y, INK, 1.12) + 22
    fs = f(OUT_REG, 24)
    for r in a_capo(d, "Venti lezioni dal vivo, " + DATA.lower() + ". Docente: Valentina Russo, Analista Certificata BG5®.", fs, larg):
        d.text((x, y), r, font=fs, fill=MUTO)
        y += 34
    y += 22
    pf = f(OUT_BOLD, 22)
    t = "SCOPRI IL CORSO  \u2192"
    l = d.textlength(t, font=pf)
    d.rounded_rectangle([x, y, x + l + 56, y + 58], radius=29, fill=INK)
    d.text((x + 28, y + 16), t, font=pf, fill=CARTA)
    salva(im, "08-banner-sito.jpg")


# --------------------------------------------------------------------------
# 9. testata newsletter
# --------------------------------------------------------------------------
def newsletter():
    W, H = 1200, 500
    im = Image.new("RGB", (W, H), NAVY)
    d = ImageDraw.Draw(im)
    d.rectangle([40, 40, W - 40, H - 40], outline=OTTONE, width=2)
    y = 120
    y = occhiello(d, "Dal 12 ottobre · ogni lunedì alle 18", W / 2, y, dim=22) + 44
    centrato(d, "Corso Base Human Design", f(PF_BOLD, 74), y, CARTA, W / 2)
    y += 104
    filetto(d, W / 2, y)
    y += 36
    centrato(d, "Venti lezioni dal vivo per leggere la tua carta, e quella degli altri.", f(PF_IT, 32), y, NEAR_W, W / 2)
    centrato(d, SITO, f(OUT_BOLD, 22), H - 92, OTTONE, W / 2)
    salva(im, "09-newsletter-testata.jpg")


if __name__ == "__main__":
    ig_feed()
    ig_story()
    carosello()
    youtube_cover()
    community_post()
    linkedin()
    whatsapp()
    banner_sito()
    newsletter()
    print("\ncartella:", OUT)
