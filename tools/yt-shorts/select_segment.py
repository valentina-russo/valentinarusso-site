"""
Selezione AI-driven del segmento (1-2 minuti) più punchy del video.

Strategia v1 (semplice, no LLM call):
  - Leggi tutti i segmenti Whisper
  - Identifica blocchi narrativi: cerca segmenti densi (più parole/secondo) e
    segmenti che iniziano con domande ("come", "perché", "cosa", ...) o
    affermazioni dirette
  - Score = densità verbale + bonus hook + bonus payoff (ultimo segmento ha
    chiusura concreta: numero, gesto, scelta)
  - Ritorna il blocco di durata 60-120s con score più alto

Strategia v2 (futura):
  - Chiama Claude API per scegliere il segmento, fornendo trascrizione completa
  - Più accurato ma costa
"""
from __future__ import annotations

from dataclasses import dataclass


@dataclass
class SelectedSegment:
    start_s: float
    end_s: float
    text: str
    score: float


HOOK_WORDS = {
    "come", "perché", "perche", "cosa", "quando", "dove", "chi",
    "il segreto", "la verità", "la differenza", "non è",
    "ti racconto", "una volta", "un giorno",
}


def _starts_with_hook(text: str) -> bool:
    t = text.lower().strip().lstrip("«\"'.")
    for h in HOOK_WORDS:
        if t.startswith(h):
            return True
    return False


def _has_payoff(text: str) -> bool:
    """Last words: number, concrete noun, decision verb, sensory image?"""
    t = text.lower().strip().rstrip(".!?,;:\"'»")
    last_words = t.split()[-6:]
    payoff_signals = ["scegli", "decidi", "fai", "prova", "smetti",
                      "cambia", "ferma", "ora", "subito", "ogni",
                      "minuti", "ore", "giorni", "anni"]
    return any(any(s in w for s in payoff_signals) for w in last_words)


def select_segment(segments: list[dict], target_min: float = 60.0,
                   target_max: float = 120.0) -> SelectedSegment:
    """
    Choose the best segment of length in [target_min, target_max].
    Uses cumulative scoring over consecutive Whisper segments.
    """
    if not segments:
        raise ValueError("No segments")

    # Build scored windows
    n = len(segments)
    best: SelectedSegment | None = None

    for i in range(n):
        start_s = segments[i]["start"]
        # find j s.t. duration in target range
        for j in range(i, n):
            end_s = segments[j]["end"]
            dur = end_s - start_s
            if dur < target_min:
                continue
            if dur > target_max:
                break
            # score
            text = " ".join(s["text"] for s in segments[i:j + 1])
            n_words = len(text.split())
            density = n_words / max(dur, 1.0)  # words/sec
            score = density
            if _starts_with_hook(segments[i]["text"]):
                score += 1.5
            if _has_payoff(segments[j]["text"]):
                score += 1.0
            # bonus on duration sweet spot 75-100s
            if 75 <= dur <= 100:
                score += 0.5

            if best is None or score > best.score:
                best = SelectedSegment(start_s=start_s, end_s=end_s,
                                       text=text, score=score)
            # try only the smallest window meeting target (move on)
            break

    if best is None:
        # fallback: just take first 90s
        first = segments[0]
        last = segments[-1]
        end_target = min(first["start"] + 90.0, last["end"])
        text = ""
        for s in segments:
            if s["start"] >= end_target:
                break
            text += s["text"] + " "
        best = SelectedSegment(start_s=first["start"], end_s=end_target,
                               text=text.strip(), score=0.0)

    return best


if __name__ == "__main__":
    import json
    import sys
    segs = json.load(open(sys.argv[1], encoding="utf-8"))
    seg = select_segment(segs)
    print(f"Start: {seg.start_s:.1f}s")
    print(f"End:   {seg.end_s:.1f}s")
    print(f"Dur:   {seg.end_s - seg.start_s:.1f}s")
    print(f"Score: {seg.score:.2f}")
    print(f"Text preview: {seg.text[:200]}")
