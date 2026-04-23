#!/usr/bin/env python3
"""
BODYGRAPH SVG RENDERER — colora il template chart.svg (hdkit) con le attivazioni
di uno specifico cliente, poi lo rasterizza in PNG via resvg-py.

Uso:
    from bodygraph_svg import render_bodygraph_png

    activations = {
        "p_gates": {60, 56, 22, ...},   # set di porte Personalità
        "d_gates": {50, 3, 54, ...},    # set di porte Design
        "defined_centers": {"SACRAL", "ROOT", "SPLEEN", "AJNA", "THROAT"},
    }
    png_bytes = render_bodygraph_png(activations, width=900)

Il template chart.svg è caricato da:
  D:/valentinarussomentaladvisor.it/grav-site/user/pages/assets/chart.svg

Convenzione colori (stesso del sito e della memoria feedback_bodygraph_coloring):
  P solo       → #1a1a2e (nero navy)
  D solo       → #c0392b (rosso)
  P+D entrambi → url(#splitGatePattern) → strisce nero/rosso
  Empty        → #c8c2bc (grigio del template)
"""

from pathlib import Path
import re

# Template SVG del sito (hdkit originale, 851.41 x 1309.4)
# Path relativo al repo root — funziona sia su Windows che su Linux (GitHub Actions)
CHART_SVG_PATH = Path(__file__).parent.parent.parent / "grav-site" / "user" / "pages" / "assets" / "chart.svg"

# ─── Colori (identici al sito) ────────────────────────────────────────────────
COL_P     = "#1a1a2e"   # Personalità (conscio)
COL_D     = "#c0392b"   # Design (inconscio)
COL_EMPTY = "#c8c2bc"

# Centri: id SVG nel template hdkit + colore quando definito
# (stessa mappatura di carta_hd.html.twig lines 1471-1479)
CENTER_SVG_ID = {
    "HEAD":   "Head",
    "AJNA":   "Ajna",
    "THROAT": "Throat",
    "G":      "G",
    "HEART":  "Heart",
    "SPLEEN": "Splenic_Center",
    "SACRAL": "Sacral",
    "SOLAR":  "Solar_Plexus",
    "ROOT":   "Root",
}

CENTER_COLOR = {
    "HEAD":   "#b4cfe8",
    "AJNA":   "#52a363",
    "THROAT": "#b8913e",
    "G":      "#d9b82a",
    "HEART":  "#b83838",
    "SPLEEN": "#957040",
    "SOLAR":  "#d47a2e",
    "SACRAL": "#c94e4e",
    "ROOT":   "#8a7868",
}

# Gate che formano i connettori di integrazione (canali tra G, Throat, Spleen, Sacral)
INTEGRATION_GATES = {10, 20, 34, 57}

# Mapping nomi italiani BG5 → codici interni (per comodità)
ITALIAN_TO_CODE = {
    "Testa":                "HEAD",
    "Ajna":                 "AJNA",
    "Gola":                 "THROAT",
    "G/Sé":                 "G",
    "G":                    "G",
    "Cuore/Ego":            "HEART",
    "Cuore":                "HEART",
    "Milza":                "SPLEEN",
    "Sacrale":              "SACRAL",
    "Risorsa Energetica":   "SACRAL",
    "Plesso Solare":        "SOLAR",
    "Intelligenza Emotiva": "SOLAR",
    "Radice":               "ROOT",
    "Spinta":               "ROOT",
}


def italian_centers_to_codes(names):
    """Traduce una lista di nomi italiani BG5 nei codici interni (HEAD, AJNA, ...)."""
    out = set()
    for n in names:
        code = ITALIAN_TO_CODE.get(n.strip())
        if code:
            out.add(code)
    return out


# ─── Build CSS rules dalle attivazioni ────────────────────────────────────────

