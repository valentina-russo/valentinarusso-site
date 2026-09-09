#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Toglie dalle descrizioni YouTube il richiamo alla lezione gratuita del 14
settembre 2026, inserito in testa il 09/09.

Non ripristina il backup: rimuove esattamente il blocco aggiunto e lascia
intatto tutto il resto, cosi' eventuali altre modifiche fatte nel frattempo
non vengono sovrascritte. E' idempotente: se il blocco non c'e', non tocca
niente.

Uso:
    py togli_richiamo_lezione.py            # esegue
    py togli_richiamo_lezione.py --prova    # dice solo cosa farebbe
"""
from __future__ import annotations

import sys
import time
from pathlib import Path

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
sys.path.insert(0, str(Path(__file__).resolve().parent))

from youtube_publisher import _service  # noqa: E402

VIDEO = [
    "O1z5mgxmz0w", "tpTmqjZOTQs", "3qdEe--_eh4", "Vys2acrYEiI", "tPBoatUTR5I",
    "V7-5EAx2XKo", "1IPj1yKOhDc", "ZFt60QYOcU0", "omw_vQSj-cs", "sbi9bHmLQAg",
]

RICHIAMO = (
    "PRIMA LEZIONE GRATUITA · lunedì 14 settembre, ore 20:30 su Zoom\n"
    "Il Corso Base di Human Design comincia da qui. Iscrizioni:\n"
    "https://valentinarussobg5.com/lezione-gratuita-human-design?da=yt\n\n"
)


def main() -> int:
    prova = "--prova" in sys.argv
    svc = _service()
    tolti = puliti = mancanti = 0

    for vid in VIDEO:
        risposta = svc.videos().list(part="snippet", id=vid).execute()
        if not risposta.get("items"):
            print(f"{vid}  video non trovato")
            mancanti += 1
            continue

        sn = risposta["items"][0]["snippet"]
        if RICHIAMO not in sn["description"]:
            print(f"{vid}  gia' pulito")
            puliti += 1
            continue

        if prova:
            print(f"{vid}  da ripulire")
            tolti += 1
            continue

        sn["description"] = sn["description"].replace(RICHIAMO, "", 1)
        svc.videos().update(part="snippet", body={"id": vid, "snippet": sn}).execute()
        print(f"{vid}  richiamo tolto")
        tolti += 1
        time.sleep(1)

    print(f"\ntolti: {tolti} | gia' puliti: {puliti} | non trovati: {mancanti}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
