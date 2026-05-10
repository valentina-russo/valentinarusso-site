#!/usr/bin/env python3
"""
generate_blueprint.py — Generic BG5 Business Blueprint generator.

Usage:
    python generate_blueprint.py \\
        --chart "path/to/chart.json" \\
        --customer "Marco Bianchi" \\
        --tier Completo \\
        --out "path/to/output.pdf"

The chart JSON is produced by calc_chart_ephem.py:
    python calc_chart_ephem.py "Marco" "1983-01-19" "01:45" 1.0 "Vicenza, Italia"

This script:
  1. Loads the chart JSON
  2. Generates 7 chapters × 7 pages via Claude API (Opus)
  3. Saves chapter .md files to a temp directory
  4. Calls build_blueprint_v5.build_pdf() for the final PDF

Cost estimate: ~€4-6 per blueprint (Opus 4.6, 7 chapters × ~2000 words each).
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import time
from pathlib import Path

HERE = Path(__file__).parent

# ─── .env loader ─────────────────────────────────────────────────────────────

_ALLOWED_ENV_KEYS = {"ANTHROPIC_API_KEY"}

def _load_env(env_path: Path):
    if not env_path.exists():
        return
    for line in env_path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        k, v = k.strip(), v.strip().strip('"').strip("'")
        if k in _ALLOWED_ENV_KEYS and v:   # allowlist: never set PATH/PYTHONPATH etc.
            os.environ[k] = v

_load_env(HERE / ".env")

try:
    import anthropic
except ImportError:
    print("ERROR: pip install anthropic")
    sys.exit(1)

try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

API_KEY = os.environ.get("ANTHROPIC_API_KEY", "")
if not API_KEY:
    print("ERROR: ANTHROPIC_API_KEY not found in .env")
    sys.exit(1)

MODEL = "claude-opus-4-7"
MAX_TOKENS = 8000    # ~3500 words / chapter — generous headroom so AI never hits the cap mid-sentence (was 4500, caused truncation)

# ─── WRITING RULES ───────────────────────────────────────────────────────────

def _load_writing_rules() -> str:
    p = HERE / "writing_rules.md"
    return p.read_text(encoding="utf-8") if p.exists() else ""

# ─── GATE NAMES LOOKUP ────────────────────────────────────────────────────────

def _load_gates() -> dict:
    p = HERE / "data" / "gates.json"
    return json.loads(p.read_text(encoding="utf-8")) if p.exists() else {}

def _gate_nome(gate: int, gates_data: dict) -> str:
    entry = gates_data.get(str(gate))
    return entry["nome"] if entry else f"Porta {gate}"

# ─── CHART FIELD SANITIZER ───────────────────────────────────────────────────

_MAX_FIELD_LEN = 200

def _safe_field(value, fallback: str = "") -> str:
    """Strip control characters and limit length for chart fields used in prompts.
    Prevents accidental or malicious prompt injection via crafted chart JSON.
    """
    if not isinstance(value, str):
        return fallback
    cleaned = re.sub(r"[\x00-\x1f\x7f]", " ", value).strip()
    return cleaned[:_MAX_FIELD_LEN] if cleaned else fallback


# ─── CHART HELPERS ────────────────────────────────────────────────────────────

def _cross_gate_numbers(chart: dict) -> tuple[int, int, int, int]:
    activations = chart.get("activations", [])
    if len(activations) >= 2:
        p_sol = int(activations[0][1].split(".")[0])
        p_ter = int(activations[1][1].split(".")[0])
        d_sol = int(activations[0][2].split(".")[0])
        d_ter = int(activations[1][2].split(".")[0])
        return p_sol, p_ter, d_sol, d_ter
    return 0, 0, 0, 0

def _cross_full_name(life_theme: str) -> str:
    if not life_theme:
        return "Croce d'Incarnazione"
    parts = [p.strip() for p in life_theme.split(",")]
    cross_part = parts[0]
    quarter = parts[1] if len(parts) > 1 else ""
    preps = {"Civilizzazione": "della", "Iniziazione": "dell'", "Dualismo": "del", "Mutazione": "della"}
    prep = preps.get(quarter, "del")
    return f"Croce {cross_part} {prep} {quarter}" if quarter else f"Croce {cross_part}"

def _channels_block(chart: dict) -> str:
    channels = chart.get("channels", [])
    if not channels:
        return "Nessun canale definito."
    return "\n".join(f"  · {c['name']}  {c['title']}  ({c['centers']})" for c in channels)

def _profile_lines(chart: dict) -> tuple[str, str, str, str]:
    """Returns (line1, line2, name1, name2)."""
    profile = chart.get("profile", "")
    profile_name = chart.get("profile_name", "")
    parts = profile.split("/") if "/" in profile else ["1", "1"]
    l1, l2 = parts[0].strip(), parts[1].strip() if len(parts) > 1 else ""
    nparts = [p.strip() for p in profile_name.split("/")] if "/" in profile_name else []
    n1 = nparts[0] if nparts else f"Linea {l1}"
    n2 = nparts[1] if len(nparts) > 1 else f"Linea {l2}"
    return l1, l2, n1, n2


# ─── SYSTEM PROMPT ────────────────────────────────────────────────────────────

def _build_system_prompt(writing_rules: str) -> str:
    return f"""Sei Valentina Russo, consulente Human Design certificata. Scrivi in italiano, in seconda persona singolare ("tu", "ti", "il tuo"), rivolgendoti direttamente al cliente. Tono: caldo, diretto, concreto. Valentina non è psicologa.

