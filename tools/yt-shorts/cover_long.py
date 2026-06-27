"""
YouTube long-form video cover (1280x720, 16:9) per Valentina Russo BG5.

Layout: foto Valentina right-bleed + dark gradient left + titolo bianco grande + brand bottom.

Uso:
    py cover_long.py "TITOLO BREVE" [out_path] [--eyebrow "EYEBROW"]
    py cover_long.py "FARMACI E HUMAN DESIGN" cover.png --eyebrow "AUTORITÀ EMOZIONALE"

Output: 1280x720 PNG.
"""
from __future__ import annotations

import sys
import argparse
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

HERE = Path(__file__).resolve().parent
FONTS_DIR = HERE.parent / "bg5-generator" / "fonts"

# Brand palette
NAVY     = "#1A2332"
ROSA     = "#B68397"
GOLD     = "#C48A3A"
CREAM    = "#FAF7F5"
WHITE    = "#FFFFFF"
NEAR_W   = "#F4EFE9"

PLAYFAIR_BOLD       = str(FONTS_DIR / "PlayfairDisplay-Bold.ttf")
PLAYFAIR_BOLD_ITAL  = str(FONTS_DIR / "PlayfairDisplay-BoldItalic.ttf")
OUTFIT_BOLD         = str(FONTS_DIR / "Outfit-Bold.ttf")
OUTFIT_MEDIUM       = str(FONTS_DIR / "Outfit-Medium.ttf")

W, H = 1280, 720

PROJECT_ROOT = HERE.parent.parent
ASSETS_DIR = PROJECT_ROOT / "grav-site" / "user" / "pages" / "assets"
DEFAULT_PHOTO = ASSETS_DIR / "valentina.jpg"  # 1600x1200, 4:3


def _wrap(text: str, max_chars: int) -> list[str]:
    """Word-wrap testo con max chars per riga."""
    words = text.split()
    lines, cur = [], ""
    for w in words:
        if not cur:
            cur = w
        elif len(cur) + 1 + len(w) <= max_chars:
            cur += " " + w
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def _photo_landscape(photo_path: Path, target_w: int = W, target_h: int = H,
                     focus_x: float = 0.85) -> Image.Image:
    """Crop+resize foto. focus_x 0.85 = sposta il framing verso destra (foto della destra)."""
    photo = Image.open(photo_path).convert("RGB")
    pw, ph = photo.size
    target_ratio = target_w / target_h
    photo_ratio = pw / ph
    if photo_ratio > target_ratio:
        # Photo is wider — crop sides, focus_x controls horizontal position
        new_w = int(ph * target_ratio)
        max_x = pw - new_w
        x0 = int(max_x * focus_x)
        photo = photo.crop((x0, 0, x0 + new_w, ph))
    else:
        # Photo is taller — crop top/bottom
        new_h = int(pw / target_ratio)
        y0 = int((ph - new_h) * 0.10)
        photo = photo.crop((0, y0, pw, y0 + new_h))
    return photo.resize((target_w, target_h), Image.LANCZOS)


def _hex_to_rgba(hex_str: str, alpha: int = 255) -> tuple[int, int, int, int]:
    hex_str = hex_str.lstrip("#")
    return (int(hex_str[0:2], 16), int(hex_str[2:4], 16), int(hex_str[4:6], 16), alpha)


def _gradient_left(strength: float = 1.0) -> Image.Image:
    """Gradient navy: solido sinistra 50%, fade rapido tra 50-70%, trasparente oltre."""
    grad = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    px = grad.load()
    nav = _hex_to_rgba(NAVY)
    solid_until = int(W * 0.50)
    fade_until = int(W * 0.75)
    for x in range(W):
        if x < solid_until:
            a = int(255 * strength)
        elif x < fade_until:
            t = (x - solid_until) / (fade_until - solid_until)
            # ease-out
            a = int(255 * strength * (1.0 - t) ** 1.5)
        else:
            a = 0
        if a < 0:
            a = 0
        for y in range(H):
            px[x, y] = (nav[0], nav[1], nav[2], a)
    return grad


