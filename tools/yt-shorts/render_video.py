"""
ffmpeg pipeline: clip → 9:16 crop → burn-in titolo (15s) + sottotitoli + watermark.

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
    """Escape per ffmpeg drawtext: ' \\ : %"""
    return (s.replace("\\", "\\\\")
             .replace("'", "\\'")
             .replace(":", "\\:")
             .replace("%", "\\%"))


def _wrap_title(title: str, max_chars: int = 22) -> tuple[str, int]:
    """
    Wrap title to max 2 lines for drawtext.
    Returns (text, fontsize).

    At PlayResX=1080 with Playfair Bold:
      - fontsize=68 → max ~25 chars single line (uses full width)
      - fontsize=60 → ~29 chars per line, safe for 2-line titles
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


def render(
    source_video: Path,
    out_path: Path,
    start_s: float,
    end_s: float,
    title: str,
    srt_path: Path | None = None,
    watermark_text: str = "@valentinarussobg5",
) -> None:
    _check_ffmpeg()
    out_path.parent.mkdir(parents=True, exist_ok=True)
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

    vf = (
        f"setpts=PTS-STARTPTS,"
        f"crop=ih*9/16:ih,scale=1080:1920,setsar=1,"
        f"{subtitle_filter}"
        f"{title_filter}"
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
