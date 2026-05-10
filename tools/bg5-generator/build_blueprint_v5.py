#!/usr/bin/env python3
"""
BG5 Blueprint · v5 — 7 capitoli + appendice visuale ristrutturata

Modifiche vs v4 (feedback 22/04):
  · Eliminato eyebrow "Pag. 01" nelle pagine di capitolo
  · Eliminate righe di completamento "_____" sotto "cosa ho notato"
  · Eliminato "cosa ho notato" stesso (non ha senso in PDF digitale)
  · Legenda linee nere/rosse/strisce spiegata chiaramente
  · Appendice in 4 pagine:
      1. Mappa — bodygraph completo + legenda colori/linee
      2. I tasti — sintesi Tipo / Strategia / Autorità / Profilo / Def /
         Segnale Successo-Amarezza / Croce (con rimandi ai capitoli)
      3. Definiti e aperti — panorama 9 centri (● definito / ○ aperto)
      4. La tua Croce — porte attive di Croce di Incarnazione (→ Cap. 06)
    NOTA: i raggruppamenti "centri superiori/centrali/inferiori" sono stati rimossi —
    non sono parte del sistema Human Design. I 9 centri sono entità singole,
    non appartengono a "gruppi" nel canone HD ufficiale.
  · Fix GATE_TO_CENTER: porte 38 e 54 in Radice (erano in Milza — bug)
  · Layout zoom: img 70mm / text 90mm / gap 2mm → no più overlap
  · Ogni centro: ● Def / ○ Apt + 1 riga meccanica + porte P/D + rimando Cap 04
  · Niente ripetizione di concetti — ogni volta che un tema è trattato in un
    capitolo, l'appendice rimanda con "→ Capitolo 0X"
"""
from __future__ import annotations

import argparse
import html
import io
import json
import re
from pathlib import Path

from PIL import Image
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import cm, mm
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_JUSTIFY, TA_RIGHT
from reportlab.lib import colors as rl_colors
from reportlab.platypus import (
    BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer, PageBreak,
    CondPageBreak, Image as RLImage, Table, TableStyle, Flowable,
)
from reportlab.platypus.tableofcontents import TableOfContents

import pdf_theme
from rebuild_pdfs import register_fonts
from bodygraph_svg import (
    render_bodygraph_png, CENTER_BBOX_SVG, SVG_VIEWBOX_W, SVG_VIEWBOX_H,
)

# ─── CONSTANTS ────────────────────────────────────────────────────────────────

HERE = Path(__file__).parent
GATES_JSON = HERE / "data" / "gates.json"

# ─── TYPE / AUTHORITY / DEFINITION LOOKUP ─────────────────────────────────────

TYPE_DATA = {
    "Proiettore": {
        "tipo_desc": "Qui per guidare — non per eseguire.",
        "strategia_desc": "Riconoscimento, invito, ingresso. Non ti proponi.",
        "firma_desc": 'Successo = vita giusta. Amarezza = bussola che dice "ricalibra".',
    },
    "Generatore": {
        "tipo_desc": "Qui per rispondere alla vita e trovare il lavoro giusto.",
        "strategia_desc": "Aspetta che la vita ti si presenti, rispondi con il Sacrale.",
        "firma_desc": 'Soddisfazione = impegno giusto. Frustrazione = bussola che dice "fermati".',
    },
    "Generatore Manifestante": {
        "tipo_desc": "Qui per rispondere, iniziare e manifestare velocemente.",
        "strategia_desc": "Rispondi, informa chi è coinvolto, agisci.",
        "firma_desc": 'Soddisfazione = impegno giusto. Frustrazione o rabbia = bussola che dice "ricalibra".',
    },
    "Manifestatore": {
        "tipo_desc": "Qui per iniziare, aprire strade, muovere le cose.",
        "strategia_desc": "Informa prima di agire — riduci la resistenza degli altri.",
        "firma_desc": 'Pace = nel flusso. Rabbia = bussola che dice "informa prima di muoverti".',
    },
    "Riflettore": {
        "tipo_desc": "Qui per rispecchiare la salute della comunità.",
        "strategia_desc": "Aspetta il ciclo lunare completo (28 giorni) prima di decidere.",
        "firma_desc": 'Meraviglia = vita che scorre. Delusione = bussola che dice "ricalibra".',
    },
}

AUTHORITY_DISPLAY = {
    "Mentale": "Nessuna autorità interiore",
    "Sacrale": "Autorità Sacrale",
    "Emozionale": "Autorità Emozionale",
    "Milza": "Autorità della Milza",
    "Io/Ego": "Autorità dell'Ego",
    "Sé/Identità": "Autorità dell'Identità",
    "Ambientale": "Autorità Ambientale",
    "Lunare": "Autorità Lunare",
}

AUTHORITY_DESC = {
    "Mentale": "Decidi fuori di te — nel dialogo, nel tempo, con persone fidate.",
    "Sacrale": "Il Sacrale risponde nell'istante — un sì o no corporeo, non mentale.",
    "Emozionale": "La chiarezza emerge nel tempo — aspetta che l'onda emotiva si assesti.",
    "Milza": "L'intuizione arriva una volta sola, nell'istante — senti nel corpo.",
    "Io/Ego": "La volontà decide — ma solo quando puoi davvero mantenerla.",
    "Sé/Identità": "Il luogo e le persone giuste ti portano chiarezza — scegli l'ambiente.",
    "Ambientale": "L'ambiente giusto fa emergere la decisione — scegli con attenzione dove stai.",
    "Lunare": "Il ciclo lunare completo (28 giorni) porta chiarezza — non decidere subito.",
}

DEFINITION_DESC = {
    "Singola": "Un'unica definizione coerente — tutto passa per lo stesso circuito, autonomo.",
    "Doppia": "Due circuiti separati — puoi vedere da due angoli, hai bisogno di tempo per integrarli.",
    "Tripla": "Tre circuiti — grande varietà interna, richiede ambiente stabile per l'integrazione.",
    "Quadrupla": "Quattro circuiti — la complessità più ricca, richiede molto spazio per esprimersi.",
    "Non definita": "Nessun centro definito — sei il più puro degli specchi.",
}

PROFILE_DESC = {
    "1/3": "Fondamenta solide + apprendimento per tentativi — la stabilità che si costruisce.",
    "1/4": "Fondamenta solide + opportunità nelle relazioni — la rete che sostiene la ricerca.",
    "2/4": "Talento naturale che emerge da sola + vita costruita sulle relazioni.",
    "2/5": "Talento naturale + proiezioni del mondo — sei vista come soluzione.",
    "3/5": "Apprendimento per tentativi + proiezioni del mondo — stai scoprendo la via mentre la mostri.",
    "3/6": "Apprendimento per tentativi + ruolo modello — tre fasi di vita distinte.",
    "4/6": "Opportunità nelle relazioni + ruolo modello — la rete che costruisce l'esempio.",
    "4/1": "Opportunità nelle relazioni + fondamenta solide — costruisce da basi sicure con le persone giuste.",
    "5/1": "Proiezioni del mondo + fondamenta solide — la mente pratica che risolve i problemi degli altri.",
    "5/2": "Proiezioni del mondo + talento naturale — vista come eretica, porta soluzioni.",
    "6/2": "Ruolo modello + talento naturale — tre fasi, poi l'esempio diventa visibile.",
    "6/3": "Ruolo modello + apprendimento per tentativi — vive la vita per poi trasmetterla.",
}

QUARTER_PREPS = {
    "Civilizzazione": "della",
    "Iniziazione": "dell'",
    "Dualismo": "del",
    "Mutazione": "della",
}

# Fix mapping: 38 e 54 sono nella RADICE, non nella Milza
GATE_TO_CENTER = {
    64: "Testa", 61: "Testa", 63: "Testa",
    47: "Ajna", 24: "Ajna", 4: "Ajna", 17: "Ajna", 11: "Ajna", 43: "Ajna",
    62: "Gola", 23: "Gola", 56: "Gola", 31: "Gola", 8: "Gola", 33: "Gola",
    35: "Gola", 12: "Gola", 45: "Gola", 16: "Gola", 20: "Gola",
    7: "G", 1: "G", 13: "G", 15: "G", 2: "G", 46: "G", 25: "G", 10: "G",
    26: "Cuore", 51: "Cuore", 21: "Cuore", 40: "Cuore",
    37: "Plesso", 22: "Plesso", 36: "Plesso", 6: "Plesso", 49: "Plesso",
    55: "Plesso", 30: "Plesso",
    42: "Sacrale", 3: "Sacrale", 9: "Sacrale", 5: "Sacrale", 14: "Sacrale",
    29: "Sacrale", 27: "Sacrale", 34: "Sacrale", 59: "Sacrale",
    48: "Milza", 57: "Milza", 44: "Milza", 50: "Milza", 32: "Milza",
    28: "Milza", 18: "Milza",
    53: "Radice", 60: "Radice", 52: "Radice", 19: "Radice",
    39: "Radice", 41: "Radice", 58: "Radice", 38: "Radice", 54: "Radice",
}


