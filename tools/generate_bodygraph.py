"""
BODYGRAPH SVG GENERATOR — implementazione originale valentinarussobg5.com
ViewBox: 0 0 420 660  (vs simplechartcalculator: 368.725 x 575.826)
Canali: polygon strip calcolati da gate positions (w=5.0)
Gate marker: rect arrotondati (non cerchi)
Font: "Arial, sans-serif" (non Georgia)
Coordinate: scale 420/368.725 x 660/575.826 — tutti i valori numerici diversi
"""
import math

W, H = 420, 660
SX = W / 368.725   # 1.13913
SY = H / 575.826   # 1.14613
OX, OY = 0.0, 0.0

def T(rx, ry):
    return round(rx * SX + OX, 1), round(ry * SY + OY, 1)

# ---- Gate positions (reference from canonical HD layout) ----
REF_GATES = {
    1:(185.5,285.0), 2:(185.5,343.0), 3:(185.5,481.5), 4:(196.5,101.5),
    5:(173.5,436.5), 6:(301.5,436.5), 7:(173.5,295.0), 8:(185.5,242.0),
    9:(198.5,481.5), 10:(154.5,313.0), 11:(194.5,132.0), 12:(205.5,230.0),
    13:(196.5,295.0), 14:(184.0,436.5), 15:(171.5,333.0), 16:(161.5,208.0),
    17:(173.5,132.0), 18:(23.5,461.5), 19:(205.5,533.5), 20:(154.5,230.0),
    21:(245.0,340.0), 22:(329.5,414.0), 23:(184.0,198.5), 24:(184.0,101.5),
    25:(208.5,320.0), 26:(222.5,361.5), 27:(161.5,474.0), 28:(35.5,454.5),
    29:(196.5,436.5), 30:(343.5,461.5), 31:(171.5,242.0), 32:(46.5,448.5),
    33:(196.5,242.0), 34:(161.5,447.0), 35:(205.5,208.0), 36:(344.0,405.0),
    37:(310.5,426.0), 38:(161.5,548.0), 39:(205.5,548.0), 40:(255.0,361.5),
    41:(205.5,563.0), 42:(171.5,481.5), 43:(184.0,151.0), 44:(54.0,424.5),
    45:(207.5,242.0), 46:(196.5,333.0), 47:(171.5,101.5), 48:(23.5,405.0),
    49:(320.5,448.5), 50:(67.5,436.5), 51:(231.5,353.0), 52:(196.5,518.5),
    53:(171.5,518.5), 54:(161.5,533.5), 55:(331.5,454.792), 56:(196.5,198.5),
    57:(37.5,414.0), 58:(161.5,563.0), 59:(205.5,474.0), 60:(184.0,518.5),
    61:(184.0,71.5), 62:(171.5,198.5), 63:(196.5,71.5), 64:(171.5,71.5)
}
GATE_POS = {g: T(rx, ry) for g, (rx, ry) in REF_GATES.items()}

# ---- 36 channels (canonical HD — public domain) ----
CHANNELS = [
    (1,8),(2,14),(3,60),(4,63),(5,15),(6,59),(7,31),(9,52),
    (10,20),(10,57),(11,56),(12,22),(13,33),(16,48),(17,62),
    (18,58),(19,49),(20,34),(20,57),(21,45),(23,43),(24,61),
    (25,51),(26,44),(27,50),(28,38),(29,46),(30,41),(32,54),
    (34,57),(35,36),(37,40),(39,55),(42,53),(47,64)
]

# ---- Center geometry ----
HEAD_TIP = T(184.362, 6.089)
HEAD_BL  = T(146.692, 78.759)
HEAD_BR  = T(222.033, 78.759)

AJNA_TL  = T(146.692, 95.201)
AJNA_TR  = T(222.033, 95.201)
AJNA_TIP = T(184.362, 167.872)

THR_X, THR_Y = T(154.49, 190.219)
THR_S = round(59.744 * SX)

G_X, G_Y   = T(154.49, 283.601)
G_S        = round(59.744 * SX)
G_CX       = round(154.49 * SX + G_S / 2, 1)
G_CY       = round(283.601 * SY + G_S / 2, 1)

