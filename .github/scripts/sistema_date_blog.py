"""Normalizza la data nel frontmatter degli articoli del blog.

- timestamp unix (es. 1773186590)  -> '2026-03-10 23:49:50' (UTC, come le mostra gia' Grav)
- 'DD-MM-YYYY HH:MM'                -> 'YYYY-MM-DD HH:MM:00'
- gia' 'YYYY-MM-DD ...'             -> invariato
Stampa il riepilogo e scrive i file solo con --applica.
"""
import re, sys, glob, os
from datetime import datetime, timezone
from zoneinfo import ZoneInfo

ROMA = ZoneInfo("Europe/Rome")
applica = "--applica" in sys.argv
radice = sys.argv[1]
cambiati = []

for f in sorted(glob.glob(os.path.join(radice, "**", "item*.md"), recursive=True)):
    testo = open(f, encoding="utf-8").read()
    if not testo.startswith("---"):
        print("SALTO (niente frontmatter):", f); continue
    fine = testo.find("\n---", 3)
    fm, corpo = testo[:fine], testo[fine:]
    m = re.search(r"^date:\s*(.+?)\s*$", fm, re.M)
    if not m:
        print("NESSUNA DATA:", f); continue
    grezzo = m.group(1).strip().strip("'\"")
    nuovo = None
    if re.fullmatch(r"\d{9,11}", grezzo):
        nuovo = datetime.fromtimestamp(int(grezzo), tz=timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    elif re.fullmatch(r"(\d{2})-(\d{2})-(\d{4})(?:\s+(\d{2}):(\d{2}))?", grezzo):
        g, me, a, h, mi = re.fullmatch(r"(\d{2})-(\d{2})-(\d{4})(?:\s+(\d{2}):(\d{2}))?", grezzo).groups()
        nuovo = f"{a}-{me}-{g} {h or '10'}:{mi or '00'}:00"
    if nuovo is None:
        print(f"OK        {os.path.relpath(f, radice)} | {grezzo}"); continue
    print(f"CAMBIA    {os.path.relpath(f, radice)} | {grezzo} -> {nuovo}")
    fm2 = fm[:m.start()] + f"date: '{nuovo}'" + fm[m.end():]
    if applica:
        open(f, "w", encoding="utf-8", newline="\n").write(fm2 + corpo)
    cambiati.append(f)

print("\nFILE DA RISCRIVERE:", len(cambiati))
open(os.path.join(radice, "_cambiati.txt"), "w").write("\n".join(cambiati))
