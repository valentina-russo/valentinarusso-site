"""
Adapt hdkit bodygraph SVG (MIT) for valentinarussobg5.com
- Change white fills to our beige/gray palette
- Remove drop shadows
- Rename center IDs to match our JS renderer
- Add CSS classes
- Keep channel IDs as-is (JS will use mapping)
"""
import re

with open('hdkit-bodygraph-original.svg', 'r', encoding='utf-8') as f:
    svg = f.read()

# Remove drop shadow filter and references
svg = svg.replace('filter="url(#drop-shadow)" ', '')
# Replace defs with our pattern (for split personality/design channels)
svg = re.sub(r'<defs>.*?</defs>', '''<defs>
<pattern id="splitGatePattern" width="5" height="5" patternUnits="userSpaceOnUse">
<rect width="5" height="2.5" fill="var(--color-personality)"/>
<rect y="2.5" width="5" height="2.5" fill="var(--color-design)"/>
</pattern>
</defs>''', svg, flags=re.DOTALL)

# Change channel fills: white -> our channel gray
svg = re.sub(
    r'(<(?:path|polyline|polygon)\s+id="Gate\w+"[^>]*?)fill="#fff"',
    r'\1fill="#c8c2bc" class="hd-channel"',
    svg
)
# GateSpan and GateConnect pieces
svg = re.sub(
    r'(<(?:path|polyline|polygon)\s+id="Gate(?:Span|Connect)\w*"[^>]*?)fill="#fff"',
    r'\1fill="#c8c2bc" class="hd-channel"',
    svg
)

# Change center fills: white -> our center beige
CENTER_IDS = ['Head', 'Ajna', 'Throat', 'Sacral', 'Root', 'SolarPlexus', 'Spleen', 'Ego']
for cid in CENTER_IDS:
    # The center path is inside <g id="CenterName"><path ... fill="#fff"/></g>
    svg = re.sub(
        rf'(<g\s+id="{cid}">\s*<path\s+)([^>]*?)fill="#fff"',
        rf'\1\2fill="#cdc6be" class="hd-center"',
        svg
    )
# G center separately (it has id="G")
svg = re.sub(
    r'(<g\s+id="G">\s*<path\s+)([^>]*?)fill="#fff"',
    r'\1\2fill="#cdc6be" class="hd-center"',
    svg
)

# Rename center group IDs to match our JS
svg = svg.replace('id="Ego"', 'id="Heart"')
svg = svg.replace('id="SolarPlexus"', 'id="Solar_Plexus"')
svg = svg.replace('id="Spleen"', 'id="Splenic_Center"')

# Gate text backgrounds: make transparent (our JS will color them)
svg = re.sub(
    r'(<g\s+id="GateTextBg(\d+)"[^>]*>\s*<(?:path|circle)[^>]*?)fill="transparent"',
    lambda m: m.group(1) + f'fill="none" class="hd-gate-bg" id="gate-bg-{m.group(2)}"',
    svg
)

# Gate text labels: keep hdkit IDs and original font-size

# Change Layer_1 id to something neutral
svg = svg.replace('id="Layer_1" data-name="Layer 1"', 'id="bodygraph"')

# Add preserveAspectRatio
svg = svg.replace('viewBox="0 0 851.41 1309.4"', 'viewBox="0 0 851.41 1309.4" preserveAspectRatio="xMidYMid meet"')

out_path = 'grav-site/user/pages/assets/bodygraph.svg'
with open(out_path, 'w', encoding='utf-8') as f:
    f.write(svg)

import shutil
shutil.copy2(out_path, out_path.replace('bodygraph.svg', 'chart.svg'))

print(f"SVG adattato: {len(svg):,} chars -> {out_path}")
