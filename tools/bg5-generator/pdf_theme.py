#!/usr/bin/env python3
"""
PDF THEME — BG5 Business Blueprint
Direzione visiva: "Almanacco Astrologa Cartografa"

Layer di fondo, decorazioni cartografiche, crocette oro, puntinato decorativo,
divisori Parte full-bleed scuri con immagini Imagen 4.0, numero pagina in IM Fell
oro, watermark numero sezione, dropcap rosa.

Importato da rebuild_pdfs.py. Le funzioni draw_* sono chiamate da onPage callback
di SimpleDocTemplate. Il `PageCategoryMarker` flowable comunica fra il flusso
Platypus e il callback canvas.
"""
from pathlib import Path
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm, mm
from reportlab.lib.utils import ImageReader
from reportlab.platypus import Flowable
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase.pdfmetrics import registerFontFamily

# ─── PALETTE ESTESA (sito + 3 derivati) ───────────────────────────────────────
CREAM        = colors.HexColor("#FAF7F5")   # fondo universale
INK_NIGHT    = colors.HexColor("#1C1210")   # testo body, fondo Parte
TEXT_INK     = colors.HexColor("#2D2926")   # body secondario
ROSA         = colors.HexColor("#B68397")   # accent bar, dropcap, highlight
MUTED_MAUVE  = colors.HexColor("#7a5c6e")   # H2, caption
TEAL         = colors.HexColor("#5DAEB1")   # bordi box, cerchio icone
TEAL_SHADOW  = colors.HexColor("#3D7A7D")   # bordi teal carichi
ORO          = colors.HexColor("#C48A3A")   # numeri pagina, crocette, filetti
WARM_GRAY    = colors.HexColor("#EAE5E1")   # watermark, puntinato

# Retrocompat con rebuild_pdfs.py pre-theme (aliases) — mantiene il file importabile
INK       = INK_NIGHT
BODY      = TEXT_INK
MUTED     = MUTED_MAUVE
LINE      = WARM_GRAY
ROSA_SOFT = colors.HexColor("#E8B8C4")
TEAL_DARK = TEAL_SHADOW

# ─── ASSETS ───────────────────────────────────────────────────────────────────
_HERE = Path(__file__).parent
ASSETS_DIR = _HERE / "assets" / "parts"
FONTS_DIR  = _HERE / "fonts"

PART_IMAGES = {
    1: ASSETS_DIR / "part-1-identita.jpg",
    2: ASSETS_DIR / "part-2-meccanica.jpg",
    3: ASSETS_DIR / "part-3-direzione.jpg",
    4: ASSETS_DIR / "part-4-magnetic.jpg",
    5: ASSETS_DIR / "part-5-sintesi.jpg",
}

COVER_HERO_PATH = _HERE / "assets" / "cover-hero.png"

PART_ROMAN = {1: "I", 2: "II", 3: "III", 4: "IV", 5: "V"}

PAGE_W, PAGE_H = A4


# ─── FONT EXTRA REGISTRATION ──────────────────────────────────────────────────
_FONTS_REGISTERED = False

def register_theme_fonts():
    """Registra IM Fell English (oltre a Playfair+Outfit già registrate altrove)."""
    global _FONTS_REGISTERED
    if _FONTS_REGISTERED:
        return
    pdfmetrics.registerFont(TTFont("IMFell",   str(FONTS_DIR / "IMFellEnglish-Regular.ttf")))
    pdfmetrics.registerFont(TTFont("IMFell-I", str(FONTS_DIR / "IMFellEnglish-Italic.ttf")))
    registerFontFamily("IMFell", normal="IMFell", italic="IMFell-I",
                       bold="IMFell", boldItalic="IMFell-I")
    _FONTS_REGISTERED = True


# ─── DECORAZIONI BASE ─────────────────────────────────────────────────────────

def draw_cartographic_corners(canvas, margin=12*mm, size=8*mm, color=None, lw=0.6):
    """4 crocette cartografiche agli angoli della pagina."""
    color = color or ORO
    canvas.saveState()
    canvas.setStrokeColor(color)
    canvas.setLineWidth(lw)
    half = size / 2
    for x, y in [
        (margin, margin),
        (PAGE_W - margin, margin),
        (margin, PAGE_H - margin),
        (PAGE_W - margin, PAGE_H - margin),
    ]:
        canvas.line(x - half, y, x + half, y)
        canvas.line(x, y - half, x, y + half)
    canvas.restoreState()


