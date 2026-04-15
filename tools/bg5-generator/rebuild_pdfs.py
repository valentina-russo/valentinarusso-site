#!/usr/bin/env python3
"""
REBUILD PDFS — BG5 Business Blueprint
Legge spike-output.json e costruisce due PDF brandizzati con le font reali
del sito (Playfair Display + Outfit). Non chiama l'API, non rigenera testo.

Input:
  tools/bg5-generator/spike-output.json

Output:
  D:/Download/bg5-blueprint-essenziale.pdf
  D:/Download/bg5-blueprint-completo.pdf
"""

import sys
import json
import re
from pathlib import Path
from datetime import datetime

try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import cm, mm
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_JUSTIFY
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, PageBreak, HRFlowable,
    Table, TableStyle, KeepTogether, Image, CondPageBreak
)
from reportlab.lib.utils import ImageReader
from io import BytesIO

# Renderer bodygraph (SVG hdkit → PNG colorato)
from bodygraph_svg import render_bodygraph_png, activations_from_chart

# Sistema visivo "Almanacco Astrologa Cartografa"
import pdf_theme
from pdf_theme import PageCategoryMarker, make_theme_callback

# ─── CANVA ASSETS (centri + circuiti) ─────────────────────────────────────────
CANVA_DIR = Path(__file__).parent / "assets" / "canva"

# Italian center name (da spike-output chart) → asset key
CENTER_IT_TO_KEY = {
    "Testa":             "head",
    "Ajna":              "ajna",
    "Gola":              "throat",
    "G":                 "g",
    "G/Sé":              "g",
    "Cuore":             "ego",
    "Cuore/Ego":         "ego",
    "Ego":               "ego",
    "Milza":             "spleen",
    "Sacrale":           "sacral",
    "Risorsa Energetica":"sacral",
    "Plesso Solare":     "solar-plexus",
    "Radice":            "root",
}

# Ogni canale HD → circuito BG5 di appartenenza. Chiavi normalizzate come
# tuple ordinata (min, max) per matchare indifferentemente "3-60" o "60-3".
def _ch(a, b): return (min(a, b), max(a, b))

CHANNEL_TO_CIRCUIT = {
    # Integration (4)
    _ch(34, 57): "integration", _ch(57, 10): "integration",
    _ch(34, 20): "integration", _ch(20, 10): "integration",
    # Centering (2)
    _ch(34, 10): "centering",   _ch(25, 51): "centering",
    # Knowing (9)
    _ch(61, 24): "knowing",  _ch(43, 23): "knowing",  _ch(28, 38): "knowing",
    _ch(57, 20): "knowing",  _ch(3, 60):  "knowing",  _ch(14, 2):  "knowing",
    _ch(1, 8):   "knowing",  _ch(55, 39): "knowing",  _ch(12, 22): "knowing",
    # Ego (5)
    _ch(54, 32): "ego", _ch(44, 26): "ego", _ch(19, 49): "ego",
    _ch(40, 37): "ego", _ch(45, 21): "ego",
    # Defense (2)
    _ch(59, 6): "defense", _ch(50, 27): "defense",
    # Logic (7)
    _ch(63, 4):  "logic", _ch(17, 62): "logic", _ch(16, 48): "logic",
    _ch(18, 58): "logic", _ch(9, 52):  "logic", _ch(15, 5):  "logic",
    _ch(7, 31):  "logic",
    # Sensing (7)
    _ch(64, 47): "sensing", _ch(11, 56): "sensing", _ch(53, 42): "sensing",
    _ch(29, 46): "sensing", _ch(13, 33): "sensing", _ch(41, 30): "sensing",
    _ch(35, 36): "sensing",
}

CIRCUIT_LABELS = {
    "integration": "Integrazione",
    "centering":   "Centratura",
    "knowing":     "Conoscenza",
    "ego":         "Ego",
    "defense":     "Difesa",
    "logic":       "Logica",
    "sensing":     "Percezione",
}

_CANVA_CROP_CACHE: dict[str, bytes] = {}

def _load_canva_image(path: Path) -> BytesIO | None:
    """Carica una PNG Canva croppata al contenuto (rimuove bordi bianchi/alpha).
    Ritorna un BytesIO pronto per reportlab.platypus.Image, o None se manca."""
    if not path.exists():
        return None
    cache_key = str(path)
    if cache_key not in _CANVA_CROP_CACHE:
        from PIL import Image as PILImage
        import numpy as np
        im = PILImage.open(path)
        if im.mode != "RGBA":
            im = im.convert("RGBA")
        # Maschera "contenuto": opaco E non near-white (rimuove bordi Canva)
        arr = np.asarray(im)
        r, g, b, a = arr[..., 0], arr[..., 1], arr[..., 2], arr[..., 3]
        opaque    = a > 16
        not_white = (r < 245) | (g < 245) | (b < 245)
        mask_np   = opaque & not_white
        if mask_np.any():
            ys, xs = np.where(mask_np)
            bbox = (int(xs.min()), int(ys.min()),
                    int(xs.max()) + 1, int(ys.max()) + 1)
        else:
            bbox = im.getbbox() or (0, 0, im.width, im.height)
        # Padding ~2% del lato minore
        w, h = im.size
        pad = int(min(w, h) * 0.02)
        x0 = max(0, bbox[0] - pad); y0 = max(0, bbox[1] - pad)
        x1 = min(w, bbox[2] + pad); y1 = min(h, bbox[3] + pad)
        cropped = im.crop((x0, y0, x1, y1))
        # Downscale: max 600 px sul lato lungo (stampa a 300dpi ~ 5 cm)
        max_side = 600
        cw, ch = cropped.size
        if max(cw, ch) > max_side:
            scale = max_side / max(cw, ch)
            cropped = cropped.resize(
                (int(cw * scale), int(ch * scale)),
                PILImage.LANCZOS,
            )
        buf = BytesIO()
        cropped.save(buf, format="PNG", optimize=True)
        _CANVA_CROP_CACHE[cache_key] = buf.getvalue()
    return BytesIO(_CANVA_CROP_CACHE[cache_key])


