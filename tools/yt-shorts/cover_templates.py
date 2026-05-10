"""
YouTube Shorts cover templates for Valentina Russo BG5.

8 design variants:
  1-5: tipografici puri (no foto)
  6-8: foto Valentina background + overlay scuro per leggibilità
Output: 1080x1920 PNG.

Brand:
  - Fonts: Playfair Display, Outfit, IM Fell English
  - Palette: navy #1E3A5F, rosa #B68397, gold #C48A3A, cream #FAF7F5,
    ink #1C1210, teal #5DAEB1, muted mauve #7a5c6e
  - Foto Valentina: grav-site/user/pages/assets/valentina.png (1080x1080)
"""
from __future__ import annotations

import textwrap
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter

# ─── Brand ────────────────────────────────────────────────────────────────────
HERE = Path(__file__).resolve().parent
FONTS_DIR = HERE.parent / "bg5-generator" / "fonts"

NAVY      = "#1E3A5F"
ROSA      = "#B68397"
GOLD      = "#C48A3A"
CREAM     = "#FAF7F5"
INK       = "#1C1210"
TEAL      = "#5DAEB1"
MAUVE     = "#7a5c6e"
WHITE     = "#FFFFFF"
NEAR_WHITE = "#F4EFE9"

W, H = 1080, 1920

PROJECT_ROOT = HERE.parent.parent
ASSETS_DIR = PROJECT_ROOT / "grav-site" / "user" / "pages" / "assets"
DEFAULT_PHOTO = ASSETS_DIR / "valentina.png"

# Foto disponibili (escludiamo duplicati in dist/ e foto bassa-qualità)
PHOTOS = {
    "square":      ASSETS_DIR / "valentina.png",            # 1080x1080
    "landscape":   ASSETS_DIR / "valentina.jpg",            # 1600x1200
    "vertical":    ASSETS_DIR / "valentina-privati.jpg",    # 886x1024
    "aziende":     ASSETS_DIR / "valentina-aziende-hero2.png",  # 657x769
}

PLAYFAIR_BOLD       = str(FONTS_DIR / "PlayfairDisplay-Bold.ttf")
PLAYFAIR_BOLD_ITAL  = str(FONTS_DIR / "PlayfairDisplay-BoldItalic.ttf")
PLAYFAIR_ITAL       = str(FONTS_DIR / "PlayfairDisplay-Italic.ttf")
OUTFIT_BOLD         = str(FONTS_DIR / "Outfit-Bold.ttf")
OUTFIT_MEDIUM       = str(FONTS_DIR / "Outfit-Medium.ttf")
OUTFIT_REGULAR      = str(FONTS_DIR / "Outfit-Regular.ttf")
IM_FELL_REGULAR     = str(FONTS_DIR / "IMFellEnglish-Regular.ttf")


# ─── Helpers ──────────────────────────────────────────────────────────────────

def _wrap_text(text: str, max_chars: int) -> list[str]:
    """Manual wrap with safe line breaks for headlines."""
    return textwrap.wrap(text, width=max_chars, break_long_words=False)