def build_style_rules(activations: dict) -> str:
    """
    Costruisce il blocco CSS da iniettare nell'SVG.
    activations = {"p_gates": set, "d_gates": set, "defined_centers": set di codici}
    """
    p_gates = set(activations.get("p_gates", []))
    d_gates = set(activations.get("d_gates", []))
    defined = set(activations.get("defined_centers", []))

    rules = []

    # Variabili CSS per il pattern bicolore
    rules.append(
        f":root{{--color-personality:{COL_P};--color-design:{COL_D};"
        f"--color-empty:{COL_EMPTY};}}"
    )

    # Colora ogni gate attivo
    for g in range(1, 65):
        in_p = g in p_gates
        in_d = g in d_gates
        if not (in_p or in_d):
            continue
        if in_p and in_d:
            fill = "url(#splitGatePattern)"
        elif in_p:
            fill = COL_P
        else:
            fill = COL_D
        rules.append(f"#Gate{g}{{fill:{fill} !important;}}")

    # Integration connectors (10-20, 20-34, 10-57, 20-57, 34-57)
    for g in INTEGRATION_GATES:
        in_p = g in p_gates
        in_d = g in d_gates
        if not (in_p or in_d):
            continue
        if in_p and in_d:
            fill = "url(#splitGatePattern)"
        elif in_p:
            fill = COL_P
        else:
            fill = COL_D
        rules.append(f"#GateConnect{g}{{fill:{fill} !important;}}")

    # Span centrale del canale 20-57 (se attivo)
    if any(g in (p_gates | d_gates) for g in INTEGRATION_GATES):
        span_gate = 20
        if span_gate in p_gates and span_gate in d_gates:
            span_fill = "url(#splitGatePattern)"
        elif span_gate in p_gates:
            span_fill = COL_P
        elif span_gate in d_gates:
            span_fill = COL_D
        else:
            span_fill = COL_EMPTY
        rules.append(f"#GateSpan{{fill:{span_fill} !important;}}")

    # Centri definiti: color dell'HD classico
    for code in defined:
        svg_id = CENTER_SVG_ID.get(code)
        color = CENTER_COLOR.get(code)
        if svg_id and color:
            rules.append(f"#{svg_id} path{{fill:{color} !important;}}")
            rules.append(f"#{svg_id} polygon{{fill:{color} !important;}}")
            rules.append(f"#{svg_id} rect{{fill:{color} !important;}}")

    # Etichette porte: attive = badge nero con testo bianco, inattive = bianco sbiadito
    # (stesso identico pattern del sito: carta_hd.html.twig:1532-1535)
    active = p_gates | d_gates
    for g in range(1, 65):
        if g in active:
            rules.append(
                f"#GateText{g}{{fill:#fff !important;font-weight:bold !important;"
                f"opacity:1 !important;}}"
            )
            rules.append(
                f"#GateTextBg{g} path,#GateTextBg{g} circle"
                f"{{fill:#1a1a2e !important;}}"
            )
        else:
            rules.append(f"#GateText{g}{{fill:#fff !important;opacity:0.4 !important;}}")

    return "\n".join(rules)


# ─── Inject stile nel template SVG ────────────────────────────────────────────

def build_colored_svg(activations: dict, template_path: Path = CHART_SVG_PATH) -> str:
    """Restituisce la stringa SVG completa, con lo stile iniettato."""
    svg_text = template_path.read_text(encoding="utf-8")
    style_block = build_style_rules(activations)
    style_el = f"<style><![CDATA[\n{style_block}\n]]></style>"

    # Iniezione: subito prima di </defs> (preserva il pattern splitGatePattern)
    if "</defs>" in svg_text:
        svg_text = svg_text.replace("</defs>", style_el + "</defs>", 1)
    else:
        # Fallback: crea un <defs> nuovo subito dopo <svg ...>
        svg_text = re.sub(
            r"(<svg[^>]*>)",
            r"\1<defs>" + style_el + "</defs>",
            svg_text,
            count=1,
        )
    return svg_text


# ─── Rasterizza a PNG ─────────────────────────────────────────────────────────

def render_bodygraph_png(activations: dict, width: int = 900) -> bytes:
    """Produce PNG bytes renderizzati del bodygraph colorato."""
    import resvg_py
    svg = build_colored_svg(activations)
    result = resvg_py.svg_to_bytes(svg_string=svg, width=width)
    return bytes(result)


# ─── Ritaglio di singoli centri ──────────────────────────────────────────────
#
# Coordinate native del chart.svg (viewBox 0 0 851.41 1309.4)
# Estratte con svgelements.bbox() — precise.
SVG_VIEWBOX_W = 851.41
SVG_VIEWBOX_H = 1309.4

CENTER_BBOX_SVG = {
    # code: (x0, y0, x1, y1) in coordinate SVG native
    "HEAD":   (335.12,   15.18,  505.34,  157.53),
    "AJNA":   (335.14,  209.83,  505.62,  355.14),
    "THROAT": (343.37,  398.45,  497.40,  559.00),
    "G":      (317.68,  589.99,  523.15,  795.51),
    "HEART":  (520.46,  746.68,  668.72,  839.19),
    "SPLEEN": (  8.25,  885.90,  164.96, 1063.92),
    "SACRAL": (343.38,  924.78,  497.41, 1078.75),
    "SOLAR":  (682.19,  885.89,  838.90, 1063.92),
    "ROOT":   (343.38, 1149.14,  497.36, 1296.27),
}


