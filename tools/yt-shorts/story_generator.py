"""
Instagram Story per Valentina Russo BG5 (1080x1920, 9:16).

Template disegnato una volta (stesso pattern di cover_long.py / community_carousel.py:
foto full-bleed in alto con fade verso un pannello colore pieno sotto) e riusato per
ogni story futura, cambia solo foto/testo/colore, mai il design.

Layout:
  - Foto a piena larghezza in alto (circa 58% altezza canvas), crop con viso in evidenza
  - Gradient fade verso il colore di sfondo (per Tipo HD, stessa convenzione di cover_long.py)
  - Pannello sotto: eyebrow HUMAN DESIGN - BG5 + titolo (Playfair Bold Italic) + body + handle
  - Zona vuota in fondo (circa 350px) riservata al Link Sticker di Instagram
"""
from __future__ import annotations

import argparse
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).resolve().parent
FONTS_DIR = HERE.parent / "bg5-generator" / "fonts"

NAVY = "#1A2332"
GOLD = "#C48A3A"
CREAM = "#FAF7F5"
NEAR_W = "#F4EFE9"
WHITE = "#FFFFFF"

TYPE_COLORS = {
    "iniziatore": "#B85F2F",
    "costruttore": "#C62828",
    "guida": "#2C5F3F",
    "valutatore": "#B9D8EC",   # azzurro cielo, allineato a cover_long
    "attualita": "#3A3D42",
}

PLAYFAIR_BOLD_ITAL = str(FONTS_DIR / "PlayfairDisplay-BoldItalic.ttf")
OUTFIT_BOLD = str(FONTS_DIR / "Outfit-Bold.ttf")
OUTFIT_REGULAR = str(FONTS_DIR / "Outfit-Regular.ttf")

W, H = 1080, 1920
PHOTO_H = int(H * 0.63)
GAP_H = int(H * 0.17)


def _hex_to_rgba(hex_str, alpha=255):
    hex_str = hex_str.lstrip("#")
    return (int(hex_str[0:2], 16), int(hex_str[2:4], 16), int(hex_str[4:6], 16), alpha)