def _draw_text_block(draw: ImageDraw.ImageDraw, lines: list[str], font: ImageFont.FreeTypeFont,
                     fill: str, x_center: int, y_top: int, line_gap: int = 12) -> int:
    """Draw multi-line text, return final y."""
    y = y_top
    for line in lines:
        bbox = draw.textbbox((0, 0), line, font=font)
        w = bbox[2] - bbox[0]
        h = bbox[3] - bbox[1]
        draw.text((x_center - w // 2, y), line, font=font, fill=fill)
        y += h + line_gap
    return y


def _draw_text_left(draw: ImageDraw.ImageDraw, lines: list[str], font: ImageFont.FreeTypeFont,
                    fill: str, x_left: int, y_top: int, line_gap: int = 12) -> int:
    y = y_top
    for line in lines:
        bbox = draw.textbbox((0, 0), line, font=font)
        h = bbox[3] - bbox[1]
        draw.text((x_left, y), line, font=font, fill=fill)
        y += h + line_gap
    return y


def _gradient_vertical(top_color: str, bottom_color: str) -> Image.Image:
    """Vertical gradient image."""
    img = Image.new("RGB", (W, H))
    draw = ImageDraw.Draw(img)
    t = tuple(int(top_color[i:i + 2], 16) for i in (1, 3, 5))
    b = tuple(int(bottom_color[i:i + 2], 16) for i in (1, 3, 5))
    for y in range(H):
        ratio = y / H
        r = int(t[0] * (1 - ratio) + b[0] * ratio)
        g = int(t[1] * (1 - ratio) + b[1] * ratio)
        bb = int(t[2] * (1 - ratio) + b[2] * ratio)
        draw.line([(0, y), (W, y)], fill=(r, g, bb))
    return img


# ─── Template 1: Editorial dark navy ──────────────────────────────────────────

def template_1_editorial_dark(title: str, subtitle: str = "Human Design · BG5") -> Image.Image:
    """Bg navy, titolo Playfair Italic white grande, ornamento gold, brand piccolo."""
    img = Image.new("RGB", (W, H), NAVY)
    draw = ImageDraw.Draw(img)

    # Top eyebrow
    eyebrow_font = ImageFont.truetype(IM_FELL_REGULAR, 42)
    eyebrow = "— Valentina Russo —"
    bbox = draw.textbbox((0, 0), eyebrow, font=eyebrow_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, 200), eyebrow, font=eyebrow_font, fill=GOLD)

    # Gold thin line
    draw.line([(W // 2 - 80, 280), (W // 2 + 80, 280)], fill=GOLD, width=2)

    # Title — Playfair Italic 110pt
    title_font = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, 110)
    lines = _wrap_text(title, max_chars=18)
    total_h = len(lines) * 130
    y_start = (H - total_h) // 2 - 60
    _draw_text_block(draw, lines, title_font, WHITE, W // 2, y_start, line_gap=20)

    # Bottom: subtitle + brand
    subtitle_font = ImageFont.truetype(OUTFIT_MEDIUM, 36)
    bbox = draw.textbbox((0, 0), subtitle, font=subtitle_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 280), subtitle, font=subtitle_font, fill=NEAR_WHITE)

    # Gold line again
    draw.line([(W // 2 - 60, H - 220), (W // 2 + 60, H - 220)], fill=GOLD, width=2)

    brand_font = ImageFont.truetype(OUTFIT_BOLD, 32)
    brand = "@valentinarussobg5"
    bbox = draw.textbbox((0, 0), brand, font=brand_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 170), brand, font=brand_font, fill=GOLD)

    return img


# ─── Template 2: Cream parchment ──────────────────────────────────────────────

def template_2_cream_parchment(title: str, subtitle: str = "Human Design · BG5") -> Image.Image:
    """Bg cream, titolo Playfair Bold navy, accent gold piccolo."""
    img = Image.new("RGB", (W, H), CREAM)
    draw = ImageDraw.Draw(img)

    # Top: small gold square
    draw.rectangle([(W // 2 - 30, 220), (W // 2 + 30, 280)], fill=GOLD)

    # Eyebrow
    eyebrow_font = ImageFont.truetype(OUTFIT_MEDIUM, 32)
    eyebrow = "VALENTINA RUSSO"
    bbox = draw.textbbox((0, 0), eyebrow, font=eyebrow_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, 320), eyebrow, font=eyebrow_font, fill=MAUVE)

    # Title — Playfair Bold 120pt navy
    title_font = ImageFont.truetype(PLAYFAIR_BOLD, 120)
    lines = _wrap_text(title, max_chars=14)
    total_h = len(lines) * 140
    y_start = (H - total_h) // 2 - 40
    _draw_text_block(draw, lines, title_font, NAVY, W // 2, y_start, line_gap=20)

    # Bottom: signature italic
    sig_font = ImageFont.truetype(PLAYFAIR_ITAL, 44)
    sig = "BG5® Analyst Certificata"
    bbox = draw.textbbox((0, 0), sig, font=sig_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 280), sig, font=sig_font, fill=INK)

    # Gold thin line
    draw.line([(200, H - 200), (W - 200, H - 200)], fill=GOLD, width=2)

    brand_font = ImageFont.truetype(OUTFIT_BOLD, 30)
    brand = "valentinarussobg5.com"
    bbox = draw.textbbox((0, 0), brand, font=brand_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 150), brand, font=brand_font, fill=NAVY)

    return img


# ─── Template 3: Magazine quote box ───────────────────────────────────────────

def template_3_magazine_quote(title: str, subtitle: str = "") -> Image.Image:
    """Bg ink, titolo Playfair Bold cream in box gold-bordered."""
    img = Image.new("RGB", (W, H), INK)
    draw = ImageDraw.Draw(img)

    # Top label
    label_font = ImageFont.truetype(OUTFIT_BOLD, 30)
    label = "HUMAN DESIGN · ITALIA"
    bbox = draw.textbbox((0, 0), label, font=label_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, 240), label, font=label_font, fill=GOLD)

    # Box border
    box_top = 480
    box_bottom = H - 480
    margin = 120
    draw.rectangle([(margin, box_top), (W - margin, box_bottom)], outline=GOLD, width=3)

    # Title centered in box
    title_font = ImageFont.truetype(PLAYFAIR_BOLD, 100)
    lines = _wrap_text(title, max_chars=15)
    total_h = len(lines) * 120
    y_start = box_top + (box_bottom - box_top - total_h) // 2
    _draw_text_block(draw, lines, title_font, NEAR_WHITE, W // 2, y_start, line_gap=20)

    # Bottom signature
    sig_font = ImageFont.truetype(PLAYFAIR_ITAL, 44)
    sig = "— Valentina Russo"
    bbox = draw.textbbox((0, 0), sig, font=sig_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 380), sig, font=sig_font, fill=GOLD)

    brand_font = ImageFont.truetype(OUTFIT_MEDIUM, 30)
    brand = "@valentinarussobg5  ·  valentinarussobg5.com"
    bbox = draw.textbbox((0, 0), brand, font=brand_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 200), brand, font=brand_font, fill=NEAR_WHITE)

    return img


# ─── Template 4: Minimalist editorial white ───────────────────────────────────

def template_4_minimalist_white(title: str, subtitle: str = "Human Design") -> Image.Image:
    """Bg white, titolo black Playfair big sinistra, linea sottile gold."""
    img = Image.new("RGB", (W, H), NEAR_WHITE)
    draw = ImageDraw.Draw(img)

    # Top number/category
    cat_font = ImageFont.truetype(IM_FELL_REGULAR, 48)
    cat = "Nº 01"
    draw.text((100, 220), cat, font=cat_font, fill=GOLD)

    # Long horizontal gold line
    draw.line([(100, 320), (W - 100, 320)], fill=GOLD, width=2)

    # Subcategory
    sub_font = ImageFont.truetype(OUTFIT_BOLD, 28)
    sub = "HUMAN DESIGN  ·  CONSULENZA"
    draw.text((100, 360), sub, font=sub_font, fill=MAUVE)

    # Title — Playfair Bold 110pt left-aligned
    title_font = ImageFont.truetype(PLAYFAIR_BOLD, 115)
    lines = _wrap_text(title, max_chars=14)
    y_start = 560
    _draw_text_left(draw, lines, title_font, INK, 100, y_start, line_gap=18)

    # Bottom: signature
    draw.line([(100, H - 320), (300, H - 320)], fill=GOLD, width=2)

    sig_font = ImageFont.truetype(PLAYFAIR_ITAL, 44)
    draw.text((100, H - 290), "Valentina Russo", font=sig_font, fill=INK)

    role_font = ImageFont.truetype(OUTFIT_MEDIUM, 30)
    draw.text((100, H - 230), "BG5® Analyst Certificata", font=role_font, fill=MAUVE)

    brand_font = ImageFont.truetype(OUTFIT_BOLD, 28)
    draw.text((100, H - 170), "valentinarussobg5.com", font=brand_font, fill=NAVY)

    return img


# ─── Template 5: Dramatic gradient navy→rosa ──────────────────────────────────

def template_5_dramatic_gradient(title: str, subtitle: str = "Human Design") -> Image.Image:
    """Gradient verticale navy→rosa, titolo Playfair Italic white grande, ornamento."""
    img = _gradient_vertical(NAVY, ROSA)
    draw = ImageDraw.Draw(img)

    # Top ornament: gold horizontal line + dot
    draw.line([(W // 2 - 200, 240), (W // 2 - 30, 240)], fill=GOLD, width=2)
    draw.ellipse([(W // 2 - 18, 228), (W // 2 + 18, 252)], fill=GOLD)
    draw.line([(W // 2 + 30, 240), (W // 2 + 200, 240)], fill=GOLD, width=2)

    # Eyebrow
    eyebrow_font = ImageFont.truetype(OUTFIT_BOLD, 36)
    eyebrow = "L A   T U A   M A P P A"
    bbox = draw.textbbox((0, 0), eyebrow, font=eyebrow_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, 290), eyebrow, font=eyebrow_font, fill=GOLD)

    # Title — Playfair Italic 105pt white
    title_font = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, 105)
    lines = _wrap_text(title, max_chars=16)
    total_h = len(lines) * 125
    y_start = (H - total_h) // 2 - 50
    _draw_text_block(draw, lines, title_font, WHITE, W // 2, y_start, line_gap=18)

    # Bottom ornament + brand
    draw.line([(W // 2 - 200, H - 280), (W // 2 - 30, H - 280)], fill=GOLD, width=2)
    draw.ellipse([(W // 2 - 18, H - 292), (W // 2 + 18, H - 268)], fill=GOLD)
    draw.line([(W // 2 + 30, H - 280), (W // 2 + 200, H - 280)], fill=GOLD, width=2)

    brand_font = ImageFont.truetype(OUTFIT_BOLD, 36)
    brand = "VALENTINA RUSSO"
    bbox = draw.textbbox((0, 0), brand, font=brand_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 230), brand, font=brand_font, fill=WHITE)

    sub_font = ImageFont.truetype(OUTFIT_MEDIUM, 28)
    sub = "BG5® · valentinarussobg5.com"
    bbox = draw.textbbox((0, 0), sub, font=sub_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 180), sub, font=sub_font, fill=NEAR_WHITE)

    return img


# ─── Photo helpers ────────────────────────────────────────────────────────────

def _load_photo(photo_path: Path | None = None) -> Image.Image:
    """Load photo, return RGB Image."""
    p = photo_path or DEFAULT_PHOTO
    if not p.exists():
        raise FileNotFoundError(f"Foto non trovata: {p}")
    return Image.open(p).convert("RGB")


def _photo_to_vertical(photo: Image.Image, target_w: int = W, target_h: int = H,
                       focus_y: float = 0.35) -> Image.Image:
    """
    Rifit photo to target_w x target_h.
    Se la foto è più larga di alta (landscape) o quadrata, la scala in modo che
    la larghezza copra target_w, poi crop verticale centrato su focus_y (0..1)
    dove 0=top, 1=bottom. focus_y=0.35 mette il volto in alto-centro.

    Se la foto è più alta di larga (portrait), scala in modo che l'altezza copra
    target_h, poi crop orizzontale centrato.
    """
    pw, ph = photo.size
    target_ratio = target_w / target_h
    photo_ratio = pw / ph

    if photo_ratio > target_ratio:
        # photo più larga: scala su altezza, poi crop orizzontale
        new_h = target_h
        new_w = int(pw * (new_h / ph))
        scaled = photo.resize((new_w, new_h), Image.LANCZOS)
        x_offset = (new_w - target_w) // 2
        return scaled.crop((x_offset, 0, x_offset + target_w, target_h))
    else:
        # photo più stretta o quadrata: scala su larghezza, poi crop verticale
        new_w = target_w
        new_h = int(ph * (new_w / pw))
        scaled = photo.resize((new_w, new_h), Image.LANCZOS)
        # crop con focus_y: il volto resta in alto-centro
        # focus_y indica dove vogliamo il "centro" della crop nell'immagine scalata
        focus_pixel = int(new_h * focus_y)
        y_offset = max(0, min(focus_pixel - target_h // 3, new_h - target_h))
        return scaled.crop((0, y_offset, target_w, y_offset + target_h))


def _gradient_overlay(direction: str = "bottom-up", strength: float = 0.85) -> Image.Image:
    """
    Crea un overlay RGBA W x H con gradient di trasparenza.
    direction='bottom-up': nero opaco in basso, trasparente in alto
    direction='full-dark': nero uniforme con alpha=strength
    direction='vignette': bordi scuri, centro più chiaro
    """
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    if direction == "bottom-up":
        for y in range(H):
            ratio = y / H
            alpha = int(255 * strength * (ratio ** 1.4))
            draw.line([(0, y), (W, y)], fill=(0, 0, 0, alpha))
    elif direction == "full-dark":
        draw.rectangle([(0, 0), (W, H)], fill=(0, 0, 0, int(255 * strength)))
    elif direction == "top-down":
        for y in range(H):
            ratio = 1 - (y / H)
            alpha = int(255 * strength * (ratio ** 1.4))
            draw.line([(0, y), (W, y)], fill=(0, 0, 0, alpha))
    elif direction == "both-ends":
        # dark top + dark bottom, clear middle
        for y in range(H):
            top_ratio = max(0, 1 - (y / (H * 0.35)))
            bot_ratio = max(0, (y - H * 0.55) / (H * 0.45))
            alpha = int(255 * strength * max(top_ratio, bot_ratio) ** 1.5)
            draw.line([(0, y), (W, y)], fill=(0, 0, 0, alpha))
    return overlay


# ─── Template 6: Photo + dark bottom gradient ─────────────────────────────────

def template_6_photo_bottom_dark(title: str, subtitle: str = "Human Design",
                                  photo_path: Path | None = None) -> Image.Image:
    """Foto Valentina full-bleed + gradient dark dal basso. Titolo Playfair Italic in basso."""
    photo = _load_photo(photo_path)
    bg = _photo_to_vertical(photo, focus_y=0.30).convert("RGBA")

    overlay = _gradient_overlay("bottom-up", strength=0.92)
    bg = Image.alpha_composite(bg, overlay)
    img = bg.convert("RGB")
    draw = ImageDraw.Draw(img)

    # Top: piccolo brand label
    label_font = ImageFont.truetype(OUTFIT_BOLD, 30)
    label = "VALENTINA RUSSO"
    bbox = draw.textbbox((0, 0), label, font=label_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, 90), label, font=label_font,
              fill=(255, 255, 255, 230), stroke_width=1, stroke_fill=(0, 0, 0, 200))

    # Gold dot under label
    draw.ellipse([(W // 2 - 5, 145), (W // 2 + 5, 155)], fill=GOLD)

    # Title — Playfair Italic 100pt, posizionato nella metà inferiore
    title_font = ImageFont.truetype(PLAYFAIR_BOLD_ITAL, 100)
    lines = _wrap_text(title, max_chars=15)
    total_h = len(lines) * 120
    y_start = H - 480 - total_h
    _draw_text_block(draw, lines, title_font, WHITE, W // 2, y_start, line_gap=20)

    # Gold thin line + brand bottom
    draw.line([(W // 2 - 100, H - 280), (W // 2 + 100, H - 280)], fill=GOLD, width=2)

    sub_font = ImageFont.truetype(OUTFIT_MEDIUM, 32)
    sub = "BG5® Analyst Certificata"
    bbox = draw.textbbox((0, 0), sub, font=sub_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 240), sub, font=sub_font, fill=GOLD)

    brand_font = ImageFont.truetype(OUTFIT_BOLD, 32)
    brand = "@valentinarussobg5"
    bbox = draw.textbbox((0, 0), brand, font=brand_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 175), brand, font=brand_font, fill=NEAR_WHITE)

    return img


# ─── Template 7: Photo top half + magazine box bottom ─────────────────────────

def template_7_photo_split_magazine(title: str, subtitle: str = "Human Design",
                                     photo_path: Path | None = None) -> Image.Image:
    """Foto top 55%, blocco solido cream bottom 45% con titolo Playfair Bold navy."""
    img = Image.new("RGB", (W, H), CREAM)

    photo = _load_photo(photo_path)
    photo_h = int(H * 0.55)
    photo_top = _photo_to_vertical(photo, target_w=W, target_h=photo_h, focus_y=0.30)
    img.paste(photo_top, (0, 0))

    # Gold horizontal line di separazione
    draw = ImageDraw.Draw(img)
    draw.rectangle([(0, photo_h), (W, photo_h + 4)], fill=GOLD)

    # Bottom: blocco cream con titolo
    title_font = ImageFont.truetype(PLAYFAIR_BOLD, 95)
    lines = _wrap_text(title, max_chars=15)
    total_h = len(lines) * 110
    text_block_y = photo_h + 100
    y_end = _draw_text_block(draw, lines, title_font, NAVY, W // 2, text_block_y, line_gap=18)

    # Sotto il titolo: signature
    sig_font = ImageFont.truetype(PLAYFAIR_ITAL, 38)
    sig = "— Valentina Russo"
    bbox = draw.textbbox((0, 0), sig, font=sig_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, y_end + 30), sig, font=sig_font, fill=MAUVE)

    # Bottom brand
    brand_font = ImageFont.truetype(OUTFIT_BOLD, 32)
    brand = "valentinarussobg5.com"
    bbox = draw.textbbox((0, 0), brand, font=brand_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 110), brand, font=brand_font, fill=NAVY)

    return img


# ─── Template 8: Photo + dark vignette + side title ──────────────────────────

def template_8_photo_vignette_title(title: str, subtitle: str = "Human Design",
                                     photo_path: Path | None = None) -> Image.Image:
    """Foto Valentina full-bleed con dark overlay both-ends. Titolo Playfair Bold cream centrato."""
    photo = _load_photo(photo_path)
    bg = _photo_to_vertical(photo, focus_y=0.30).convert("RGBA")

    overlay = _gradient_overlay("both-ends", strength=0.78)
    bg = Image.alpha_composite(bg, overlay)
    img = bg.convert("RGB")
    draw = ImageDraw.Draw(img)

    # Top eyebrow
    eyebrow_font = ImageFont.truetype(IM_FELL_REGULAR, 44)
    eyebrow = "Human Design · Italia"
    bbox = draw.textbbox((0, 0), eyebrow, font=eyebrow_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, 200), eyebrow, font=eyebrow_font,
              fill=GOLD, stroke_width=1, stroke_fill=(0, 0, 0, 200))

    # Gold thin line
    draw.line([(W // 2 - 80, 270), (W // 2 + 80, 270)], fill=GOLD, width=2)

    # Title centrato verticalmente, Playfair Bold cream con outline forte
    title_font = ImageFont.truetype(PLAYFAIR_BOLD, 110)
    lines = _wrap_text(title, max_chars=14)
    total_h = len(lines) * 130
    y_start = (H - total_h) // 2 - 60

    # Disegno con stroke per leggibilità
    y = y_start
    for line in lines:
        bbox = draw.textbbox((0, 0), line, font=title_font)
        w = bbox[2] - bbox[0]
        h = bbox[3] - bbox[1]
        x = (W - w) // 2
        # outline manuale
        for dx in (-3, 0, 3):
            for dy in (-3, 0, 3):
                if dx or dy:
                    draw.text((x + dx, y + dy), line, font=title_font, fill=(0, 0, 0))
        draw.text((x, y), line, font=title_font, fill=NEAR_WHITE)
        y += h + 20

    # Bottom: signature + brand
    draw.line([(W // 2 - 120, H - 290), (W // 2 + 120, H - 290)], fill=GOLD, width=2)

    sub_font = ImageFont.truetype(PLAYFAIR_ITAL, 42)
    sub = "Valentina Russo · BG5® Analyst"
    bbox = draw.textbbox((0, 0), sub, font=sub_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 250), sub, font=sub_font,
              fill=NEAR_WHITE, stroke_width=1, stroke_fill=(0, 0, 0))

    brand_font = ImageFont.truetype(OUTFIT_BOLD, 32)
    brand = "@valentinarussobg5"
    bbox = draw.textbbox((0, 0), brand, font=brand_font)
    draw.text(((W - (bbox[2] - bbox[0])) // 2, H - 175), brand, font=brand_font, fill=GOLD)

    return img


# ─── Registry ─────────────────────────────────────────────────────────────────

TEMPLATES = {
    "1-editorial-dark":      template_1_editorial_dark,
    "2-cream-parchment":     template_2_cream_parchment,
    "3-magazine-quote":      template_3_magazine_quote,
    "4-minimalist-white":    template_4_minimalist_white,
    "5-dramatic-gradient":   template_5_dramatic_gradient,
    "6-photo-bottom-dark":   template_6_photo_bottom_dark,
    "7-photo-split-magazine": template_7_photo_split_magazine,
    "8-photo-vignette":      template_8_photo_vignette_title,
}


def render(template_name: str, title: str, subtitle: str = "Human Design",
           photo_key: str | None = None) -> Image.Image:
    """
    Render a cover by template name.
    Per i template foto-based (6/7/8), photo_key sceglie la foto da PHOTOS.
    """
    if template_name not in TEMPLATES:
        raise ValueError(f"Unknown template: {template_name}. Available: {list(TEMPLATES)}")
    fn = TEMPLATES[template_name]
    if template_name in ("6-photo-bottom-dark", "7-photo-split-magazine", "8-photo-vignette"):
        photo_path = PHOTOS.get(photo_key, DEFAULT_PHOTO) if photo_key else DEFAULT_PHOTO
        return fn(title, subtitle, photo_path=photo_path)
    return fn(title, subtitle)


# ─── CLI / preview generator ──────────────────────────────────────────────────

if __name__ == "__main__":
    import sys
    out_dir = Path(sys.argv[1]) if len(sys.argv) > 1 and sys.argv[1] else (HERE / "cover-previews")
    out_dir.mkdir(parents=True, exist_ok=True)

    sample_title = sys.argv[2] if len(sys.argv) > 2 else "Come prendere decisioni nel Tipo Generatore"

    print(f"Output dir: {out_dir}")
    print(f"Sample title: '{sample_title}'")
    print()

    PHOTO_TEMPLATES = {
        "6-photo-bottom-dark":   template_6_photo_bottom_dark,
        "7-photo-split-magazine": template_7_photo_split_magazine,
        "8-photo-vignette":      template_8_photo_vignette_title,
    }

    # Tipografici puri (1-5): solo una volta
    typographic_templates = {k: v for k, v in TEMPLATES.items() if k not in PHOTO_TEMPLATES}
    for name, fn in typographic_templates.items():
        img = fn(sample_title)
        out_path = out_dir / f"cover-{name}.png"
        img.save(out_path, "PNG", quality=95)
        print(f"  [OK] {out_path}")

    # Photo-based: 1 cover per (template × foto)
    for name, fn in PHOTO_TEMPLATES.items():
        for photo_key, photo_path in PHOTOS.items():
            if not photo_path.exists():
                print(f"  [SKIP] {photo_path} non trovata")
                continue
            try:
                img = fn(sample_title, photo_path=photo_path)
                out_path = out_dir / f"cover-{name}--{photo_key}.png"
                img.save(out_path, "PNG", quality=95)
                print(f"  [OK] {out_path.name}")
            except Exception as e:
                print(f"  [ERR] {name} + {photo_key}: {e}")

    n_total = len(typographic_templates) + len(PHOTO_TEMPLATES) * sum(1 for p in PHOTOS.values() if p.exists())
    print()
    print(f"Generate {n_total} cover totali in {out_dir}.")
    print("Tipografiche: 1-5 (no foto). Foto-based: 6/7/8 × ogni foto disponibile.")
