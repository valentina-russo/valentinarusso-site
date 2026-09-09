#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Invito alla lezione gratuita del 14 settembre 2026, come commento sotto tutti i
video lunghi pubblici del canale.

Un solo file per le due direzioni:
    py invito_lezione_commenti.py metti      # pubblica dove manca
    py invito_lezione_commenti.py togli      # cancella dove c'e'
    py invito_lezione_commenti.py <cmd> --prova

La lista dei video non e' scritta a mano: viene dal canale, filtrando per durata
sopra i tre minuti, cosi' gli Short restano fuori. Entrambe le direzioni sono
idempotenti, si possono rilanciare senza fare danni.
"""
from __future__ import annotations

import json
import re
import sys
import time
from pathlib import Path

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
sys.path.insert(0, str(Path(__file__).resolve().parent))

from youtube_publisher import _service  # noqa: E402

CANALE = "@valentinarussobg5"
IMPRONTA = "valentinarussobg5.com/lezione-gratuita"
SOGLIA = 180  # sotto i tre minuti e' uno Short, non ci interessa

TESTO = (
    "Lunedì 14 settembre alle 20:30 faccio la prima lezione del Corso Base "
    "di Human Design, gratuita, su Zoom. Presento il programma e rispondo alle "
    "domande.\n"
    "Se vuoi esserci: https://valentinarussobg5.com/lezione-gratuita-human-design?da=yt"
)


def _durata(iso: str) -> int:
    unita = {"D": 86400, "H": 3600, "M": 60, "S": 1}
    return sum(int(n) * unita[u] for n, u in re.findall(r"(\d+)([DHMS])", iso))


def video_lunghi(svc) -> list[str]:
    """I video pubblici del canale che non sono Short."""
    canale = svc.channels().list(part="contentDetails", mine=True).execute()
    carichi = canale["items"][0]["contentDetails"]["relatedPlaylists"]["uploads"]

    ids, pagina = [], None
    while True:
        r = svc.playlistItems().list(
            part="contentDetails", playlistId=carichi, maxResults=50, pageToken=pagina,
        ).execute()
        ids += [i["contentDetails"]["videoId"] for i in r["items"]]
        pagina = r.get("nextPageToken")
        if not pagina:
            break

    lunghi = []
    for i in range(0, len(ids), 50):
        r = svc.videos().list(
            part="contentDetails,status", id=",".join(ids[i:i + 50]),
        ).execute()
        for it in r["items"]:
            if it["status"]["privacyStatus"] != "public":
                continue
            secondi = _durata(it["contentDetails"]["duration"])
            # durata 0 = diretta mai processata: non e' uno Short, tienila
            if secondi == 0 or secondi > SOGLIA:
                lunghi.append(it["id"])
    return lunghi


class CommentiChiusi(Exception):
    pass


def mio_commento(svc, vid: str) -> str | None:
    """L'id del commento di invito su questo video, se c'e'."""
    try:
        r = svc.commentThreads().list(
            part="snippet", videoId=vid, maxResults=20, order="relevance",
        ).execute()
    except Exception as e:
        if "commentsDisabled" in str(e):
            raise CommentiChiusi from e
        raise
    for filo in r.get("items", []):
        sn = filo["snippet"]["topLevelComment"]["snippet"]
        if sn["authorDisplayName"] == CANALE and IMPRONTA in sn["textOriginal"]:
            return filo["snippet"]["topLevelComment"]["id"]
    return None


def main() -> int:
    if len(sys.argv) < 2 or sys.argv[1] not in ("metti", "togli"):
        print(__doc__)
        return 2
    verso, prova = sys.argv[1], "--prova" in sys.argv

    svc = _service()
    video = video_lunghi(svc)
    print(f"video lunghi pubblici: {len(video)}\n")

    fatti = saltati = falliti = chiusi = 0
    for vid in video:
        try:
            gia = mio_commento(svc, vid)
        except CommentiChiusi:
            print(f"{vid}  commenti disattivati, lasciato stare")
            chiusi += 1
            continue

        if verso == "metti":
            if gia:
                saltati += 1
                continue
            if prova:
                print(f"{vid}  da commentare")
                fatti += 1
                continue
            try:
                svc.commentThreads().insert(part="snippet", body={"snippet": {
                    "videoId": vid,
                    "topLevelComment": {"snippet": {"textOriginal": TESTO}},
                }}).execute()
            except Exception as e:  # commenti chiusi, quota, video protetto
                print(f"{vid}  NON riuscito: {str(e)[:90]}")
                falliti += 1
                continue
            print(f"{vid}  commentato")
            fatti += 1
            time.sleep(0.5)

        else:
            if not gia:
                saltati += 1
                continue
            if prova:
                print(f"{vid}  da cancellare")
                fatti += 1
                continue
            try:
                svc.comments().delete(id=gia).execute()
            except Exception as e:
                print(f"{vid}  NON riuscito: {str(e)[:90]}")
                falliti += 1
                continue
            print(f"{vid}  commento cancellato")
            fatti += 1
            time.sleep(0.5)

    verbo = "commentati" if verso == "metti" else "ripuliti"
    print(f"\n{verbo}: {fatti} | gia' a posto: {saltati} | falliti: {falliti}")
    return 1 if falliti else 0


if __name__ == "__main__":
    raise SystemExit(main())