def draw_dotted_grid(canvas, x, y, w, h, step=4*mm, r=0.35*mm, color=None):
    """Griglia di punti decorativa."""
    color = color or WARM_GRAY
    canvas.saveState()
    canvas.setFillColor(color)
    canvas.setStrokeColor(color)
    yi = y
    while yi <= y + h:
        xi = x
        while xi <= x + w:
            canvas.circle(xi, yi, r, stroke=0, fill=1)
            xi += step
        yi += step
    canvas.restoreState()


def draw_cream_background(canvas):
    """Riempie la pagina con il colore crema fondale."""
    canvas.saveState()
    canvas.setFillColor(CREAM)
    canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
    canvas.restoreState()


def draw_footer(canvas, page_num, tier, customer, show_customer=True):
    """Numero pagina in IM Fell oro centrato + meta tier/customer sotto."""
    canvas.saveState()
    canvas.setFont("IMFell", 9)
    canvas.setFillColor(ORO)
    try:
        canvas.setCharSpace(0.6)
    except Exception:
        pass
    canvas.drawCentredString(PAGE_W/2, 1.15*cm, f"— {page_num} —")
    try:
        canvas.setCharSpace(0)
    except Exception:
        pass
    if show_customer:
        canvas.setFont("Outfit", 7)
        canvas.setFillColor(MUTED_MAUVE)
        canvas.drawCentredString(
            PAGE_W/2, 0.55*cm,
            f"BG5 Business Blueprint  ·  {tier}  ·  {customer}"
        )
    canvas.restoreState()


def draw_section_watermark(canvas, section_num):
    """Numero sezione grande in IM Fell sulla destra in alto, colore warm gray."""
    canvas.saveState()
    canvas.setFont("IMFell", 68)
    canvas.setFillColor(WARM_GRAY)
    canvas.drawRightString(PAGE_W - 1.8*cm, PAGE_H - 2.8*cm, f"{section_num:02d}")
    canvas.restoreState()


def draw_dropcap(canvas, letter, x, y, color=None, size=42):
    """Dropcap Playfair Bold rosa (uso opzionale)."""
    color = color or ROSA
    canvas.saveState()
    canvas.setFont("Playfair-B", size)
    canvas.setFillColor(color)
    canvas.drawString(x, y, letter)
    canvas.restoreState()


# ─── LAYOUT CATEGORIE DI PAGINA ───────────────────────────────────────────────

def _initials_from_name(name):
    if not name:
        return ""
    words = [w for w in name.split() if w]
    if not words:
        return ""
    if len(words) == 1:
        return words[0][:2].upper()
    return (words[0][0] + words[-1][0]).upper()


def draw_ornamental_frame(canvas, margin=18*mm):
    """Cornice doppia oro con angoli decorati — stile scrigno rinascimentale."""
    canvas.saveState()
    canvas.setStrokeColor(ORO)
    # Cornice esterna spessa
    canvas.setLineWidth(1.4)
    canvas.rect(margin, margin, PAGE_W - 2*margin, PAGE_H - 2*margin, stroke=1, fill=0)
    # Cornice interna sottile
    inset = 3.2
    canvas.setLineWidth(0.4)
    canvas.rect(margin + inset, margin + inset,
                PAGE_W - 2*margin - 2*inset,
                PAGE_H - 2*margin - 2*inset, stroke=1, fill=0)
    canvas.restoreState()


