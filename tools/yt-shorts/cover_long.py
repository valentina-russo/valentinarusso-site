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
DARK_INK = "#31271D"   # marrone scuro delle cover a pannello chiaro

PLAYFAIR_BOLD       = str(FONTS_DIR / "PlayfairDisplay-Bold.ttf")
PLAYFAIR_BOLD_ITAL  = str(FONTS_DIR / "PlayfairDisplay-BoldItalic.ttf")
OUTFIT_BOLD         = str(FONTS_DIR / "Outfit-Bold.ttf")
OUTFIT_MEDIUM       = str(FONTS_DIR / "Outfit-Medium.ttf")

SCALE = 1.5  # 1280x720 (min YouTube) -> 1920x1080 (risoluzione reale del video sorgente, niente upscale)
W, H = int(1280 * SCALE), int(720 * SCALE)

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


TITLE_MAX   = int(106 * SCALE)  # px — titoli brevi (1-2 parole), pannello dominante — alzato 22/07/2026
TITLE_MIN   = int(66 * SCALE)   # px — floor assoluto: leggibile con Playfair — alzato 22/07/2026
TITLE_STEP  = int(6 * SCALE)    # px — step di riduzione per auto-fit
TITLE_MAX_LINES = 3  # max righe: 4 righe a 60px diventano troppo sottili a thumbnail

# Zona verticale sicura per il blocco titolo (tra eyebrow e brand bar)
SAFE_TOP    = int(130 * SCALE)  # px dal top canvas — sotto eyebrow + gap
SAFE_BOTTOM = int(630 * SCALE)  # px dal top canvas — sopra brand bar


