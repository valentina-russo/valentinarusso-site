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
            sys.executable, "-m", "yt_dlp",
            input_str,
            "-f", "bestvideo[height<=1080]+bestaudio/best[height<=1080]",
            "--merge-output-format", "mp4",
            "-o", str(src),
        ]
        print("[acquire] yt-dlp", " ".join(cmd[3:]))
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
    """
    Build subtitles for the chosen segment, rebased to t=0.

    Writes:
      - subtitles.srt          (human-readable, for debug)
      - subtitles_karaoke.ass  (used by ffmpeg — word-highlight karaoke,
                                explicit PlayResY=1920, no scaling guesses)

    Returns path to the .ass karaoke file.
    """
    from transcribe import to_srt, to_ass_karaoke, WordTimestamp
    seg_words = []
    for w in transcript_full.words:
        if w.start >= seg.start_s and w.end <= seg.end_s:
            seg_words.append(WordTimestamp(
                word=w.word,
                start=w.start - seg.start_s,
                end=w.end - seg.start_s,
            ))
    # SRT for readability
    (work_dir / "subtitles.srt").write_text(to_srt(seg_words), encoding="utf-8")
    # ASS karaoke for rendering (word-highlight, unambiguous coordinate system)
    ass_content = to_ass_karaoke(seg_words, play_res_x=1080, play_res_y=1920,
                                 font_size=100, margin_v=280,
                                 primary_color="&H00FFFFFF",
                                 highlight_color="&H0000D7FF")
    p = work_dir / "subtitles_karaoke.ass"
    p.write_text(ass_content, encoding="utf-8")
    return p


def generate_titles_step(seg_text: str, work_dir: Path):
    # Se titles_raw.json è già stato scritto (es. da Claude Code interno), usalo.
    titles_raw = work_dir / "titles_raw.json"
    if titles_raw.exists():
        print(f"[titles] carico da cache: {titles_raw}")
        return load_manual(titles_raw.read_text(encoding="utf-8"))

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
        raw = titles_raw.read_text(encoding="utf-8")
        pkg = load_manual(raw)
    return pkg


def choose_title(pkg, work_dir: Path, title_index: int | None = None) -> str:
    print()
    print("=== 10 TITOLI ===")
    for i, t in enumerate(pkg.titles, 1):
        print(f"  {i}. {t}")
    if title_index is not None:
        idx = max(0, min(title_index - 1, len(pkg.titles) - 1))
        chosen = pkg.titles[idx]
        print(f"[auto] titolo scelto: {idx + 1}. {chosen}")
    else:
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
    """Render the clip (crop, title, karaoke subtitles, watermark)."""
    from render_video import render
    out = work_dir / "short.mp4"
    render(
        source_video=source,
        out_path=out,
        start_s=seg.start_s,
        end_s=seg.end_s,
        title=title,
        srt_path=srt,           # ASS karaoke subtitles
        watermark_text=WATERMARK,
    )
    return out


def captacity_step(no_subs_path: Path, transcript_full, seg, work_dir: Path) -> Path:
    """
    Overlay word-highlight captions via captacity.
    Uses pre-computed word timestamps — no re-transcription.
    """
    import captacity as cap
    from render_video import HERE as _RV_HERE

    OUTFIT_BOLD = _RV_HERE.parent / "bg5-generator" / "fonts" / "Outfit-Bold.ttf"

    # Convert faster-whisper words to captacity's segment format.
    # captacity expects words with a LEADING SPACE (openai-whisper convention).
    # Timestamps must be rebased to t=0 relative to the clip start.
    seg_words = [
        w for w in transcript_full.words
        if w.start >= seg.start_s and w.end <= seg.end_s
    ]
    captacity_segments = [{
        "start": 0.0,
        "end": seg.end_s - seg.start_s,
        "words": [
            {
                "word": " " + w.word.lstrip(),
                "start": round(w.start - seg.start_s, 3),
                "end":   round(w.end   - seg.start_s, 3),
            }
            for w in seg_words
        ],
    }]

    out = work_dir / "short.mp4"
    print("[captacity] aggiunta didascalie word-highlight...")
    cap.add_captions(
        video_file=str(no_subs_path),
        output_file=str(out),
        font=str(OUTFIT_BOLD),
        font_size=110,
        font_color="white",
        stroke_width=3,
        stroke_color="black",
        highlight_current_word=True,
        word_highlight_color="#FFD700",   # oro
        line_count=2,
        shadow_strength=1.0,
        shadow_blur=0.1,
        padding=60,
        segments=captacity_segments,
        print_info=True,
    )
    print(f"[captacity] done: {out}")
    return out


