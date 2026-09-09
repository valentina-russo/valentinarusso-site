#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Cancella dai video YouTube il commento di invito alla lezione gratuita del 14
settembre 2026, pubblicato e messo in primo piano il 09/09.

Cerca fra i commenti in cima a ciascun video quello scritto dal canale che
contiene il link di iscrizione, e cancella solo quello. Se non lo trova non
tocca niente: e' idempotente, si puo' rilanciare senza danni.

Uso:
    py togli_commento_lezione.py            # esegue
    py togli_commento_lezione.py --prova    # dice solo cosa farebbe
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

CANALE = "@valentinarussobg5"
IMPRONTA = "valentinarussobg5.com/lezione-gratuita"


def main() -> int:
    prova = "--prova" in sys.argv
    svc = _service()
    tolti = assenti = 0

    for vid in VIDEO:
        risposta = svc.commentThreads().list(
            part="snippet", videoId=vid, maxResults=20, order="relevance",
        ).execute()

        bersaglio = None
        for filo in risposta.get("items", []):
            sn = filo["snippet"]["topLevelComment"]["snippet"]
            if sn["authorDisplayName"] == CANALE and IMPRONTA in sn["textOriginal"]:
                bersaglio = filo["snippet"]["topLevelComment"]["id"]
                break

        if bersaglio is None:
            print(f"{vid}  nessun commento da togliere")
            assenti += 1
            continue

        if prova:
            print(f"{vid}  da cancellare ({bersaglio})")
            tolti += 1
            continue

        svc.comments().delete(id=bersaglio).execute()
        print(f"{vid}  commento cancellato")
        tolti += 1
        time.sleep(1)

    print(f"\ncancellati: {tolti} | gia' assenti: {assenti}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
