"""
BODYGRAPH SVG GENERATOR — basato su coordinate hdkit (MIT, jdempcy/hdkit)
ViewBox originale hdkit: 0 0 851.41 1309.4
Scalato a ViewBox nostro: 0 0 420 645
Canali: polygon strip (w=5.0)
Font: Arial, sans-serif
"""
import math

# hdkit viewBox: 851.41 x 1309.4
# Nostro viewBox: 420 x 645
W, H = 420, 645
SX = W / 851.41   # 0.4933
SY = H / 1309.4   # 0.4926

def S(hx, hy):
    """Scale hdkit coords to our viewBox."""
    return round(hx * SX, 1), round(hy * SY, 1)

# ---- Gate positions from hdkit (MIT) — scaled to our viewBox ----
# Central column gates aligned to exact same x for perfectly vertical strips:
#   Left column  x=376:  gates 47,64, 17,62, 7,31, 5,15, 42,53
#   Center column x=413:  gates 24,61, 23,43, 1,8,  2,14, 3,60
#   Right column  x=448:  gates 4,63, 11,56, 13,33, 29,46, 9,52
LEFT_X   = 376.0   # avg of hdkit 372-378
CENTER_X = 413.0   # avg of hdkit 410-416
RIGHT_X  = 448.0   # avg of hdkit 447-453

HDKIT_GATES = {
    # --- Central columns (x aligned for straight vertical strips) ---
    # Left column
    47:(LEFT_X,235.13), 64:(LEFT_X,146.38),   # Head-Ajna
    17:(LEFT_X,268.33), 62:(LEFT_X,422.40),   # Ajna-Throat
    7:(LEFT_X,658.16),  31:(LEFT_X,547.19),   # Throat-G
    5:(LEFT_X,949.04),  15:(LEFT_X,741.89),   # G-Sacral
    42:(LEFT_X,1066.29),53:(LEFT_X,1177.68),  # Sacral-Root
    # Center column
    24:(CENTER_X,235.13),61:(CENTER_X,146.38),# Head-Ajna
    23:(CENTER_X,422.40),43:(CENTER_X,331.40),# Ajna-Throat
    1:(CENTER_X,622.01), 8:(CENTER_X,546.37), # Throat-G
    2:(CENTER_X,773.05),14:(CENTER_X,948.50), # G-Sacral
    3:(CENTER_X,1065.47),60:(CENTER_X,1176.86),# Sacral-Root
    # Right column
    4:(RIGHT_X,235.11), 63:(RIGHT_X,146.38),  # Head-Ajna
    11:(RIGHT_X,270.01),56:(RIGHT_X,423.26),  # Ajna-Throat
    13:(RIGHT_X,657.34),33:(RIGHT_X,546.37),  # Throat-G
    29:(RIGHT_X,949.08),46:(RIGHT_X,740.50),  # G-Sacral
    9:(RIGHT_X,1066.33),52:(RIGHT_X,1177.72), # Sacral-Root
    # --- Non-central gates (original hdkit positions) ---
    6:(706.58,982.61), 10:(334.31,697.90), 12:(468.45,482.09),
    16:(351.30,453.40), 18:(16.61,1037.03), 19:(467.58,1208.69),
    20:(353.01,501.14), 21:(594.91,771.84), 22:(768.50,945.00),
    25:(482.00,705.00), 26:(543.84,826.05), 27:(353.00,1031.00),
    28:(52.30,1018.59), 30:(804.13,1037.88), 32:(85.81,999.32),
    34:(353.00,984.50), 35:(469.31,448.40), 36:(801.50,923.00),
    37:(738.87,964.19), 38:(353.72,1244.62), 39:(468.43,1244.62),
    40:(628.36,826.03), 41:(468.43,1280.66), 44:(85.83,963.33),
    45:(469.31,516.23), 48:(17.50,923.00), 49:(738.85,1000.17),
    50:(121.75,982.61), 51:(571.51,800.95), 54:(353.72,1208.69),
    55:(771.48,1019.45), 57:(52.32,945.77), 58:(353.72,1280.66),
    59:(466.00,1030.00)
}
GATE_POS = {g: S(hx, hy) for g, (hx, hy) in HDKIT_GATES.items()}

