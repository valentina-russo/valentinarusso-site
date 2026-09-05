#!/usr/bin/env python3
"""Porta i riferimenti immagine delle pagine dai PNG pesanti ai JPEG leggeri.

Serve per gli articoli che vivono solo sul server: nel progetto i riferimenti
sono gia' aggiornati, sul server no. Scrive l'elenco dei file cambiati in
_cambiati.txt, che il workflow usa per ricaricare solo quelli.
"""
import os
import sys

NOMI = ["blog-1", "blog-2", "blog-3", "blog-4", "blog-5",
        "perche-ti-attrae-quella-persona", "disegno-umano-carta-completa",
        "come-leggere-carta-human-design", "4-tipi-human-design-archetip",
        "mammal-chart-cane", "croce-incarnazione-human-design",
        "libretto-cover-hero-16x9", "firefly-double-exposure", "costruttori-e-guide"]


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
            if nuovo != t:
                open(p, "w", encoding="utf-8", newline="
").write(nuovo)
                cambiati.append(p)
    open("_cambiati.txt", "w", encoding="utf-8").write("
".join(cambiati))
    print("pagine da aggiornare:", len(cambiati))
    for c in cambiati:
        print("  ", c)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "pagine")
