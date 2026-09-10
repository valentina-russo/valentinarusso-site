#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Prepara la registrazione di una lezione per lo streaming: da un file unico
ricava tre qualita' in formato HLS, cosi' il player sceglie quella che regge la
connessione di chi guarda.

Non carica niente: produce una cartella pronta, che carica_su_r2.py spedisce.

    py prepara_lezione.py "D:/.../lezione-01.mp4" s1-01
    py prepara_lezione.py <sorgente> <slug> --uscita D:/Download/corso-hls
"""
from __future__ import annotations

import argparse
import shutil
import subprocess
import sys
from pathlib import Path

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# Tre gradini bastano: sopra i 720p non si guadagna niente su una persona che
# parla con delle slide, sotto i 360p non si legge il testo proiettato.
GRADINI = [
    # nome, larghezza, altezza, video kbps, tetto kbps, audio kbps
    ("720p", 1280, 720, 1800, 1980, 128),
    ("480p",  854, 480,  900,  990,  96),
    ("360p",  640, 360,  500,  550,  96),
]
DURATA_PEZZO = 6  # secondi per segmento: compromesso fra reattivita' e numero di file


def ffmpeg() -> str:
    trovato = shutil.which("ffmpeg")
    if not trovato:
        sys.exit("ffmpeg non trovato nel PATH.")
    return trovato


def durata(sorgente: Path) -> float:
    fp = shutil.which("ffprobe") or "ffprobe"
    r = subprocess.run(
        [fp, "-v", "error", "-show_entries", "format=duration",
         "-of", "default=nw=1:nk=1", str(sorgente)],
        capture_output=True, text=True,
    )
    try:
        return float(r.stdout.strip())
    except ValueError:
        return 0.0


def comando(sorgente: Path, dest: Path) -> list[str]:
    n = len(GRADINI)
    parti = [f"[0:v]split={n}" + "".join(f"[s{i}]" for i in range(n))]
    for i, (_, w, h, *_r) in enumerate(GRADINI):
        # force_original_aspect_ratio + pad: una sorgente 4:3 non viene stirata
        parti.append(
            f"[s{i}]scale=w={w}:h={h}:force_original_aspect_ratio=decrease,"
            f"pad={w}:{h}:(ow-iw)/2:(oh-ih)/2,setsar=1[v{i}]"
        )
    filtro = ";".join(parti)

    cmd = [ffmpeg(), "-hide_banner", "-y", "-i", str(sorgente), "-filter_complex", filtro]
    for i, (_, _w, _h, kbps, tetto, _a) in enumerate(GRADINI):
        cmd += [
            "-map", f"[v{i}]",
            f"-c:v:{i}", "libx264", "-preset", "medium", "-profile:v", "high",
            f"-b:v:{i}", f"{kbps}k", f"-maxrate:v:{i}", f"{tetto}k",
            f"-bufsize:v:{i}", f"{kbps * 2}k",
            "-g", "48", "-keyint_min", "48", "-sc_threshold", "0",
        ]
    for i, (*_r, audio) in enumerate(GRADINI):
        cmd += ["-map", "a:0?", f"-c:a:{i}", "aac", f"-b:a:{i}", f"{audio}k", "-ac", "2"]

    mappa = " ".join(f"v:{i},a:{i},name:{nome}" for i, (nome, *_r) in enumerate(GRADINI))
    cmd += [
        "-f", "hls",
        "-hls_time", str(DURATA_PEZZO),
        "-hls_playlist_type", "vod",
        "-hls_list_size", "0",
        "-hls_segment_filename", str(dest / "%v" / "pezzo%05d.ts"),
        "-master_pl_name", "master.m3u8",
        "-var_stream_map", mappa,
        str(dest / "%v" / "lista.m3u8"),
    ]
    return cmd


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("sorgente")
    ap.add_argument("slug", help="identificativo della lezione, es. s1-01")
    ap.add_argument("--uscita", default="D:/Download/corso-hls")
    args = ap.parse_args()

    sorgente = Path(args.sorgente)
    if not sorgente.is_file():
        sys.exit(f"sorgente non trovata: {sorgente}")

    dest = Path(args.uscita) / args.slug
    for nome, *_r in GRADINI:
        (dest / nome).mkdir(parents=True, exist_ok=True)

    sec = durata(sorgente)
    print(f"sorgente: {sorgente.name}  ({sec / 60:.0f} minuti)")
    print(f"uscita:   {dest}")
    print("codifica in corso, ci vuole piu' o meno il tempo del video\n")

    esito = subprocess.run(comando(sorgente, dest))
    if esito.returncode != 0:
        return esito.returncode

    # Su Windows ffmpeg scrive i percorsi nel manifesto con la barra rovesciata
    # ("720p\\lista.m3u8"), che in un URL non significa niente e il player
    # non trova le liste. Va raddrizzato, altrimenti il video non parte.
    master = dest / "master.m3u8"
    testo = master.read_text(encoding="utf-8")
    if "\\" in testo:
        master.write_text(testo.replace("\\", "/"), encoding="utf-8")
        print("percorsi del manifesto raddrizzati")

    pezzi = sorted(dest.rglob("*.ts"))
    peso = sum(p.stat().st_size for p in pezzi)
    print(f"\nfatto: {len(pezzi)} pezzi, {peso / 1024 / 1024:.0f} MB in tutto")
    if sec:
        print(f"circa {peso / 1024 / 1024 / (sec / 3600):.0f} MB per ora di video")
    print(f"manifesto: {dest / 'master.m3u8'}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