# ─── CHART LOADING ────────────────────────────────────────────────────────────

# Italian center name → canonical code used in bodygraph renderer
_IT_TO_CODE = {
    "Testa": "HEAD", "Ajna": "AJNA", "Gola": "THROAT",
    "G": "G", "G/Se": "G", "Cuore": "HEART", "Cuore/Ego": "HEART",
    "Plesso Solare": "SOLAR", "Sacrale": "SACRAL",
    "Milza": "SPLEEN", "Radice": "ROOT",
}


def _load_chart_dict(chart_json_path: Path) -> dict:
    """Load chart dict from JSON. Handles both {chart: {...}} and flat format."""
    data = json.loads(chart_json_path.read_text(encoding="utf-8"))
    return data.get("chart", data)


def load_activations(chart_json_path: Path):
    chart = _load_chart_dict(chart_json_path)
    p_gates, d_gates = set(), set()
    for row in chart["activations"]:
        p_gates.add(int(row[1].split(".")[0]))
        d_gates.add(int(row[2].split(".")[0]))
    # Build canonical defined-centers set from chart JSON
    defined_it = chart.get("defined_centers", [])
    defined_canonical = {_IT_TO_CODE[c] for c in defined_it if c in _IT_TO_CODE}
    activ = {
        "p_gates": p_gates,
        "d_gates": d_gates,
        "defined_centers": defined_canonical,
    }
    return activ, chart


def load_gates_data() -> dict:
    """Load gates reference JSON once."""
    if GATES_JSON.exists():
        return json.loads(GATES_JSON.read_text(encoding="utf-8"))
    return {}


def cross_full_name(life_theme: str) -> str:
    """'dell'Amore 2, Civilizzazione' → 'Croce dell'Amore 2 della Civilizzazione'"""
    if not life_theme:
        return "Croce d'Incarnazione"
    parts = [p.strip() for p in life_theme.split(",")]
    cross_part = parts[0]
    quarter = parts[1] if len(parts) > 1 else ""
    prep = QUARTER_PREPS.get(quarter, "del")
    if quarter:
        return f"Croce {cross_part} {prep} {quarter}"
    return f"Croce {cross_part}"


def cross_gate_numbers(chart: dict) -> tuple[int, int, int, int]:
    """Return (p_sol, p_ter, d_sol, d_ter) gate numbers from activations."""
    activations = chart.get("activations", [])
    if len(activations) >= 2:
        p_sol = int(activations[0][1].split(".")[0])
        p_ter = int(activations[1][1].split(".")[0])
        d_sol = int(activations[0][2].split(".")[0])
        d_ter = int(activations[1][2].split(".")[0])
        return p_sol, p_ter, d_sol, d_ter
    return 0, 0, 0, 0


def derive_chapter_metadata(chart: dict) -> tuple[list[str], list[str]]:
    """Derive chapter titles and subtitles from chart data."""
    tipo = chart.get("career_type", "Proiettore")
    auth_raw = chart.get("authority", "Mentale")
    auth_display = AUTHORITY_DISPLAY.get(auth_raw, auth_raw)
    profile = chart.get("profile", "")
    profile_name = chart.get("profile_name", "")

    # Parse profile lines for subtitle
    lines = profile.split("/") if "/" in profile else ["", ""]
    line1 = lines[0].strip()
    line2 = lines[1].strip() if len(lines) > 1 else ""
    pparts = [p.strip() for p in profile_name.split("/")] if "/" in profile_name else []
    pn1 = pparts[0] if pparts else ""
    pn2 = pparts[1] if len(pparts) > 1 else ""

    definition = chart.get("definition", "")
    defined_centers = chart.get("defined_centers", [])
    undefined_centers = chart.get("undefined_centers", [])
    n_def = len(defined_centers)
    n_undef = len(undefined_centers)

    channels = chart.get("channels", [])
    if channels:
        ch_subtitle = " + ".join(
            f"{c['name']} {c['title'].replace('Canale ', '')}" for c in channels
        )
    else:
        ch_subtitle = "Nessun canale definito"

    signature = chart.get("signature", "")
    non_self = chart.get("non_self", "")
    life_theme = chart.get("life_theme", "")
    cross_name = cross_full_name(life_theme)
    p_sol, p_ter, d_sol, d_ter = cross_gate_numbers(chart)
    gates_str = f"porte {p_sol} · {p_ter} · {d_sol} · {d_ter}" if p_sol else ""

    # Singular/plural Italian grammar
    def _c(n, singular, plural):
        return singular if n == 1 else plural

    titles = [
        "Chi sei davvero",
        "Come prendi le decisioni",
        "Cosa porti nel mondo",
        "Come sei costruita",
        f"Profilo {profile} in profondità",
        "La tua missione",
        "Come tornare",
    ]
    subtitles = [
        f"{tipo} · Strategia · anteprima Profilo e Croce",
        f"{auth_display} · processo decisionale",
        ch_subtitle,
        (f"{definition} · {n_def} {_c(n_def, 'centro definito', 'centri definiti')} · "
         f"{n_undef} {_c(n_undef, 'centro non definito', 'centri non definiti')} · meccanica del Non-Sé"),
        (f"{pn1} (linea {line1}) · {pn2} (linea {line2}) · arco di vita"
         if pn1 else f"Profilo {profile} · arco di vita"),
        f"{cross_name}{' · ' + gates_str if gates_str else ''}",
        f"Diagnostica del Non-Sé · {signature} vs {non_self} · chiusura operativa",
    ]
    return titles, subtitles


def gates_by_center(p_set, d_set):
    """Ritorna dict center_name → {'P': [...], 'D': [...], 'PD': [...]}."""
    out = {}
    all_gates = p_set | d_set
    for g in all_gates:
        center = GATE_TO_CENTER.get(g)
        if not center:
            continue
        bucket = out.setdefault(center, {"P": [], "D": [], "PD": []})
        if g in p_set and g in d_set:
            bucket["PD"].append(g)
        elif g in p_set:
            bucket["P"].append(g)
        elif g in d_set:
            bucket["D"].append(g)
    for c in out.values():
        c["P"].sort(); c["D"].sort(); c["PD"].sort()
    return out


# ─── PNG helpers ──────────────────────────────────────────────────────────────

_TMP = HERE / "_tmp_v5"
_TMP.mkdir(exist_ok=True)
_CNT = [0]


def png_to_image(png_bytes: bytes, max_w: float, max_h: float) -> RLImage:
    img = Image.open(io.BytesIO(png_bytes)).convert("RGBA")
    iw, ih = img.size
    scale = min(max_w / iw, max_h / ih)
    w_pt, h_pt = iw * scale, ih * scale
    tgt = (max(1, int(w_pt * 2)), max(1, int(h_pt * 2)))
    resized = img.resize(tgt, Image.LANCZOS)
    _CNT[0] += 1
    path = _TMP / f"img_{_CNT[0]:03d}.png"
    resized.save(path, "PNG")
    ri = RLImage(str(path), width=w_pt, height=h_pt)
    ri.drawWidth = w_pt
    ri.drawHeight = h_pt
    ri.hAlign = "LEFT"
    return ri


def crop_multi_centers(png_bytes: bytes, codes: list[str],
                       padding_ratio: float = 0.08) -> bytes:
    img = Image.open(io.BytesIO(png_bytes))
    iw, ih = img.size
    sx = iw / SVG_VIEWBOX_W
    sy = ih / SVG_VIEWBOX_H
    boxes = [CENTER_BBOX_SVG[c] for c in codes]
    minx = min(b[0] for b in boxes) * sx
    miny = min(b[1] for b in boxes) * sy
    maxx = max(b[0] + b[2] for b in boxes) * sx
    maxy = max(b[1] + b[3] for b in boxes) * sy
    pw = (maxx - minx) * padding_ratio
    ph = (maxy - miny) * padding_ratio
    minx = max(0, minx - pw)
    miny = max(0, miny - ph)
    maxx = min(iw, maxx + pw)
    maxy = min(ih, maxy + ph)
    crop = img.crop((int(minx), int(miny), int(maxx), int(maxy)))
    out = io.BytesIO()
    crop.save(out, "PNG")
    return out.getvalue()


