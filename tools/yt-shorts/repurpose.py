"""
Orchestrator end-to-end: link YouTube → Short pubblicato.

Usage:
  python repurpose.py <URL_YT | path/video.mp4> [--start MM:SS] [--end MM:SS]
                                                 [--cover-template NAME]
                                                 [--no-publish]
                                                 [--privacy public|unlisted|private]

Output dir: D:/Download/yt-shorts/<slug>/
"""
from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

from dotenv import load_dotenv

import cover_templates
from select_segment import select_segment
from generate_titles import via_api, manual_prompt, load_manual
from find_youtube_url import find_youtube_url

HERE = Path(__file__).resolve().parent
load_dotenv(HERE / ".env")

OUT_ROOT = Path(os.environ.get("YT_SHORTS_OUT_ROOT", "D:/Download/yt-shorts"))
COVER_TEMPLATE = os.environ.get("COVER_TEMPLATE", "6-photo-bottom-dark")
COVER_PHOTO = os.environ.get("COVER_PHOTO", "square")
WATERMARK = os.environ.get("WATERMARK_TEXT", "@valentinarussobg5")
DEFAULT_PRIVACY = os.environ.get("DEFAULT_PRIVACY", "unlisted")


def _slugify(s: str) -> str:
    s = re.sub(r"[^a-z0-9-]+", "-", s.lower()).strip("-")
    return s[:60] or "short"


def _parse_ms(t: str) -> float:
    parts = t.split(":")
    if len(parts) == 2:
        return int(parts[0]) * 60 + float(parts[1])
    elif len(parts) == 3:
        return int(parts[0]) * 3600 + int(parts[1]) * 60 + float(parts[2])
    return float(t)


def acquire(input_str: str, work_dir: Path) -> Path:
    """Download or copy source video. Returns path to source.mp4."""
    src = work_dir / "source.mp4"
    if input_str.startswith("http"):
        cmd = [
            "yt-dlp",
            input_str,
            "-f", "bestvideo[height<=1080]+bestaudio/best[height<=1080]",
            "--merge-output-format", "mp4",
            "-o", str(src),
        ]
        print("[acquire] yt-dlp", " ".join(cmd[1:]))
        subprocess.run(cmd, check=True)
    else:
        shutil.copy(input_str, src)
    return src


def transcribe_step(video: Path, work_dir: Path):
    from transcribe import transcribe, to_srt
    print("[transcribe] running Whisper (italiano, modello medium)...")
    t = transcribe(video, model_size="medium")
    (work_dir / "transcript_full.json").write_text(
        json.dumps({"full_text": t.full_text, "segments": t.segments,
                    "words": [{"w": w.word, "s": w.start, "e": w.end} for w in t.words]},
                   ensure_ascii=False, indent=2),
        encoding="utf-8"
    )
    return t


def select_segment_step(transcript_full, args, work_dir: Path):
    if args.start is not None and args.end is not None:
        text = " ".join(s["text"] for s in transcript_full.segments
                        if s["start"] >= args.start and s["end"] <= args.end)
        from select_segment import SelectedSegment
        seg = SelectedSegment(start_s=args.start, end_s=args.end, text=text, score=99.0)
        print(f"[segment] manuale: {args.start:.1f}s -> {args.end:.1f}s")
    else:
        seg = select_segment(transcript_full.segments)
        print(f"[segment] AI-pick: {seg.start_s:.1f}s -> {seg.end_s:.1f}s "
              f"(dur {seg.end_s - seg.start_s:.1f}s, score {seg.score:.2f})")
    return seg


def write_segment_srt(transcript_full, seg, work_dir: Path) -> Path:
    """Build SRT only for words within the chosen segment."""
    from transcribe import to_srt, WordTimestamp
    seg_words = []
    for w in transcript_full.words:
        if w.start >= seg.start_s and w.end <= seg.end_s:
            # rebase timestamps to start at 0 for the clip
            seg_words.append(WordTimestamp(
                word=w.word,
                start=w.start - seg.start_s,
                end=w.end - seg.start_s,
            ))
    srt = to_srt(seg_words)
    p = work_dir / "subtitles.srt"
    p.write_text(srt, encoding="utf-8")
    return p


def generate_titles_step(seg_text: str, work_dir: Path):
    if os.environ.get("ANTHROPIC_API_KEY"):
        print("[titles] via Anthropic API")
        pkg = via_api(seg_text)
    else:
        prompt = manual_prompt(seg_text)
        prompt_path = work_dir / "titles_prompt.txt"
        prompt_path.write_text(prompt, encoding="utf-8")
        print(f"[titles] manuale. Prompt scritto in: {prompt_path}")
        print("Incollalo in Claude Code interno, poi salva l'output JSON in titles_raw.json e premi INVIO...")
        input()
        raw = (work_dir / "titles_raw.json").read_text(encoding="utf-8")
        pkg = load_manual(raw)
    return pkg