def draw_compass_ornament(canvas, cx, cy, size=10*mm):
    """Rosa dei venti decorativa — 8 punte + doppio cerchio oro."""
    import math
    canvas.saveState()
    canvas.setStrokeColor(ORO)
    canvas.setFillColor(ORO)
    r_out = size / 2
    r_in = size / 3.2
    # Cerchio esterno
    canvas.setLineWidth(0.7)
    canvas.circle(cx, cy, r_out, stroke=1, fill=0)
    # Cerchio interno
    canvas.setLineWidth(0.4)
    canvas.circle(cx, cy, r_in, stroke=1, fill=0)
    # Rosa a 8 punte: alterna lunghe N/E/S/W e corte NE/SE/SW/NW
    canvas.setLineWidth(0.5)
    for i in range(8):
        a = i * math.pi / 4
        r = r_out + 1.8 if (i % 2 == 0) else r_out
        canvas.line(cx, cy, cx + math.cos(a) * r, cy + math.sin(a) * r)
    # 4 petali riempiti ai punti cardinali (rombi)
    for a in (0, math.pi/2, math.pi, 3*math.pi/2):
        p = canvas.beginPath()
        tip_x = cx + math.cos(a) * r_in * 0.95
        tip_y = cy + math.sin(a) * r_in * 0.95
        side_a1 = a + math.pi/2
        side1_x = cx + math.cos(side_a1) * 0.9
        side1_y = cy + math.sin(side_a1) * 0.9
        side2_x = cx - math.cos(side_a1) * 0.9
        side2_y = cy - math.sin(side_a1) * 0.9
        p.moveTo(cx, cy)
        p.lineTo(side1_x, side1_y)
        p.lineTo(tip_x, tip_y)
        p.lineTo(side2_x, side2_y)
        p.close()
        canvas.drawPath(p, stroke=0, fill=1)
    # Punto centrale
    canvas.circle(cx, cy, 0.7, stroke=0, fill=1)
    canvas.restoreState()


def draw_fleuron(canvas, cx, cy, size=8*mm):
    """Fleuron decorativo minimalista — 3 punti + curve ornamentali."""
    canvas.saveState()
    canvas.setStrokeColor(ORO)
    canvas.setFillColor(ORO)
    canvas.setLineWidth(0.5)
    # Curve a S su entrambi i lati
    p = canvas.beginPath()
    p.moveTo(cx - size, cy)
    p.curveTo(cx - size*0.7, cy + size*0.5,
              cx - size*0.25, cy + size*0.25,
              cx, cy)
    p.curveTo(cx + size*0.25, cy - size*0.25,
              cx + size*0.7, cy - size*0.5,
              cx + size, cy)
    canvas.drawPath(p, stroke=1, fill=0)
    # Specchio (sopra-sotto)
    p2 = canvas.beginPath()
    p2.moveTo(cx - size, cy)
    p2.curveTo(cx - size*0.7, cy - size*0.5,
               cx - size*0.25, cy - size*0.25,
               cx, cy)
    p2.curveTo(cx + size*0.25, cy + size*0.25,
               cx + size*0.7, cy + size*0.5,
               cx + size, cy)
    canvas.drawPath(p2, stroke=1, fill=0)
    # Rombo centrale
    d = 1.4
    p3 = canvas.beginPath()
    p3.moveTo(cx, cy + d)
    p3.lineTo(cx + d, cy)
    p3.lineTo(cx, cy - d)
    p3.lineTo(cx - d, cy)
    p3.close()
    canvas.drawPath(p3, stroke=0, fill=1)
    # Puntini alle estremità
    canvas.circle(cx - size - 1, cy, 0.7, stroke=0, fill=1)
    canvas.circle(cx + size + 1, cy, 0.7, stroke=0, fill=1)
    canvas.restoreState()


def draw_wax_seal(canvas, cx, cy, initials, radius=12*mm):
    """Sigillo di ceralacca rosa malva con bordo oro doppio e iniziali monogramma."""
    canvas.saveState()
    # Ombra di profondità
    canvas.setFillColor(colors.HexColor("#6B4856"))
    canvas.circle(cx + 1.2, cy - 1.2, radius, stroke=0, fill=1)
    # Disco rosa
    canvas.setFillColor(ROSA)
    canvas.setStrokeColor(ORO)
    canvas.setLineWidth(1.0)
    canvas.circle(cx, cy, radius, stroke=1, fill=1)
    # Anello interno oro
    canvas.setLineWidth(0.4)
    canvas.circle(cx, cy, radius - 2.2, stroke=1, fill=0)
    # Stelline decorative sull'anello (4 punti cardinali)
    import math
    canvas.setFillColor(colors.HexColor("#F8EFE4"))
    for a in (math.pi/2, 0, -math.pi/2, math.pi):
        x = cx + math.cos(a) * (radius - 1.1)
        y = cy + math.sin(a) * (radius - 1.1)
        canvas.circle(x, y, 0.7, stroke=0, fill=1)
    # Iniziali grandi in Playfair BoldItalic
    canvas.setFont("Playfair-BI", 22)
    canvas.setFillColor(colors.HexColor("#FBF6EF"))
    # offset verticale per centratura ottica
    canvas.drawCentredString(cx, cy - 7, initials)
    canvas.restoreState()