def _center_image_flowable(center_key: str, target_h_cm: float = 2.4):
    """Ritorna un Image flowable per l'icona centro, o None se asset mancante."""
    path = CANVA_DIR / "centers" / f"center-{center_key}-defined.png"
    buf = _load_canva_image(path)
    if buf is None:
        return None
    # Leggi dimensione post-crop per aspect ratio
    from PIL import Image as PILImage
    buf.seek(0)
    iw, ih = PILImage.open(buf).size
    buf.seek(0)
    target_h = target_h_cm * cm
    target_w = target_h * (iw / ih)
    img = Image(buf, width=target_w, height=target_h)
    img.hAlign = "CENTER"
    return img


def _circuit_image_flowable(circuit_key: str, target_w_cm: float = 8.5):
    """Ritorna un Image flowable per il bodygraph-circuito (highlight arancio)."""
    path = CANVA_DIR / "circuits" / f"circuit-{circuit_key}-full.png"
    buf = _load_canva_image(path)
    if buf is None:
        return None
    from PIL import Image as PILImage
    buf.seek(0)
    iw, ih = PILImage.open(buf).size
    buf.seek(0)
    target_w = target_w_cm * cm
    target_h = target_w * (ih / iw)
    img = Image(buf, width=target_w, height=target_h)
    img.hAlign = "CENTER"
    return img


def _circuits_for_channels(channels: list[dict]) -> list[str]:
    """Estrae i circuiti attivi (deduplicati, ordinati) dai canali del chart."""
    seen: list[str] = []
    for ch in channels or []:
        name = ch.get("name", "")
        m = re.match(r"^(\d+)\s*[-–]\s*(\d+)$", name)
        if not m:
            continue
        key = _ch(int(m.group(1)), int(m.group(2)))
        circuit = CHANNEL_TO_CIRCUIT.get(key)
        if circuit and circuit not in seen:
            seen.append(circuit)
    return seen

# ─── FONTS ────────────────────────────────────────────────────────────────────
FONTS_DIR = Path(__file__).parent / "fonts"

def register_fonts():
    pdfmetrics.registerFont(TTFont("Playfair",    str(FONTS_DIR / "PlayfairDisplay-Regular.ttf")))
    pdfmetrics.registerFont(TTFont("Playfair-B",  str(FONTS_DIR / "PlayfairDisplay-Bold.ttf")))
    pdfmetrics.registerFont(TTFont("Playfair-I",  str(FONTS_DIR / "PlayfairDisplay-Italic.ttf")))
    pdfmetrics.registerFont(TTFont("Playfair-BI", str(FONTS_DIR / "PlayfairDisplay-BoldItalic.ttf")))
    pdfmetrics.registerFont(TTFont("Outfit",      str(FONTS_DIR / "Outfit-Regular.ttf")))
    pdfmetrics.registerFont(TTFont("Outfit-M",    str(FONTS_DIR / "Outfit-Medium.ttf")))
    pdfmetrics.registerFont(TTFont("Outfit-SB",   str(FONTS_DIR / "Outfit-SemiBold.ttf")))
    pdfmetrics.registerFont(TTFont("Outfit-B",    str(FONTS_DIR / "Outfit-Bold.ttf")))
    from reportlab.pdfbase.pdfmetrics import registerFontFamily
    registerFontFamily("Playfair", normal="Playfair", bold="Playfair-B",
                       italic="Playfair-I", boldItalic="Playfair-BI")
    registerFontFamily("Outfit", normal="Outfit", bold="Outfit-B",
                       italic="Outfit", boldItalic="Outfit-B")
    # IM Fell English (terzo font: numeri sezione/pagina, numeri romani)
    pdf_theme.register_theme_fonts()

# ─── PALETTE (dal theme "Almanacco Astrologa Cartografa") ────────────────────
# Sorgente: pdf_theme.py — estratta da valentinarussobg5.com + 3 derivati
ROSA       = pdf_theme.ROSA
ROSA_SOFT  = pdf_theme.ROSA_SOFT
TEAL       = pdf_theme.TEAL
TEAL_DARK  = pdf_theme.TEAL_SHADOW
INK        = pdf_theme.INK_NIGHT
BODY       = pdf_theme.TEXT_INK
MUTED      = pdf_theme.MUTED_MAUVE
LINE       = pdf_theme.WARM_GRAY
CREAM      = pdf_theme.CREAM
ORO        = pdf_theme.ORO

