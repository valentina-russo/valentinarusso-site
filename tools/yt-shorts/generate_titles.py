"""
Genera 10 titoli + 1 descrizione + hashtag a partire dal testo del segmento.

Modalità:
  - default: ANTHROPIC_API_KEY presente in .env → chiama Claude API (Opus 4-7)
  - manuale: stampa il prompt — l'utente lo dà a Claude Code interno (zero costo)
    e poi salva l'output in titles.txt

Il prompt rispetta le writing rules di Valentina (no em-dash, no triplette,
no meta-frasi, no "non X ma Y", max 7 parole per titolo).
"""
from __future__ import annotations

import json
import os
from pathlib import Path
from dataclasses import dataclass


HERE = Path(__file__).resolve().parent
WRITING_RULES_PATH = HERE.parent / "bg5-generator" / "writing_rules.md"


@dataclass
class TitlesPackage:
    titles: list[str]   # 10 titoli max 7 parole
    description: str    # 80-150 caratteri
    hashtags: list[str] # 5-10 hashtag


SYSTEM_PROMPT_HEADER = """Sei un copywriter italiano specialista di social short-form (YouTube Shorts, IG Reels).
Scrivi per Valentina Russo, BG5® Analyst Certificata e consulente Human Design.

Tono: caldo, diretto, concreto. Niente clickbait, niente promesse esagerate.
Le persone che cercano questi video non vogliono "trucchi", vogliono la versione
più precisa di una verità Human Design specifica.

REGOLE WRITING (vincoli assoluti — le seguenti formule sono BANDITE):
- Niente em-dash (—). Mai.
- Niente triplette (3 aggettivi/verbi/sostantivi consecutivi).
- Niente "non X ma Y", "non è X, è Y", "non si tratta di X, si tratta di Y".
- Niente meta-frasi: "è importante", "è cruciale", "è fondamentale", "è essenziale", "scopri come".
- Niente "delve into", "in today's world", "navigare la complessità".
- Niente sentence-starter vuoti: "So...", "Well...", "Look...", "Here's the thing...".
- Niente clickbait emozionale: "Non crederai mai a...", "Il segreto che nessuno ti dice...", "Cosa succede quando...".
"""


def build_user_prompt(segment_text: str) -> str:
    return f"""Trascrizione del segmento video (italiano, 60-120 secondi):

\"\"\"
{segment_text.strip()}
\"\"\"

Produci, in formato JSON valido (e nient'altro):
{{
  "titles": [<10 titoli in italiano, max 7 parole ciascuno, niente clickbait, niente meta-frasi, niente em-dash>],
  "description": "<descrizione di 80-150 caratteri per la caption del Short YouTube. Diretta, una frase. Niente em-dash.>",
  "hashtags": [<5-10 hashtag senza # iniziale, lower-case, mix brand+topic+italiano>]
}}

I 10 titoli devono coprire angolature diverse (domanda, scena concreta, affermazione,
beneficio operativo, dato verificabile, riferimento a Tipo/Strategia/Autorità HD).
Ogni titolo deve poter funzionare da solo come hook nei primi 2-3 secondi del video.
"""


def via_api(segment_text: str, model: str = "claude-opus-4-7") -> TitlesPackage:
    """Call Anthropic API. Requires ANTHROPIC_API_KEY in env."""
    import anthropic

    client = anthropic.Anthropic()
    rules = ""
    if WRITING_RULES_PATH.exists():
        rules = "\n\n" + WRITING_RULES_PATH.read_text(encoding="utf-8")

    sys_prompt = SYSTEM_PROMPT_HEADER + rules
    user_prompt = build_user_prompt(segment_text)

    resp = client.messages.create(
        model=model,
        max_tokens=2000,
        system=sys_prompt,
        messages=[{"role": "user", "content": user_prompt}],
    )

    text = resp.content[0].text.strip()
    # strip markdown fences if present
    if text.startswith("```"):
        text = text.split("```")[1]
        if text.startswith("json"):
            text = text[4:]
    try:
        data = json.loads(text)
    except json.JSONDecodeError as e:
        raise ValueError(f"Risposta AI non è JSON valido: {e}\nTesto ricevuto:\n{text[:500]}") from e
    return TitlesPackage(
        titles=data["titles"][:10],
        description=data["description"][:200],
        hashtags=data["hashtags"][:10],
    )


def manual_prompt(segment_text: str) -> str:
    """
    Ritorna il prompt completo da incollare in Claude Code interno (zero costo).
    L'output JSON va salvato in titles_raw.json e processato da load_manual().
    """
    rules = ""
    if WRITING_RULES_PATH.exists():
        rules = "\n\n" + WRITING_RULES_PATH.read_text(encoding="utf-8")
    return SYSTEM_PROMPT_HEADER + rules + "\n\n" + build_user_prompt(segment_text)


def load_manual(json_text: str) -> TitlesPackage:
    """Parse un blob JSON ricevuto da Claude Code interno."""
    text = json_text.strip()
    if text.startswith("```"):
        text = text.split("```")[1]
        if text.startswith("json"):
            text = text[4:]
    try:
        data = json.loads(text)
    except json.JSONDecodeError as e:
        raise ValueError(f"JSON non valido: {e}\nTesto:\n{text[:500]}") from e
    return TitlesPackage(
        titles=data["titles"][:10],
        description=data["description"][:200],
        hashtags=data["hashtags"][:10],
    )


if __name__ == "__main__":
    import sys
    text = Path(sys.argv[1]).read_text(encoding="utf-8") if len(sys.argv) > 1 else "..."
    if os.environ.get("ANTHROPIC_API_KEY"):
        pkg = via_api(text)
        print(json.dumps({
            "titles": pkg.titles,
            "description": pkg.description,
            "hashtags": pkg.hashtags,
        }, ensure_ascii=False, indent=2))
    else:
        print("=== PROMPT MANUALE (incolla in Claude Code interno) ===")
        print(manual_prompt(text))