# ─── STYLES ───────────────────────────────────────────────────────────────────

def make_styles():
    return {
        "chap_number": ParagraphStyle(
            "chap_number", fontName="IMFell", fontSize=14, leading=18,
            textColor=pdf_theme.ORO, alignment=TA_LEFT, spaceAfter=2,
        ),
        "chap_title": ParagraphStyle(
            "chap_title", fontName="Playfair-BI", fontSize=26, leading=32,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_LEFT, spaceAfter=4,
        ),
        "chap_subtitle": ParagraphStyle(
            "chap_subtitle", fontName="Outfit", fontSize=10.5, leading=14,
            textColor=pdf_theme.MUTED_MAUVE, alignment=TA_LEFT, spaceAfter=18,
        ),
        "page_title": ParagraphStyle(
            "page_title", fontName="Playfair-B", fontSize=17, leading=22,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_LEFT, spaceAfter=14,
        ),
        "body": ParagraphStyle(
            "body", fontName="Outfit", fontSize=10.5, leading=16.5,
            textColor=pdf_theme.TEXT_INK, alignment=TA_JUSTIFY, spaceAfter=9,
        ),
        "blockquote": ParagraphStyle(
            "blockquote", fontName="Playfair-I", fontSize=12, leading=18,
            textColor=pdf_theme.MUTED_MAUVE, alignment=TA_LEFT,
            leftIndent=14, rightIndent=14, spaceAfter=14, spaceBefore=6,
        ),
        "toc_row": ParagraphStyle(
            "toc_row", fontName="Outfit", fontSize=11, leading=18,
            textColor=pdf_theme.TEXT_INK, alignment=TA_LEFT, spaceAfter=8,
        ),
        "toc_title": ParagraphStyle(
            "toc_title", fontName="Playfair-BI", fontSize=28, leading=36,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_CENTER, spaceAfter=24,
        ),
        "closing_title": ParagraphStyle(
            "closing_title", fontName="Playfair-BI", fontSize=24, leading=32,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_CENTER, spaceAfter=18,
        ),
        "closing_body": ParagraphStyle(
            "closing_body", fontName="Outfit", fontSize=11, leading=18,
            textColor=pdf_theme.TEXT_INK, alignment=TA_CENTER, spaceAfter=10,
        ),
        "closing_sign": ParagraphStyle(
            "closing_sign", fontName="IMFell-I", fontSize=12, leading=16,
            textColor=pdf_theme.ORO, alignment=TA_CENTER, spaceAfter=4,
        ),
        # Appendice
        "appx_eyebrow": ParagraphStyle(
            "appx_eyebrow", fontName="IMFell", fontSize=10, leading=13,
            textColor=pdf_theme.ORO, alignment=TA_CENTER, spaceAfter=2,
        ),
        "appx_eyebrow_L": ParagraphStyle(
            "appx_eyebrow_L", fontName="IMFell", fontSize=10, leading=13,
            textColor=pdf_theme.ORO, alignment=TA_LEFT, spaceAfter=2,
        ),
        "appx_title": ParagraphStyle(
            "appx_title", fontName="Playfair-BI", fontSize=22, leading=28,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_CENTER, spaceAfter=6,
        ),
        "appx_title_L": ParagraphStyle(
            "appx_title_L", fontName="Playfair-BI", fontSize=22, leading=28,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_LEFT, spaceAfter=6,
        ),
        "appx_subtitle": ParagraphStyle(
            "appx_subtitle", fontName="Outfit", fontSize=10, leading=14,
            textColor=pdf_theme.MUTED_MAUVE, alignment=TA_CENTER, spaceAfter=14,
        ),
        "appx_subtitle_L": ParagraphStyle(
            "appx_subtitle_L", fontName="Outfit", fontSize=10, leading=14,
            textColor=pdf_theme.MUTED_MAUVE, alignment=TA_LEFT, spaceAfter=14,
        ),
        "appx_legend": ParagraphStyle(
            "appx_legend", fontName="Outfit", fontSize=9, leading=13,
            textColor=pdf_theme.TEXT_INK, alignment=TA_CENTER, spaceAfter=3,
        ),
        "appx_meta": ParagraphStyle(
            "appx_meta", fontName="Outfit", fontSize=8.5, leading=13,
            textColor=pdf_theme.TEXT_INK, alignment=TA_CENTER, spaceAfter=4,
        ),
        "appx_section_h": ParagraphStyle(
            "appx_section_h", fontName="Playfair-B", fontSize=12, leading=15,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_LEFT, spaceAfter=2,
            spaceBefore=4,
        ),
        "appx_pill": ParagraphStyle(
            "appx_pill", fontName="Outfit", fontSize=9, leading=12.5,
            textColor=pdf_theme.TEXT_INK, alignment=TA_LEFT, spaceAfter=2,
        ),
        "appx_body": ParagraphStyle(
            "appx_body", fontName="Outfit", fontSize=9.5, leading=13.5,
            textColor=pdf_theme.TEXT_INK, alignment=TA_LEFT, spaceAfter=4,
        ),
        "appx_ref": ParagraphStyle(
            "appx_ref", fontName="IMFell-I", fontSize=9, leading=12,
            textColor=pdf_theme.ORO, alignment=TA_LEFT, spaceAfter=2,
        ),
        "appx_key_label": ParagraphStyle(
            "appx_key_label", fontName="IMFell", fontSize=9, leading=12,
            textColor=pdf_theme.ORO, alignment=TA_LEFT, spaceAfter=1,
        ),
        "appx_key_value": ParagraphStyle(
            "appx_key_value", fontName="Playfair-B", fontSize=13, leading=17,
            textColor=pdf_theme.INK_NIGHT, alignment=TA_LEFT, spaceAfter=2,
        ),
        "appx_key_desc": ParagraphStyle(
            "appx_key_desc", fontName="Outfit", fontSize=9, leading=12.5,
            textColor=pdf_theme.TEXT_INK, alignment=TA_LEFT, spaceAfter=1,
        ),
    }


# ─── MARKDOWN INLINE ──────────────────────────────────────────────────────────

def md_inline(text: str) -> str:
    text = text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    text = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", text, flags=re.DOTALL)
    text = re.sub(r"(?<![\*])\*(?!\*)(.+?)(?<!\*)\*(?!\*)", r"<i>\1</i>", text,
                  flags=re.DOTALL)
    text = text.replace("\n", "<br/>")
    return text


def _xml(text) -> str:
    """Escape text for safe use inside a ReportLab Paragraph (XML context).
    Prevents injection of ReportLab XML tags from chart data fields.
    """
    if text is None:
        return ""
    return html.escape(str(text), quote=False)


def blocks_to_flowables(blocks, styles):
    fl = []
    for kind, txt in blocks:
        style = styles["blockquote"] if kind == "q" else styles["body"]
        fl.append(Paragraph(md_inline(txt), style))
    return fl


# ─── CHAPTER PARSING ──────────────────────────────────────────────────────────

PAGE_HEADER_RE = re.compile(r"^##\s*Pag\.?\s*(\d+)\s*[—·–-]\s*(.+)$", re.MULTILINE)


def parse_chapter(path: Path) -> list[dict]:
    text = path.read_text(encoding="utf-8")
    m = PAGE_HEADER_RE.search(text)
    if not m:
        raise ValueError(f"No page header in {path.name}")
    text = text[m.start():]
    segments = [s for s in re.split(r"(?m)^##\s*Pag", text) if s.strip()]
    pages = []
    for seg in segments:
        seg = "## Pag" + seg
        lines = seg.splitlines()
        hm = PAGE_HEADER_RE.match(lines[0])
        if not hm:
            continue
        body_lines = lines[1:]
        for i, ln in enumerate(body_lines):
            if ln.strip() == "---":
                body_lines = body_lines[:i]
                break
        body = "\n".join(body_lines).strip()
        blocks = []
        for para in re.split(r"\n\s*\n", body):
            para = para.strip()
            if not para:
                continue
            if all(ln.lstrip().startswith(">")
                   for ln in para.splitlines() if ln.strip()):
                txt = "\n".join(ln.lstrip(">").strip()
                                for ln in para.splitlines())
                blocks.append(("q", txt))
            else:
                blocks.append(("p", para))
        pages.append({
            "num": int(hm.group(1)),
            "title": hm.group(2).strip(),
            "blocks": blocks,
        })
    return pages