# ─── STRUTTURA BLUEPRINT ──────────────────────────────────────────────────────

ESSENZIALE_KEYS = [
    "intro", "carta_spiegata", "tipo_strategia", "autorita", "profilo",
    "definizione", "firma_nonself", "centri_definiti", "centri_aperti",
    "attrazione", "canali", "porte", "croce", "variabile_phs", "suggerimenti",
]

COMPLETO_KEYS = [
    "intro", "carta_spiegata", "tipo_strategia", "autorita", "profilo",
    "definizione", "firma_nonself", "centri_definiti", "centri_aperti",
    "attrazione", "canali", "porte", "croce", "variabile_phs",
    "offerte_allineate", "voce_gola", "vendita_allineata", "strategia_contenuti",
    "suggerimenti",
]

def section_titles(chart=None):
    """Titoli sezione dinamici: profilo e definizione dipendono dal chart del cliente."""
    profile_label = chart.get("profile_name", "") if chart else ""
    definition_label = chart.get("definition", "") if chart else ""
    return {
        "intro":               "Il tuo Business by Design",
        "carta_spiegata":      "La tua Carta BG5 spiegata",
        "tipo_strategia":      "Il tuo Tipo di Carriera e la Strategia",
        "autorita":            "La tua Autorità Interiore",
        "profilo":             f"Il tuo Profilo · {profile_label}" if profile_label else "Il tuo Profilo",
        "definizione":         f"La tua Definizione · {definition_label}" if definition_label else "La tua Definizione",
        "firma_nonself":       "Firma di Allineamento e Tema del Non-Sé",
        "centri_definiti":     "I tuoi Centri Definiti",
        "centri_aperti":       "I tuoi Centri Aperti",
        "attrazione":          "Il tuo Campo di Attrazione",
        "canali":              "I tuoi Canali Attivi",
        "porte":               "Le Porte più rilevanti",
        "croce":               "La tua Croce di Incarnazione",
        "variabile_phs":       "Variabile · Dieta cognitiva · Ambiente",
        "offerte_allineate":   "Offerte allineate · costruisci dai tuoi Centri",
        "voce_gola":           "La tua Voce di Gola",
        "vendita_allineata":   "Vendita allineata al tuo design",
        "strategia_contenuti": "Strategia contenuti per i tuoi Centri",
        "suggerimenti":        "Sintesi e 7 suggerimenti pratici",
    }

# Backward compat: modulo-level dict per riferimenti che non hanno chart
SECTION_TITLES = section_titles()

# Suddivisione in "parti" (per i divisori di sezione)
PARTS = [
    ("Parte I", "Identità energetica", [
        "intro", "carta_spiegata", "tipo_strategia", "autorita", "profilo",
        "definizione", "firma_nonself",
    ]),
    ("Parte II", "Meccanica del corpo", [
        "centri_definiti", "centri_aperti", "attrazione",
        "canali", "porte",
    ]),
    ("Parte III", "Direzione e salute cognitiva", [
        "croce", "variabile_phs",
    ]),
    ("Parte IV", "Magnetic Marketing", [  # solo Completo
        "offerte_allineate", "voce_gola", "vendita_allineata", "strategia_contenuti",
    ]),
    ("Parte V", "Sintesi e pratica", [
        "suggerimenti",
    ]),
]

# ─── LESSICO PATCHER (safety net post-generazione) ───────────────────────────
# Se la prima occorrenza di un termine BG5 in una sezione non ha l'equivalente
# Human Design nei 120 caratteri successivi, iniettiamo il parentetico qui.
# Solo per i termini che non creano ambiguità lessicali.

LESSICO_PATCHES = [
    ("Costruttore Classico", "Generatore Puro"),
    ("Costruttore Rapido",   "Generatore Manifestante"),
    # "Iniziatore" / "Guida" / "Valutatore" sono evitati perché "guida" è anche
    # un sostantivo comune (guida interna, guida pratica) e scatenerebbe falsi match.
]

def ensure_lessico(text: str) -> str:
    """Post-processing: garantisce BG5 (HD) alla prima occorrenza in ogni sezione."""
    for bg5, hd in LESSICO_PATCHES:
        idx = text.find(bg5)
        if idx == -1:
            continue
        window = text[idx:idx + 160]
        # Già presente in parentesi o virgola entro 160 char?
        if hd in window:
            continue
        # Inietta subito dopo il termine
        injection = f"{bg5} ({hd} in Human Design)"
        text = text[:idx] + injection + text[idx + len(bg5):]
    return text


# ─── ESCAPE + MARKDOWN MINIMAL ────────────────────────────────────────────────

def escape_xml(s: str) -> str:
    return (s.replace("&", "&amp;")
             .replace("<", "&lt;")
             .replace(">", "&gt;"))

def inline_md(s: str) -> str:
    # **bold** → <b>bold</b>  (prima, per non essere confuso con *italic*)
    s = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", s)
    # *italic* → <i>italic</i>  (i bullet usano -·•, mai *, quindi safe)
    s = re.sub(r"(?<!\w)\*(?!\s)(.+?)(?<!\s)\*(?!\w)", r"<i>\1</i>", s)
    # _italic_ → <i>italic</i>  (alternativa con underscore)
    s = re.sub(r"(?<!\w)_(.+?)_(?!\w)", r"<i>\1</i>", s)
    return s

