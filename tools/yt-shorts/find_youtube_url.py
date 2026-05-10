"""
Identifica il video YouTube originale a partire da un file video locale.

Strategia:
  1. ffprobe del file → leggi metadati embedded (purl, comment) per match diretto.
  2. Se assente: estrai durata del file.
  3. yt-dlp lista i video del canale di Valentina (via @handle).
  4. Filtra per durata ±5s del file locale.
  5. Se >1 candidato, fallback: trascrivi 60s del file con Whisper, confronta
     con titoli/descrizioni dei candidati (match testuale, score Jaccard).
  6. Ritorna l'URL del miglior match (o None).

Default channel: @valentinarussobg5.
"""
from __future__ import annotations

import json
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path

DEFAULT_CHANNEL_HANDLE = "@valentinarussobg5"
DURATION_MATCH_TOLERANCE_S = 5.0

# Lookup table noto per evitare resolve dinamico fragile.
# Aggiungere altri canali qui se serve.
KNOWN_CHANNEL_IDS = {
    "@valentinarussobg5": "UCIW4aZwPaYirGVWAg5uTXBA",
}


@dataclass
class YouTubeCandidate:
    video_id: str
    title: str
    duration_s: float | None
    score: float = 0.0

    @property
    def url(self) -> str:
        return f"https://youtu.be/{self.video_id}"


def _ffprobe_metadata(path: Path) -> tuple[float, dict]:
    """Return (duration_s, format_tags_dict)."""
    cmd = ["ffprobe", "-v", "quiet", "-print_format", "json",
           "-show_format", str(path)]
    res = subprocess.run(cmd, capture_output=True, text=True, check=False)
    if res.returncode != 0:
        raise RuntimeError(f"ffprobe failed: {res.stderr}")
    data = json.loads(res.stdout)
    fmt = data.get("format", {})
    duration = float(fmt.get("duration", 0))
    tags = fmt.get("tags", {}) or {}
    return duration, tags


def _check_embedded_youtube(tags: dict) -> str | None:
    """Look for YouTube URL in standard MP4 metadata tags."""
    candidates = []
    for key in ("purl", "comment", "description", "URL", "url"):
        v = tags.get(key) or tags.get(key.lower())
        if v:
            candidates.append(str(v))
    pattern = re.compile(r"(?:youtu\.be/|youtube\.com/watch\?v=)([A-Za-z0-9_-]{11})")
    for c in candidates:
        m = pattern.search(c)
        if m:
            return f"https://youtu.be/{m.group(1)}"
    return None


def _resolve_channel_id(channel_handle: str) -> str | None:
    """Risolve @handle → channel ID UC... Usa lookup table; fallback yt-dlp."""
    if channel_handle in KNOWN_CHANNEL_IDS:
        return KNOWN_CHANNEL_IDS[channel_handle]
    # fallback: chiede a yt-dlp 1 video del canale e ne legge channel_id
    url = f"https://www.youtube.com/{channel_handle}/videos"
    cmd = ["python", "-m", "yt_dlp", "--flat-playlist", "--no-warnings",
           "--playlist-items", "1", "--print", "%(channel_id)s", url]
    res = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8",
                         errors="replace", check=False)
    cid = (res.stdout or "").strip().splitlines()
    return cid[0] if cid and cid[0].startswith("UC") else None


def _list_channel_videos(channel_handle: str = DEFAULT_CHANNEL_HANDLE,
                         max_videos: int = 200) -> list[YouTubeCandidate]:
    """
    yt-dlp lista video del canale con durata.
    L'URL @handle/videos NON funziona con --flat-playlist (ritorna 0 items).
    Soluzione: risolvi channel_id UC... e usa l'uploads playlist UULF...
    (sostituisce 'UC' con 'UULF').
    """
    channel_id = _resolve_channel_id(channel_handle)
    if not channel_id:
        return []
    uploads_id = "UULF" + channel_id[2:]  # UC... → UULF...
    playlist_url = f"https://www.youtube.com/playlist?list={uploads_id}"

    cmd = [
        "python", "-m", "yt_dlp", "--flat-playlist", "--no-warnings",
        "--dump-json", playlist_url,
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8",
                         errors="replace", check=False)
    out = res.stdout

    cands: list[YouTubeCandidate] = []
    for line in out.splitlines()[:max_videos]:
        line = line.strip()
        if not line:
            continue
        try:
            d = json.loads(line)
        except Exception:
            continue
        vid = d.get("id")
        title = d.get("title") or ""
        dur = d.get("duration")
        if vid:
            cands.append(YouTubeCandidate(
                video_id=vid,
                title=title,
                duration_s=float(dur) if dur is not None else None,
            ))
    return cands