# ---- 36 channels (canonical HD) ----
CHANNELS = [
    (1,8),(2,14),(3,60),(4,63),(5,15),(6,59),(7,31),(9,52),
    (10,20),(10,57),(11,56),(12,22),(13,33),(16,48),(17,62),
    (18,58),(19,49),(20,34),(20,57),(21,45),(23,43),(24,61),
    (25,51),(26,44),(27,50),(28,38),(29,46),(30,41),(32,54),
    (34,57),(35,36),(37,40),(39,55),(42,53),(47,64)
]

# Integration channels hidden by default — JS colors them when active.
HIDDEN_CHANNELS = {(10,20), (20,34)}

# ---- Center geometry — proportioned from christieinge.com reference ----
# Head: triangle pointing up (gates 64,61,63 at y~146)
HEAD_TIP = S(411, 55)
HEAD_BL  = S(330, 180)
HEAD_BR  = S(492, 180)

# Ajna: inverted triangle (gates 47,24,4 at y~235; 17,11 at y~270)
AJNA_TL  = S(330, 200)
AJNA_TR  = S(492, 200)
AJNA_TIP = S(411, 365)

# Throat: rounded rect (gates 62,23,56 top ~422; 31,8,33,45 bottom ~547)
THR_X, THR_Y = S(336, 410)
THR_W = round(150 * SX)
THR_H = round(160 * SY)

# G (Identity): diamond — large, similar to Throat (gates 1,7,13,10,25)
G_CX, G_CY = S(411, 700)
G_HALF = round(105 * SX)

# Heart (Ego/Will): triangle right (gates 21,51,26,40 range 527-628, 772-838)
HEART_BL  = S(520, 845)
HEART_TIP = S(600, 765)
HEART_BR  = S(650, 845)

# Spleen: triangle left — larger (gates 48,57,44,50,32,28,18)
SPL_TL  = S(-15, 890)
SPL_TIP = S(155, 990)
SPL_BL  = S(-15, 1090)

# Sacral: rounded rect (gates 5,14,29 top ~949; 42,3,9 bottom ~1066)
SAC_X, SAC_Y = S(330, 920)
SAC_W = round(160 * SX)
SAC_H = round(175 * SY)

# Solar Plexus: triangle right — larger (gates 36,22,37,49,55,30)
SOL_TR  = S(835, 890)
SOL_TIP = S(665, 990)
SOL_BR  = S(835, 1090)

# Root: rounded rect (gates 53,60,52 top ~1177; 58,41 bottom ~1281)
ROOT_X, ROOT_Y = S(335, 1155)
ROOT_W = round(152 * SX)
ROOT_H = round(150 * SY)

EMPTY_CTR = '#cdc6be'
EMPTY_CH  = '#c8c2bc'

def rounded_rect(x, y, w, h, r=8):
    w, h, r = int(w), int(h), int(r)
    return (f"M{x+r},{y} h{w-2*r} a{r},{r} 0 0 1 {r},{r} "
            f"v{h-2*r} a{r},{r} 0 0 1 -{r},{r} "
            f"h-{w-2*r} a{r},{r} 0 0 1 -{r},-{r} "
            f"v-{h-2*r} a{r},{r} 0 0 1 {r},-{r}z")

def diamond(cx, cy, half):
    """Diamond (rotated square) centered at cx,cy."""
    return (f"M{cx},{cy-half} L{cx+half},{cy} "
            f"L{cx},{cy+half} L{cx-half},{cy} Z")

def tri_poly(pts, id_, fill):
    coords = ' '.join(f"{x},{y}" for x, y in pts)
    return f'<polygon id="{id_}" points="{coords}" fill="{fill}" stroke="none" class="hd-center"/>'

def channel_strip(g1, g2, w=5.0, hidden=False):
    x1, y1 = GATE_POS[g1]; x2, y2 = GATE_POS[g2]
    dx = x2-x1; dy = y2-y1
    length = math.hypot(dx, dy)
    if length < 1:
        return None
    px = -dy/length * w/2
    py =  dx/length * w/2
    pts = (f"{x1+px:.1f},{y1+py:.1f} {x2+px:.1f},{y2+py:.1f} "
           f"{x2-px:.1f},{y2-py:.1f} {x1-px:.1f},{y1-py:.1f}")
    fill = 'none' if hidden else EMPTY_CH
    return f'<polygon id="ch-{g1}-{g2}" points="{pts}" fill="{fill}" class="hd-channel"/>'

# ---- Build SVG ----
parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" preserveAspectRatio="xMidYMid meet">']