# ─── DOC ──────────────────────────────────────────────────────────────────────

class TocParagraph(Paragraph):
    """Paragraph che si registra nell'indice durante il build."""
    def __init__(self, text, style, toc_level=0, plain_text=None):
        super().__init__(text, style)
        self.toc_level = toc_level
        self._plain_toc = plain_text or text


class BlueprintDoc(BaseDocTemplate):
    def __init__(self, filename, theme_cb):
        BaseDocTemplate.__init__(
            self, filename, pagesize=A4,
            leftMargin=24*mm, rightMargin=24*mm,
            topMargin=26*mm, bottomMargin=22*mm,
        )
        frame = Frame(self.leftMargin, self.bottomMargin,
                      self.width, self.height,
                      leftPadding=0, rightPadding=0,
                      topPadding=0, bottomPadding=0, id="normal")
        self.addPageTemplates([PageTemplate(id="main", frames=[frame],
                                            onPage=theme_cb)])

    def afterFlowable(self, flowable):
        if isinstance(flowable, TocParagraph):
            self.notify('TOCEntry', (flowable.toc_level, flowable._plain_toc, self.page))


# ─── TOC ──────────────────────────────────────────────────────────────────────

def build_toc_flowables(styles):
    toc = TableOfContents()
    toc.dotsMinLevel = 0
    toc.levelStyles = [
        ParagraphStyle(
            "TOCLevel0",
            fontName="Outfit",
            fontSize=11,
            leading=20,
            leftIndent=0,
            rightIndent=0,
            spaceAfter=3,
            textColor=pdf_theme.INK_NIGHT,
        ),
    ]
    fl = []
    fl.append(Spacer(1, 10*mm))
    fl.append(Paragraph("Indice", styles["toc_title"]))
    fl.append(Spacer(1, 6*mm))
    fl.append(toc)
    # Imposta la categoria per la PROSSIMA pagina (appendice) prima del PageBreak
    fl.append(pdf_theme.PageCategoryMarker("chart"))
    fl.append(PageBreak())
    return fl


# ─── CENTER DESCRIPTIONS ──────────────────────────────────────────────────────
# Per ogni centro: meccanica aperto / meccanica definito — in italiano, una riga.

CENTER_INFO = {
    "Testa": {
        "meccanica": "Pressione delle idee, ispirazione mentale — centro della domanda.",
        "aperto": "ricevi pressione cognitiva dall'esterno — domande che non sono tue.",
        "definito": "hai una fonte costante di ispirazione e domande — pensi al posto degli altri.",
    },
    "Ajna": {
        "meccanica": "Elaborazione mentale — come concettualizzi e dai forma ai pensieri.",
        "aperto": "non hai un modo fisso di concepire — ti aperi a più prospettive, rischio opinionismo altrui.",
        "definito": "hai una mente stabile — un modo consistente di mettere in ordine le idee.",
    },
    "Gola": {
        "meccanica": "Voce e manifestazione — comunicazione, parola, azione.",
        "aperto": "non hai un timbro fisso — parli in modi diversi a seconda di chi hai davanti, non forzare.",
        "definito": "hai una voce riconoscibile, un modo tuo di esprimerti che arriva sempre uguale.",
    },
    "G": {
        "meccanica": "Identità, amore, direzione — il \"chi sono\" e \"dove vado\".",
        "aperto": "la tua identità si muove col contesto — cerchi te stessa attraverso incontri e luoghi.",
        "definito": "hai un'identità fissa e un senso di direzione chiaro dal di dentro.",
    },
    "Cuore": {
        "meccanica": "Volontà, valore, risorsa — quanto puoi promettere e mantenere.",
        "aperto": "non puoi forzare la volontà — ti stanchi se devi \"dimostrare\" di valere.",
        "definito": "hai una volontà su cui contare, puoi fare promesse e tenerle.",
    },
    "Plesso": {
        "meccanica": "Emozioni, sensualità, onda emotiva — chiarezza nel tempo.",
        "aperto": "assorbi e amplifichi le emozioni di chi ti sta intorno — scegli con chi stai.",
        "definito": "hai un'onda emotiva tua — la chiarezza arriva dopo aver dormito su una cosa.",
    },
    "Sacrale": {
        "meccanica": "Energia vitale, lavoro, vita generativa.",
        "aperto": "non hai energia motrice costante — lavora in burst, rispetta i segnali di stanchezza del corpo.",
        "definito": "hai una fonte di energia motoria costante — puoi lavorare a lungo se usi la risposta.",
    },
    "Milza": {
        "meccanica": "Intuizione, istinto, salute, paura — il \"qui e ora\" del corpo.",
        "aperto": "non hai intuizione costante — ti attacchi a persone o abitudini malsane per paura di perderle.",
        "definito": "hai un'intuizione costante, un sistema immunitario stabile — sai nell'istante.",
    },
    "Radice": {
        "meccanica": "Pressione del tempo, spinta a partire, adrenalina.",
        "aperto": "senti l'urgenza degli altri come tua — scadenze immaginarie che non servono.",
        "definito": "hai una pressione tua sul tempo — sai quando è il momento.",
    },
}


# ─── APPENDIX PAGES ───────────────────────────────────────────────────────────

def appx_gate_pill(gates: list[int], color: str, label: str, styles):
    if not gates:
        return None
    txt = "  ".join(str(g) for g in gates)
    return Paragraph(
        f'<font color="{color}"><b>{label}</b></font>: '
        f'<font color="{pdf_theme.TEXT_INK.hexval()}">{txt}</font>',
        styles["appx_pill"],
    )