def publish_step(short_path: Path, cover_path: Path, title: str, pkg, original_url: str | None,
                 privacy: str, auto_yes: bool = False) -> str | None:
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
    if auto_yes:
        print("[auto] pubblicazione confermata.")
        confirm = "y"
    else:
        confirm = input("Pubblicare ora? [y/N]: ").strip().lower()
    if confirm != "y":
        print("[publish] annullato. File pronti in:", short_path.parent)
        return None

    from youtube_publisher import upload, post_comment, add_to_playlist_by_name
    video_id = upload(
        video_path=short_path,
        title=title,
        description=description,
        tags=pkg.hashtags,
        privacy=privacy,
        cover_path=cover_path,
    )

    # Aggiungi alla playlist "Shorts" (deve esistere già sul canale).
    # Nome override: env var YOUTUBE_PLAYLIST_NAME (default "Shorts").
    # Disattiva con YOUTUBE_PLAYLIST_NAME="" (vuoto).
    playlist_name = os.environ.get("YOUTUBE_PLAYLIST_NAME", "Shorts")
    if playlist_name:
        try:
            add_to_playlist_by_name(video_id, playlist_name)
        except Exception as e:
            print(f"[playlist] errore non bloccante: {e}")

    # Commento con link al video originale (cliccabile su mobile e desktop,
    # a differenza dei link nella descrizione degli Short).
    if original_url:
        post_comment(video_id, f"📺 Video completo: {original_url}")

    # Link Studio per le due azioni manuali (30 sec in totale):
    #   1. Pinna il commento
    #   2. Aggiungi la card "video correlato"
    studio_url = f"https://studio.youtube.com/video/{video_id}/edit"
    short_url = f"https://youtu.be/{video_id}"
    print()
    # Video correlato: automazione Playwright di YouTube Studio
    if original_url:
        try:
            from add_related_video import add_related_video as _add_rv
            ok = _add_rv(video_id, original_url)
            if ok:
                print(f"[studio] video correlato impostato automaticamente.")
            else:
                print(f"[studio] automazione non riuscita. Aggiungi manualmente:")
                print(f"  {studio_url}")
                print("  Editor -> 'Aggiungi' -> 'Video correlato'")
        except Exception as e:
            print(f"[studio] Playwright non disponibile ({e}). Aggiungi manualmente:")
            print(f"  {studio_url}")

    print()
    print("=== POST-UPLOAD ===")
    print(f"  Short:   {short_url}")
    print(f"  Studio:  {studio_url}")
    print("  Ricorda: pinna il commento con il link dal tab 'Commenti' in Studio")

    return video_id


def main():
    p = argparse.ArgumentParser()
    p.add_argument("input", help="URL YouTube o path locale al video")
    p.add_argument("--start", type=str, help="MM:SS (override AI segment)")
    p.add_argument("--end", type=str, help="MM:SS (override AI segment)")
    p.add_argument("--cover-template", type=str, help="Override COVER_TEMPLATE env")
    p.add_argument("--no-publish", action="store_true", help="Stop dopo render, no upload")
    p.add_argument("--privacy", type=str, default=DEFAULT_PRIVACY)
    p.add_argument("--title-index", type=int, default=None,
                   help="Scegli titolo N (1-10) senza input interattivo")
    p.add_argument("--yes", action="store_true",
                   help="Conferma pubblicazione automaticamente (no input interattivo)")
    p.add_argument("--resume", action="store_true",
                   help="Salta acquisizione+trascrizione se transcript_full.json esiste già in work_dir")
    p.add_argument("--segment-only", action="store_true",
                   help="Ferma dopo acquire+trascrizione+selezione segmento (Phase 1). "
                        "Salva segment_info.json. Poi genera titoli esternamente e riprendi con --resume.")
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

    transcript_cache = work_dir / "transcript_full.json"
    source_cache = work_dir / "source.mp4"

    if args.resume and transcript_cache.exists() and source_cache.exists():
        print("[resume] transcript_full.json trovato, salto acquisizione+trascrizione.")
        from transcribe import WordTimestamp
        raw = json.loads(transcript_cache.read_text(encoding="utf-8"))
        # Reconstruct transcript from cache.
        # segments: plain dicts (select_segment uses s["start"] dict-style access).
        # words: WordTimestamp objects (write_segment_srt uses w.start dot access).
        class _T:
            def __init__(self, r):
                self.full_text = r["full_text"]
                self.segments = r["segments"]  # already list[dict] from JSON
                self.words = [WordTimestamp(word=w["w"], start=w["s"], end=w["e"])
                              for w in r["words"]]
        transcript = _T(raw)
        source = source_cache
    else:
        source = acquire(args.input, work_dir)
        transcript = transcribe_step(source, work_dir)

    seg = select_segment_step(transcript, args, work_dir)
    srt = write_segment_srt(transcript, seg, work_dir)

    if args.segment_only:
        # Salva info segmento per Phase 2 esterna (generazione titoli in Claude Code).
        import dataclasses
        (work_dir / "segment_info.json").write_text(
            json.dumps({"start_s": seg.start_s, "end_s": seg.end_s,
                        "text": seg.text, "score": seg.score,
                        "srt_path": str(srt)},
                       ensure_ascii=False, indent=2),
            encoding="utf-8"
        )
        print(f"[segment-only] Segmento salvato: {work_dir / 'segment_info.json'}")
        print(f"  Segmento: {seg.start_s:.1f}s -> {seg.end_s:.1f}s ({seg.end_s - seg.start_s:.1f}s)")
        print(f"  Testo ({len(seg.text)} chars): {seg.text[:200]}...")
        print()
        print("PHASE 1 COMPLETA. Prossimi step:")
        print(f"  1. Genera titoli da segment_info.json → scrivi {work_dir}/titles_raw.json")
        print(f"  2. Riprendi: python repurpose.py \"{args.input}\" --resume --title-index N --yes")
        return

    pkg = generate_titles_step(seg.text, work_dir)
    title = choose_title(pkg, work_dir, title_index=args.title_index)
    cover = render_cover(title, work_dir)
    short_base = render_video_step(source, seg, title, srt, work_dir)  # → short.mp4

    # Prepend cover as 2-second branded intro — YouTube Shorts non accetta
    # thumbnail custom via API né web desktop, solo app mobile. Bruciare la
    # cover nel video garantisce che sia sempre visibile.
    # short_base = short.mp4 → rinomina a short_no_subs.mp4 (già usato come alias ok)
    # poi scrivi il finale su short.mp4 tramite concat.
    short_no_cover = work_dir / "short_no_cover.mp4"
    short_base.rename(short_no_cover)
    short = work_dir / "short.mp4"
    from render_video import prepend_cover
    prepend_cover(cover, short_no_cover, short)

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
        vid_id = publish_step(short, cover, title, pkg, original_url, args.privacy,
                              auto_yes=args.yes)
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
