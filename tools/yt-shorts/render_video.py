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

    title_safe = _escape_drawtext(title)
    wm_safe = _escape_drawtext(watermark_text)

    pf_path = _ffpath(PLAYFAIR_BOLD)
    of_path = _ffpath(OUTFIT_BOLD)

    # Filtergraph:
    # 1. setpts rebase  2. Crop 9:16  3. Scale 1080x1920
    # 4. (optional) ASS subtitles  5. Title drawtext (0-15s)  6. Watermark fade-in

    fade_start = max(0, duration - 3)

    # Subtitle layer (optional — omit if captacity handles captions externally).
    subtitle_filter = ""
    if srt_path is not None:
        srt_str = _ffpath(srt_path)
        subtitle_filter = f"ass='{srt_str}',"

    vf = (
        # Rebase PTS to 0 so any subtitle timestamps always match frame timestamps.
        f"setpts=PTS-STARTPTS,"
        f"crop=ih*9/16:ih,scale=1080:1920,setsar=1,"
        f"{subtitle_filter}"
        # Title: top area, well clear of the speaker's head.
        f"drawtext=fontfile='{pf_path}':text='{title_safe}':fontsize=68:"
        f"fontcolor=white:bordercolor=black:borderw=4:"
        f"box=1:boxcolor=black@0.55:boxborderw=22:"
        f"x=(w-text_w)/2:y=h*0.07:enable='between(t,0,15)',"
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