def draw_cover_hero(canvas):
    """Disegna l'immagine scrigno full-bleed centrata (crop implicito fuori pagina)."""
    if not COVER_HERO_PATH.exists():
        return False
    img = ImageReader(str(COVER_HERO_PATH))
    iw, ih = img.getSize()
    # Scale to cover the full page (max fit, crop lo sforamento)
    scale = max(PAGE_W / iw, PAGE_H / ih)
    sw = iw * scale
    sh = ih * scale
    x = (PAGE_W - sw) / 2
    y = (PAGE_H - sh) / 2
    canvas.drawImage(img, x, y, width=sw, height=sh,
                     preserveAspectRatio=False, mask='auto')
    return True


_COVER_OVERLAY_CACHE = _HERE / "assets" / "_cover-overlay.png"


def _build_cover_overlay_png():
    """Genera un PNG RGBA smooth da usare come overlay sopra l'hero.
    Gradiente verticale: scuro in alto, trasparente al centro, scuro in basso.
    Cache su disco — rigenerato solo se mancante."""
    if _COVER_OVERLAY_CACHE.exists():
        return _COVER_OVERLAY_CACHE
    try:
        from PIL import Image
    except ImportError:
        return None

    # Risoluzione generosa per un gradiente liscio (1 px = ~0.14 mm su A4)
    W = 600
    H = 848  # ratio A4 (210:297)
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    px = img.load()

    # Curve di alpha per y in [0..H-1]. y=0 è in alto (PIL).
    # Zona top: 0 -> ~45% dell'altezza, alpha decrescente da 0.78 a 0.
    # Zona bottom: ~72% -> 1.0, alpha crescente da 0 a 0.62.
    # Zona centrale: trasparente (lo scrigno respira).
    top_end   = int(H * 0.44)
    bot_start = int(H * 0.72)

    # Colore overlay = INK_NIGHT (#1C1210)
    R, G, B = 0x1C, 0x12, 0x10

    for y in range(H):
        if y <= top_end:
            # easing smooth: cos^2 per transizione più morbida
            import math
            t = y / top_end
            # da 0.78 a 0, con curva cos che parte lenta e accelera
            a = 0.78 * (math.cos(t * math.pi / 2) ** 2)
        elif y >= bot_start:
            import math
            t = (y - bot_start) / (H - 1 - bot_start)
            a = 0.62 * (math.sin(t * math.pi / 2) ** 2)
        else:
            a = 0.0
        alpha = int(round(a * 255))
        row = (R, G, B, alpha)
        for x in range(W):
            px[x, y] = row

    _COVER_OVERLAY_CACHE.parent.mkdir(parents=True, exist_ok=True)
    img.save(str(_COVER_OVERLAY_CACHE), "PNG")
    return _COVER_OVERLAY_CACHE


def draw_cover_overlay(canvas):
    """Overlay scuro gradient sopra l'hero per leggibilità del testo.
    Usa un PNG RGBA pre-renderizzato con gradiente smooth — più scuro in alto
    (zona titolo), trasparente al centro (zona scrigno), scuro in basso (credito).
    """
    path = _build_cover_overlay_png()
    if path is None or not path.exists():
        return
    canvas.saveState()
    try:
        img = ImageReader(str(path))
        canvas.drawImage(img, 0, 0, width=PAGE_W, height=PAGE_H,
                         preserveAspectRatio=False, mask='auto')
    finally:
        canvas.restoreState()
        return

    try:
        canvas.setFillAlpha(1.0)
    except Exception:
        pass
    canvas.restoreState()


