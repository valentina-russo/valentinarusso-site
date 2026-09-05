#!/usr/bin/env python3
"""Porta i riferimenti immagine delle pagine dai PNG pesanti ai JPEG leggeri.

Serve per gli articoli che vivono solo sul server: nel progetto i riferimenti
sono gia' aggiornati, sul server no. Scrive l'elenco dei file cambiati in
_cambiati.txt, che il workflow usa per ricaricare solo quelli.
"""
import os
import re
import sys

NOMI = ["blog-1", "blog-2", "blog-3", "blog-4", "blog-5",
        "perche-ti-attrae-quella-persona", "disegno-umano-carta-completa",
        "come-leggere-carta-human-design", "4-tipi-human-design-archetip",
        "mammal-chart-cane", "croce-incarnazione-human-design",
        "libretto-cover-hero-16x9", "firefly-double-exposure", "costruttori-e-guide"]

TESTI = [("dell'Human Design", "dello Human Design"),
         ("all'Human Design", "allo Human Design"),
         ("nell'Human Design", "nello Human Design"),
         ("l'Human Design", "lo Human Design"),
         ("il Human Design", "lo Human Design"),
         ("Il Human Design", "Lo Human Design"),
         ("al Human Design", "allo Human Design"),
         ("del Human Design", "dello Human Design")]


def spegni_doppioni(p, testo):
    """Le pagine col "(Copy)" nel titolo sono duplicati rimasti indietro:
    restano sul server ma dichiarate non pubblicate, cosi' escono dalla mappa."""
    if "(Copy)" not in testo:
        return testo
    if re.search(r"^published:", testo, re.M):
        testo = re.sub(r"^published:.*$", "published: false", testo, count=1, flags=re.M)
    else:
        testo = re.sub(r"^(title:.*)$", r"
published: false", testo, count=1, flags=re.M)
    if re.search(r"^routable:", testo, re.M):
        testo = re.sub(r"^routable:.*$", "routable: false", testo, count=1, flags=re.M)
    return testo


def main(radice):
    cambiati = []
    for cartella, _, file in os.walk(radice):
        for f in file:
            if not f.endswith(".md"):
                continue
            p = os.path.join(cartella, f)
            t = open(p, encoding="utf-8", errors="replace").read()
            nuovo = t
            for n in NOMI:
                nuovo = nuovo.replace(n + ".png", n + ".jpg")
            for a, b in TESTI:
                nuovo = nuovo.replace(a, b)
            nuovo = spegni_doppioni(p, nuovo)
            if nuovo != t:
                open(p, "w", encoding="utf-8", newline="\n").write(nuovo)
                cambiati.append(p)
    open("_cambiati.txt", "w", encoding="utf-8").write("\n".join(cambiati))
    print("pagine da aggiornare:", len(cambiati))
    for c in cambiati:
        print("  ", c)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "pagine")