Lessico: usa terminologia Human Design pura (Tipo, Strategia, Autorità, Profilo, Definizione, Centri, Canali, Porte, Croce d'Incarnazione, Firma, Non-Sé). NON usare terminologia BG5 business ("Guida", "Costruttore", "Salvadanaio") né Gene Keys.

FORMATO OUTPUT OBBLIGATORIO:
Ogni capitolo contiene esattamente 7 pagine. Ogni pagina inizia con:
## Pag. N — Titolo della pagina

Separatore tra pagine: riga con tre trattini (---) su riga vuota.
Lunghezza per pagina: ~280 parole. Paragrafi discorsivi, niente elenchi puntati.

REGOLE DI SCRITTURA ITALIAN (vincoli assoluti — rileggiti prima di rispondere):

{writing_rules}

REGOLA CRITICA — HD PURO:
NON usare: Shadow/Gift/Siddhi, frequenze Gene Keys, Hologenetic Profile, terminologia Richard Rudd.
USA SOLO: Tipo, Strategia, Autorità, Profilo, Definizione, Centri, Canali, Porte, Croce d'Incarnazione, Firma, Non-Sé, linee 1-6.
"""

# ─── CHAPTER PROMPTS ─────────────────────────────────────────────────────────

def _chart_block(chart: dict) -> str:
    name     = _safe_field(chart.get("customer_name"), "il cliente")
    tipo     = _safe_field(chart.get("career_type"), "Proiettore")
    strategy = _safe_field(chart.get("strategy"), "")
    authority = _safe_field(chart.get("authority"), "")
    profile  = _safe_field(chart.get("profile"), "")
    prof_name = _safe_field(chart.get("profile_name"), "")
    definition = _safe_field(chart.get("definition"), "")
    signature = _safe_field(chart.get("signature"), "")
    non_self = _safe_field(chart.get("non_self"), "")
    defined   = ", ".join(_safe_field(c) for c in chart.get("defined_centers", []))
    undefined = ", ".join(_safe_field(c) for c in chart.get("undefined_centers", []))
    return f"""DATI DELLA CARTA — {name}:
Tipo: {tipo} · Strategia: {strategy}
Autorità: {authority} · Profilo: {profile} ({prof_name})
Definizione: {definition} · Croce: {_cross_full_name(chart.get("life_theme", ""))}
Firma: {signature} · Non-Sé: {non_self}
Centri definiti: {defined}
Centri non definiti: {undefined}
Canali:
{_channels_block(chart)}
"""

def _chapter_prompt(chapter_num: int, chart: dict, gates_data: dict) -> str:
    name = chart.get("customer_name", "il cliente")
    tipo = chart.get("career_type", "Proiettore")
    strategy = chart.get("strategy", "")
    authority = chart.get("authority", "")
    profile = chart.get("profile", "")
    definition = chart.get("definition", "")
    signature = chart.get("signature", "")
    non_self = chart.get("non_self", "")
    channels = chart.get("channels", [])
    defined_centers = chart.get("defined_centers", [])
    undefined_centers = chart.get("undefined_centers", [])
    l1, l2, n1, n2 = _profile_lines(chart)
    life_theme = chart.get("life_theme", "")
    cross_name = _cross_full_name(life_theme)
    p_sol, p_ter, d_sol, d_ter = _cross_gate_numbers(chart)
    g_p_sol = _gate_nome(p_sol, gates_data)
    g_p_ter = _gate_nome(p_ter, gates_data)
    g_d_sol = _gate_nome(d_sol, gates_data)
    g_d_ter = _gate_nome(d_ter, gates_data)

    cb = _chart_block(chart)

    if chapter_num == 1:
        ch_desc = f"""Il canale principale è {channels[0]["name"]} {channels[0]["title"]}""" if channels else "nessun canale definito"
        return f"""{cb}
Scrivi il CAPITOLO 1 — "Chi sei davvero" per {name}.
Struttura: 7 pagine, ~280 parole ciascuna.

Pag. 1 — Apertura
Un'apertura calda e personale. Cos'è questo libretto: una mappa del funzionamento energetico di {name}, non un oroscopo né un test della personalità. Come leggerlo (un capitolo alla volta, senza fretta). Non scrivere il titolo del capitolo, entra subito nel contenuto.

Pag. 2 — Il Glossario
I termini tecnici di questa carta specifica, spiegati in modo semplice per {name}:
Tipo ({tipo}), Strategia ({strategy}), Autorità ({authority}), Profilo ({profile} — {n1} e {n2}), Definizione ({definition}), il canale ({ch_desc}), Centri, Croce d'Incarnazione, Non-Sé. Una o due righe per termine.
IMPORTANTE: usa il formato "**Termine**: definizione" (due punti, NON trattino em). Non usare mai "—" come separatore tra termine e definizione.

Pag. 3 — Il tuo Tipo
Cos'è il tipo {tipo}: la meccanica energetica concreta. Come funziona questa energia. Perché è diversa dagli altri tipi. Cosa succede quando si vive in modo congruente con questa meccanica.

Pag. 4 — La tua Strategia
La strategia "{strategy}" spiegata in modo operativo. Come si applica nella vita quotidiana di {name}. Esempi concreti: una mattina, una telefonata, una decisione lavorativa.

Pag. 5 — I segnali del corpo
La Firma "{signature}": come si sente nel corpo, quando arriva, come riconoscerla. Il Non-Sé "{non_self}": come si sente, quando compare, cosa dice. Due barocche interne che guidano in modo più affidabile della mente.

Pag. 6 — Scena
Una scena concreta e realistica: una giornata di {name} nella sua strategia vs una fuori strategia. La differenza si sente nel corpo, non nella performance. Usa dettagli specifici.

Pag. 7 — Anteprima Profilo e Croce
Un paragrafo sul profilo {profile} ({n1}/{n2}): il carattere di base, come influenza il modo di stare nel mondo. Un paragrafo sulla {cross_name}: il tema di vita a cui torna il capitolo 6. Non anticipare troppo — questo è solo un primo orientamento."""

    elif chapter_num == 2:
        return f"""{cb}
Scrivi il CAPITOLO 2 — "Come prendi le decisioni" per {name}.
Struttura: 7 pagine, ~280 parole ciascuna.

Pag. 1 — Apertura
Cos'è l'Autorità nel sistema Human Design: il meccanismo corporeo (non mentale) con cui le decisioni giuste maturano. Perché la mente è un ottimo strumento per analizzare ma un pessimo strumento per decidere.

Pag. 2 — Il tuo meccanismo
L'autorità specifica di {name}: {authority}. Come funziona nella meccanica di questa carta. Cosa lo distingue dalle altre autorità. Un'immagine concreta di come si sente quando è attiva.

Pag. 3 — Come usarla nel concreto
Passi pratici: come si usa questa autorità nella vita quotidiana di {name}. Come si presenta a una proposta, una scelta professionale, un cambiamento di direzione. Il tempo necessario, i segnali corporei, la differenza tra chiarezza e affanno.

Pag. 4 — I testimoni / L'ambiente decisionale
Per autorità esterne (Mentale/Ambientale): chi sono i testimoni giusti, come sceglierli, cosa fanno davvero (ascoltano, non risolvono). Per autorità interne (Sacrale/Plesso Solare/Milza): come creare le condizioni corporee per sentire il segnale. Adatta al tipo di autorità di {name}.

Pag. 5 — Quando la decisione è sbagliata
Come riconoscere che si sta decidendo nel Non-Sé: i pattern ricorrenti, il segnale nel corpo che viene ignorato, le circostanze che portano fuori rotta. Non una diagnosi, una mappa di orientamento.

Pag. 6 — Scena
Una scena concreta: {name} di fronte a una decisione difficile, una volta rispettando l'autorità e una volta ignorandola. Il dettaglio fisico conta più della morale.

Pag. 7 — Il protocollo operativo
Un protocollo semplice e applicabile: dalla proposta alla decisione, i passaggi concreti che {name} può ripetere ogni volta. Tre o quattro gesti in ordine, ciascuno verificabile."""

    elif chapter_num == 3:
        ch_names = ", ".join(f"{c['name']} {c['title']}" for c in channels) if channels else "nessun canale definito"
        ch_centers = "; ".join(c["centers"] for c in channels) if channels else ""
        return f"""{cb}
Scrivi il CAPITOLO 3 — "Cosa porti nel mondo" per {name}.
Struttura: 7 pagine, ~280 parole ciascuna.
Canali attivi: {ch_names}
Centri collegati: {ch_centers}

Pag. 1 — Apertura
Cos'è un canale nel Human Design: il ponte energetico tra due centri, sempre acceso, un modo di funzionare costante. La differenza tra avere un canale definito e non averlo. Perché il canale dice cosa si "porta" nel mondo.

Pag. 2 — Il tuo canale
{"Il canale " + channels[0]["name"] + " " + channels[0]["title"] + " (" + channels[0]["centers"] + ")" if channels else "Il tuo quadro energetico"}: cosa porta questa connessione specifica. La meccanica del canale: come funziona, cosa produce, quale dono porta nell'interazione con gli altri.

Pag. 3 — Come si manifesta nel lavoro
Come questo canale (o questa assenza di canali) si mostra nella vita professionale di {name}. Situazioni concrete in cui si vede. Perché le persone cercano questo in {name} senza saper nominare cosa cercano.

Pag. 4 — Come si manifesta nelle relazioni
Come il canale funziona nei legami: con i clienti, con i colleghi, con i partner. Cosa offre {name} senza sforzo, cosa non può offrire (e non deve cercare di farlo).

Pag. 5 — Il lato d'ombra
Quando il canale lavora male: la distorsione, l'eccesso, il costo quando si abusa o si nega questa energia. I segnali che la meccanica è fuori equilibrio.

Pag. 6 — Scena
Una scena concreta: {name} nel suo canale al lavoro. Qualcosa di specifico che succede, qualcuno che risponde, un momento in cui si sente la differenza tra essere nel proprio dono e fuori.

Pag. 7 — Sintesi operativa
Come orientare il proprio lavoro, le proprie offerte, le proprie collaborazioni a partire da questa meccanica. Tre o quattro indicazioni pratiche, non principi astratti."""

    elif chapter_num == 4:
        n_def = len(defined_centers)
        n_undef = len(undefined_centers)
        return f"""{cb}
Scrivi il CAPITOLO 4 — "Come sei costruita" per {name}.
Struttura: 7 pagine, ~280 parole ciascuna.
Centri definiti ({n_def}): {", ".join(defined_centers)}
Centri non definiti ({n_undef}): {", ".join(undefined_centers)}
Definizione: {definition}

Pag. 1 — La forma della tua struttura
Una panoramica visiva della carta: {n_def} centri definiti, {n_undef} non definiti, la definizione {definition}. Cosa vuol dire questa configurazione in termini pratici. Come si differenzia da persone con più o meno centri definiti.

Pag. 2 — Cosa vuol dire Definizione {definition}
La definizione {definition} spiegata concretamente: come i centri definiti di {name} sono collegati tra loro. Cosa produce questa connessione specifica. Come si sente nella vita quotidiana.

Pag. 3 — I centri che portano la tua voce interna
I centri definiti: {", ".join(defined_centers)}. Uno per uno, in modo sintetico: cosa fanno, che energia portano, perché sono costanti. Come {name} può fidarsi di queste energie.

Pag. 4 — I sette centri non definiti
I centri non definiti: {", ".join(undefined_centers)}. Uno per uno: cosa assorbono, cosa amplificano, quale saggezza portano quando sono usati bene, quale trappola nascondono quando ci si identifica con quello che si assorbe.

Pag. 5 — Come si manifesta l'Amarezza / il Non-Sé nei centri aperti
Il tema del Non-Sé per {name} ({non_self}): come entra dai centri non definiti, come si accumula, come si riconosce. Non una diagnosi ma una mappa per tornare.

Pag. 6 — Scene
Due scene parallele: una giornata in cui i centri aperti vengono usati come saggezza (ascolto, adattamento, intelligenza); una giornata in cui si fa confusione tra ciò che si assorbe e ciò che si è. La differenza pratica tra le due.

Pag. 7 — La lettura operativa
Come usare questa mappa nel quotidiano: la domanda da fare quando si sente qualcosa di strano, il gesto concreto per distinguere i centri definiti da quelli aperti, come creare le condizioni per stare bene energeticamente."""

    elif chapter_num == 5:
        return f"""{cb}
Scrivi il CAPITOLO 5 — "Profilo {profile} in profondità" per {name}.
Struttura: 7 pagine, ~280 parole ciascuna.
Profilo: {profile} — Linea {l1} {n1} (conscia, solare) · Linea {l2} {n2} (inconscia, terrestre)

Pag. 1 — Due numeri, due movimenti
Il profilo {profile}: due numeri che descrivono due modi di stare nel mondo che lavorano in {name} contemporaneamente, spesso in direzioni opposte. Un primo sguardo su cosa portano la linea {l1} e la linea {l2}.

Pag. 2 — La linea {l1} · {n1}
La linea {l1} spiegata in profondità: come funziona, cosa porta, quali sono i suoi bisogni specifici, come si manifesta nel lavoro e nelle relazioni di {name}. Il rischio quando non è rispettata.

Pag. 3 — La linea {l2} · {n2}
La linea {l2} spiegata in profondità: come funziona, cosa porta, quali sono i suoi bisogni specifici, come si manifesta nel lavoro e nelle relazioni di {name}. Il rischio quando non è rispettata.

Pag. 4 — Come coesistono le due linee
La tensione tra linea {l1} e linea {l2}: come si nutrono a vicenda quando vengono rispettate entrambe. La proporzione giusta. Cosa succede quando una prende troppo spazio rispetto all'altra.

Pag. 5 — L'arco di vita
Come il profilo {profile} si esprime in fasi diverse: i primi trent'anni, i trent'anni di mezzo, dopo i cinquanta. Cosa cambia, cosa resta. Come orientarsi nella fase attuale.

Pag. 6 — Scena
Una scena concreta realistica: {name} nel profilo {profile} al lavoro, in un momento in cui entrambe le linee trovano il loro spazio. Dettagli specifici, non astrazioni.

Pag. 7 — Vivere un {profile} con consapevolezza
L'architettura pratica: cosa proteggere, cosa non trascurare, come alternare i due movimenti senza cercare di fonderli in uno. Tre o quattro indicazioni concrete."""

    elif chapter_num == 6:
        return f"""{cb}
Scrivi il CAPITOLO 6 — "La tua missione" per {name}.
Struttura: 7 pagine, ~280 parole ciascuna.
Croce d'Incarnazione: {cross_name}
Porte: {p_sol} ({g_p_sol}) · {p_ter} ({g_p_ter}) · {d_sol} ({g_d_sol}) · {d_ter} ({g_d_ter})
Sole Personalità: porta {p_sol} ({g_p_sol}) · Terra Personalità: porta {p_ter} ({g_p_ter})
Sole Design: porta {d_sol} ({g_d_sol}) · Terra Design: porta {d_ter} ({g_d_ter})
Quarto: {life_theme.split(",")[1].strip() if "," in life_theme else ""}

Pag. 1 — Cosa dice una Croce d'Incarnazione
La Croce d'Incarnazione come tema di fondo della vita, non come identità rigida. Quattro porte, due Soli e due Terre. Come si distingue dal tipo e dall'autorità. Perché si legge sempre nel lungo periodo.

Pag. 2 — Il Quarto
Il Quarto {"della " + life_theme.split(",")[1].strip() if "," in life_theme else ""}: il suo tema generale. Come inquadra la missione specifica di {name}. Cosa lo distingue dagli altri quarti.

Pag. 3 — Porta {p_sol} · Sole di Personalità · {g_p_sol}
La porta {p_sol} ({g_p_sol}) come filo conduttore cosciente: cosa porta, come si manifesta nella vita quotidiana di {name}, come orienta il lavoro e le relazioni.

Pag. 4 — Porta {p_ter} · Terra di Personalità · {g_p_ter}
La porta {p_ter} ({g_p_ter}) come base del Sole Personalità: cosa porta, la sua meccanica specifica, come interagisce con la porta {p_sol} nel dare forma alla missione conscia di {name}.

Pag. 5 — Porta {d_sol} · Sole di Design · {g_d_sol}
La porta {d_sol} ({g_d_sol}) come qualità di presenza inconscia: portata nel corpo prima della nascita, riconoscibile dagli altri più che da {name} stessa. Come si manifesta, qual è il suo dono.

Pag. 6 — Porta {d_ter} · Terra di Design · {g_d_ter}
La porta {d_ter} ({g_d_ter}) come fondamento silenzioso: cosa ancora l'innocenza della porta {d_sol}, come si manifesta nel corpo, come si nutre concretamente.

Pag. 7 — Il tema portante
Mettendo insieme le quattro porte: una frase sola che descrive la missione di {name}. Come questa croce si manifesta nella sua pratica professionale. La responsabilità che viene con questa configurazione."""

    else:  # chapter_num == 7
        return f"""{cb}
Scrivi il CAPITOLO 7 — "Come tornare" per {name}.
Struttura: 7 pagine, ~280 parole ciascuna.
Firma: {signature} · Non-Sé: {non_self} · Tipo: {tipo} · Profilo: {profile}

Pag. 1 — Tornare, non arrivare
Nessuno vive sempre nel proprio design. Il capitolo non è una prescrizione: è una diagnostica. La velocità di ritorno conta più della perfezione. Come usare questa mappa come bussola, non come gabbia.

Pag. 2 — Il sapore del {non_self}
Il {non_self}: come si sente nel corpo, quando arriva, con quale ritmo si accumula. Come distinguerlo da altri stati emotivi. Perché compare: la strategia violata, il centro aperto che ha preso troppo.

Pag. 3 — Il sapore della {signature}
La firma {signature}: come si sente concretamente, quando arriva, cosa la precede. Come coltivare il riconoscimento di questo sapore. Perché la firma sana non è mai rumorosa.

Pag. 4 — Il protocollo di ritorno
Quando ti accorgi di essere lontana dal design: cinque gesti concreti in ordine. Ognuno ricolloca una parte del sistema. Specifici per il tipo {tipo} e l'autorità {authority}.

Pag. 5 — Le tre violazioni più frequenti
Per il tipo {tipo} con profilo {profile}: le tre violazioni della strategia che si ripetono con regolarità. Per ciascuna: come si manifesta, il segnale precoce nel corpo, la regola operativa concreta.

Pag. 6 — Le tre abitudini che tengono in allineamento
Le tre pratiche semplici che rendono difficile vivere per mesi fuori dal design senza accorgersene. Specifiche per questa carta: non rituali generici ma gesti calibrati su questo tipo, questa autorità, questo profilo.

Pag. 7 — Integrazione e Sintesi
Siamo arrivati alla fine. Le cinque cose da tenere di tutto il libretto. Una per una, in modo diretto: il tipo, l'autorità, il canale, la struttura, la missione. Un'ultima cosa — l'invito a tornare quando qualcosa si sposta."""


# ─── CLAUDE CALL ─────────────────────────────────────────────────────────────

def generate_chapter(
    client: anthropic.Anthropic,
    system_prompt: str,
    user_prompt: str,
    chapter_num: int,
) -> str:
    """Call Claude API and return chapter text."""
    print(f"    Generating chapter {chapter_num}…", end=" ", flush=True)
    t0 = time.time()
    response = client.messages.create(
        model=MODEL,
        max_tokens=MAX_TOKENS,
        system=system_prompt,
        messages=[{"role": "user", "content": user_prompt}],
    )
    elapsed = time.time() - t0
    in_tok = response.usage.input_tokens
    out_tok = response.usage.output_tokens
    cost_eur = (in_tok * 15.0 + out_tok * 75.0) / 1_000_000 * 0.93  # approx EUR
    print(f"done in {elapsed:.0f}s · {in_tok}+{out_tok} tok · ~€{cost_eur:.3f}")
    return response.content[0].text


def clean_chapter_output(text: str) -> str:
    """Ensure the text starts at the first ## Pag marker."""
    lines = text.splitlines()
    for i, line in enumerate(lines):
        if line.startswith("## Pag"):
            return "\n".join(lines[i:])
    return text


# ─── MAIN ─────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="Generate a BG5 Blueprint PDF for any person.",
    )
    parser.add_argument("--chart", required=True, help="Path to chart JSON")
    parser.add_argument("--customer", required=True, help="Customer full name")
    parser.add_argument("--tier", default="Completo", choices=["Completo", "Essenziale", "Supplemento"])
    parser.add_argument("--out", required=True, help="Output PDF path")
    parser.add_argument(
        "--chapters-dir", default=None,
        help="Directory to save generated chapter .md files (default: temp subdir)",
    )
    parser.add_argument(
        "--skip-generation", action="store_true",
        help="Skip AI generation and use existing .md files in --chapters-dir",
    )
    args = parser.parse_args()

    chart_path = Path(args.chart)
    out_path = Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)

    # Load chart (handles both {"chart": {...}} and flat format)
    _raw = json.loads(chart_path.read_text(encoding="utf-8"))
    chart = _raw.get("chart", _raw)
    print(f"Chart loaded: {chart.get('customer_name')} · {chart.get('career_type')} "
          f"· {chart.get('profile')} · {chart.get('definition')}")

    # Chapters directory
    if args.chapters_dir:
        chapters_dir = Path(args.chapters_dir)
    else:
        chapters_dir = out_path.parent / f"chapters_{chart_path.stem}"
    chapters_dir.mkdir(parents=True, exist_ok=True)
    print(f"Chapters dir: {chapters_dir}")

    chapter_files = [chapters_dir / f"cap{i}-draft-completo.md" for i in range(1, 8)]

    # Generate chapters via Claude
    if not args.skip_generation:
        writing_rules = _load_writing_rules()
        gates_data = _load_gates()
        system_prompt = _build_system_prompt(writing_rules)
        client = anthropic.Anthropic(api_key=API_KEY)

        total_cost = 0.0
        print("\nGenerating 7 chapters via Claude API…")
        for ch_num in range(1, 8):
            ch_file = chapter_files[ch_num - 1]
            if ch_file.exists():
                print(f"  Chapter {ch_num}: already exists, skipping.")
                continue
            user_prompt = _chapter_prompt(ch_num, chart, gates_data)
            text = generate_chapter(client, system_prompt, user_prompt, ch_num)
            text = clean_chapter_output(text)
            ch_file.write_text(text, encoding="utf-8")
    else:
        print("Skipping chapter generation (--skip-generation).")

    # Verify all chapter files exist
    missing = [f for f in chapter_files if not f.exists()]
    if missing:
        print(f"ERROR: Missing chapter files: {[f.name for f in missing]}")
        sys.exit(1)

    # Build PDF
    print(f"\nBuilding PDF: {out_path}")
    # Import here to avoid circular imports at top level
    import build_blueprint_v5
    build_blueprint_v5.build_pdf(
        out_path,
        customer=args.customer,
        tier=args.tier,
        chart_json_path=chart_path,
        chapter_files=chapter_files,
    )
    size_kb = out_path.stat().st_size // 1024
    print(f"Done. {size_kb} KB → {out_path}")


if __name__ == "__main__":
    main()