def draw_cover_page(canvas, customer_name=None, tier_name=None,
                    tier_subtitle=None, chart=None):
    """Copertina 'scrigno prezioso' — hero Imagen 4.0 full-bleed + tipografia
    editoriale + cartiglio personale col nome cliente + frase dedicatoria.
    Se l'hero non è disponibile, fallback su versione vettoriale crema."""
    from datetime import datetime
    import math

    has_hero = draw_cover_hero(canvas)

    if has_hero:
        # Overlay scuro per leggibilità del testo
        draw_cover_overlay(canvas)
        # Palette testo per sfondo scuro
        title_color  = colors.HexColor("#FBF6EF")   # cream chiaro
        prefix_color = colors.HexColor("#E8D5AA")   # oro chiaro
        name_color   = colors.HexColor("#FBF6EF")
        phrase_color = colors.HexColor("#E8C4CC")   # rosa chiaro
        credit_color = colors.HexColor("#EFE7DB")
        eyebrow_color = colors.HexColor("#E8D5AA")
    else:
        # Fallback: fondo crema + puntinato
        draw_cream_background(canvas)
        draw_dotted_grid(canvas, 18*mm, 18*mm, PAGE_W - 36*mm, PAGE_H - 36*mm,
                         step=7*mm, r=0.22*mm, color=colors.HexColor("#EFE7DB"))
        title_color  = INK_NIGHT
        prefix_color = MUTED_MAUVE
        name_color   = INK_NIGHT
        phrase_color = TEAL_SHADOW
        credit_color = MUTED_MAUVE
        eyebrow_color = MUTED_MAUVE

    # 3. Cornice ornamentale doppia oro
    draw_ornamental_frame(canvas, margin=14*mm)

    # 4. Rose dei venti ai 4 angoli interni
    corner_inset = 26*mm
    for cx, cy in [
        (corner_inset, PAGE_H - corner_inset),
        (PAGE_W - corner_inset, PAGE_H - corner_inset),
        (corner_inset, corner_inset),
        (PAGE_W - corner_inset, corner_inset),
    ]:
        draw_compass_ornament(canvas, cx, cy, size=10*mm)

    # 5. Fleuron in testa
    draw_fleuron(canvas, PAGE_W/2, PAGE_H - 36*mm, size=8*mm)

    # 6. Eyebrow "B · G · 5" in IM Fell, letter-spacing ampio
    canvas.saveState()
    canvas.setFont("IMFell", 12)
    canvas.setFillColor(eyebrow_color)
    try:
        canvas.setCharSpace(3.2)
    except Exception:
        pass
    canvas.drawCentredString(PAGE_W/2, PAGE_H - 50*mm, "B  ·  G  ·  5")
    try:
        canvas.setCharSpace(0)
    except Exception:
        pass
    canvas.restoreState()

    # 7. Titolo principale — Playfair BoldItalic
    canvas.saveState()
    canvas.setFont("Playfair-BI", 36)
    canvas.setFillColor(title_color)
    canvas.drawCentredString(PAGE_W/2, PAGE_H - 68*mm, "Business Blueprint")
    canvas.restoreState()

    # 8. Tier name in IM Fell Italic oro
    if tier_name:
        canvas.saveState()
        canvas.setFont("IMFell-I", 20)
        canvas.setFillColor(ORO)
        canvas.drawCentredString(PAGE_W/2, PAGE_H - 80*mm, tier_name)
        canvas.restoreState()

    # 9. Filetto decorativo con rombo centrale
    canvas.saveState()
    canvas.setStrokeColor(ORO)
    canvas.setFillColor(ORO)
    canvas.setLineWidth(0.5)
    y_rule = PAGE_H - 89*mm
    canvas.line(PAGE_W/2 - 36*mm, y_rule, PAGE_W/2 - 2.5*mm, y_rule)
    canvas.line(PAGE_W/2 + 2.5*mm, y_rule, PAGE_W/2 + 36*mm, y_rule)
    p = canvas.beginPath()
    p.moveTo(PAGE_W/2, y_rule + 1.6)
    p.lineTo(PAGE_W/2 + 1.6, y_rule)
    p.lineTo(PAGE_W/2, y_rule - 1.6)
    p.lineTo(PAGE_W/2 - 1.6, y_rule)
    p.close()
    canvas.drawPath(p, stroke=0, fill=1)
    canvas.restoreState()

    # 10. Cartiglio personale — "A [Nome Cognome]" + frase dedicatoria
    if customer_name:
        y_c = PAGE_H - 108*mm
        # "A" prefix
        canvas.saveState()
        canvas.setFont("Playfair-I", 15)
        canvas.setFillColor(prefix_color)
        canvas.drawCentredString(PAGE_W/2, y_c + 10*mm, "A")
        canvas.restoreState()

        # Nome cliente — PROTAGONISTA
        canvas.saveState()
        canvas.setFont("Playfair-BI", 30)
        canvas.setFillColor(name_color)
        canvas.drawCentredString(PAGE_W/2, y_c, customer_name)
        canvas.restoreState()

        # Filetto sotto il nome
        canvas.saveState()
        canvas.setStrokeColor(ORO)
        canvas.setLineWidth(0.4)
        canvas.line(PAGE_W/2 - 40*mm, y_c - 4*mm,
                    PAGE_W/2 + 40*mm, y_c - 4*mm)
        canvas.restoreState()

        # Frase dedicatoria
        canvas.saveState()
        canvas.setFont("IMFell-I", 13)
        canvas.setFillColor(phrase_color)
        canvas.drawCentredString(PAGE_W/2, y_c - 12*mm,
                                 "la mappa che ti appartiene da sempre.")
        canvas.restoreState()

    # 11. Sigillo ceralacca (solo in modalità fallback senza hero — l'immagine
    # Imagen contiene già una pergamena con sigillo di ceralacca fisico).
    if not has_hero:
        initials = _initials_from_name(customer_name)
        if initials:
            draw_wax_seal(canvas, PAGE_W/2, 86*mm, initials, radius=13*mm)

    # 12. Fleuron in coda
    draw_fleuron(canvas, PAGE_W/2, 44*mm, size=6*mm)

    # 13. Credito Valentina Russo (sulla banda scura inferiore)
    canvas.saveState()
    canvas.setFont("IMFell-I", 10)
    canvas.setFillColor(credit_color)
    canvas.drawCentredString(PAGE_W/2, 35*mm, "Elaborato da Valentina Russo")
    canvas.setFont("IMFell", 9)
    canvas.drawCentredString(PAGE_W/2, 30*mm, "consulente BG5 certificata")
    canvas.setFont("IMFell", 9)
    canvas.setFillColor(ORO)
    canvas.drawCentredString(PAGE_W/2, 24*mm, "valentinarussobg5.com")
    canvas.restoreState()

    # 14. Data in basso
    canvas.saveState()
    canvas.setFont("IMFell-I", 8)
    canvas.setFillColor(credit_color)
    try:
        canvas.setCharSpace(1.2)
    except Exception:
        pass
    canvas.drawCentredString(PAGE_W/2, 19*mm,
                             datetime.now().strftime("· %d  %B  %Y ·").lower())
    try:
        canvas.setCharSpace(0)
    except Exception:
        pass
    canvas.restoreState()


