"""
ffmpeg pipeline: clip -> 9:16 crop -> burn-in titolo (15s) + sottotitoli + watermark.

Output: 1080x1920 mp4 H.264 + AAC, ~30 fps, CRF 20.
"""
from __future__ import annotations

import shutil
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent
FONTS_DIR = HERE.parent / "bg5-generator" / "fonts"
PLAYFAIR_BOLD = FONTS_DIR / "PlayfairDisplay-Bold.ttf"
OUTFIT_BOLD = FONTS_DIR / "Outfit-Bold.ttf"


def _check_ffmpeg() -> None:
    if not shutil.which("ffmpeg"):
        raise RuntimeError("ffmpeg non installato o non in PATH")


def _escape_drawtext(s: str) -> str:
    """Escape per ffmpeg drawtext filtergraph (Windows-safe).

    In FFmpeg filter syntax, single quotes delimit option values and CANNOT be
    escaped inside the quoted string (the string ends at the first unescaped ').
    Solution: replace straight apostrophe (U+0027) with right single quotation
    mark (U+2019) which is visually identical and requires no escaping.
    """
    return (s.replace("\\", "\\\\")
             .replace("'", "’")    # straight ' -> curly ' (no ffmpeg escaping needed)
             .replace(":", "\\:")
             .replace("%", "\\%"))


def _wrap_title(title: str, max_chars: int = 22) -> tuple[str, int]:
    """
    Wrap title to max 2 lines for drawtext.
    Returns (text, fontsize).

    At PlayResX=1080 with Playfair Bold:
      - fontsize=68 -> max ~25 chars single line (uses full width)
      - fontsize=60 -> ~29 chars per line, safe for 2-line titles
    Split at nearest word boundary to the middle.
    ffmpeg drawtext interprets literal \\n as a newline.
    """
    if len(title) <= max_chars:
        return title, 68

    mid = len(title) // 2
    left = title.rfind(" ", 0, mid + 1)
    right = title.find(" ", mid)

    if left == -1 and right == -1:
        return title, 52  # no spaces — just shrink font
    elif left == -1:
        split = right
    elif right == -1:
        split = left
    else:
        split = left if (mid - left) <= (right - mid) else right

    line1 = title[:split]
    line2 = title[split + 1:]
    return f"{line1}\x00{line2}", 60


def _ffpath(p: Path) -> str:
    """Path normalization for ffmpeg filtergraph (forward slashes, no colon escape on drive)."""
    s = str(p).replace("\\", "/")
    # On Windows, escape the colon after drive letter for filtergraph
    if len(s) > 1 and s[1] == ":":
        s = s[0] + "\\:" + s[2:]
    return s


def _probe_duration(video: Path) -> float:
    """Ritorna durata in secondi del video via ffprobe."""
    r = subprocess.run(
        ["ffprobe", "-v", "error",
         "-show_entries", "format=duration",
         "-of", "default=noprint_wrappers=1:nokey=1",
         str(video)],
        capture_output=True, text=True,
    )
    return float(r.stdout.strip()) if r.stdout.strip() else 0.0