def paragraphs(raw: str):
    """Split su doppia newline, yield (tipo, testo).
    tipo in {p, h2, h3, h4, bullet, num}.
    Markdown: # → h2, ## → h3, ### → h4 (indipendentemente dalla lunghezza).
    """
    blocks = raw.strip().split("\n\n")
    for block in blocks:
        block = block.strip()
        if not block:
            continue
        lines = block.split("\n")
        # Header markdown — rilevato su blocco monoriga, qualsiasi lunghezza
        if len(lines) == 1:
            m = re.match(r"^(#{1,6})\s+(.+?)\s*#*\s*$", block)
            if m:
                level = len(m.group(1))
                txt = m.group(2).strip()
                if level == 1:
                    yield ("h2", txt)
                elif level == 2:
                    yield ("h3", txt)
                else:  # 3+
                    yield ("h4", txt)
                continue
        # Lista puntata?
        if all(re.match(r"^[-·•]\s+", l) for l in lines):
            for l in lines:
                yield ("bullet", l.lstrip("-·• ").strip())
            continue
        # Lista numerata?
        if all(re.match(r"^\d+[.)]\s+", l) for l in lines):
            for l in lines:
                yield ("num", re.sub(r"^\d+[.)]\s+", "", l).strip())
            continue
        # Paragrafo
        yield ("p", block.replace("\n", " "))

# ─── HEADER / FOOTER ──────────────────────────────────────────────────────────
# Il footer+background+crocette sono gestiti dal theme callback di pdf_theme.
# Questa funzione resta come shim retrocompat per chiamate esterne.

def make_footer(tier: str, customer: str):
    return make_theme_callback(tier, customer)

# ─── STILI ────────────────────────────────────────────────────────────────────

def build_styles():
    return {
        "cover_title":   ParagraphStyle("ct", fontName="Playfair-B", fontSize=36,
                                        textColor=INK, alignment=TA_CENTER,
                                        leading=42, spaceAfter=4),
        "cover_sub":     ParagraphStyle("cs", fontName="Playfair-I", fontSize=18,
                                        textColor=ROSA, alignment=TA_CENTER,
                                        leading=22, spaceAfter=16),
        "cover_tag":     ParagraphStyle("ctg", fontName="Outfit", fontSize=11,
                                        textColor=MUTED, alignment=TA_CENTER,
                                        leading=16, spaceAfter=24),
        "cover_name":    ParagraphStyle("cn", fontName="Playfair", fontSize=22,
                                        textColor=TEAL_DARK, alignment=TA_CENTER,
                                        leading=28, spaceAfter=4),
        "cover_meta":    ParagraphStyle("cm", fontName="Outfit", fontSize=10,
                                        textColor=BODY, alignment=TA_CENTER,
                                        leading=15),
        "cover_credit":  ParagraphStyle("cc", fontName="Outfit", fontSize=9,
                                        textColor=MUTED, alignment=TA_CENTER,
                                        leading=14),

        "part_num":      ParagraphStyle("pn", fontName="Outfit-M", fontSize=11,
                                        textColor=ROSA, alignment=TA_CENTER,
                                        leading=14, spaceAfter=2),
        "part_title":    ParagraphStyle("pt", fontName="Playfair-B", fontSize=30,
                                        textColor=INK, alignment=TA_CENTER,
                                        leading=36, spaceAfter=10),
        "part_desc":     ParagraphStyle("pd", fontName="Playfair-I", fontSize=14,
                                        textColor=TEAL, alignment=TA_CENTER,
                                        leading=20),

        "section_num":   ParagraphStyle("sn", fontName="Outfit-M", fontSize=10,
                                        textColor=ROSA, alignment=TA_LEFT,
                                        leading=14, spaceAfter=2,
                                        letterSpacing=1.5),
        "section_title": ParagraphStyle("st", fontName="Playfair-B", fontSize=22,
                                        textColor=INK, alignment=TA_LEFT,
                                        leading=28, spaceAfter=12),

        "h2":            ParagraphStyle("h2", fontName="Playfair-BI", fontSize=16,
                                        textColor=INK, alignment=TA_LEFT,
                                        leading=21, spaceBefore=16, spaceAfter=6),
        "h3":            ParagraphStyle("h3", fontName="Outfit-SB", fontSize=12,
                                        textColor=TEAL_DARK, alignment=TA_LEFT,
                                        leading=16, spaceBefore=10, spaceAfter=4),
        "h4":            ParagraphStyle("h4", fontName="Outfit-SB", fontSize=10,
                                        textColor=MUTED, alignment=TA_LEFT,
                                        leading=14, spaceBefore=8, spaceAfter=3),
        "body":          ParagraphStyle("bd", fontName="Outfit", fontSize=10.5,
                                        textColor=BODY, alignment=TA_LEFT,
                                        leading=16, spaceAfter=9),
        "section_lead":  ParagraphStyle("sl", fontName="Playfair-I", fontSize=13,
                                        textColor=TEAL_DARK, alignment=TA_LEFT,
                                        leading=19, spaceAfter=0,
                                        leftIndent=10, rightIndent=4),
        "pull_quote":    ParagraphStyle("pq", fontName="Playfair-I", fontSize=15,
                                        textColor=ROSA, alignment=TA_CENTER,
                                        leading=22, spaceBefore=6, spaceAfter=6,
                                        leftIndent=20, rightIndent=20),
        "bullet":        ParagraphStyle("bu", fontName="Outfit", fontSize=10.5,
                                        textColor=BODY, alignment=TA_LEFT,
                                        leading=16, spaceAfter=4,
                                        leftIndent=14, bulletIndent=0,
                                        firstLineIndent=0),
        "num":           ParagraphStyle("nu", fontName="Outfit", fontSize=10.5,
                                        textColor=BODY, alignment=TA_LEFT,
                                        leading=16, spaceAfter=6,
                                        leftIndent=16, bulletIndent=0),

        "toc_head":      ParagraphStyle("th", fontName="Playfair-B", fontSize=24,
                                        textColor=INK, alignment=TA_LEFT,
                                        leading=30, spaceAfter=18),
        "toc_part":      ParagraphStyle("tp", fontName="Outfit-M", fontSize=10,
                                        textColor=ROSA, alignment=TA_LEFT,
                                        leading=14, spaceBefore=10, spaceAfter=4,
                                        letterSpacing=1.5),
        "toc_item":      ParagraphStyle("ti", fontName="Outfit", fontSize=11,
                                        textColor=BODY, alignment=TA_LEFT,
                                        leading=18),

        "closing_h":     ParagraphStyle("ch", fontName="Playfair-I", fontSize=22,
                                        textColor=TEAL_DARK, alignment=TA_CENTER,
                                        leading=28, spaceAfter=16),
        "closing_b":     ParagraphStyle("cb", fontName="Outfit", fontSize=11,
                                        textColor=BODY, alignment=TA_CENTER,
                                        leading=17, spaceAfter=10),
    }

