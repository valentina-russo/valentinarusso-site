"""Whisper italiano wrapper."""
from __future__ import annotations

from pathlib import Path
from dataclasses import dataclass


@dataclass
class WordTimestamp:
    word: str
    start: float
    end: float


@dataclass
class Transcript:
    full_text: str
    segments: list[dict]   # {start, end, text}
    words: list[WordTimestamp]


def transcribe(audio_or_video: Path, model_size: str = "medium") -> Transcript:
    """
    Transcribe italian audio with faster-whisper.
    Returns full text, segments, and word-level timestamps.
    """
    from faster_whisper import WhisperModel

    model = WhisperModel(model_size, device="cpu", compute_type="int8")
    segs, info = model.transcribe(
        str(audio_or_video),
        language="it",
        word_timestamps=True,
        beam_size=5,
    )

    segments_out = []
    words_out = []
    full = []
    for s in segs:
        seg_text = (s.text or "").strip()
        full.append(seg_text)
        segments_out.append({
            "start": float(s.start),
            "end": float(s.end),
            "text": seg_text,
        })
        if s.words:
            for w in s.words:
                words_out.append(WordTimestamp(
                    word=w.word.strip(),
                    start=float(w.start),
                    end=float(w.end),
                ))

    return Transcript(
        full_text=" ".join(full),
        segments=segments_out,
        words=words_out,
    )


def to_srt(words: list[WordTimestamp], chars_per_line: int = 28, lines_per_cue: int = 2) -> str:
    """Build a karaoke-friendly SRT from word timestamps. ~5-6 words per cue, max 2 lines."""
    if not words:
        return ""

    cues = []
    current_words: list[WordTimestamp] = []
    current_chars = 0
    current_lines = 1

    for w in words:
        wl = len(w.word) + 1
        if current_chars + wl > chars_per_line:
            current_lines += 1
            current_chars = wl
            if current_lines > lines_per_cue:
                cues.append(current_words)
                current_words = [w]
                current_chars = wl
                current_lines = 1
                continue
        else:
            current_chars += wl
        current_words.append(w)

    if current_words:
        cues.append(current_words)

    out = []
    for i, cue in enumerate(cues, 1):
        start = cue[0].start
        end = cue[-1].end
        text = " ".join(w.word for w in cue).strip()
        out.append(f"{i}")
        out.append(f"{_srt_ts(start)} --> {_srt_ts(end)}")
        out.append(text)
        out.append("")
    return "\n".join(out)


def _srt_ts(seconds: float) -> str:
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = seconds % 60
    return f"{h:02d}:{m:02d}:{s:06.3f}".replace(".", ",")


def _ass_ts(seconds: float) -> str:
    """ASS timestamp: h:mm:ss.cs (centiseconds, NO zero-padding on hours)."""
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = int(seconds % 60)
    cs = int(round((seconds % 1) * 100))
    return f"{h}:{m:02d}:{s:02d}.{cs:02d}"


def to_ass(
    words: list[WordTimestamp],
    chars_per_line: int = 28,
    lines_per_cue: int = 2,
    play_res_x: int = 1080,
    play_res_y: int = 1920,
    font_name: str = "Outfit Bold",
    font_size: int = 52,
    margin_v: int = 200,
    margin_lr: int = 60,
) -> str:
    """
    Build an ASS subtitle file with explicit PlayResX/Y so libass never has to
    guess the coordinate system.  All values (FontSize, MarginV) are in pixels
    relative to the play_res_y reference frame — no hidden scaling.

    Default target: 1080x1920 (YouTube Shorts vertical).
    """
    if not words:
        return ""

    header = (
        "[Script Info]\n"
        "ScriptType: v4.00+\n"
        f"PlayResX: {play_res_x}\n"
        f"PlayResY: {play_res_y}\n"
        "WrapStyle: 1\n"
        "ScaledBorderAndShadow: yes\n"
        "\n"
        "[V4+ Styles]\n"
        "Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, "
        "OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, "
        "ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, "
        "Alignment, MarginL, MarginR, MarginV, Encoding\n"
        f"Style: Default,{font_name},{font_size},"
        "&H00FFFFFF,&H000000FF,&H00000000,&H64000000,"
        f"-1,0,0,0,100,100,0,0,1,3,1,2,{margin_lr},{margin_lr},{margin_v},1\n"
        "\n"
        "[Events]\n"
        "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n"
    )

    # Build cues using same grouping logic as to_srt
    cues: list[list[WordTimestamp]] = []
    current_words: list[WordTimestamp] = []
    current_chars = 0
    current_lines = 1

    for w in words:
        wl = len(w.word) + 1
        if current_chars + wl > chars_per_line:
            current_lines += 1
            current_chars = wl
            if current_lines > lines_per_cue:
                cues.append(current_words)
                current_words = [w]
                current_chars = wl
                current_lines = 1
                continue
        else:
            current_chars += wl
        current_words.append(w)

    if current_words:
        cues.append(current_words)

    events = []
    for cue in cues:
        t0 = _ass_ts(cue[0].start)
        t1 = _ass_ts(cue[-1].end)
        text = " ".join(w.word for w in cue).strip()
        events.append(f"Dialogue: 0,{t0},{t1},Default,,0,0,0,,{text}")

    return header + "\n".join(events) + "\n"