# Defs
parts.append('<defs>')
parts.append(
    '<pattern id="splitGatePattern" width="5" height="5" patternUnits="userSpaceOnUse">'
    '<rect width="5" height="2.5" fill="var(--color-personality)"/>'
    '<rect y="2.5" width="5" height="2.5" fill="var(--color-design)"/>'
    '</pattern>'
)
parts.append('</defs>')

# Channels group
parts.append('<g id="Channels">')
for g1, g2 in CHANNELS:
    hide = (g1, g2) in HIDDEN_CHANNELS or (g2, g1) in HIDDEN_CHANNELS
    s = channel_strip(g1, g2, hidden=hide)
    if s:
        parts.append(s)
parts.append('</g>')

# Centers group
parts.append('<g id="Centers">')
parts.append(tri_poly([HEAD_TIP, HEAD_BL, HEAD_BR], 'Head', EMPTY_CTR))
parts.append(tri_poly([AJNA_TL, AJNA_TR, AJNA_TIP], 'Ajna', EMPTY_CTR))
parts.append(f'<path id="Throat" d="{rounded_rect(THR_X[0] if isinstance(THR_X, tuple) else THR_X, THR_Y if not isinstance(THR_X, tuple) else THR_X[1], THR_W, THR_H)}" fill="{EMPTY_CTR}" stroke="none" class="hd-center"/>')

gcx, gcy = G_CX if not isinstance(G_CX, tuple) else G_CX[0], G_CY if not isinstance(G_CX, tuple) else G_CX[1]
parts.append(f'<path id="G" d="{diamond(gcx, gcy, G_HALF)}" fill="{EMPTY_CTR}" stroke="none" class="hd-center"/>')

parts.append(tri_poly([HEART_BL, HEART_TIP, HEART_BR], 'Heart', EMPTY_CTR))
parts.append(tri_poly([SPL_TL, SPL_TIP, SPL_BL], 'Splenic_Center', EMPTY_CTR))
parts.append(f'<path id="Sacral" d="{rounded_rect(SAC_X[0] if isinstance(SAC_X, tuple) else SAC_X, SAC_Y if not isinstance(SAC_X, tuple) else SAC_X[1], SAC_W, SAC_H)}" fill="{EMPTY_CTR}" stroke="none" class="hd-center"/>')
parts.append(tri_poly([SOL_TR, SOL_TIP, SOL_BR], 'Solar_Plexus', EMPTY_CTR))
parts.append(f'<path id="Root" d="{rounded_rect(ROOT_X[0] if isinstance(ROOT_X, tuple) else ROOT_X, ROOT_Y if not isinstance(ROOT_X, tuple) else ROOT_X[1], ROOT_W, ROOT_H)}" fill="{EMPTY_CTR}" stroke="none" class="hd-center"/>')
parts.append('</g>')

# Gates group
GS = 5   # half-size of gate square
GR = 2   # corner radius
parts.append('<g id="Gates" class="pointer-events-none select-none">')
for g in range(1, 65):
    if g not in GATE_POS:
        continue
    gx, gy = GATE_POS[g]
    parts.append(
        f'<rect id="gate-bg-{g}" x="{gx-GS}" y="{gy-GS}" '
        f'width="{GS*2}" height="{GS*2}" rx="{GR}" ry="{GR}" fill="none" class="hd-gate-bg"/>'
    )
    parts.append(
        f'<text id="gate-text-{g}" x="{gx}" y="{gy+2.5}" '
        f'text-anchor="middle" font-family="Arial, sans-serif" font-size="6.5" '
        f'font-weight="normal" fill="#888" class="hd-gate-text">{g}</text>'
    )
parts.append('</g>')

parts.append('</svg>')

svg_content = '\n'.join(parts)

out_path = 'D:/valentinarussomentaladvisor.it/grav-site/user/pages/assets/bodygraph.svg'
with open(out_path, 'w', encoding='utf-8') as f:
    f.write(svg_content)

# Also write chart.svg for cache-busting
import shutil
chart_path = out_path.replace('bodygraph.svg', 'chart.svg')
shutil.copy2(out_path, chart_path)

print(f"SVG scritto: {len(svg_content):,} chars -> {out_path}")
print(f"Copiato anche in: {chart_path}")
print(f"Channels: {len(CHANNELS)}, Gates: 64")