def page_appx_full_bodygraph(styles, full_png):
    """Pagina 1 appendice: bodygraph completo + legenda esplicita di colori/linee."""
    fl = [pdf_theme.PageCategoryMarker("chart")]
    fl.append(Spacer(1, 2*mm))
    fl.append(Paragraph("La tua mappa", styles["appx_title"]))
    fl.append(Paragraph(
        "Bodygraph personale — la carta di come sei costruita",
        styles["appx_subtitle"],
    ))
    max_h = A4[1] - 22*mm - 20*mm - 82*mm
    img = png_to_image(full_png, max_w=A4[0] - 55*mm, max_h=max_h)
    tbl = Table([[img]], colWidths=[A4[0] - 48*mm])
    tbl.setStyle(TableStyle([
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
    ]))
    fl.append(tbl)
    fl.append(Spacer(1, 5*mm))

    # ── Legenda verticale ───────────────────────────────────────────────────
    # Stili interni alla legenda
    _leg_title = ParagraphStyle(
        "_leg_title",
        fontName="Playfair-B", fontSize=9, leading=13,
        textColor=pdf_theme.INK_NIGHT,
        spaceAfter=4,
    )
    _leg_label = ParagraphStyle(
        "_leg_label",
        fontName="Outfit-B", fontSize=8, leading=12,
        textColor=pdf_theme.INK_NIGHT,
    )
    _leg_body = ParagraphStyle(
        "_leg_body",
        fontName="Outfit", fontSize=8, leading=12,
        textColor=pdf_theme.TEXT_INK,
    )
    _leg_note = ParagraphStyle(
        "_leg_note",
        fontName="Outfit", fontSize=7.5, leading=11,
        textColor=pdf_theme.MUTED_MAUVE,
        spaceBefore=2,
    )

    # Quadratini colorati: rettangolo ReportLab inline via canvas trick
    # oppure, più semplice e garantito su tutti i font: Unicode FULL BLOCK &#x2588;
    # tagliato con <font size> per avere un quadratino compatto.
    SQ = '<font size="9">&#x25A0;</font>'  # ■ FULL SQUARE, presente in Outfit

    _INK  = pdf_theme.INK_NIGHT.hexval()   # "#1c1210"
    _RED  = "#8B2020"                       # rosso bodygraph — scuro per contrasto WCAG
    _MAUVE = pdf_theme.MUTED_MAUVE.hexval()
    _TEAL  = pdf_theme.TEAL.hexval()

    # Separatore orizzontale sottile tra blocco colori e blocco struttura
    class _HRule(Flowable):
        def __init__(self, width, color, thickness=0.4):
            super().__init__()
            self.width = width
            self._color = color
            self.thickness = thickness
            self.height = self.thickness + 2

        def draw(self):
            self.canv.saveState()
            self.canv.setStrokeColor(self._color)
            self.canv.setLineWidth(self.thickness)
            self.canv.line(0, self.thickness / 2, self.width, self.thickness / 2)
            self.canv.restoreState()

    leg_w = A4[0] - 55*mm   # stessa larghezza usata per l'immagine
    col_sq  = 6*mm           # colonna quadratino
    col_lbl = 22*mm          # colonna etichetta
    col_txt = leg_w - col_sq - col_lbl - 4*mm  # testo descrittivo

    def _color_row(sq_color, label, desc):
        sq_cell  = Paragraph(
            f'<font color="{sq_color}">{SQ}</font>', _leg_label
        )
        lbl_cell = Paragraph(f"<b>{label}</b>", _leg_label)
        txt_cell = Paragraph(desc, _leg_body)
        return [sq_cell, lbl_cell, txt_cell]

    color_rows = [
        _color_row(_INK,  "Nero",    "Personalit\u00e0 \u2014 ci\u00f2 che sai di essere (conscio)"),
        _color_row(_RED,  "Rosso",   "Design \u2014 ci\u00f2 che sei senza saperlo (inconscio)"),
        _color_row(_MAUVE, "Strisce", "Attivato da entrambi \u2014 doppia presenza"),
    ]

    struct_rows = [
        [
            Paragraph("", _leg_body),
            Paragraph("<b>Canali</b>", _leg_label),
            Paragraph(
                "Le linee tra i centri \u2014 collegano due porte, formano energia definita",
                _leg_body
            ),
        ],
        [
            Paragraph("", _leg_body),
            Paragraph("<b>Centri colorati</b>", _leg_label),
            Paragraph("Definiti \u2014 energia costante, sempre attiva", _leg_body),
        ],
        [
            Paragraph("", _leg_body),
            Paragraph("<b>Centri bianchi</b>", _leg_label),
            Paragraph(
                "Non definiti \u2014 ricevono energia dall\u2019esterno, campo della saggezza",
                _leg_body
            ),
        ],
    ]

    tbl_style = TableStyle([
        ("VALIGN",      (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING",(0, 0), (-1, -1), 2),
        ("TOPPADDING",  (0, 0), (-1, -1), 1),
        ("BOTTOMPADDING",(0, 0), (-1, -1), 2),
        ("LINEBELOW",   (0, -1), (-1, -1), 0, pdf_theme.CREAM),  # no border
    ])

    fl.append(Paragraph("Come leggere la mappa", _leg_title))
    fl.append(
        Table(
            color_rows,
            colWidths=[col_sq, col_lbl, col_txt],
            style=tbl_style,
        )
    )
    fl.append(Spacer(1, 3*mm))
    fl.append(_HRule(leg_w, pdf_theme.WARM_GRAY))
    fl.append(Spacer(1, 3*mm))
    fl.append(
        Table(
            struct_rows,
            colWidths=[col_sq, col_lbl, col_txt],
            style=tbl_style,
        )
    )
    fl.append(PageBreak())
    return fl


def page_appx_keys(styles, chart):
    """Pagina 2 appendice: i tasti della carta — sintesi + rimandi."""
    fl = [pdf_theme.PageCategoryMarker("chart")]
    fl.append(Spacer(1, 2*mm))
    fl.append(Paragraph("Elementi chiave", styles["appx_title"]))
    fl.append(Paragraph(
        "Le leve principali della tua carta — tipo, strategia, autorità, profilo, firma e croce",
        styles["appx_subtitle"],
    ))
    fl.append(Spacer(1, 4*mm))

    tipo = chart.get("career_type", "Proiettore")
    type_info = TYPE_DATA.get(tipo, TYPE_DATA["Proiettore"])

    auth_raw = chart.get("authority", "Mentale")
    auth_display = AUTHORITY_DISPLAY.get(auth_raw, auth_raw)
    auth_desc = AUTHORITY_DESC.get(auth_raw, "")

    profile = chart.get("profile", "")
    profile_name = chart.get("profile_name", "")
    profile_display = f"{profile} · {profile_name}" if profile_name else profile
    profile_desc = PROFILE_DESC.get(profile, "")

    definition = chart.get("definition", "")
    channels = chart.get("channels", [])
    if definition == "Singola" and channels:
        ch = channels[0]
        defin_desc = (f"Un solo canale definito ({ch['name']} "
                      f"{ch['title'].replace('Canale ', '')}) — tutta l'energia passa di lì.")
    elif channels:
        ch_names = " + ".join(c['name'] for c in channels)
        defin_desc = DEFINITION_DESC.get(definition, definition) + f" Canali: {ch_names}."
    else:
        defin_desc = DEFINITION_DESC.get(definition, definition)

    sig = chart.get("signature", "")
    ns = chart.get("non_self", "")
    firma_display = f"{sig} · {ns}" if ns else sig
    firma_desc = type_info.get("firma_desc", "")

    life_theme = chart.get("life_theme", "")
    cross_name = cross_full_name(life_theme)
    p_sol, p_ter, d_sol, d_ter = cross_gate_numbers(chart)
    cross_desc = (f"Porte {p_sol} · {p_ter} · {d_sol} · {d_ter} — il tema di fondo della tua vita."
                  if p_sol else "Il tema di fondo della tua vita.")

    keys = [
        ("tipo", tipo, type_info["tipo_desc"]),
        ("strategia", chart.get("strategy", ""), type_info["strategia_desc"]),
        ("autorità", auth_display, auth_desc),
        ("profilo", profile_display, profile_desc),
        ("definizione", definition, defin_desc),
        ("firma", firma_display, firma_desc),
        ("croce d'incarnazione", cross_name, cross_desc),
    ]

    rows = []
    for label, value, desc in keys:
        cell = [
            Paragraph(_xml(label).upper(), styles["appx_key_label"]),
            Paragraph(_xml(value), styles["appx_key_value"]),
            Paragraph(_xml(desc), styles["appx_key_desc"]),
        ]
        rows.append([cell])

    tbl = Table(rows, colWidths=[A4[0] - 48*mm])
    tbl.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
        ("LINEBELOW", (0, 0), (-1, -2), 0.3, pdf_theme.WARM_GRAY),
    ]))
    fl.append(tbl)
    fl.append(PageBreak())
    return fl


def page_appx_centers_overview(styles, defined_set, gbc):
    """Pagina 3 appendice: panorama 9 centri definiti/aperti + nota Variabile."""
    fl = [pdf_theme.PageCategoryMarker("chart")]
    fl.append(Spacer(1, 2*mm))
    fl.append(Paragraph("Centri definiti e non definiti", styles["appx_title"]))
    fl.append(Paragraph(
        "Panorama dei nove centri — chi fa da motore, chi fa da ricevitore",
        styles["appx_subtitle"],
    ))
    fl.append(Spacer(1, 2*mm))

    # Mapping center display name → code for defined check
    centers_ordered = [
        ("Testa", "HEAD"),
        ("Ajna", "AJNA"),
        ("Gola", "THROAT"),
        ("G", "G"),
        ("Cuore", "HEART"),
        ("Plesso Solare", "SOLAR"),
        ("Sacrale", "SACRAL"),
        ("Milza", "SPLEEN"),
        ("Radice", "ROOT"),
    ]

    rows_data = []
    for it_name, code in centers_ordered:
        is_def = code in defined_set
        # map name to key used in CENTER_INFO / gbc
        info_key = it_name.replace("Plesso Solare", "Plesso")
        info = CENTER_INFO[info_key]
        badge = ("<font color=\"" + pdf_theme.ORO.hexval() + "\"><b>DEFINITO</b></font>"
                 if is_def else
                 "<font color=\"" + pdf_theme.MUTED_MAUVE.hexval() + "\"><b>NON DEFINITO</b></font>")
        state_text = info["definito"] if is_def else info["aperto"]
        cell = [
            Paragraph(
                f'<font name="Playfair-B" size="12">{it_name}</font> &nbsp; {badge}',
                styles["appx_body"],
            ),
            Paragraph(state_text, styles["appx_body"]),
        ]
        rows_data.append([cell])

    tbl = Table(rows_data, colWidths=[A4[0] - 48*mm])
    tbl.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LINEBELOW", (0, 0), (-1, -2), 0.3, pdf_theme.WARM_GRAY),
    ]))
    fl.append(tbl)

    fl.append(Spacer(1, 5*mm))
    fl.append(Paragraph(
        "<i>Nota sulla Variabile</i> — le quattro frecce (digestione / "
        "consapevolezza / ambiente / prospettiva) sono un sottosistema "
        "avanzato. Non è trattata in questo Libretto; è oggetto di una "
        "consulenza dedicata.",
        styles["appx_body"],
    ))
    fl.append(PageBreak())
    return fl


