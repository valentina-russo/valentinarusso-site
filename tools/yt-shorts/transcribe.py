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
