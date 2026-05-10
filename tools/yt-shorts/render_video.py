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
    srt_path: Path,
    watermark_text: str = "@valentinarussobg5",
) -> None:
    _check_ffmpeg()
    out_path.parent.mkdir(parents=True, exist_ok=True)
    duration = end_s - start_s

    title_safe = _escape_drawtext(title)
    wm_safe = _escape_drawtext(watermark_text)

    pf_path = _ffpath(PLAYFAIR_BOLD)
    of_path = _ffpath(OUTFIT_BOLD)
    srt_str = _ffpath(srt_path)

    # Filtergraph:
    # 1. Crop center vertical 9:16
    # 2. Scale 1080x1920
    # 3. Subtitles burn-in safe zone bassa (MarginV=240 ~75% from bottom in libass coords)
    # 4. Drawtext title 0-15s, top-third, Playfair Bold 72, white text + black outline + semi-transparent box
    # 5. Drawtext watermark fade-in last 3s, bottom-left, Outfit Bold 32

    # libass FontName: il filtro subtitles usa libass; per usare il file font passiamo
    # fontsdir e il nome del file (font name). Più semplice: uso force_style con FontName.
    # Per rendere più affidabile su Windows, copiamo i font in un dir tmp.
    fonts_dir_str = _ffpath(FONTS_DIR)

    subtitle_style = (
        "FontName=Outfit Bold,"
        "FontSize=18,"
        "PrimaryColour=&H00FFFFFF,"
        "OutlineColour=&H00000000,"
        "Bold=1,"
        "Outline=2,"
        "Shadow=1,"
        "MarginV=320,"
        "Alignment=2"
    )

    fade_start = max(0, duration - 3)

    vf = (
        f"crop=ih*9/16:ih,scale=1080:1920,setsar=1,"
        f"subtitles='{srt_str}':fontsdir='{fonts_dir_str}':"
        f"force_style='{subtitle_style}',"
        f"drawtext=fontfile='{pf_path}':text='{title_safe}':fontsize=68:"
        f"fontcolor=white:bordercolor=black:borderw=4:"
        f"box=1:boxcolor=black@0.55:boxborderw=22:"
        f"x=(w-text_w)/2:y=h*0.18:enable='between(t,0,15)',"
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