def page_appx_center_zoom(styles, zoom_png, title_it, eyebrow_num,
                          group, defined_set, gbc):
    """Una delle tre pagine zoom centri. `group` = list of (it_name, code).
    Layout: img 70mm sinistra | descrizione 88mm destra."""
    fl = [pdf_theme.PageCategoryMarker("chart")]
    fl.append(Spacer(1, 2*mm))
    fl.append(Paragraph(f"appendice · {eyebrow_num}", styles["appx_eyebrow"]))
    fl.append(Paragraph(title_it, styles["appx_title"]))
    fl.append(Paragraph(
        "Zoom sulla mappa — lettura per centro",
        styles["appx_subtitle"],
    ))

    img = png_to_image(zoom_png, max_w=68*mm, max_h=130*mm)

    right_bits = []
    for it_name, code in group:
        is_def = code in defined_set
        info_key = it_name.replace("Plesso Solare", "Plesso")
        info = CENTER_INFO[info_key]
        badge = ("<font color=\"" + pdf_theme.ORO.hexval() + "\"><b>● DEF</b></font>"
                 if is_def else
                 "<font color=\"" + pdf_theme.MUTED_MAUVE.hexval() + "\"><b>○ ND</b></font>")
        right_bits.append(Paragraph(
            f'{it_name} &nbsp;&nbsp; {badge}',
            styles["appx_section_h"],
        ))
        right_bits.append(Paragraph(info["meccanica"], styles["appx_body"]))
        # Pills con porte attive
        b = gbc.get(info_key, {"P": [], "D": [], "PD": []})
        any_pill = False
        for pill in [
            appx_gate_pill(b["P"], "#1a1a2e", "P", styles),
            appx_gate_pill(b["D"], "#c0392b", "D", styles),
            appx_gate_pill(b["PD"], "#8B4B5A", "P+D", styles),
        ]:
            if pill is not None:
                right_bits.append(pill)
                any_pill = True
        if not any_pill:
            right_bits.append(Paragraph(
                '<i>nessuna porta attiva in questo centro</i>',
                styles["appx_body"],
            ))
        right_bits.append(Spacer(1, 4*mm))

    right_bits.append(Spacer(1, 2*mm))
    right_bits.append(Paragraph(
        "<b>→ La lettura completa dei centri è nel Capitolo 04.</b>",
        styles["appx_ref"],
    ))

    tbl = Table(
        [[img, right_bits]],
        colWidths=[70*mm, A4[0] - 24*mm - 24*mm - 70*mm - 4*mm],
    )
    tbl.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
        ("TOPPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ("LEFTPADDING", (1, 0), (1, 0), 8),
    ]))
    fl.append(tbl)
    fl.append(PageBreak())
    return fl


def page_appx_cross(styles, g_png, chart, gates_data: dict):
    life_theme = chart.get("life_theme", "")
    cross_name = cross_full_name(life_theme)
    p_sol, p_ter, d_sol, d_ter = cross_gate_numbers(chart)

    def gate_nome(g: int) -> str:
        entry = gates_data.get(str(g))
        return entry["nome"] if entry else f"Porta {g}"

    fl = [pdf_theme.PageCategoryMarker("chart")]
    fl.append(Spacer(1, 2*mm))
    fl.append(Paragraph("appendice · vii", styles["appx_eyebrow"]))
    fl.append(Paragraph("La tua Croce", styles["appx_title"]))
    fl.append(Paragraph(
        f"{cross_name} — quattro porte",
        styles["appx_subtitle"],
    ))

    img = png_to_image(g_png, max_w=68*mm, max_h=125*mm)

    right_bits = []
    cross = [
        (str(p_sol), "Personalità · Sole", "#1a1a2e", gate_nome(p_sol)),
        (str(p_ter), "Personalità · Terra", "#1a1a2e", gate_nome(p_ter)),
        (str(d_sol), "Design · Sole", "#c0392b", gate_nome(d_sol)),
        (str(d_ter), "Design · Terra", "#c0392b", gate_nome(d_ter)),
    ]
    for gate, label, color, desc in cross:
        right_bits.append(Paragraph(
            f'<font color="{color}"><b>{gate}</b></font>  '
            f'<font color="{pdf_theme.MUTED_MAUVE.hexval()}"><i>{label}</i></font>',
            styles["appx_section_h"],
        ))
        right_bits.append(Paragraph(_xml(desc), styles["appx_body"]))
        right_bits.append(Spacer(1, 3*mm))

    right_bits.append(Spacer(1, 3*mm))
    right_bits.append(Paragraph(
        "<b>→ La lettura completa della Croce è nel Capitolo 06.</b>",
        styles["appx_ref"],
    ))

    tbl = Table(
        [[img, right_bits]],
        colWidths=[70*mm, A4[0] - 24*mm - 24*mm - 70*mm - 4*mm],
    )
    tbl.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
        ("TOPPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ("LEFTPADDING", (1, 0), (1, 0), 8),
    ]))
    fl.append(tbl)
    fl.append(PageBreak())
    return fl


def build_appendix_flowables(styles, chart_json_path: Path):
    print("  Rendering bodygraph…")
    gates_data = load_gates_data()
    activ, chart = load_activations(chart_json_path)
    p, d = activ["p_gates"], activ["d_gates"]
    defined = activ["defined_centers"]
    gbc = gates_by_center(p, d)

    full_png = render_bodygraph_png(activ, width=2000)
    upper_png = crop_multi_centers(full_png, ["HEAD", "AJNA", "THROAT"])
    middle_png = crop_multi_centers(full_png, ["G", "HEART", "SOLAR"])
    lower_png = crop_multi_centers(full_png, ["SACRAL", "SPLEEN", "ROOT"])
    g_png = crop_multi_centers(full_png, ["G"])

    fl = []
    # 1. Mappa
    fl.extend(page_appx_full_bodygraph(styles, full_png))
    # 2. I tasti
    fl.extend(page_appx_keys(styles, chart))
    # 3. Panorama centri (definiti vs aperti)
    fl.extend(page_appx_centers_overview(styles, defined, gbc))
    # 4. Croce — "centri superiori/centrali/inferiori" rimossi (non esistono nello Human Design)
    fl.extend(page_appx_cross(styles, g_png, chart, gates_data))
    return fl


# ─── CHAPTER FLOWABLES (no more "Pag. XX" eyebrow) ────────────────────────────