def crop_center_png(
    png_bytes: bytes,
    center_code: str,
    padding_ratio: float = 0.20,
) -> bytes:
    """Ritaglia la regione di un singolo centro da un PNG di bodygraph.

    Args:
        png_bytes:     PNG pieno del bodygraph (output di render_bodygraph_png)
        center_code:   uno di HEAD, AJNA, THROAT, G, HEART, SPLEEN, SACRAL, SOLAR, ROOT
        padding_ratio: padding attorno al centro (0.20 = +20% su ogni lato)

    Returns: PNG bytes del centro ritagliato.
    """
    from PIL import Image
    import io

    if center_code not in CENTER_BBOX_SVG:
        raise ValueError(f"Unknown center code: {center_code}")

    img = Image.open(io.BytesIO(png_bytes))
    img_w, img_h = img.size

    # Scala SVG → pixel
    sx = img_w / SVG_VIEWBOX_W
    sy = img_h / SVG_VIEWBOX_H

    x0, y0, x1, y1 = CENTER_BBOX_SVG[center_code]
    # Converti in pixel
    px0, py0, px1, py1 = x0 * sx, y0 * sy, x1 * sx, y1 * sy

    # Padding
    bw, bh = px1 - px0, py1 - py0
    pad_x = bw * padding_ratio
    pad_y = bh * padding_ratio
    px0 = max(0, px0 - pad_x)
    py0 = max(0, py0 - pad_y)
    px1 = min(img_w, px1 + pad_x)
    py1 = min(img_h, py1 + pad_y)

    cropped = img.crop((int(px0), int(py0), int(px1), int(py1)))
    out = io.BytesIO()
    cropped.save(out, format="PNG")
    return out.getvalue()


# ─── Estrazione attivazioni da dati CHART di spike_test.py ────────────────────

def activations_from_chart(chart: dict) -> dict:
    """
    Converte il dict CHART di spike_test.py in activations dict con:
      - p_gates, d_gates: set di numeri porta
      - defined_centers: set di codici centro (HEAD, AJNA, ...)

    `chart["activations"]` è lista di tuple (planet_name_it, "gate.line", "gate.line")
    dove la prima è Personalità e la seconda è Design.
    """
    p_gates = set()
    d_gates = set()
    for planet, p_str, d_str in chart.get("activations", []):
        p_gate = int(p_str.split(".")[0])
        d_gate = int(d_str.split(".")[0])
        p_gates.add(p_gate)
        d_gates.add(d_gate)

    defined_centers = italian_centers_to_codes(chart.get("defined_centers", []))

    return {
        "p_gates":         p_gates,
        "d_gates":         d_gates,
        "defined_centers": defined_centers,
    }


# ─── Test standalone ──────────────────────────────────────────────────────────

if __name__ == "__main__":
    import sys, json
    try:
        sys.stdout.reconfigure(encoding="utf-8")
    except Exception:
        pass

    # Carica Marco dal spike-output.json
    data = json.loads(
        Path(__file__).parent.joinpath("spike-output.json").read_text(encoding="utf-8")
    )
    chart = data["chart"]
    activ = activations_from_chart(chart)

    print(f"Cliente: {chart['customer_name']}")
    print(f"P gates ({len(activ['p_gates'])}): {sorted(activ['p_gates'])}")
    print(f"D gates ({len(activ['d_gates'])}): {sorted(activ['d_gates'])}")
    print(f"Centri definiti: {sorted(activ['defined_centers'])}")
    print(f"Gate in entrambi (PD): {sorted(activ['p_gates'] & activ['d_gates'])}")
    print()

    # Genera PNG di test
    out = Path("D:/Download/test-marco-bodygraph.png")
    png = render_bodygraph_png(activ, width=900)
    out.write_bytes(png)
    print(f"PNG scritto: {out} ({len(png)} bytes)")

    # Salva anche l'SVG colorato per ispezione
    svg_out = Path("D:/Download/test-marco-bodygraph.svg")
    svg_out.write_text(build_colored_svg(activ), encoding="utf-8")
    print(f"SVG scritto: {svg_out}")