def _photo_top_bleed(photo_path, target_w, target_h, face_y_ratio=0.22):
    photo = Image.open(photo_path).convert("RGB")
    pw, ph = photo.size
    target_ratio = target_w / target_h
    photo_ratio = pw / ph
    if photo_ratio > target_ratio:
        new_w = int(ph * target_ratio)
        x0 = max(0, (pw - new_w) // 2)
        photo = photo.crop((x0, 0, x0 + new_w, ph))
    else:
        new_h = int(pw / target_ratio)
        y_center = int(ph * face_y_ratio)
        y0 = max(0, min(ph - new_h, y_center - int(new_h * 0.35)))
        photo = photo.crop((0, y0, pw, y0 + new_h))
    return photo.resize((target_w, target_h), Image.LANCZOS)


def _wrap_by_width(draw, text, font, max_w):
    # \n esplicito forza un a-capo (es. "Sacrale Definito\ne Non Definito");
    # ogni segmento tra \n viene comunque auto-wrappato se troppo largo.
    lines = []
    for segment in text.split("\n"):
        words = segment.split()
        cur = ""
        for w in words:
            cand = (cur + " " + w).strip() if cur else w
            bbox = draw.textbbox((0, 0), cand, font=font)
            if bbox[2] - bbox[0] <= max_w:
                cur = cand
            else:
                if cur:
                    lines.append(cur)
                cur = w
        if cur:
            lines.append(cur)
    return lines


def _draw_centered(draw, text, font, y, fill):
    bbox = draw.textbbox((0, 0), text, font=font)
    x = (W - (bbox[2] - bbox[0])) // 2
    draw.text((x, y), text, font=font, fill=fill)


def build_story(title, body, photo_path, bg_color=TYPE_COLORS["guida"], eyebrow="HUMAN DESIGN - BG5", bottom_safe_px=60, fade=True):
    """bottom_safe_px: margine minimo dal bordo inferiore per l'ultimo elemento
    (handle). Default 60 = comportamento Story invariato. Per i Reel passare
    ~300px per restare fuori dalla safe zone coperta dai controlli IG
    (like/commenti/condividi/didascalia/audio, ~280-320px dal basso)."""
    def _luminanza(hexcol: str) -> float:
        h = str(hexcol).lstrip("#")
        r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
        return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255

    # Su pannello molto chiaro il testo chiaro sparisce: si inverte su marrone.
    _light = _luminanza(bg_color) > 0.75
    DARK_INK = "#31271D"
    title_color = DARK_INK if _light else WHITE
    body_color = GOLD if _light else NEAR_W

    img = Image.new("RGB", (W, H), bg_color)

    photo = _photo_top_bleed(photo_path, W, PHOTO_H)
    img.paste(photo, (0, 0))

    if fade:
        fade_h = int(PHOTO_H * 0.42)
        fade_y0 = PHOTO_H - fade_h
        overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        ov_draw = ImageDraw.Draw(overlay)
        bg_rgba = _hex_to_rgba(bg_color)
        for i in range(fade_h):
            t = i / fade_h
            alpha = int(255 * (t ** 1.4))
            ov_draw.line([(0, fade_y0 + i), (W, fade_y0 + i)], fill=(bg_rgba[0], bg_rgba[1], bg_rgba[2], alpha))
        img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")
    draw = ImageDraw.Draw(img)

    max_w = int(W * 0.9)

    y = PHOTO_H + int(H * 0.045)

    eyebrow_font = ImageFont.truetype(OUTFIT_BOLD, 30)
    _draw_centered(draw, eyebrow.upper(), eyebrow_font, y, GOLD)
    bbox = draw.textbbox((0, 0), eyebrow.upper(), font=eyebrow_font)
    line_w = int(W * 0.12)
    y_line = y + (bbox[3] - bbox[1]) + 16
    draw.line([(W // 2 - line_w // 2, y_line), (W // 2 + line_w // 2, y_line)], fill=GOLD, width=4)
    y = y_line + 38

    title_font, title_lines = None, []
    for size in range(92, 47, -4):
        f = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, size)
        lines = _wrap_by_width(draw, title, f, max_w)
        if len(lines) <= 2:
            title_font, title_lines = f, lines
            break
    if title_font is None:
        title_font = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, 48)
        title_lines = _wrap_by_width(draw, title, title_font, max_w)[:2]

    title_line_h = int(title_font.size * 1.18)
    for line in title_lines:
        _draw_centered(draw, line, title_font, y, title_color)
        y += title_line_h
    y += int(H * 0.025)

    body_font = ImageFont.truetype(OUTFIT_REGULAR, 34)
    body_lines = _wrap_by_width(draw, body, body_font, max_w)[:4]
    body_line_h = int(34 * 1.42)
    for line in body_lines:
        _draw_centered(draw, line, body_font, y, body_color)
        y += body_line_h

    handle_y = min(y + 44, H - bottom_safe_px)
    draw.line([(W // 2 - 60, handle_y - 26), (W // 2 + 60, handle_y - 26)], fill=GOLD, width=3)
    handle_font = ImageFont.truetype(OUTFIT_BOLD, 32)
    _draw_centered(draw, "@valentinarussobg5", handle_font, handle_y, title_color)

    return img


def build_reel_cover(title, body, photo_path, bg_color=TYPE_COLORS["guida"], eyebrow="HUMAN DESIGN - BG5"):
    """Copertina Reel (1080x1920): NON e' la Story ridimensionata. I Reel hanno
    controlli IG (like/commenti/condividi/didascalia/audio) che coprono
    ~280-320px dal basso e ~90-120px da destra durante la riproduzione.
    Il layout qui e' compatto: foto piu' bassa, testi ravvicinati, tutto il
    blocco eyebrow+titolo+body+handle chiuso entro la meta' superiore del
    canvas, cosi' resta lontano dalla safe zone invece di riempirla."""
    img = Image.new("RGB", (W, H), bg_color)

    photo_h = int(H * 0.40)
    photo = _photo_top_bleed(photo_path, W, photo_h)
    img.paste(photo, (0, 0))

    fade_h = int(photo_h * 0.5)
    fade_y0 = photo_h - fade_h
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ov_draw = ImageDraw.Draw(overlay)
    bg_rgba = _hex_to_rgba(bg_color)
    for i in range(fade_h):
        t = i / fade_h
        alpha = int(255 * (t ** 1.4))
        ov_draw.line([(0, fade_y0 + i), (W, fade_y0 + i)], fill=(bg_rgba[0], bg_rgba[1], bg_rgba[2], alpha))
    img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")
    draw = ImageDraw.Draw(img)

    max_w = int(W * 0.8)
    y = photo_h + 36

    eyebrow_font = ImageFont.truetype(OUTFIT_BOLD, 26)
    _draw_centered(draw, eyebrow.upper(), eyebrow_font, y, GOLD)
    bbox = draw.textbbox((0, 0), eyebrow.upper(), font=eyebrow_font)
    line_w = int(W * 0.1)
    y_line = y + (bbox[3] - bbox[1]) + 12
    draw.line([(W // 2 - line_w // 2, y_line), (W // 2 + line_w // 2, y_line)], fill=GOLD, width=4)
    y = y_line + 30

    title_font, title_lines = None, []
    for size in range(72, 39, -4):
        f = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, size)
        lines = _wrap_by_width(draw, title, f, max_w)
        if len(lines) <= 2:
            title_font, title_lines = f, lines
            break
    if title_font is None:
        title_font = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, 40)
        title_lines = _wrap_by_width(draw, title, title_font, max_w)[:2]

    title_line_h = int(title_font.size * 1.12)
    for line in title_lines:
        _draw_centered(draw, line, title_font, y, WHITE)
        y += title_line_h
    y += 22

    body_font = ImageFont.truetype(OUTFIT_REGULAR, 30)
    body_lines = _wrap_by_width(draw, body, body_font, max_w)[:2]
    body_line_h = int(30 * 1.35)
    for line in body_lines:
        _draw_centered(draw, line, body_font, y, NEAR_W)
        y += body_line_h

    handle_y = y + 30
    draw.line([(W // 2 - 55, handle_y - 22), (W // 2 + 55, handle_y - 22)], fill=GOLD, width=3)
    handle_font = ImageFont.truetype(OUTFIT_BOLD, 28)
    _draw_centered(draw, "@valentinarussobg5", handle_font, handle_y, NEAR_W)

    return img


def main():
    parser = argparse.ArgumentParser(description="Instagram Story (1080x1920)")
    parser.add_argument("title", help="Titolo breve (2 righe auto-fit)")
    parser.add_argument("body", help="Testo hook/descrizione (max 4 righe)")
    parser.add_argument("photo", help="Path foto Valentina")
    parser.add_argument("out", help="Path PNG output")
    parser.add_argument("--type", choices=["iniziatore", "costruttore", "guida", "valutatore", "attualita"],
                         default="guida", help="Colore sfondo per Tipo HD (default: guida), o 'attualita' per casi di cronaca")
    parser.add_argument("--eyebrow", default="HUMAN DESIGN - BG5")
    args = parser.parse_args()

    img = build_story(args.title, args.body, Path(args.photo),
                       bg_color=TYPE_COLORS[args.type], eyebrow=args.eyebrow)
    out_path = Path(args.out).resolve()
    img.save(out_path, "PNG")
    print("[story] " + str(out_path) + " (" + str(img.size[0]) + "x" + str(img.size[1]) + ")")


if __name__ == "__main__":
    main()