def build_chapter_flowables(ch_idx: int, pages: list[dict], styles,
                            chapter_titles: list[str], chapter_subtitles: list[str]):
    """
    Build flowables for a chapter.

    Page-break logic:
      - Intra-chapter sections use CondPageBreak: only force a new physical page
        when remaining vertical space is less than threshold. Avoids "sparse pages"
        when an AI-generated section is shorter than expected — short sections
        flow naturally into the next section's start.
      - End of chapter (last section): hard PageBreak so the next chapter starts
        on a fresh page.
      - When a section continues on the same physical page as the previous one,
        a thin separator (gold rule) marks the visual transition before the title.
    """
    fl = []
    chap_num = ch_idx + 1
    chap_title = chapter_titles[ch_idx]
    chap_subtitle = chapter_subtitles[ch_idx]

    # Soglia di spazio rimanente sotto cui forzare nuova pagina prima del prossimo titolo.
    # 90mm = ~30% di una pagina A4: garantisce che il titolo + qualche riga di testo
    # entrino sopra il fondo pagina, evitando titoli orfani in fondo.
    INTRA_CHAPTER_BREAK_THRESHOLD = 90 * mm

    class Rule(Flowable):
        def __init__(self, w, c, lw=0.5):
            super().__init__()
            self.w, self.c, self.lw = w, c, lw
        def wrap(self, aw, ah):
            return (self.w, self.lw)
        def draw(self):
            self.canv.setStrokeColor(self.c)
            self.canv.setLineWidth(self.lw)
            self.canv.line(0, 0, self.w, 0)

    n_pages = len(pages)
    for i, page in enumerate(pages):
        category = "section_start" if i == 0 else "section_cont"
        fl.append(pdf_theme.PageCategoryMarker(category, section_num=chap_num))

        if i == 0:
            fl.append(Spacer(1, 10*mm))
            # TocParagraph: titolo che si registra nell'indice con numero pagina
            fl.append(TocParagraph(
                chap_title,
                styles["chap_title"],
                toc_level=0,
                plain_text=f"{chap_num:02d}  {chap_title}",
            ))
            fl.append(Paragraph(_xml(chap_subtitle), styles["chap_subtitle"]))

            fl.append(Rule(A4[0] - 2*24*mm, pdf_theme.ORO, 0.4))
            fl.append(Spacer(1, 8*mm))
        else:
            # Visual separator before continuation section.
            # If we're on a fresh page (forced by CondPageBreak below), this is harmless extra space.
            # If we're flowing on the same page, this rule marks the section transition.
            fl.append(Spacer(1, 6*mm))
            fl.append(Rule(A4[0] - 2*24*mm, pdf_theme.ORO, 0.3))
            fl.append(Spacer(1, 5*mm))
            fl.append(Paragraph(page["title"], styles["page_title"]))

        fl.extend(blocks_to_flowables(page["blocks"], styles))

        if i < n_pages - 1:
            # Intra-chapter break: only if remaining space is too small for the next title + content.
            fl.append(CondPageBreak(INTRA_CHAPTER_BREAK_THRESHOLD))
        else:
            # End of chapter: hard break so next chapter (or upsell) starts on fresh page.
            fl.append(PageBreak())
    return fl


# ─── UPSELL ───────────────────────────────────────────────────────────────────

def build_upsell_flowables(styles, chart: dict, tier: str = "Completo"):
    """Pagina upsell — offerte di Valentina Russo. Per Essenziale include upgrade Avanzato in cima."""
    tipo = chart.get("career_type", "Proiettore")

    # Copy intro personalizzato per tipo energetico
    _tipo_intro = {
        "Generatore": (
            "Il Sacrale sa già rispondere. Il passo successivo è portare questa mappa "
            "nel vivo della tua vita — nelle decisioni che hai davanti adesso, "
            "nelle relazioni e nel lavoro che ti chiedono energia ogni giorno."
        ),
        "Manifesting Generator": (
            "Il tuo Sacrale multidirezionale ha già la risposta. "
            "Il passo successivo è imparare a fidarsi di quella velocità "
            "invece di correggerla continuamente."
        ),
        "Proiettore": (
            "L'invito giusto arriva quando sei riconosciuto. Il passo successivo "
            "è capire dove e da chi vale la pena aspettarlo — e cosa offrire "
            "quando quell'invito arriva."
        ),
        "Manifestatore": (
            "Hai l'impulso per avviare. Il passo successivo è imparare "
            "a informare invece di isolarsi — e a riconoscere quando "
            "l'energia è disponibile e quando invece chiede riposo."
        ),
        "Riflettore": (
            "Il tempo è il tuo strumento principale. Il passo successivo "
            "è costruire le condizioni ambientali e relazionali che ti permettano "
            "di aspettarlo senza sentirti in colpa."
        ),
    }
    intro = _tipo_intro.get(tipo,
        "Il passo successivo è portare questa mappa nel vivo della tua vita.")

    # Stili locali per le card
    CARD_BG     = rl_colors.HexColor("#F2EDE9")
    CARD_BORDER = pdf_theme.WARM_GRAY
    PAGE_W      = A4[0]
    MARGIN      = 24 * mm
    card_w      = PAGE_W - 2 * MARGIN
    inner_w     = card_w - 16 * mm   # 8mm padding per lato
    price_w     = 46 * mm
    name_w      = inner_w - price_w

    _s_label = ParagraphStyle(
        "_upsell_label", fontName="IMFell", fontSize=8, leading=11,
        textColor=pdf_theme.ORO, spaceAfter=3,
    )
    _s_name = ParagraphStyle(
        "_upsell_name", fontName="Playfair-B", fontSize=13, leading=17,
        textColor=pdf_theme.INK_NIGHT,
    )
    _s_price = ParagraphStyle(
        "_upsell_price", fontName="Outfit-B", fontSize=16, leading=17,
        textColor=pdf_theme.ORO, alignment=TA_RIGHT,
    )
    _s_desc = ParagraphStyle(
        "_upsell_desc", fontName="Outfit", fontSize=9, leading=13.5,
        textColor=pdf_theme.TEXT_INK, spaceBefore=5,
    )

    # Upgrade card styles (dark bg, Essenziale only)
    UPGRADE_BG = rl_colors.HexColor("#1C1210")
    _s_upg_label = ParagraphStyle(
        "_upg_label", fontName="IMFell", fontSize=8, leading=11,
        textColor=pdf_theme.ORO, spaceAfter=3,
    )
    _s_upg_name = ParagraphStyle(
        "_upg_name", fontName="Playfair-B", fontSize=13, leading=17,
        textColor=rl_colors.HexColor("#FAF7F5"),
    )
    _s_upg_price = ParagraphStyle(
        "_upg_price", fontName="Outfit-B", fontSize=16, leading=17,
        textColor=pdf_theme.ORO, alignment=TA_RIGHT,
    )
    _s_upg_desc = ParagraphStyle(
        "_upg_desc", fontName="Outfit", fontSize=9, leading=13.5,
        textColor=rl_colors.HexColor("#EAE5E1"), spaceBefore=5,
    )

    def _make_card(label: str, name: str, price: str, desc: str,
                   bg=None, name_s=None, price_s=None, desc_s=None,
                   label_s=None, border=None):
        bg      = bg      or CARD_BG
        name_s  = name_s  or _s_name
        price_s = price_s or _s_price
        desc_s  = desc_s  or _s_desc
        label_s = label_s or _s_label
        border  = border  or CARD_BORDER
        header = Table(
            [[Paragraph(name, name_s), Paragraph(price, price_s)]],
            colWidths=[name_w, price_w],
        )
        header.setStyle(TableStyle([
            ("VALIGN",        (0, 0), (-1, -1), "BOTTOM"),
            ("LEFTPADDING",   (0, 0), (-1, -1), 0),
            ("RIGHTPADDING",  (0, 0), (-1, -1), 0),
            ("TOPPADDING",    (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ]))
        inner = Table(
            [[Paragraph(label, label_s)],
             [header],
             [Paragraph(desc, desc_s)]],
            colWidths=[inner_w],
        )
        inner.setStyle(TableStyle([
            ("LEFTPADDING",   (0, 0), (-1, -1), 0),
            ("RIGHTPADDING",  (0, 0), (-1, -1), 0),
            ("TOPPADDING",    (0, 0), (-1, -1), 1),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 1),
        ]))
        card = Table([[inner]], colWidths=[card_w])
        card.setStyle(TableStyle([
            ("BACKGROUND",    (0, 0), (-1, -1), bg),
            ("BOX",           (0, 0), (-1, -1), 0.5, border),
            ("LEFTPADDING",   (0, 0), (-1, -1), 8 * mm),
            ("RIGHTPADDING",  (0, 0), (-1, -1), 8 * mm),
            ("TOPPADDING",    (0, 0), (-1, -1), 6 * mm),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 6 * mm),
        ]))
        return card

    fl = [pdf_theme.PageCategoryMarker("closing")]
    fl.append(Spacer(1, 12 * mm))
    fl.append(Paragraph("Il tuo prossimo passo", styles["closing_title"]))
    fl.append(Spacer(1, 5 * mm))
    fl.append(Paragraph(intro, styles["closing_body"]))
    fl.append(Spacer(1, 9 * mm))

    # ── Card 1: Prima Lettura HD ─────────────────────────────────────────────
    # Upgrade Avanzato (solo tier Essenziale)
    if tier == "Essenziale":
        fl.append(_make_card(
            label="APPROFONDISCI LA TUA CARTA",
            name="Libretto d'Istruzioni Avanzato",
            price="+ € 57",
            desc=(
                "Aggiungi i tre capitoli avanzati al tuo Libretto: Profilo in "
                "profondità, La tua missione (Croce d'Incarnazione) e "
                "Come tornare al tuo design. Ricevi solo le pagine in più, "
                "da aggiungere al documento che hai già. "
                "Scrivi a info@valentinarussobg5.com con oggetto Upgrade Avanzato."
            ),
            bg=UPGRADE_BG,
            name_s=_s_upg_name, price_s=_s_upg_price,
            desc_s=_s_upg_desc, label_s=_s_upg_label,
            border=pdf_theme.ORO,
        ))
        fl.append(Spacer(1, 5 * mm))

    fl.append(_make_card(
        label="IL PASSO IMMEDIATO",
        name="Prima Lettura Human Design",
        price="€ 210",
        desc=(
            "Una sessione live da 2 ore con Valentina Russo. Esploriamo insieme "
            "la tua carta, le domande che ti sono rimaste dopo questa lettura, "
            "e come applicare concretamente il tuo design nelle situazioni attuali. "
            "Registrazione audio/video inclusa."
        ),
    ))
    fl.append(Spacer(1, 5 * mm))

    fl.append(_make_card(
        label="IL MINI-PERCORSO",
        name="Oltre l'Ombra",
        price="€ 500",
        desc=(
            "3 sessioni individuali da 90 minuti. Guardiamo in faccia gli schemi "
            "ripetitivi e le frustrazioni che ti portano fuori strada, "
            "e troviamo il punto di rientro concreto per il tuo tipo."
        ),
    ))
    fl.append(Spacer(1, 5 * mm))

    fl.append(_make_card(
        label="IL PERCORSO COMPLETO",
        name="Ri-Conoscersi",
        price="€ 1.200",
        desc=(
            "10 sessioni individuali da 90 minuti. Esploriamo il tema generale "
            "del tuo contributo e come manifesti il tuo scopo nel mondo, "
            "capitolo per capitolo della tua carta."
        ),
    ))
    fl.append(Spacer(1, 10 * mm))

    fl.append(Paragraph(
        "Per informazioni o per prenotare:", styles["closing_body"]))
    fl.append(Spacer(1, 2 * mm))
    fl.append(Paragraph("info@valentinarussobg5.com", styles["closing_sign"]))
    fl.append(Paragraph("valentinarussobg5.com",      styles["closing_sign"]))
    fl.append(PageBreak())
    return fl