# ─── BUILDERS ─────────────────────────────────────────────────────────────────

def build_cover(story, styles, chart, tier_name, tier_subtitle):
    """La copertina è 100% disegnata dal canvas (pdf_theme.draw_cover_page):
    cornice doppia oro, rose dei venti, fleuron, cartiglio col nome del cliente,
    frase dedicatoria personalizzata, sigillo di ceralacca con iniziali.
    Qui inseriamo solo un placeholder invisibile per riservare la pagina."""
    story.append(Spacer(1, 1*mm))
    # (marker + PageBreak gestiti da build_pdf)


def build_chart_page(story, styles, chart, png_bytes):
    """Pagina dedicata al bodygraph colorato personalizzato del cliente."""
    story.append(Spacer(1, 0.6*cm))
    story.append(Paragraph("La tua Carta", styles["toc_head"]))
    story.append(HRFlowable(width="20%", thickness=1.2, color=ROSA, hAlign="LEFT"))
    story.append(Spacer(1, 0.4*cm))
    story.append(Paragraph(
        f"Generata dal tuo momento di nascita: "
        f"<b>{chart['birth_date']}</b> · {chart['birth_time']} · {chart['birth_place']}.",
        styles["body"]
    ))
    story.append(Spacer(1, 0.4*cm))

    # Carica PNG in un oggetto Image centrato
    img_reader = ImageReader(BytesIO(png_bytes))
    iw, ih = img_reader.getSize()
    # Altezza target 17 cm, larghezza proporzionale
    target_h = 17 * cm
    target_w = target_h * (iw / ih)
    img = Image(BytesIO(png_bytes), width=target_w, height=target_h)
    img.hAlign = "CENTER"
    story.append(img)

    story.append(Spacer(1, 0.5*cm))
    # Legenda colori — swatch fatti con backColor di ReportLab (3 nbsp su font
    # colorato) al posto di ■ che non esiste in Outfit/Playfair.
    nbsp = "\u00a0" * 3
    legend = (
        f"<font backColor='#1a1a2e' color='#1a1a2e'>{nbsp}</font> "
        "Porte di Personalità (conscio) &nbsp;·&nbsp; "
        f"<font backColor='#c0392b' color='#c0392b'>{nbsp}</font> "
        "Porte di Design (inconscio) &nbsp;·&nbsp; "
        "porte attivate da entrambi = striscia bicolore. "
        "I centri colorati sono Definiti, quelli grigi sono Aperti."
    )
    leg_style = ParagraphStyle(
        "leg", fontName="Outfit", fontSize=8.5, textColor=MUTED,
        alignment=TA_CENTER, leading=13,
    )
    story.append(Paragraph(legend, leg_style))


def build_toc(story, styles, section_keys, chart=None):
    titles = section_titles(chart)
    story.append(Spacer(1, 1*cm))
    story.append(Paragraph("Indice", styles["toc_head"]))
    story.append(HRFlowable(width="20%", thickness=1.2, color=ROSA, hAlign="LEFT"))
    story.append(Spacer(1, 0.8*cm))

    section_num = 1
    for part_num, part_desc, part_keys in PARTS:
        keys_in_tier = [k for k in part_keys if k in section_keys]
        if not keys_in_tier:
            continue
        story.append(Paragraph(f"{part_num.upper()} · {part_desc}", styles["toc_part"]))
        for key in keys_in_tier:
            title = titles[key]
            story.append(Paragraph(
                f"<font color='#B68397'>{section_num:02d}</font>  &nbsp; {title}",
                styles["toc_item"]
            ))
            section_num += 1