def to_ass_karaoke(
    words: list[WordTimestamp],
    chars_per_line: int = 20,
    lines_per_cue: int = 2,
    play_res_x: int = 1080,
    play_res_y: int = 1920,
    font_name: str = "Outfit Bold",
    font_size: int = 100,
    margin_v: int = 280,
    margin_lr: int = 80,
    primary_color: str = "&H00FFFFFF",    # white  — inactive words
    highlight_color: str = "&H0000D7FF",  # gold #FFD700 — active word
) -> str:
    """
    ASS subtitles with word-highlight (karaoke) style.

    Each caption group stays on screen for the combined duration of all its
    words.  While each word is being spoken it turns `highlight_color` (gold);
    the rest of the line stays in `primary_color` (white).

    Implemented with inline ASS \\c tags — no extra dependencies, no double
    encode.  libass is already compiled into ffmpeg.
    """
    if not words:
        return ""

    header = (
        "[Script Info]\n"
        "ScriptType: v4.00+\n"
        f"PlayResX: {play_res_x}\n"
        f"PlayResY: {play_res_y}\n"
        "WrapStyle: 2\n"          # no auto-wrap — we handle \N manually
        "ScaledBorderAndShadow: yes\n"
        "\n"
        "[V4+ Styles]\n"
        "Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, "
        "OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, "
        "ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, "
        "Alignment, MarginL, MarginR, MarginV, Encoding\n"
        f"Style: Default,{font_name},{font_size},"
        f"{primary_color},&H000000FF,&H00000000,&H78000000,"
        f"-1,0,0,0,100,100,0,0,1,4,2,2,{margin_lr},{margin_lr},{margin_v},1\n"
        "\n"
        "[Events]\n"
        "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n"
    )

    # ── build cues (same grouping as to_ass) ────────────────────────────────
    cues: list[list[WordTimestamp]] = []
    current_words: list[WordTimestamp] = []
    current_chars = 0
    current_lines = 1

    for w in words:
        wl = len(w.word) + 1
        if current_chars + wl > chars_per_line:
            current_lines += 1
            current_chars = wl
            if current_lines > lines_per_cue:
                cues.append(current_words)
                current_words = [w]
                current_chars = wl
                current_lines = 1
                continue
        else:
            current_chars += wl
        current_words.append(w)

    if current_words:
        cues.append(current_words)

    events: list[str] = []

    for cue in cues:
        if not cue:
            continue

        # Split cue words into lines for \N placement
        lines: list[list[WordTimestamp]] = [[]]
        lchars = 0
        lidx = 0
        for w in cue:
            wl = len(w.word) + 1
            if lchars + wl > chars_per_line and lidx < lines_per_cue - 1:
                lidx += 1
                lines.append([])
                lchars = 0
            lines[lidx].append(w)
            lchars += wl

        # One dialogue event per word in the cue
        for word_pos, active_word in enumerate(cue):
            t0 = _ass_ts(active_word.start)
            # event ends when next word starts (or at active_word.end for last)
            t1 = _ass_ts(
                cue[word_pos + 1].start if word_pos + 1 < len(cue)
                else active_word.end
            )

            # Build text: active word in highlight_color, rest in primary_color
            # {\c&Hcolor&} changes color; {\r} resets to style default (white)
            line_texts: list[str] = []
            for line in lines:
                parts: list[str] = []
                for w in line:
                    txt = w.word.strip()
                    if w is active_word:
                        parts.append(
                            f"{{\\c{highlight_color}&}}{txt}{{\\r}}"
                        )
                    else:
                        parts.append(txt)
                line_texts.append(" ".join(parts))

            full_text = "\\N".join(line_texts)
            events.append(
                f"Dialogue: 0,{t0},{t1},Default,,0,0,0,,{full_text}"
            )

    return header + "\n".join(events) + "\n"


if __name__ == "__main__":
    import sys
    p = Path(sys.argv[1])
    t = transcribe(p)
    print("FULL TEXT:")
    print(t.full_text[:500])
    print()
    print(f"Segments: {len(t.segments)}")
    print(f"Words: {len(t.words)}")
    print()
    print("SRT preview:")
    print(to_srt(t.words)[:500])
