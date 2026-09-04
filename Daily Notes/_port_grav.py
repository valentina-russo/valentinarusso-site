#!/usr/bin/env python3
"""Converte una pagina della bozza statica nel corpo di un modello Twig di Grav.

La bozza usa nomi di file (.html) e immagini rinominate; il sito vero usa gli
indirizzi puliti di Grav e le immagini al loro posto. Qui si fa la traduzione,
cosi' il porto di ogni pagina e' meccanico e ripetibile.
"""
import json
import re
import sys
from pathlib import Path

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

SP = Path(r"C:/Users/marco/AppData/Local/Temp/claude/D--valentinarussomentaladvisor-it/dffa5996-baa6-4998-ae2f-50bebb183883/scratchpad/restyle")
BOZZA = SP / "sito-v2"

ROTTE = {
    "dir-2.html": "/", "index.html": "/", "chi-sono.html": "/chi-sono", "servizi.html": "/servizi",
    "human-design.html": "/human-design", "calcolo-human-design.html": "/calcolo-human-design",
    "libretto-istruzioni.html": "/libretto-istruzioni", "blog.html": "/blog", "contatti.html": "/contatti",
    "privacy.html": "/privacy", "terms.html": "/terms", "archivio.html": "/archivio",
    "hd-relazionale.html": "/hd-relazionale", "workshop-proposta.html": "/workshop-proposta",
    "aziende.html": "/aziende", "aziende-servizi.html": "/aziende/servizi",
    "aziende-blog.html": "/aziende/blog", "aziende-contatti.html": "/aziende/contatti",
    "aziende-bg5.html": "/aziende/bg5",
}


def rotta(nome):
    if nome in ROTTE:
        return ROTTE[nome]
    if nome.startswith("consulente-bg5-"):
        return "/" + nome[:-5]
    if nome.startswith("aziende-articolo-"):
        return "/aziende/blog/" + nome[len("aziende-articolo-"):-5]
    if nome.startswith("articolo-"):
        return "/blog/articoli/" + nome[len("articolo-"):-5]
    return None


def _immagini():
    m = json.loads((SP / "img-map.json").read_text(encoding="utf-8"))
    return {v: k for k, v in m.items()}


IMG = _immagini()


def converti(frag):
    """Indirizzi e immagini della bozza -> indirizzi e immagini di Grav."""
    def _link(m):
        r = rotta(m.group(1))
        return 'href="{{ base }}%s"' % r if r else m.group(0)
    frag = re.sub(r'href="([a-z0-9-]+\.html)"', _link, frag)

    def _ancora(m):
        r = rotta(m.group(1))
        return 'href="{{ base }}%s#%s"' % (r, m.group(2)) if r else m.group(0)
    frag = re.sub(r'href="([a-z0-9-]+\.html)#([^"]+)"', _ancora, frag)

    def _img(m):
        vero = IMG.get(m.group(1))
        return '%s="{{ base }}%s"' % (m.group(0).split("=")[0], vero) if vero else m.group(0)
    frag = re.sub(r'(?:src|href)="(img/[^"]+)"', _img, frag)
    return frag


def corpo(nome):
    """Il contenuto fra la barra di navigazione e il pie' di pagina."""
    t = (BOZZA / nome).read_text(encoding="utf-8")
    i = t.find("</nav>") + len("</nav>")
    j = t.find("<footer")
    return converti(t[i:j].strip())


def script(nome):
    """Gli script della pagina, esclusi quelli comuni gia' caricati dalla base."""
    t = (BOZZA / nome).read_text(encoding="utf-8")
    coda = t[t.find("</footer>") + len("</footer>"):]
    fuori = ("su.js", "menu.js", "cta.js")
    pezzi = [s for s in re.findall(r"<script[\s\S]*?</script>", coda)
             if not any(f in s for f in fuori)]
    return converti(chr(10).join(pezzi))


if __name__ == "__main__":
    quale = sys.argv[2] if len(sys.argv) > 2 else "corpo"
    print(corpo(sys.argv[1]) if quale == "corpo" else script(sys.argv[1]))