def build_part_divider(story, styles, part_num, part_title, part_desc):
    """Il divisore di Parte è 100% disegnato dal canvas (pdf_theme.draw_part_page).
    Qui mettiamo solo un placeholder invisibile per riservare la pagina."""
    story.append(Spacer(1, 1*mm))


def _section_lead_flowable(content_html: str, styles):
    """Lead paragraph (il primo p del body) reso come blocco italico
    teal con barra verticale rosa a sinistra — rompe la wall-of-text
    e dà al lettore un'ancora visiva all'inizio di ogni sezione."""
    p = Paragraph(content_html, styles["section_lead"])
    t = Table([[p]], colWidths=["*"])
    t.setStyle(TableStyle([
        ("LINEBEFORE", (0, 0), (0, 0), 2.5, ROSA),
        ("LEFTPADDING",   (0, 0), (-1, -1), 12),
        ("RIGHTPADDING",  (0, 0), (-1, -1), 6),
        ("TOPPADDING",    (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("VALIGN",        (0, 0), (-1, -1), "TOP"),
    ]))
    return t


def _maybe_pull_quote(blocks: list, styles) -> tuple[int, list] | None:
    """Sceglie un paragrafo medio-corto (tra 120-260 char) dopo la prima metà
    di una sezione lunga e lo trasforma in pull-quote. Ritorna (idx, flowables)
    da sostituire al paragrafo originale, o None se la sezione è troppo corta
    o non trova candidati."""
    # Solo paragrafi p (indici assoluti nella lista blocks)
    p_indices = [i for i, (k, _) in enumerate(blocks) if k == "p"]
    if len(p_indices) < 6:
        return None
    # Cerca nella seconda metà
    start = p_indices[len(p_indices) // 2]
    end   = p_indices[-2] if len(p_indices) > 3 else p_indices[-1]
    candidates = []
    for i in p_indices:
        if i < start or i > end:
            continue
        raw = blocks[i][1]
        n = len(raw)
        if 120 <= n <= 260:
            candidates.append((n, i, raw))
    if not candidates:
        return None
    # Pull quote = il più corto (più scannabile)
    candidates.sort()
    _, idx, raw = candidates[0]
    # Usa la prima frase se multi-frase
    m = re.match(r"^(.+?[\.!\?])\s", raw)
    quote = m.group(1) if m else raw
    quote_html = inline_md(escape_xml(quote))
    quote_html = f"&ldquo;{quote_html}&rdquo;"
    flowables = [
        Spacer(1, 0.15 * cm),
        HRFlowable(width="22%", thickness=0.6, color=ROSA, hAlign="CENTER"),
        Spacer(1, 0.15 * cm),
        Paragraph(quote_html, styles["pull_quote"]),
        Spacer(1, 0.15 * cm),
        HRFlowable(width="22%", thickness=0.6, color=ROSA, hAlign="CENTER"),
        Spacer(1, 0.3 * cm),
    ]
    return idx, flowables


def _section_end_ornament(story):
    """Piccolo ornamento centrato a fine sezione — dà aria e chiude.
    Usa caratteri presenti in Outfit (niente glyph Playfair mancanti che
    creano pagine orfane). "· · ·" è sempre disponibile."""
    orn_style = ParagraphStyle(
        "orn", fontName="Outfit-M", fontSize=11, textColor=ORO,
        alignment=TA_CENTER, leading=14, spaceBefore=6, spaceAfter=2,
        letterSpacing=4,
    )
    story.append(Spacer(1, 0.15 * cm))
    story.append(Paragraph("·  ·  ·", orn_style))
    story.append(Spacer(1, 0.1 * cm))


def _inject_section_assets(story, styles, key, chart):
    """Asset Canva disabilitati: né centri né circuiti vengono inseriti.
    Lasciato come hook per futura reintroduzione selettiva."""
    return


def render_section(story, styles, num, key, text, chart=None, tier_name="Completo"):
    titles = section_titles(chart)
    title = titles[key]

    # --- Header della sezione: titolo + HR + spacer ---
    # Raccolti in una lista per wrappare in KeepTogether col primo paragrafo
    header = [
        Paragraph(f"SEZIONE {num:02d}", styles["section_num"]),
        Paragraph(title, styles["section_title"]),
        HRFlowable(width="22%", thickness=1.2, color=ROSA, hAlign="LEFT"),
        Spacer(1, 0.5*cm),
    ]

    # Asset grafici Canva all'apertura di alcune sezioni specifiche
    asset_flowables = []
    if chart is not None:
        _inject_section_assets(asset_flowables, styles, key, chart)

    # Safety net lessico: garantisce BG5 (HD) alla prima occorrenza
    text = ensure_lessico(text)

    # Salta eventuale duplicato del titolo sezione (il modello ripete "# <titolo>"
    # all'inizio). Confronto per parole significative: estrae parole alfabetiche
    # (≥3 lettere, no numeri, no articoli/preposizioni) e verifica che le prime N
    # del titolo siano tutte presenti nelle prime parole del heading.
    _STOP = {"il", "lo", "la", "le", "gli", "un", "una", "del", "dei", "di",
             "nel", "per", "tuo", "tua", "tuoi", "tue", "suo", "sua", "suoi"}
    def _keywords(s):
        return [w for w in re.findall(r"[a-zA-ZàèéìòùÀÈÉÌÒÙ]+", s.lower())
                if len(w) >= 3 and w not in _STOP]

    title_kw = _keywords(title)

    # Materializza i blocchi prima: serve per (a) saltare titolo duplicato,
    # (b) promuovere il primo p a "section lead", (c) inserire una pull quote.
    blocks = list(paragraphs(text))
    # Salta titolo duplicato se presente come primo heading
    if blocks and blocks[0][0] in ("h2", "h3", "h4"):
        head_kw = _keywords(blocks[0][1])
        # Match se almeno le prime 2 keyword del titolo compaiono nelle prime 6 del heading
        match_count = sum(1 for kw in title_kw[:3] if kw in head_kw[:6])
        if match_count >= 2:
            blocks = blocks[1:]

    # Indice del primo paragrafo p (diventerà il lead)
    lead_idx = next((i for i, (k, _) in enumerate(blocks) if k == "p"), -1)

    # Pull quote: solo Completo, solo se la sezione è lunga abbastanza
    is_essenziale = tier_name.lower() == "essenziale"
    pull = None if is_essenziale else _maybe_pull_quote(blocks, styles)
    pull_idx, pull_flowables = (pull if pull else (-1, None))

    # KeepTogether: header + asset + primo paragrafo lead devono stare
    # sulla stessa pagina. Se non ci stanno, vanno TUTTI alla pagina dopo.
    # Questo impedisce che il titolo inizi a fondo pagina col corpo altrove.
    keep_together_block = header + asset_flowables

    first_body_added = False
    for i, (kind, content) in enumerate(blocks):
        if i == pull_idx and pull_flowables is not None:
            # Pull quote: prima chiudi il KeepTogether se non ancora fatto
            if not first_body_added:
                story.append(KeepTogether(keep_together_block))
                first_body_added = True
            for f in pull_flowables:
                story.append(f)
            continue
        content_html = inline_md(escape_xml(content))
        if i == lead_idx and kind == "p":
            # Il lead va dentro il KeepTogether col titolo
            keep_together_block.append(_section_lead_flowable(content_html, styles))
            keep_together_block.append(Spacer(1, 0.4 * cm))
            story.append(KeepTogether(keep_together_block))
            first_body_added = True
            continue

        # Se arriviamo a un blocco non-lead senza aver chiuso il KT
        if not first_body_added:
            story.append(KeepTogether(keep_together_block))
            first_body_added = True

        if kind == "h2":
            story.append(Paragraph(content_html, styles["h2"]))
        elif kind == "h3":
            story.append(Paragraph(content_html, styles["h3"]))
        elif kind == "h4":
            story.append(Paragraph(content_html, styles["h4"]))
        elif kind == "bullet":
            story.append(Paragraph(f"•  {content_html}", styles["bullet"]))
        elif kind == "num":
            story.append(Paragraph(content_html, styles["num"], bulletText=None))
        else:
            story.append(Paragraph(content_html, styles["body"]))

    # Se la sezione non aveva blocchi di testo, chiudi comunque il KT
    if not first_body_added:
        story.append(KeepTogether(keep_together_block))

    # (ornamento di fine sezione rimosso: in alcuni casi atterrava
    # da solo su una pagina orfana a causa del PageBreak successivo.)


def build_closing(story, styles):
    story.append(Spacer(1, 5*cm))
    story.append(Paragraph("Questo è solo l'inizio.", styles["closing_h"]))
    story.append(HRFlowable(width="16%", thickness=1, color=ROSA, hAlign="CENTER"))
    story.append(Spacer(1, 0.8*cm))
    story.append(Paragraph(
        "Il BG5 Business Blueprint funziona come uno specchio da rileggere nel tempo.<br/>"
        "Torna alle pagine che ti colpiscono di più, prova una cosa alla volta nel corpo,<br/>"
        "e osserva la Soddisfazione quando arriva.",
        styles["closing_b"]
    ))
    story.append(Spacer(1, 1.2*cm))
    story.append(Paragraph(
        "Per domande, approfondimenti o una sessione dedicata:",
        styles["closing_b"]
    ))
    story.append(Paragraph(
        "<font color='#4A8C8C'><b>valentina@valentinarussobg5.com</b></font>",
        styles["closing_b"]
    ))
    story.append(Paragraph("valentinarussobg5.com", styles["closing_b"]))
    story.append(Spacer(1, 2*cm))
    story.append(HRFlowable(width="10%", thickness=0.6, color=TEAL, hAlign="CENTER"))
    story.append(Spacer(1, 0.3*cm))
    story.append(Paragraph(
        "Valentina Russo · consulente BG5 certificata",
        styles["cover_credit"]
    ))


def _mark_and_break(story, category, **kwargs):
    """Inserisce il marker categoria + PageBreak. Il marker viene eseguito durante
    il draw della pagina corrente (prima del break) e setta lo stato per onPage
    della pagina successiva."""
    story.append(PageCategoryMarker(category, **kwargs))
    story.append(PageBreak())


def _mark_and_soft_break(story, category, **kwargs):
    """Come _mark_and_break ma con un break condizionale: spezza pagina solo
    se rimane meno di 7 cm, altrimenti mette un semplice spacer tra le sezioni.
    Usato tra sezioni della stessa Parte per evitare pagine bianche."""
    story.append(PageCategoryMarker(category, **kwargs))
    story.append(CondPageBreak(7*cm))
    story.append(Spacer(1, 0.8*cm))


def build_pdf(sections, section_keys, out_path, tier_name, tier_subtitle, chart, chart_png):
    # Margine sinistro leggermente più ampio per ospitare crocette + watermark
    doc = SimpleDocTemplate(
        str(out_path), pagesize=A4,
        leftMargin=2.4*cm, rightMargin=2.4*cm,
        topMargin=2.6*cm, bottomMargin=2.4*cm,
        title=f"BG5 Business Blueprint {tier_name} — {chart['customer_name']}",
        author="Valentina Russo — valentinarussobg5.com",
        subject="BG5 Business Blueprint personalizzato",
    )
    styles = build_styles()
    story = []

    # 1. Copertina (pagina 1 = cover di default)
    build_cover(story, styles, chart, tier_name, tier_subtitle)
    _mark_and_break(story, "chart")

    # 2. Carta personalizzata
    build_chart_page(story, styles, chart, chart_png)
    _mark_and_break(story, "toc")

    # 3. Indice
    build_toc(story, styles, section_keys, chart=chart)

    # 4. Contenuto con divisori di Parte
    section_num = 1
    for part_num_str, part_desc, part_keys in PARTS:
        keys_in_tier = [k for k in part_keys if k in section_keys]
        if not keys_in_tier:
            continue
        # Numero parte (I..V) dal prefisso "Parte X"
        roman = part_num_str.replace("Parte ", "").strip()
        roman_to_int = {"I": 1, "II": 2, "III": 3, "IV": 4, "V": 5}
        pnum = roman_to_int.get(roman, 1)

        # Transizione → pagina Parte (full-bleed)
        _mark_and_break(story, "part", part_num=pnum)
        build_part_divider(story, styles, part_num_str, part_desc, "")

        # Prima sezione della Parte: hard break (viene da pagina Part divider)
        _mark_and_break(story, "section_start", section_num=section_num)
        for i, key in enumerate(keys_in_tier):
            if i > 0:
                # Tra sezioni della stessa Parte: soft break condizionale
                _mark_and_soft_break(story, "section_start", section_num=section_num)
            text = sections.get(key, "[sezione mancante]")
            render_section(story, styles, section_num, key, text, chart=chart,
                           tier_name=tier_name)
            section_num += 1

    # 5. Chiusura
    _mark_and_break(story, "closing")
    build_closing(story, styles)

    # Il callback riceve il chart intero così la copertina può usare i campi
    # (es. tier_subtitle, metadata) per personalizzare frase/sigillo.
    chart_with_tier = dict(chart)
    chart_with_tier["tier_subtitle"] = tier_subtitle
    callback = make_theme_callback(tier_name, chart["customer_name"], chart=chart_with_tier)
    doc.build(story, onFirstPage=callback, onLaterPages=callback)
    print(f"[rebuild] PDF {tier_name}: {out_path} ({out_path.stat().st_size // 1024} KB)")


# ─── MAIN ─────────────────────────────────────────────────────────────────────

def main():
    register_fonts()

    json_path = Path(__file__).parent / "spike-output.json"
    if not json_path.exists():
        print(f"ERRORE: {json_path} non trovato. Lancia prima spike_test.py")
        sys.exit(1)

    data = json.loads(json_path.read_text(encoding="utf-8"))
    sections = data["sections"]
    chart = data["chart"]

    print(f"[rebuild] Font registrate: Playfair + Outfit")
    print(f"[rebuild] Sezioni disponibili: {len(sections)}")
    print(f"[rebuild] Cliente: {chart['customer_name']}")
    print()

    out_dir = Path("D:/Download")
    out_dir.mkdir(parents=True, exist_ok=True)

    # Genera una sola volta il bodygraph personalizzato (PNG ad alta risoluzione)
    activations = activations_from_chart(chart)
    print(f"[rebuild] Bodygraph attivazioni: "
          f"{len(activations['p_gates'])} P · "
          f"{len(activations['d_gates'])} D · "
          f"{len(activations['defined_centers'])} centri definiti")
    chart_png = render_bodygraph_png(activations, width=1400)
    print(f"[rebuild] Bodygraph PNG: {len(chart_png) // 1024} KB")
    print()

    build_pdf(
        sections, ESSENZIALE_KEYS,
        out_dir / f"bg5-{chart['customer_name'].lower().replace(' ','-')}-essenziale.pdf",
        tier_name="Essenziale",
        tier_subtitle="Identità energetica",
        chart=chart,
        chart_png=chart_png,
    )
    build_pdf(
        sections, COMPLETO_KEYS,
        out_dir / f"bg5-{chart['customer_name'].lower().replace(' ','-')}-completo.pdf",
        tier_name="Completo",
        tier_subtitle="Identità energetica + Magnetic Marketing",
        chart=chart,
        chart_png=chart_png,
    )
    print()
    print(f"[rebuild] Done.")


if __name__ == "__main__":
    main()