def draw_chart_page(canvas, page_num, tier, customer):
    """Pagina bodygraph: crema + crocette + footer."""
    draw_cream_background(canvas)
    draw_cartographic_corners(canvas, margin=12*mm, size=8*mm)
    draw_footer(canvas, page_num, tier, customer)


def draw_toc_page(canvas, page_num, tier, customer):
    """Pagina indice: crema + crocette + footer."""
    draw_cream_background(canvas)
    draw_cartographic_corners(canvas, margin=12*mm, size=8*mm)
    draw_footer(canvas, page_num, tier, customer)


def draw_part_page(canvas, part_num):
    """Divisore Parte: full-bleed inchiostro notte + immagine + numero romano oro."""
    # 1. Sfondo full-bleed scuro
    canvas.saveState()
    canvas.setFillColor(INK_NIGHT)
    canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
    canvas.restoreState()

    # 2. Immagine Imagen 4.0 centrata in alto
    img_path = PART_IMAGES.get(part_num)
    if img_path and img_path.exists():
        img = ImageReader(str(img_path))
        iw, ih = img.getSize()
        target_w = 14*cm
        target_h = target_w * ih / iw
        x = (PAGE_W - target_w) / 2
        y = PAGE_H - 4*cm - target_h
        # Bordo oro sottile attorno all'immagine
        canvas.saveState()
        canvas.setStrokeColor(ORO)
        canvas.setLineWidth(0.8)
        canvas.rect(x - 2.5, y - 2.5, target_w + 5, target_h + 5, stroke=1, fill=0)
        canvas.drawImage(img, x, y, width=target_w, height=target_h,
                         preserveAspectRatio=True, mask='auto')
        canvas.restoreState()

    # 3. Numero romano gigante in IM Fell rosa malva, sotto l'immagine
    canvas.saveState()
    canvas.setFont("IMFell", 72)
    canvas.setFillColor(ROSA)
    canvas.drawCentredString(PAGE_W/2, 4.2*cm, PART_ROMAN.get(part_num, ""))
    canvas.restoreState()

    # 4. Filetto oro
    canvas.saveState()
    canvas.setStrokeColor(ORO)
    canvas.setLineWidth(0.5)
    canvas.line(PAGE_W/2 - 2.5*cm, 3.6*cm, PAGE_W/2 + 2.5*cm, 3.6*cm)
    canvas.restoreState()

    # 5. Crocette oro sugli angoli
    draw_cartographic_corners(canvas, margin=12*mm, size=9*mm, color=ORO, lw=0.7)