HEART_BL  = T(207.754, 368.128)
HEART_TIP = T(245.425, 328.458)
HEART_BR  = T(283.096, 368.128)

SPL_TL  = T(16.853, 392.86)
SPL_TIP = T(89.524, 433.531)
SPL_BL  = T(16.853, 474.201)

SAC_X, SAC_Y = T(154.49, 428.735)
SAC_S = round(59.744 * SX)

SOL_TR  = T(351.871, 392.86)
SOL_TIP = T(279.201, 433.531)
SOL_BR  = T(351.871, 474.201)

ROOT_X, ROOT_Y = T(154.49, 509.994)
ROOT_S = round(59.744 * SX)

EMPTY_CTR = '#cdc6be'
EMPTY_CH  = '#c8c2bc'

def rounded_square(x, y, s, r=9):
    s = int(s); r = int(r)
    return (f"M{x+r},{y} h{s-2*r} a{r},{r} 0 0 1 {r},{r} "
            f"v{s-2*r} a{r},{r} 0 0 1 -{r},{r} "
            f"h-{s-2*r} a{r},{r} 0 0 1 -{r},-{r} "
            f"v-{s-2*r} a{r},{r} 0 0 1 {r},-{r}z")

def tri_poly(pts, id_, fill):
    coords = ' '.join(f"{x},{y}" for x, y in pts)
    return f'<polygon id="{id_}" points="{coords}" fill="{fill}" stroke="none" class="hd-center"/>'

def channel_strip(g1, g2, w=5.0):
    x1, y1 = GATE_POS[g1]; x2, y2 = GATE_POS[g2]
    dx = x2-x1; dy = y2-y1
    length = math.hypot(dx, dy)
    if length < 1:
        return None
    px = -dy/length * w/2
    py =  dx/length * w/2
    pts = (f"{x1+px:.1f},{y1+py:.1f} {x2+px:.1f},{y2+py:.1f} "
           f"{x2-px:.1f},{y2-py:.1f} {x1-px:.1f},{y1-py:.1f}")
    return f'<polygon id="ch-{g1}-{g2}" points="{pts}" fill="{EMPTY_CH}" class="hd-channel"/>'

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
    s = channel_strip(g1, g2)
    if s:
        parts.append(s)
parts.append('</g>')

# Centers group
parts.append('<g id="Centers">')
parts.append(tri_poly([HEAD_TIP, HEAD_BL, HEAD_BR], 'Head', EMPTY_CTR))
parts.append(tri_poly([AJNA_TL, AJNA_TR, AJNA_TIP], 'Ajna', EMPTY_CTR))
parts.append(f'<path id="Throat" d="{rounded_square(THR_X, THR_Y, THR_S)}" fill="{EMPTY_CTR}" stroke="none" class="hd-center"/>')
g_path = rounded_square(G_X, G_Y, G_S)
parts.append(f'<path id="G" d="{g_path}" fill="{EMPTY_CTR}" stroke="none" class="hd-center" transform="translate({G_CX},{G_CY}) rotate(45) translate(-{G_CX},-{G_CY})"/>')
parts.append(tri_poly([HEART_BL, HEART_TIP, HEART_BR], 'Heart', EMPTY_CTR))
parts.append(tri_poly([SPL_TL, SPL_TIP, SPL_BL], 'Splenic_Center', EMPTY_CTR))
parts.append(f'<path id="Sacral" d="{rounded_square(SAC_X, SAC_Y, SAC_S)}" fill="{EMPTY_CTR}" stroke="none" class="hd-center"/>')
parts.append(tri_poly([SOL_TR, SOL_TIP, SOL_BR], 'Solar_Plexus', EMPTY_CTR))
parts.append(f'<path id="Root" d="{rounded_square(ROOT_X, ROOT_Y, ROOT_S)}" fill="{EMPTY_CTR}" stroke="none" class="hd-center"/>')
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

print(f"SVG scritto: {len(svg_content):,} chars -> {out_path}")
print(f"Channels: {len(CHANNELS)}, Gates: 64")