def choose_title(pkg, work_dir: Path) -> str:
    print()
    print("=== 10 TITOLI ===")
    for i, t in enumerate(pkg.titles, 1):
        print(f"  {i}. {t}")
    while True:
        ans = input("Scegli numero [1-10] (default=1): ").strip() or "1"
        try:
            idx = int(ans) - 1
            if 0 <= idx < len(pkg.titles):
                chosen = pkg.titles[idx]
                break
        except ValueError:
            pass
        print("Numero non valido, riprova.")
    (work_dir / "titles.txt").write_text(
        "\n".join(f"{'>>> ' if t == chosen else '    '}{t}" for t in pkg.titles),
        encoding="utf-8"
    )
    return chosen


def render_cover(title: str, work_dir: Path) -> Path:
    img = cover_templates.render(COVER_TEMPLATE, title, photo_key=COVER_PHOTO)
    p = work_dir / "cover.png"
    img.save(p, "PNG", quality=95)
    print(f"[cover] template={COVER_TEMPLATE} photo={COVER_PHOTO} -> {p}")
    return p


def render_video_step(source: Path, seg, title: str, srt: Path, work_dir: Path) -> Path:
    from render_video import render
    out = work_dir / "short.mp4"
    render(
        source_video=source,
        out_path=out,
        start_s=seg.start_s,
        end_s=seg.end_s,
        title=title,
        srt_path=srt,
        watermark_text=WATERMARK,
    )
    return out


def publish_step(short_path: Path, cover_path: Path, title: str, pkg, original_url: str | None,
                 privacy: str) -> str | None:
    desc_parts = [pkg.description.strip()]
    if original_url:
        desc_parts.append(f"\n\nVideo originale: {original_url}")
    desc_parts.append("\n\n" + " ".join(f"#{h}" for h in pkg.hashtags))
    description = "\n".join(desc_parts)

    print()
    print("=== PREVIEW UPLOAD ===")
    print(f"Titolo:       {title}")
    print(f"Descrizione:  {description[:200]}...")
    print(f"Privacy:      {privacy}")
    print(f"Cover:        {cover_path}")
    print(f"Video:        {short_path}")
    confirm = input("Pubblicare ora? [y/N]: ").strip().lower()
    if confirm != "y":
        print("[publish] annullato. File pronti in:", short_path.parent)
        return None

    from youtube_publisher import upload
    return upload(
        video_path=short_path,
        title=title,
        description=description,
        tags=pkg.hashtags,
        privacy=privacy,
        cover_path=cover_path,
    )


def main():
    p = argparse.ArgumentParser()
    p.add_argument("input", help="URL YouTube o path locale al video")
    p.add_argument("--start", type=str, help="MM:SS (override AI segment)")
    p.add_argument("--end", type=str, help="MM:SS (override AI segment)")
    p.add_argument("--cover-template", type=str, help="Override COVER_TEMPLATE env")
    p.add_argument("--no-publish", action="store_true", help="Stop dopo render, no upload")
    p.add_argument("--privacy", type=str, default=DEFAULT_PRIVACY)
    args = p.parse_args()

    args.start = _parse_ms(args.start) if args.start else None
    args.end = _parse_ms(args.end) if args.end else None

    if args.cover_template:
        global COVER_TEMPLATE
        COVER_TEMPLATE = args.cover_template

    # output dir
    is_url = args.input.startswith("http")
    slug_seed = re.sub(r".*=", "", args.input.split("/")[-1].split("?")[0])
    work_dir = OUT_ROOT / _slugify(slug_seed)
    work_dir.mkdir(parents=True, exist_ok=True)
    print(f"[init] work_dir = {work_dir}")

    source = acquire(args.input, work_dir)
    transcript = transcribe_step(source, work_dir)
    seg = select_segment_step(transcript, args, work_dir)
    srt = write_segment_srt(transcript, seg, work_dir)
    pkg = generate_titles_step(seg.text, work_dir)
    title = choose_title(pkg, work_dir)
    cover = render_cover(title, work_dir)
    short = render_video_step(source, seg, title, srt, work_dir)

    # description
    (work_dir / "description.txt").write_text(pkg.description, encoding="utf-8")

    if not args.no_publish:
        if is_url:
            original_url = args.input
        else:
            # File locale: prova ad auto-identificare il video originale sul canale.
            print("[find_yt] tentativo di auto-identificazione del video originale...")
            try:
                original_url = find_youtube_url(Path(args.input))
            except Exception as e:
                print(f"[find_yt] errore: {e}")
                original_url = None
            if original_url:
                print(f"[find_yt] OK: {original_url}")
            else:
                print("[find_yt] non identificato. Il link al video originale sarà omesso dalla descrizione.")
        vid_id = publish_step(short, cover, title, pkg, original_url, args.privacy)
        if vid_id:
            (work_dir / "metadata.json").write_text(
                json.dumps({"video_id": vid_id, "url": f"https://youtu.be/{vid_id}",
                            "title": title, "template": COVER_TEMPLATE,
                            "segment": [seg.start_s, seg.end_s]},
                           ensure_ascii=False, indent=2),
                encoding="utf-8"
            )

    print()
    print(f"DONE. Output in: {work_dir}")


if __name__ == "__main__":
    main()
