# -*- coding: utf-8 -*-
"""Corregge i metadati SEO di articoli che vivono solo sul server.

Alcuni articoli sono stati creati dall'admin di Grav e non esistono nel repo:
si scaricano, si riscrive il frontmatter e si ricaricano. Lo script lavora su
una cartella gia' scaricata e scrive l'elenco dei file cambiati in _cambiati.txt

    python3 correggi_seo_articoli.py <cartella>
"""
import io
import os
import re
import sys

# percorso relativo a user/pages -> campi da impostare
CAMBI = {
    "05.aziende/02.blog/aspettare-invito-senza-perdere-slancio-professionale": {
        "seo_title": "Aspettare l'invito senza perdere slancio (Guida BG5)",
    },
    "05.aziende/02.blog/costruttori-burn-out-imitare-iniziatore-strategia-carriera": {
        "seo_title": "Burn-out del Costruttore BG5: la strategia sbagliata",
    },
    "05.aziende/02.blog/proiettore-human-design-invito-lavoro": {
        "canonical": "https://valentinarussobg5.com/blog/articoli/proiettore-human-design-invito-lavoro",
    },
}


def imposta(testa, chiave, valore):
    """Sostituisce o aggiunge una riga di frontmatter, con le virgolette giuste."""
    riga = "%s: '%s'" % (chiave, valore.replace("'", "''"))
    pat = re.compile(r"^%s:.*$" % re.escape(chiave), re.M)
    if pat.search(testa):
        return pat.sub(lambda m: riga, testa, count=1)
    return testa.rstrip("\n") + "\n" + riga


def main(radice):
    cambiati = []
    for rel, campi in CAMBI.items():
        f = os.path.join(radice, *rel.split("/"), "item.md")
        if not os.path.isfile(f):
            print("non trovato sul server:", rel)
            continue
        s = io.open(f, encoding="utf-8").read()
        m = re.match(r"^(---\s*\n)(.*?)(\n---\s*\n)(.*)$", s, re.S)
        if not m:
            print("frontmatter non riconosciuto:", rel)
            continue
        testa = m.group(2)
        prima = testa
        for k, v in campi.items():
            testa = imposta(testa, k, v)
        if testa == prima:
            print("gia' a posto:", rel)
            continue
        io.open(f, "w", encoding="utf-8", newline="\n").write(
            m.group(1) + testa + m.group(3) + m.group(4))
        cambiati.append(os.path.relpath(f, radice).replace(os.sep, "/"))
        print("corretto:", rel, "->", ", ".join(campi))

    with io.open("_cambiati.txt", "w", encoding="utf-8", newline="\n") as fh:
        fh.write("\n".join(cambiati) + ("\n" if cambiati else ""))
    print("file da ricaricare:", len(cambiati))


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "pagine")
