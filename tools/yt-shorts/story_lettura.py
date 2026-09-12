"""
Instagram Story per le letture di Valentina Russo (1080x1920, 9:16).

Template dedicato ai servizi, diverso da story_generator.py: le foto delle
letture sono 700x525 e su un layout full-bleed andrebbero ingrandite 2,3 volte
(risultato molle). Qui l'immagine sta in una cornice 900x700, ingrandimento
1,33, e il fondo e' la carta crema del sito, cosi' la story e la pagina di
atterraggio sono la stessa cosa.

Palette: identica alla direzione 2 del sito.
  crema  #FBF7F5  fondo
  ink    #3A3532  titolo e handle
  muto   #6B625B  body
  vino   #7A4F5E  eyebrow e prezzo
  ottone #C48A3A  solo filetti, mai testo (contrasto 2,8 su crema)

Flusso verticale: eyebrow, cornice immagine, titolo Playfair, body Outfit,
prezzo, handle. Sotto y=1700 resta libero per il Link Sticker di Instagram.
"""
from __future__ import annotations

import argparse
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

from story_generator import _wrap_by_width

HERE = Path(__file__).resolve().parent
FONTS_DIR = HERE.parent / "bg5-generator" / "fonts"

PLAYFAIR_BOLD_ITAL = str(FONTS_DIR / "PlayfairDisplay-BoldItalic.ttf")
OUTFIT_BOLD = str(FONTS_DIR / "Outfit-Bold.ttf")
OUTFIT_REGULAR = str(FONTS_DIR / "Outfit-Regular.ttf")

CREMA = "#FBF7F5"
INK = "#3A3532"
MUTO = "#6B625B"
VINO = "#7A4F5E"
OTTONE = "#C48A3A"

W, H = 1080, 1920
BOX_X0, BOX_X1 = 90, 990
BOX_Y0, BOX_H = 400, 640
LINK_ZONE_Y = 1700


def _fill_box(photo_path, w, h):
    """Riempie w x h tagliando il minimo, con il centro dell'immagine al centro."""
    src = Image.open(photo_path).convert("RGB")
    scale = max(w / src.width, h / src.height)
    nw, nh = int(src.width * scale + 0.5), int(src.height * scale + 0.5)
    src = src.resize((nw, nh), Image.LANCZOS)
    left = (nw - w) // 2
    top = (nh - h) // 2
    return src.crop((left, top, left + w, top + h))


def _mosaico(paths, w, h, gap=8):
    """Griglia 2x2 per la story di apertura della serie."""
    cell_w = (w - gap) // 2
    cell_h = (h - gap) // 2
    out = Image.new("RGB", (w, h), CREMA)
    for i, p in enumerate(paths[:4]):
        x = (i % 2) * (cell_w + gap)
        y = (i // 2) * (cell_h + gap)
        out.paste(_fill_box(p, cell_w, cell_h), (x, y))
    return out


def _tracked(draw, text, font, y, fill, tracking=6):
    widths = [draw.textlength(ch, font=font) for ch in text]
    total = sum(widths) + tracking * (len(text) - 1)
    x = (W - total) / 2
    for ch, cw in zip(text, widths):
        draw.text((x, y), ch, font=font, fill=fill)
        x += cw + tracking
    return draw.textbbox((0, 0), text, font=font)[3]


def _centered(draw, text, font, y, fill):
    w = draw.textlength(text, font=font)
    draw.text(((W - w) / 2, y), text, font=font, fill=fill)


def build(titolo, corpo, prezzo, immagine, eyebrow="CICLI DI VITA"):
    img = Image.new("RGB", (W, H), CREMA)
    draw = ImageDraw.Draw(img)

    # eyebrow + filetto
    y = 292
    eb_font = ImageFont.truetype(OUTFIT_BOLD, 30)
    h_eb = _tracked(draw, eyebrow.upper(), eb_font, y, VINO, tracking=8)
    y_line = y + h_eb + 20
    draw.line([(W // 2 - 46, y_line), (W // 2 + 46, y_line)], fill=OTTONE, width=3)

    # cornice immagine: filetto ottone a 14px dal bordo foto
    box_w = BOX_X1 - BOX_X0
    foto = _mosaico(immagine, box_w, BOX_H) if isinstance(immagine, (list, tuple)) \
        else _fill_box(immagine, box_w, BOX_H)
    img.paste(foto, (BOX_X0, BOX_Y0))
    draw.rectangle(
        [BOX_X0 - 14, BOX_Y0 - 14, BOX_X1 + 13, BOX_Y0 + BOX_H + 13],
        outline=OTTONE, width=2,
    )

    y = BOX_Y0 + BOX_H + 66

    # titolo: Playfair italic, massimo 2 righe
    t_font, t_lines = None, []
    for size in range(82, 47, -4):
        f = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, size)
        lines = _wrap_by_width(draw, titolo, f, box_w)
        if len(lines) <= 2:
            t_font, t_lines = f, lines
            break
    if t_font is None:
        t_font = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, 48)
        t_lines = _wrap_by_width(draw, titolo, t_font, box_w)[:2]
    for line in t_lines:
        _centered(draw, line, t_font, y, INK)
        y += int(t_font.size * 1.16)

    # body
    y += 30
    b_font = ImageFont.truetype(OUTFIT_REGULAR, 34)
    for line in _wrap_by_width(draw, corpo, b_font, 880)[:4]:
        _centered(draw, line, b_font, y, MUTO)
        y += 48

    # prezzo
    y += 34
    p_font = ImageFont.truetype(OUTFIT_BOLD, 40)
    _centered(draw, prezzo, p_font, y, VINO)
    y += 76

    # handle
    draw.line([(W // 2 - 46, y), (W // 2 + 46, y)], fill=OTTONE, width=2)
    y += 24
    h_font = ImageFont.truetype(OUTFIT_BOLD, 32)
    _centered(draw, "@valentinarussobg5", h_font, y, INK)
    fine = y + 40

    if fine > LINK_ZONE_Y:
        raise SystemExit(
            "Il testo invade la zona del Link Sticker (fine %d > %d): accorcia il body."
            % (fine, LINK_ZONE_Y)
        )
    return img


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("titolo")
    ap.add_argument("corpo")
    ap.add_argument("prezzo")
    ap.add_argument("immagine", nargs="+")
    ap.add_argument("--out", required=True)
    ap.add_argument("--eyebrow", default="CICLI DI VITA")
    a = ap.parse_args()
    im = a.immagine if len(a.immagine) > 1 else a.immagine[0]
    out = Path(a.out)
    out.parent.mkdir(parents=True, exist_ok=True)
    build(a.titolo, a.corpo, a.prezzo, im, a.eyebrow).save(out, quality=95)
    print(out)


if __name__ == "__main__":
    main()