def _filter_by_duration(cands: list[YouTubeCandidate], target_s: float,
                        tol_s: float = DURATION_MATCH_TOLERANCE_S
                        ) -> list[YouTubeCandidate]:
    return [c for c in cands
            if c.duration_s is not None
            and abs(c.duration_s - target_s) <= tol_s]


def _transcribe_60s(path: Path) -> str:
    """Quick transcription of first 60s for textual matching."""
    from faster_whisper import WhisperModel
    # extract 60s with ffmpeg first (faster than letting whisper read full file)
    sample = path.parent / f"_sample_60s_{path.stem}.wav"
    cmd = ["ffmpeg", "-y", "-ss", "0", "-t", "60", "-i", str(path),
           "-vn", "-acodec", "pcm_s16le", "-ar", "16000", "-ac", "1",
           str(sample)]
    subprocess.run(cmd, check=True, capture_output=True)
    m = WhisperModel("small", device="cpu", compute_type="int8")
    segs, _info = m.transcribe(str(sample), language="it", beam_size=1)
    text = " ".join((s.text or "").strip() for s in segs)
    try:
        sample.unlink()
    except Exception:
        pass
    return text


def _jaccard(a: str, b: str) -> float:
    """Jaccard token overlap (lowercase, alpha-only, len>=4)."""
    def toks(s):
        return set(t for t in re.findall(r"[a-zàèéìòù]+", s.lower()) if len(t) >= 4)
    sa, sb = toks(a), toks(b)
    if not sa or not sb:
        return 0.0
    return len(sa & sb) / len(sa | sb)


def find_youtube_url(
    local_path: Path,
    channel_handle: str = DEFAULT_CHANNEL_HANDLE,
    verbose: bool = True,
) -> str | None:
    """
    Trova l'URL del video YouTube originale corrispondente al file locale.
    Ritorna l'URL (https://youtu.be/...) o None se non determinabile.
    """
    duration_s, tags = _ffprobe_metadata(local_path)
    if verbose:
        print(f"[find_yt] file duration: {duration_s:.1f}s")

    # Step 1: metadati embedded
    embedded = _check_embedded_youtube(tags)
    if embedded:
        if verbose:
            print(f"[find_yt] embedded YouTube URL: {embedded}")
        return embedded

    # Step 2: lista canale + filtra durata
    if verbose:
        print(f"[find_yt] listing channel {channel_handle}...")
    cands = _list_channel_videos(channel_handle)
    if verbose:
        print(f"[find_yt] {len(cands)} videos in channel")

    by_dur = _filter_by_duration(cands, duration_s)
    if verbose:
        print(f"[find_yt] {len(by_dur)} match by duration (±{DURATION_MATCH_TOLERANCE_S}s)")

    if len(by_dur) == 0:
        return None
    if len(by_dur) == 1:
        c = by_dur[0]
        if verbose:
            print(f"[find_yt] unique match: {c.title} -> {c.url}")
        return c.url

    # Step 3: trascrivi 60s e fai match testuale sui candidati
    if verbose:
        print("[find_yt] multiple duration matches, transcribing 60s for tie-break...")
    text = _transcribe_60s(local_path)
    for c in by_dur:
        c.score = _jaccard(text, c.title)
    by_dur.sort(key=lambda c: c.score, reverse=True)
    if verbose:
        for c in by_dur[:3]:
            print(f"  score={c.score:.2f}  {c.title[:60]}  -> {c.url}")
    best = by_dur[0]
    return best.url if best.score > 0.05 else None


if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("usage: find_youtube_url.py <video_path>")
        sys.exit(1)
    p = Path(sys.argv[1])
    url = find_youtube_url(p)
    if url:
        print()
        print(f"FOUND: {url}")
    else:
        print()
        print("NOT FOUND")