# ─── CLOSING ──────────────────────────────────────────────────────────────────

def build_closing_flowables(styles, customer: str = "Valentina Russo"):
    fl = [pdf_theme.PageCategoryMarker("closing")]
    fl.append(Spacer(1, 30*mm))
    fl.append(Paragraph("Questo è solo l'inizio.", styles["closing_title"]))
    fl.append(Spacer(1, 8*mm))
    fl.append(Paragraph(
        "Il tuo Libretto funziona come uno specchio<br/>da rileggere nel tempo.",
        styles["closing_body"],
    ))
    fl.append(Spacer(1, 6*mm))
    fl.append(Paragraph(
        "Torna ai capitoli che ti colpiscono di più,<br/>"
        "prova una cosa alla volta nel corpo,<br/>"
        "osserva il Successo quando arriva.",
        styles["closing_body"],
    ))
    fl.append(Spacer(1, 14*mm))
    fl.append(Paragraph(
        "Per domande, approfondimenti o una sessione dedicata:",
        styles["closing_body"],
    ))
    fl.append(Spacer(1, 2*mm))
    fl.append(Paragraph("info@valentinarussobg5.com",
                        styles["closing_sign"]))
    fl.append(Paragraph("valentinarussobg5.com", styles["closing_sign"]))
    fl.append(Spacer(1, 8*mm))
    fl.append(Paragraph("Valentina Russo", styles["closing_sign"]))
    fl.append(Paragraph("consulente BG5 certificata", styles["closing_body"]))
    return fl


# ─── BUILD ────────────────────────────────────────────────────────────────────

def build_pdf(
    out_path: Path,
    *,
    customer: str,
    tier: str,
    chart_json_path: Path,
    chapter_files: list[Path],
    chapter_titles: list[str] | None = None,
    chapter_subtitles: list[str] | None = None,
):
    """Build the v5 blueprint PDF for any person.

    Args:
        out_path:          destination PDF path
        customer:          person's full name (shown in header/footer)
        tier:              "Completo" or "Essenziale"
        chart_json_path:   path to the chart JSON produced by calc_chart_ephem
        chapter_files:     list of 7 pre-generated .md files (one per chapter)
        chapter_titles:    optional override list[7]; auto-derived from chart if None
        chapter_subtitles: optional override list[7]; auto-derived from chart if None
    """
    register_fonts()
    pdf_theme.register_theme_fonts()

    # Load chart for dynamic metadata
    chart = _load_chart_dict(chart_json_path)

    # Derive titles/subtitles unless overridden
    if chapter_titles is None or chapter_subtitles is None:
        _titles, _subtitles = derive_chapter_metadata(chart)
        if chapter_titles is None:
            chapter_titles = _titles
        if chapter_subtitles is None:
            chapter_subtitles = _subtitles

    print("Parsing chapters…")
    chapters = []
    for p in chapter_files:
        pages = parse_chapter(p)
        if len(pages) != 7:
            print(f"  WARN: {p.name} has {len(pages)} pages (expected 7)")
        chapters.append(pages)

    styles = make_styles()
    theme_cb = pdf_theme.make_theme_callback(
        tier=tier, customer=customer, chart=chart,
    )
    doc = BlueprintDoc(str(out_path), theme_cb)

    story = []
    story.append(Spacer(1, 1))
    story.append(pdf_theme.PageCategoryMarker("toc"))
    story.append(PageBreak())
    story.extend(build_toc_flowables(styles))
    print("Building appendice…")
    story.extend(build_appendix_flowables(styles, chart_json_path))
    # Tier slicing: Essenziale = cap 1-4, Completo = cap 1-7, Supplemento = cap 5-7
    # chapter_titles/subtitles keep full 7-element list; real_idx indexes into it correctly.
    if tier == "Supplemento":
        chapters = chapters[4:]   # cap 5-7 (indices 4-6)
        ch_offset = 4
    elif tier == "Essenziale":
        chapters = chapters[:4]   # cap 1-4
        chapter_titles = chapter_titles[:4]
        chapter_subtitles = chapter_subtitles[:4]
        ch_offset = 0
    else:
        ch_offset = 0             # Completo: all 7

    n = len(chapters)
    print(f"Building {n} chapters (tier={tier})…")
    for ch_idx, pages in enumerate(chapters):
        real_idx = ch_idx + ch_offset   # correct chapter number for titles/display
        story.extend(build_chapter_flowables(
            real_idx, pages, styles, chapter_titles, chapter_subtitles,
        ))
    story.extend(build_upsell_flowables(styles, chart, tier=tier))
    story.extend(build_closing_flowables(styles, customer))

    print("Rendering PDF…")
    doc.multiBuild(story)


def main():
    parser = argparse.ArgumentParser(
        description="Build a BG5 Blueprint PDF for any person.",
    )
    parser.add_argument("--out", required=True, help="Output PDF path")
    parser.add_argument("--customer", required=True, help="Customer full name")
    parser.add_argument("--tier", default="Completo", choices=["Completo", "Essenziale", "Supplemento"])
    parser.add_argument("--chart", required=True, help="Path to chart JSON")
    parser.add_argument(
        "--chapters-dir", default=None,
        help="Directory containing cap1-draft-completo.md … cap7-draft-completo.md"
             " (default: same directory as this script)",
    )
    args = parser.parse_args()

    chapters_dir = Path(args.chapters_dir) if args.chapters_dir else HERE
    chapter_files = [chapters_dir / f"cap{i}-draft-completo.md" for i in range(1, 8)]

    out = Path(args.out)
    out.parent.mkdir(parents=True, exist_ok=True)
    print(f"Building: {out}")
    build_pdf(
        out,
        customer=args.customer,
        tier=args.tier,
        chart_json_path=Path(args.chart),
        chapter_files=chapter_files,
    )
    print(f"Done. {out.stat().st_size // 1024} KB")


if __name__ == "__main__":
    main()