def draw_section_page(canvas, page_num, tier, customer, section_num=None, is_first=False):
    """Pagina sezione: crema + crocette + watermark (solo prima pagina) + footer."""
    draw_cream_background(canvas)
    draw_cartographic_corners(canvas, margin=12*mm, size=7*mm, lw=0.5)
    if is_first and section_num is not None:
        draw_section_watermark(canvas, section_num)
    draw_footer(canvas, page_num, tier, customer)


def draw_closing_page(canvas, page_num, tier, customer):
    """Pagina chiusura: crema + crocette + puntinato soft + footer."""
    draw_cream_background(canvas)
    draw_dotted_grid(canvas, 2*cm, PAGE_H/2 - 3*cm, PAGE_W - 4*cm, 6*cm,
                     step=6*mm, r=0.3*mm)
    draw_cartographic_corners(canvas, margin=14*mm, size=10*mm, lw=0.7)
    draw_footer(canvas, page_num, tier, customer)


# ─── PAGE CATEGORY MARKER ─────────────────────────────────────────────────────

class PageCategoryMarker(Flowable):
    """Flowable invisibile che comunica al canvas la categoria della prossima pagina.
    Inserire PRIMA del PageBreak che la precede.
    Sulla pagina corrente viene eseguito draw() che scrive `_nextPageCategory` sul
    canvas; il callback onPage della pagina successiva lo legge e resetta."""
    def __init__(self, category, **kwargs):
        super().__init__()
        self.category = category
        self.kw = kwargs

    def wrap(self, aw, ah):
        return (0, 0)

    def draw(self):
        self.canv._nextPageCategory = self.category
        self.canv._nextPageKwargs   = self.kw


# ─── THEME CALLBACK FACTORY ───────────────────────────────────────────────────

def make_theme_callback(tier, customer, chart=None):
    """Restituisce un callback (canvas, doc) adatto a onFirstPage / onLaterPages.
    Legge `canvas._nextPageCategory` e disegna il layout giusto.
    Prima pagina = "cover" di default. Se `chart` è fornito, la copertina
    personalizza il cartiglio col nome e mostra un sigillo di ceralacca con
    le iniziali del cliente."""
    def _draw(canvas, doc):
        category = getattr(canvas, "_nextPageCategory", None)
        kwargs   = getattr(canvas, "_nextPageKwargs", {}) or {}
        if category is None:
            # prima pagina del documento = cover
            category = "cover"
            kwargs = {}

        if category == "cover":
            draw_cover_page(
                canvas,
                customer_name=customer,
                tier_name=tier,
                tier_subtitle=(chart or {}).get("tier_subtitle"),
                chart=chart,
            )

        elif category == "chart":
            draw_chart_page(canvas, doc.page, tier, customer)

        elif category == "toc":
            draw_toc_page(canvas, doc.page, tier, customer)

        elif category == "part":
            draw_part_page(canvas, kwargs.get("part_num", 1))
            # Niente footer sulla pagina Parte (full-bleed)

        elif category == "section_start":
            draw_section_page(canvas, doc.page, tier, customer,
                              section_num=kwargs.get("section_num"),
                              is_first=True)
            # Auto-transizione: le pagine successive della stessa sezione
            # sono "section_cont" finché un altro marker non cambia stato
            canvas._nextPageCategory = "section_cont"
            canvas._nextPageKwargs = {"section_num": kwargs.get("section_num")}

        elif category == "section_cont":
            draw_section_page(canvas, doc.page, tier, customer,
                              section_num=kwargs.get("section_num"),
                              is_first=False)

        elif category == "closing":
            draw_closing_page(canvas, doc.page, tier, customer)

        else:
            # fallback: pagina sezione generica
            draw_section_page(canvas, doc.page, tier, customer, is_first=False)

    return _draw