def render(
    source_video: Path,
    out_path: Path,
    start_s: float,
    end_s: float,
    title: str,
    srt_path: Path | None = None,
    watermark_text: str = "@valentinarussobg5",
    silence_padding_s: float = 1.0,
    cta_text: str | None = None,
) -> None:
    """
    silence_padding_s: secondi di respiro audio prima/dopo il segmento parlato.
    Espande start_s/end_s di `silence_padding_s` su ogni lato, clampato a [0, duration].
    Default 1.0 (brand rule Valentina). Imposta 0 per disattivare.
    """
    _check_ffmpeg()
    out_path.parent.mkdir(parents=True, exist_ok=True)

    if silence_padding_s > 0:
        source_dur = _probe_duration(source_video)
        new_start = max(0.0, start_s - silence_padding_s)
        new_end = min(source_dur, end_s + silence_padding_s) if source_dur > 0 else (end_s + silence_padding_s)
        if new_start != start_s or new_end != end_s:
            print(f"[render] padding {silence_padding_s}s: {start_s:.2f}-{end_s:.2f} -> {new_start:.2f}-{new_end:.2f}")
        start_s, end_s = new_start, new_end

    duration = end_s - start_s

    title_text, title_fontsize = _wrap_title(title)
    wm_safe = _escape_drawtext(watermark_text)

    pf_path = _ffpath(PLAYFAIR_BOLD)
    of_path = _ffpath(OUTFIT_BOLD)

    # Filtergraph:
    # 1. setpts rebase  2. Crop 9:16  3. Scale 1080x1920
    # 4. (optional) ASS subtitles  5. Title drawtext (0-15s)  6. Watermark fade-in

    fade_start = max(0, duration - 3)

    # Subtitle layer
    subtitle_filter = ""
    if srt_path is not None:
        srt_str = _ffpath(srt_path)
        subtitle_filter = f"ass='{srt_str}',"

    # Title drawtext — due drawtext separati se il titolo ha 2 righe.
    # \\n in ffmpeg drawtext dentro single-quotes NON viene interpretato come newline
    # su Windows. Soluzione: un drawtext per riga, posizionati esplicitamente.
    TITLE_Y = "h*0.10"   # 10% dall'alto = ~192px
    if "\x00" in title_text:
        line1, line2 = title_text.split("\x00", 1)
        s1 = _escape_drawtext(line1)
        s2 = _escape_drawtext(line2)
        line_gap = int(title_fontsize * 1.25)
        title_filter = (
            f"drawtext=fontfile='{pf_path}':text='{s1}':fontsize={title_fontsize}:"
            f"fontcolor=white:bordercolor=black:borderw=4:"
            f"box=1:boxcolor=black@0.55:boxborderw=18:"
            f"x=(w-text_w)/2:y={TITLE_Y}:enable='between(t,0,15)',"
            f"drawtext=fontfile='{pf_path}':text='{s2}':fontsize={title_fontsize}:"
            f"fontcolor=white:bordercolor=black:borderw=4:"
            f"box=1:boxcolor=black@0.55:boxborderw=18:"
            f"x=(w-text_w)/2:y={TITLE_Y}+{line_gap}:enable='between(t,0,15)',"
        )
    else:
        s = _escape_drawtext(title_text)
        title_filter = (
            f"drawtext=fontfile='{pf_path}':text='{s}':fontsize={title_fontsize}:"
            f"fontcolor=white:bordercolor=black:borderw=4:"
            f"box=1:boxcolor=black@0.55:boxborderw=22:"
            f"x=(w-text_w)/2:y={TITLE_Y}:enable='between(t,0,15)',"
        )

    # CTA finale (es. "VIDEO COMPLETO QUI SOTTO") — box centrato basso negli ultimi ~4s.
    # Punta al commento pinnato col link al video intero (gli Short non hanno link cliccabili in descrizione).
    cta_filter = ""
    if cta_text:
        cta_safe = _escape_drawtext(cta_text)
        cta_start = max(0.0, duration - 4.0)
        cta_filter = (
            f"drawtext=fontfile='{of_path}':text='{cta_safe}':fontsize=58:"
            f"fontcolor=white:bordercolor=black:borderw=3:"
            f"box=1:boxcolor=black@0.62:boxborderw=26:"
            f"x=(w-text_w)/2:y=h*0.74:enable='gte(t,{cta_start:.2f})',"
        )

    vf = (
        f"setpts=PTS-STARTPTS,"
        f"crop=ih*9/16:ih,scale=1080:1920,setsar=1,"
        f"{subtitle_filter}"
        f"{title_filter}"
        f"{cta_filter}"
        f"drawtext=fontfile='{of_path}':text='{wm_safe}':fontsize=32:"
        f"fontcolor=white:bordercolor=black:borderw=2:"
        f"x=60:y=h-100:"
        f"alpha='if(lt(t,{fade_start}),0,min((t-{fade_start})/0.5,1))'"
    )

    cmd = [
        "ffmpeg", "-y",
        "-ss", f"{start_s:.3f}",
        "-to", f"{end_s:.3f}",
        "-i", str(source_video),
        "-vf", vf,
        "-c:v", "libx264",
        "-preset", "medium",
        "-crf", "20",
        "-pix_fmt", "yuv420p",
        "-c:a", "aac",
        "-b:a", "192k",
        "-movflags", "+faststart",
        str(out_path),
    ]

    print("[render] running ffmpeg...")
    print("  input:", source_video)
    print("  out:  ", out_path)
    print("  segment:", f"{start_s:.1f}s -> {end_s:.1f}s ({duration:.1f}s)")
    print("  title: ", title[:60])
    rc = subprocess.run(cmd, check=False)
    if rc.returncode != 0:
        raise RuntimeError(f"ffmpeg failed (exit {rc.returncode})")