def _photo_for_right_panel(photo_path: Path, panel_w: int, panel_h: int,
                            face_x_ratio: float = 0.4) -> Image.Image:
    """
    Crop foto per riempire il pannello destro mantenendo il viso visibile.
    face_x_ratio: posizione orizzontale del viso nella sorgente (0=left, 1=right).
    """
    photo = Image.open(photo_path).convert("RGB")
    pw, ph = photo.size
    target_ratio = panel_w / panel_h
    # Calcola la larghezza/altezza ideale del crop dalla sorgente
    photo_ratio = pw / ph
    if photo_ratio > target_ratio:
        # Sorgente più larga → crop ai lati. Centra sul viso.
        new_w = int(ph * target_ratio)
        x_center = int(pw * face_x_ratio)
        x0 = max(0, min(pw - new_w, x_center - new_w // 2))
        photo = photo.crop((x0, 0, x0 + new_w, ph))
    else:
        # Sorgente più alta → crop verticale, mantieni full width.
        new_h = int(pw / target_ratio)
        y0 = max(0, int((ph - new_h) * 0.05))  # leggera preferenza per parte alta
        photo = photo.crop((0, y0, pw, y0 + new_h))
    return photo.resize((panel_w, panel_h), Image.LANCZOS)


TITLE_MAX   = 96   # px — titoli brevi (1-2 parole), pannello dominante
TITLE_MIN   = 60   # px — floor assoluto: ~15px a thumbnail 320px, leggibile con Playfair
TITLE_STEP  =  6   # px — step di riduzione per auto-fit
TITLE_MAX_LINES = 3  # max righe: 4 righe a 60px diventano troppo sottili a thumbnail

# Zona verticale sicura per il blocco titolo (tra eyebrow e brand bar)
SAFE_TOP    = 130  # px dal top canvas — sotto eyebrow + gap
SAFE_BOTTOM = 630  # px dal top canvas — sopra brand bar


def build_cover(title: str, eyebrow: str = "", photo_path: Path | None = None,
                 bg_color: str = NAVY, subtitle: str = "",
                 title_size: int | None = None) -> Image.Image:
    """
    Magazine split layout:
    - LEFT 60% panel: bg_color solido con eyebrow gold + titolo bianco Playfair + brand
    - RIGHT 40% panel: foto Valentina (crop centrato sul viso)
    - Border line gold tra i due pannelli

    bg_color: hex color del pannello sinistro. Default NAVY (#1A2332).
    Convenzione colori per Tipo Valentina:
      - Iniziatore/Manifestatore: arancione (#B85F2F)
      - Costruttore/Generatore: rosso scuro (#8B2C2C) [da confermare]
      - Guida/Proiettore: verde scuro (#2C5F3F) [da confermare]
      - Valutatore/Riflettore: blu scuro (#1F3A5F) [da confermare]
      - Default/generale: navy (#1A2332)

    title_size: None (default) = auto-fit tra TITLE_MIN e TITLE_MAX.
                Valore intero = override manuale (es. --title-size 72 per forzare).
    """
    split_x = int(W * 0.58)
    photo_panel_w = W - split_x

    # Adattamento contrasto: su bg navy gold funziona, su altri colori (es. arancione)
    # gold scompare → usare cream per eyebrow + badge text.
    is_navy_bg = bg_color.lower() in (NAVY.lower(), "#1a2332")
    eyebrow_color = GOLD if is_navy_bg else NEAR_W
    badge_text_color = GOLD if is_navy_bg else NEAR_W

    # Right panel: photo
    img = Image.new("RGB", (W, H), bg_color)
    photo = _photo_for_right_panel(photo_path or DEFAULT_PHOTO, photo_panel_w, H, face_x_ratio=0.4)
    img.paste(photo, (split_x, 0))

    draw = ImageDraw.Draw(img)

    # Gold vertical accent line at split boundary
    draw.line([(split_x - 2, 0), (split_x - 2, H)], fill=GOLD, width=3)

    # Subtle gradient on right edge of left panel (soft transition into photo)
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ov_draw = ImageDraw.Draw(overlay)
    fade_w = 100
    for i in range(fade_w):
        alpha = int(180 * (1.0 - i / fade_w) ** 1.5)
        ov_draw.line([(split_x + i, 0), (split_x + i, H)], fill=(0, 0, 0, alpha))
    img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")
    draw = ImageDraw.Draw(img)

    # Eyebrow uppercase (cream su bg non-navy per contrasto)
    if eyebrow:
        eyebrow_font = ImageFont.truetype(OUTFIT_BOLD, 24)
        draw.text((60, 75), eyebrow.upper(), font=eyebrow_font, fill=eyebrow_color)
        # Underline same color as eyebrow
        bbox = draw.textbbox((60, 75), eyebrow.upper(), font=eyebrow_font)
        draw.line([(60, bbox[3] + 8), (60 + 40, bbox[3] + 8)], fill=eyebrow_color, width=2)

    # Title: Playfair Bold, width-based wrap (greedy fill)
    max_title_w = split_x - 110

    def wrap_by_width(text: str, font) -> list[str]:
        """Greedy wrap basato sulla larghezza pixel reale."""
        words = text.split()
        lines, cur = [], ""
        for w in words:
            candidate = (cur + " " + w).strip() if cur else w
            bbox = draw.textbbox((0, 0), candidate, font=font)
            if bbox[2] - bbox[0] <= max_title_w:
                cur = candidate
            else:
                if cur:
                    lines.append(cur)
                cur = w
        if cur:
            lines.append(cur)
        return lines

    # Auto-fit: parte da TITLE_MAX (o dall'override manuale) e scende di TITLE_STEP
    # finché il titolo non sta in TITLE_MAX_LINES righe E il blocco totale entra
    # nella safe zone verticale (SAFE_TOP→SAFE_BOTTOM = 500px).
    safe_h = SAFE_BOTTOM - SAFE_TOP
    start_size = title_size if title_size is not None else TITLE_MAX

    title_font_size = start_size
    title_font = ImageFont.truetype(PLAYFAIR_BOLD, title_font_size)
    lines = wrap_by_width(title, title_font)

    if title_size is None:
        # Modalità auto-fit: cerca la dimensione più grande che soddisfa i vincoli
        for candidate_size in range(start_size, TITLE_MIN - 1, -TITLE_STEP):
            title_font = ImageFont.truetype(PLAYFAIR_BOLD, candidate_size)
            lines = wrap_by_width(title, title_font)
            line_h = int(candidate_size * 1.10)
            block_h = line_h * min(len(lines), TITLE_MAX_LINES)
            if len(lines) <= TITLE_MAX_LINES and block_h <= safe_h:
                title_font_size = candidate_size
                break
        else:
            # Nessuna dimensione soddisfa i vincoli: usa il minimo e tronca
            title_font_size = TITLE_MIN
            title_font = ImageFont.truetype(PLAYFAIR_BOLD, title_font_size)
            lines = wrap_by_width(title, title_font)
    else:
        # Override manuale: applica lo stesso max-lines per coerenza
        pass

    lines = lines[:TITLE_MAX_LINES]
    line_height = int(title_font_size * 1.10)
    title_total_h = line_height * len(lines)

    # Subtitle setup (Playfair BoldItalic, sotto il titolo, color near-white)
    # Ratio 0.36 mantiene il subtitle chiaramente sotto-ordinato anche a dimensioni grandi
    subtitle_lines: list[str] = []
    subtitle_font = None
    subtitle_line_height = 0
    subtitle_total_h = 0
    subtitle_gap = 0
    if subtitle:
        sub_size = max(26, int(title_font_size * 0.36))
        subtitle_font = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, sub_size)
        # Wrap subtitle by width (same max_title_w)
        def _wrap_sub(text: str, font) -> list[str]:
            out = []
            for seg in text.split("\n"):  # "\n" esplicito = a-capo forzato
                words = seg.split()
                cur = ""
                for w in words:
                    cand = (cur + " " + w).strip() if cur else w
                    bb = draw.textbbox((0, 0), cand, font=font)
                    if bb[2] - bb[0] <= max_title_w:
                        cur = cand
                    else:
                        if cur:
                            out.append(cur)
                        cur = w
                if cur:
                    out.append(cur)
            return out
        subtitle_lines = _wrap_sub(subtitle, subtitle_font)
        subtitle_line_height = int(sub_size * 1.18)
        subtitle_total_h = subtitle_line_height * len(subtitle_lines)
        # Gap proporzionale alla dimensione del titolo: respira di più con titoli grandi
        subtitle_gap = int(title_font_size * 0.28)

    total_h = title_total_h + subtitle_gap + subtitle_total_h
    # Centra nella safe zone (tra eyebrow e brand bar), non sull'intera altezza canvas
    y0 = SAFE_TOP + (safe_h - total_h) // 2

    for i, line in enumerate(lines):
        draw.text((60, y0 + i * line_height), line, font=title_font, fill=WHITE)

    if subtitle_lines and subtitle_font is not None:
        y_sub = y0 + title_total_h + subtitle_gap
        for i, line in enumerate(subtitle_lines):
            draw.text((60, y_sub + i * subtitle_line_height), line,
                      font=subtitle_font, fill=NEAR_W)

    # Bottom-left: gold line + brand
    line_y = H - 80
    draw.line([(60, line_y), (160, line_y)], fill=GOLD, width=3)

    brand_font = ImageFont.truetype(OUTFIT_BOLD, 26)
    draw.text((60, line_y + 16), "@valentinarussobg5", font=brand_font, fill=NEAR_W)

    # Bottom-right (over photo, bottom-right corner): BG5 Analyst small badge
    badge_font = ImageFont.truetype(OUTFIT_MEDIUM, 18)
    badge_text = "BG5® Analyst"
    bbox = draw.textbbox((0, 0), badge_text, font=badge_font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    # Dark rounded box behind badge
    pad_x, pad_y = 14, 8
    box_x0 = W - 70 - tw - pad_x * 2
    box_y0 = H - 70 - th - pad_y * 2
    box_x1 = W - 70
    box_y1 = H - 70
    # Badge background = bg_color with alpha (matches left panel theme)
    bg_rgba = (*_hex_to_rgba(bg_color)[:3], 220)
    draw.rounded_rectangle([(box_x0, box_y0), (box_x1, box_y1)], radius=6,
                            fill=bg_rgba)
    draw.text((box_x0 + pad_x, box_y0 + pad_y - 2), badge_text, font=badge_font, fill=badge_text_color)

    return img


def main():
    parser = argparse.ArgumentParser(description="Cover YouTube long-form (1280x720)")
    parser.add_argument("title", help="Titolo principale (max ~40 chars per leggibilità)")
    parser.add_argument("out", nargs="?", default="cover-long.png", help="Path PNG output")
    parser.add_argument("--eyebrow", default="", help="Eyebrow uppercase (opzionale)")
    parser.add_argument("--subtitle", default="", help="Sottotitolo italic sotto il titolo (opzionale)")
    parser.add_argument("--title-size", type=int, default=None,
                        help="Override dimensione font titolo (default: auto-fit tra 60-96px). "
                             "Ometti per auto-fit ottimale. Passa un valore per forzare.")
    parser.add_argument("--photo", default=None, help="Path foto custom (default: valentina.jpg)")
    parser.add_argument("--bg-color", default=NAVY,
                        help=f"Hex color pannello sinistro (default: {NAVY}). "
                             "Convenzione: Iniziatore #B85F2F (arancione), "
                             "Costruttore/Guida/Valutatore da definire.")
    parser.add_argument("--type", choices=["iniziatore", "costruttore", "guida", "valutatore"],
                        help="Shortcut per --bg-color basato sul Tipo HD/BG5.")
    args = parser.parse_args()

    # Type shortcut → bg_color override
    type_colors = {
        "iniziatore": "#B85F2F",   # arancione (Manifestatore)
        "costruttore": "#C62828",  # rosso vivo (Generatore) — confermato 23/05/2026
        "guida": "#2C5F3F",        # verde scuro (Proiettore) — da confermare
        "valutatore": "#1F3A5F",   # blu scuro (Riflettore) — da confermare
    }
    bg_color = type_colors[args.type] if args.type else args.bg_color

    if args.photo == "pool":
        from photo_pool import pick_photo
        photo = pick_photo(tag=Path(args.out).stem)
        print(f"[photo-pool] {photo.name}")
    else:
        photo = Path(args.photo) if args.photo else None
    args.subtitle = args.subtitle.replace("\\n", "\n")  # "\n" da CLI = a-capo forzato
    img = build_cover(args.title, args.eyebrow, photo, bg_color=bg_color,
                       subtitle=args.subtitle, title_size=args.title_size)
    out_path = Path(args.out).resolve()
    img.save(out_path, "PNG", quality=95)
    print(f"[cover] {out_path} ({img.size[0]}x{img.size[1]})")


if __name__ == "__main__":
    main()