def build_cover(title: str, eyebrow: str = "", photo_path: Path | None = None,
                 bg_color: str = NAVY, subtitle: str = "",
                 title_size: int | None = None, badge_text: str | None = "BG5® Analyst") -> Image.Image:
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
      - Valutatore/Riflettore: azzurro cielo (#B9D8EC), testi su marrone scuro
      - Default/generale: navy (#1A2332)

    title_size: None (default) = auto-fit tra TITLE_MIN e TITLE_MAX.
                Valore intero = override manuale (es. --title-size 72 per forzare).
    """
    split_x = int(W * 0.58)
    photo_panel_w = W - split_x

    # Adattamento contrasto: su bg navy gold funziona, su altri colori (es. arancione)
    # gold scompare → usare cream per eyebrow + badge text.
    def _luminanza(hexcol: str) -> float:
        h = hexcol.lstrip("#")
        r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
        return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255

    # Prima si confrontava il colore esatto con NAVY: qualsiasi altro sfondo
    # scuro (es. il bordeaux delle cover intervista) perdeva gli accenti oro.
    # Soglia 0.35 catturava anche i colori --type saturi (rosso Costruttore
    # 0.289, verde Guida 0.321), che nelle cover live hanno sempre eyebrow
    # bianca, non oro. 0.25 include ancora il bordeaux (~0.185) ma esclude
    # i colori --type.
    is_navy_bg = _luminanza(bg_color) < 0.25
    # Pannello molto chiaro (crema): il testo chiaro sparirebbe, quindi si
    # inverte su marrone scuro e l'oro torna leggibile per gli accenti.
    is_light_bg = _luminanza(bg_color) > 0.75
    eyebrow_color = GOLD if (is_navy_bg or is_light_bg) else NEAR_W
    badge_text_color = GOLD if is_navy_bg else NEAR_W
    title_color = DARK_INK if is_light_bg else WHITE
    subtitle_color = GOLD if is_light_bg else NEAR_W
    brand_color = DARK_INK if is_light_bg else NEAR_W

    # Right panel: photo
    img = Image.new("RGB", (W, H), bg_color)
    photo = _photo_for_right_panel(photo_path or DEFAULT_PHOTO, photo_panel_w, H, face_x_ratio=0.4)
    img.paste(photo, (split_x, 0))

    draw = ImageDraw.Draw(img)

    # Gold vertical accent line at split boundary
    draw.line([(split_x - 2, 0), (split_x - 2, H)], fill=GOLD, width=max(3, int(3 * SCALE)))

    # Subtle gradient on right edge of left panel (soft transition into photo)
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ov_draw = ImageDraw.Draw(overlay)
    fade_w = int(100 * SCALE)
    for i in range(fade_w):
        alpha = int(180 * (1.0 - i / fade_w) ** 1.5)
        ov_draw.line([(split_x + i, 0), (split_x + i, H)], fill=(0, 0, 0, alpha))
    img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")
    draw = ImageDraw.Draw(img)

    # Eyebrow uppercase (cream su bg non-navy per contrasto)
    if eyebrow:
        eyebrow_font = ImageFont.truetype(OUTFIT_BOLD, int(24 * SCALE))
        ey_x, ey_y = int(60 * SCALE), int(75 * SCALE)
        draw.text((ey_x, ey_y), eyebrow.upper(), font=eyebrow_font, fill=eyebrow_color)
        # Underline same color as eyebrow
        bbox = draw.textbbox((ey_x, ey_y), eyebrow.upper(), font=eyebrow_font)
        gap, ulen = int(8 * SCALE), int(40 * SCALE)
        draw.line([(ey_x, bbox[3] + gap), (ey_x + ulen, bbox[3] + gap)], fill=eyebrow_color, width=max(2, int(2*SCALE)))

    # Title: Playfair Bold, width-based wrap (greedy fill)
    max_title_w = split_x - int(110 * SCALE)

    def wrap_by_width(text: str, font) -> list[str]:
        """Greedy wrap basato sulla larghezza pixel reale. "\n" esplicito = a-capo forzato."""
        lines: list[str] = []
        for segment in text.split("\n"):
            words = segment.split()
            cur = ""
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
        sub_size = max(int(30 * SCALE), int(title_font_size * 0.42))  # alzato 22/07/2026
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
        draw.text((int(60*SCALE), y0 + i * line_height), line, font=title_font, fill=title_color)

    if subtitle_lines and subtitle_font is not None:
        y_sub = y0 + title_total_h + subtitle_gap
        for i, line in enumerate(subtitle_lines):
            draw.text((int(60*SCALE), y_sub + i * subtitle_line_height), line,
                      font=subtitle_font, fill=subtitle_color)

    # Bottom-left: gold line + brand
    line_y = H - int(80 * SCALE)
    draw.line([(int(60*SCALE), line_y), (int(160*SCALE), line_y)], fill=GOLD, width=max(3, int(3*SCALE)))

    brand_font = ImageFont.truetype(OUTFIT_BOLD, int(26 * SCALE))
    draw.text((int(60*SCALE), line_y + int(16*SCALE)), "@valentinarussobg5", font=brand_font, fill=brand_color)

    # Bottom-right (over photo, bottom-right corner): badge opzionale (default BG5 Analyst,
    # va disattivato con badge_text=None quando la foto non e' di Valentina, es. figura storica)
    if badge_text:
        badge_font = ImageFont.truetype(OUTFIT_MEDIUM, int(18 * SCALE))
        bbox = draw.textbbox((0, 0), badge_text, font=badge_font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        # Dark rounded box behind badge
        pad_x, pad_y = int(14 * SCALE), int(8 * SCALE)
        bo = int(70 * SCALE)
        box_x0 = W - bo - tw - pad_x * 2
        box_y0 = H - bo - th - pad_y * 2
        box_x1 = W - bo
        box_y1 = H - bo
        # Badge background = bg_color with alpha (matches left panel theme)
        bg_rgba = (*_hex_to_rgba(bg_color)[:3], 220)
        draw.rounded_rectangle([(box_x0, box_y0), (box_x1, box_y1)], radius=int(6*SCALE),
                                fill=bg_rgba)
        draw.text((box_x0 + pad_x, box_y0 + pad_y - int(2*SCALE)), badge_text, font=badge_font, fill=badge_text_color)

    return img


def main():
    parser = argparse.ArgumentParser(description="Cover YouTube long-form (1280x720)")
    parser.add_argument("title", help="Titolo principale (max ~40 chars per leggibilità)")
    parser.add_argument("out", nargs="?", default="cover-long.jpg", help="Path JPEG output")
    parser.add_argument("--eyebrow", default="", help="Eyebrow uppercase (opzionale)")
    parser.add_argument("--subtitle", default="", help="Sottotitolo italic sotto il titolo (opzionale)")
    parser.add_argument("--title-size", type=int, default=None,
                        help="Override dimensione font titolo (default: auto-fit tra 60-96px). "
                             "Ometti per auto-fit ottimale. Passa un valore per forzare.")
    parser.add_argument("--photo", default=None, help="Path foto custom (default: valentina.jpg)")
    parser.add_argument("--no-badge", action="store_true",
                         help="Disattiva il badge 'BG5 Analyst' (usare quando la foto non e' di Valentina)")
    parser.add_argument("--bg-color", default=NAVY,
                        help=f"Hex color pannello sinistro (default: {NAVY}). "
                             "Convenzione: Iniziatore #B85F2F (arancione), "
                             "Costruttore/Guida/Valutatore da definire.")
    parser.add_argument("--type", choices=["iniziatore", "costruttore", "guida", "valutatore", "attualita"],
                        help="Shortcut per --bg-color basato sul Tipo HD/BG5, o 'attualita' per la playlist di cronaca/attualità.")
    args = parser.parse_args()

    # Type shortcut → bg_color override
    type_colors = {
        "iniziatore": "#B85F2F",   # arancione (Manifestatore)
        "costruttore": "#C62828",  # rosso vivo (Generatore) — confermato 23/05/2026
        "guida": "#2C5F3F",        # verde scuro (Proiettore) — da confermare
        "valutatore": "#B9D8EC",   # azzurro cielo (Riflettore) — scelto da Marco 08/09/2026
        "attualita": "#3A3D42",    # grigio grafite (playlist Attualità, casi di cronaca) — 21/07/2026
    }
    bg_color = type_colors[args.type] if args.type else args.bg_color

    if args.photo == "pool":
        from photo_pool import pick_photo
        photo = pick_photo(tag=Path(args.out).stem)
        print(f"[photo-pool] {photo.name}")
    else:
        photo = Path(args.photo) if args.photo else None
        if photo is not None:
            from photo_pool import mark_used
            mark_used(photo, tag=Path(args.out).stem)
    args.subtitle = args.subtitle.replace("\\n", "\n")  # "\n" da CLI = a-capo forzato
    args.title = args.title.replace("\\n", "\n")  # "\n" da CLI = a-capo forzato anche sul titolo
    img = build_cover(args.title, args.eyebrow, photo, bg_color=bg_color,
                       subtitle=args.subtitle, title_size=args.title_size,
                       badge_text=None if args.no_badge else "BG5® Analyst")
    out_path = Path(args.out).resolve()
    # JPEG, non PNG: a 1920x1080 un PNG lossless supera facilmente il limite
    # di 2MB dell'API thumbnails().set() di YouTube. JPEG qualita 92 resta
    # nitido e sta ben sotto il limite.
    img.convert("RGB").save(out_path, "JPEG", quality=92)
    print(f"[cover] {out_path} ({img.size[0]}x{img.size[1]})")


if __name__ == "__main__":
    main()