def render_slide_vertical(
    source_video: Path,
    out_path: Path,
    start_s: float,
    end_s: float,
    title: str,
    srt_path: Path | None = None,
    watermark_text: str = "@valentinarussobg5",
    silence_padding_s: float = 1.0,
    cta_text: str | None = None,
    bg_color: str = "0x1A2332",
) -> None:
    """
    Short verticale per contenuto SCREEN-SHARE / slide (sorgente 16:9 con layout
    decentrato: titolo slide a sinistra, webcam piccola in alto a destra).

    Invece del center-crop (che taglierebbe il titolo della slide), scala la slide
    INTERA a tutta larghezza (1040px) su un canvas 1080x1920 brand scuro, con:
      - titolo tipologia in alto (Playfair)
      - sottotitoli karaoke in basso (via ASS, se srt_path passato)
      - CTA finale negli ultimi ~4s
      - watermark
    """
    _check_ffmpeg()
    out_path.parent.mkdir(parents=True, exist_ok=True)

    if silence_padding_s > 0:
        source_dur = _probe_duration(source_video)
        new_start = max(0.0, start_s - silence_padding_s)
        new_end = min(source_dur, end_s + silence_padding_s) if source_dur > 0 else (end_s + silence_padding_s)
        start_s, end_s = new_start, new_end
    duration = end_s - start_s

    pf_path = _ffpath(PLAYFAIR_BOLD)
    of_path = _ffpath(OUTFIT_BOLD)

    # Titolo (fascia alta, sopra la slide)
    title_text, title_fontsize = _wrap_title(title, max_chars=20)
    TITLE_Y = "h*0.13"
    if "\x00" in title_text:
        line1, line2 = title_text.split("\x00", 1)
        s1 = _escape_drawtext(line1); s2 = _escape_drawtext(line2)
        gap = int(title_fontsize * 1.25)
        title_filter = (
            f"drawtext=fontfile='{pf_path}':text='{s1}':fontsize={title_fontsize}:"
            f"fontcolor=white:bordercolor=black:borderw=3:x=(w-text_w)/2:y={TITLE_Y},"
            f"drawtext=fontfile='{pf_path}':text='{s2}':fontsize={title_fontsize}:"
            f"fontcolor=white:bordercolor=black:borderw=3:x=(w-text_w)/2:y={TITLE_Y}+{gap},"
        )
    else:
        s = _escape_drawtext(title_text)
        title_filter = (
            f"drawtext=fontfile='{pf_path}':text='{s}':fontsize={title_fontsize}:"
            f"fontcolor=white:bordercolor=black:borderw=3:x=(w-text_w)/2:y={TITLE_Y},"
        )

    subtitle_filter = ""
    if srt_path is not None:
        subtitle_filter = f"ass='{_ffpath(srt_path)}',"

    cta_filter = ""
    if cta_text:
        cta_safe = _escape_drawtext(cta_text)
        cta_start = max(0.0, duration - 4.0)
        cta_filter = (
            f"drawtext=fontfile='{of_path}':text='{cta_safe}':fontsize=54:"
            f"fontcolor=white:bordercolor=black:borderw=3:"
            f"box=1:boxcolor=black@0.62:boxborderw=24:"
            f"x=(w-text_w)/2:y=h*0.66:enable='gte(t,{cta_start:.2f})',"
        )

    fade_start = max(0, duration - 3)

    # Slide scalata a 1040px di larghezza, centrata su canvas scuro con top a y=540.
    vf = (
        f"setpts=PTS-STARTPTS,"
        f"scale=1040:-2,setsar=1,"
        f"pad=1080:1920:(ow-iw)/2:540:color={bg_color},"
        f"{title_filter}"
        f"{subtitle_filter}"
        f"{cta_filter}"
        f"drawtext=fontfile='{of_path}':text='{_escape_drawtext(watermark_text)}':fontsize=30:"
        f"fontcolor=white:bordercolor=black:borderw=2:x=60:y=h-90:"
        f"alpha='if(lt(t,{fade_start}),0,min((t-{fade_start})/0.5,1))'"
    )

    cmd = [
        "ffmpeg", "-y",
        "-ss", f"{start_s:.3f}", "-to", f"{end_s:.3f}", "-i", str(source_video),
        "-vf", vf,
        "-c:v", "libx264", "-preset", "medium", "-crf", "20", "-pix_fmt", "yuv420p",
        "-c:a", "aac", "-b:a", "192k", "-movflags", "+faststart",
        str(out_path),
    ]
    print("[render-slide] running ffmpeg...")
    print("  segment:", f"{start_s:.1f}-{end_s:.1f}s ({duration:.1f}s)")
    print("  title:  ", title[:60])
    rc = subprocess.run(cmd, check=False)
    if rc.returncode != 0:
        raise RuntimeError(f"render_slide_vertical ffmpeg failed (exit {rc.returncode})")


def prepend_cover(cover_png: Path, short_mp4: Path, out_mp4: Path, cover_duration: float = 2.0) -> None:
    """
    Prepend cover_png as a still-image intro (cover_duration seconds) before short_mp4.
    Writes result to out_mp4. Used to embed the branded cover into the video itself,
    since YouTube Shorts does not support custom thumbnails via API or desktop web.

    Uses filter_complex concat (NOT the concat demuxer) to guarantee A/V sync.
    The concat demuxer with -c copy inherits the cover's timebase (30fps → 1/15360)
    and misinterprets the main video's 25fps timestamps, making video play 20% too
    fast vs audio (video=56s, audio=68s). filter_complex re-encodes both streams at
    a consistent 25fps timebase, eliminating the drift entirely.
    """
    _check_ffmpeg()

    # Single-pass: PNG looped for cover_duration + main short → filter_complex concat.
    # [0:v] = cover PNG scaled/padded to 1080x1920 at 25fps
    # aevalsrc = silent AAC-ready audio for the cover segment
    # concat=n=2 merges cover(v+a) + short(v+a) into one stream pair
    fc = (
        f"[0:v]scale=1080:1920:force_original_aspect_ratio=decrease,"
        f"pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=fps=25[cv];"
        f"aevalsrc=0:c=stereo:s=44100:d={cover_duration}[ca];"
        f"[cv][ca][1:v][1:a]concat=n=2:v=1:a=1[outv][outa]"
    )
    cmd = [
        "ffmpeg", "-y",
        "-loop", "1", "-t", str(cover_duration), "-i", str(cover_png),
        "-i", str(short_mp4),
        "-filter_complex", fc,
        "-map", "[outv]", "-map", "[outa]",
        "-c:v", "libx264", "-preset", "medium", "-crf", "20", "-pix_fmt", "yuv420p",
        "-c:a", "aac", "-b:a", "192k",
        "-movflags", "+faststart",
        str(out_mp4),
    ]
    rc = subprocess.run(cmd, check=False)
    if rc.returncode != 0:
        raise RuntimeError(f"prepend_cover ffmpeg failed (exit {rc.returncode})")

    print(f"[cover] prepended {cover_duration}s intro -> {out_mp4.name} ({out_mp4.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    import sys
    if len(sys.argv) < 6:
        print("usage: render_video.py <source.mp4> <out.mp4> <start_s> <end_s> <title> <subtitles.srt>")
        sys.exit(1)
    render(
        source_video=Path(sys.argv[1]),
        out_path=Path(sys.argv[2]),
        start_s=float(sys.argv[3]),
        end_s=float(sys.argv[4]),
        title=sys.argv[5],
        srt_path=Path(sys.argv[6]),
    )
