#!/usr/bin/env python3
"""
BG5 Business Blueprint Generator
Legge job da jobs/pending/, genera il PDF con Claude API + ReportLab, sposta in jobs/review/

Uso:
    python generator.py              # processa tutti i job pending
    python generator.py job_XXX      # processa un job specifico
    python generator.py --dry-run    # mostra cosa farebbe senza fare nulla

Requisiti:
    pip install anthropic reportlab ephem pytz
"""

import os
import sys
import json
import time
import argparse
import logging
from pathlib import Path
from datetime import datetime

# Assicura che la directory del generatore sia nel path per gli import locali
sys.path.insert(0, str(Path(__file__).parent))

# ─── .env LOADER (no deps) ────────────────────────────────────────────────────
def _load_env(env_path: Path) -> None:
    """Carica variabili dal file .env senza dipendenze esterne."""
    if not env_path.exists():
        return
    for line in env_path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        k, v = k.strip(), v.strip().strip('"').strip("'")
        if k and v and not os.environ.get(k):   # sovrascrive se assente o vuota
            os.environ[k] = v

_load_env(Path(__file__).parent / ".env")

# ─── CONFIGURAZIONE ───────────────────────────────────────────────────────────

BASE_DIR   = Path(__file__).parent.parent.parent / "grav-site" / "root" / "bg5-blueprint"
JOBS_DIR   = BASE_DIR / "jobs"
PDFS_DIR   = BASE_DIR / "pdfs"
LOGS_DIR   = BASE_DIR / "logs"

ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "")
CLAUDE_MODEL      = "claude-opus-4-6"     # ~€1.45/PDF — qualità massima
# Pricing per stima costi (USD per milione di token)
MODEL_PRICING = {
    "claude-opus-4-6":   {"input": 15.0, "output": 75.0},
    "claude-sonnet-4-6": {"input": 3.0,  "output": 15.0},
    "claude-haiku-4-5":  {"input": 0.80, "output": 4.0},
}

# ─── LOGGING ──────────────────────────────────────────────────────────────────

LOGS_DIR.mkdir(parents=True, exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    handlers=[
        logging.FileHandler(LOGS_DIR / "generator.log"),
        logging.StreamHandler()
    ]
)
log = logging.getLogger(__name__)

# ─── HD CHART ────────────────────────────────────────────────────────────────

def load_chart(job: dict) -> dict:
    """
    Carica il chart HD/BG5 dal job.

    Se il job contiene già il campo "chart" (pre-calcolato), lo usa direttamente.
    Altrimenti lo calcola automaticamente da birth_date / birth_time / birth_place
    usando calc_chart_ephem + timezonefinder per determinare il TZ offset.
    """
    chart = job.get("chart")

    if not chart:
        log.info("  Chart non presente nel job — calcolo automatico da dati nascita...")
        try:
            from calc_chart_ephem import calculate_chart
            import pytz
            from datetime import datetime as _dt

            birth_date  = job["birth_date"]   # "YYYY-MM-DD"
            birth_time  = job["birth_time"]   # "HH:MM"
            birth_place = job["birth_place"]
            birth_lat   = job.get("birth_lat")
            birth_lon   = job.get("birth_lon")

            # Calcola TZ offset da lat/lon (se disponibili), altrimenti default CET
            tz_offset = 1.0
            if birth_lat and birth_lon:
                try:
                    from timezonefinder import TimezoneFinder
                    tf = TimezoneFinder()
                    tz_name = tf.timezone_at(lat=float(birth_lat), lng=float(birth_lon))
                    if tz_name:
                        tz = pytz.timezone(tz_name)
                        birth_dt = _dt.strptime(f"{birth_date} {birth_time}", "%Y-%m-%d %H:%M")
                        offset = tz.utcoffset(birth_dt)
                        tz_offset = offset.total_seconds() / 3600
                        log.info(f"  TZ: {tz_name} → offset {tz_offset:+.1f}h")
                except Exception as tz_err:
                    log.warning(f"  TZ lookup fallito ({tz_err}) — uso default {tz_offset:+.1f}h")

            chart = calculate_chart(
                job.get("customer_name", "Cliente"),
                birth_date, birth_time, tz_offset, birth_place,
            )
            log.info(f"  Chart calcolato: {chart['career_type']} · Profilo {chart['profile']}")

        except Exception as e:
            raise ValueError(
                f"Job {job.get('id', '?')}: impossibile calcolare chart da dati nascita: {e}"
            ) from e

    # Assicura che customer_name sia nel chart
    if "customer_name" not in chart:
        chart["customer_name"] = job.get("customer_name", "Cliente")
    return chart

# ─── AI GENERATION (19 sezioni) ──────────────────────────────────────────────

def generate_sections(chart: dict, section_keys: list[str]) -> dict:
    """
    Genera il testo delle sezioni richieste usando Claude API.
    Usa i prompt template da prompts.py, popolati con i dati del chart.
    Restituisce dict {key: testo_markdown}.
    """
    import anthropic
    from prompts import (
        SECTION_PROMPTS, build_system_prompt, build_format_data,
    )

    client = anthropic.Anthropic(api_key=ANTHROPIC_API_KEY)

    # System prompt con KB + writing rules + lessico BG5
    system = build_system_prompt()

    # Placeholder dict dal chart
    fmt = build_format_data(chart)

    sections = {}
    total_input = 0
    total_output = 0

    for key in section_keys:
        template = SECTION_PROMPTS.get(key)
        if not template:
            log.warning(f"  Nessun prompt per sezione '{key}', salto")
            continue

        prompt = template.format(**fmt)
        log.info(f"  Generando sezione: {key}")

        try:
            response = client.messages.create(
                model=CLAUDE_MODEL,
                max_tokens=3000,
                system=system,
                messages=[{"role": "user", "content": prompt}],
            )
            sections[key] = response.content[0].text
            total_input  += response.usage.input_tokens
            total_output += response.usage.output_tokens
            time.sleep(0.5)  # rate limiting
        except Exception as e:
            log.error(f"  Errore sezione {key}: {e}")
            sections[key] = f"[ERRORE GENERAZIONE: {e}]"

    pricing = MODEL_PRICING.get(CLAUDE_MODEL, MODEL_PRICING["claude-sonnet-4-6"])
    cost_usd = (total_input * pricing["input"] + total_output * pricing["output"]) / 1_000_000
    cost_eur = cost_usd * 0.92
    log.info(f"  [{CLAUDE_MODEL}] Token: {total_input} in + {total_output} out -> ~EUR{cost_eur:.3f}")
    log.info(f"  Sezioni generate: {len(sections)}/{len(section_keys)}")

    return sections


# ─── PDF BUILD (branded, via rebuild_pdfs) ───────────────────────────────────

def build_branded_pdfs(chart: dict, sections: dict, job_id: str) -> dict:
    """
    Genera i PDF branded (Essenziale + Completo) usando rebuild_pdfs.
    Restituisce dict con i path dei PDF generati.
    """
    from rebuild_pdfs import (
        register_fonts, build_pdf as _build_pdf,
    )
    from bodygraph_svg import render_bodygraph_png, activations_from_chart
    from prompts import ESSENZIALE_KEYS, COMPLETO_KEYS

    register_fonts()

    # Bodygraph PNG
    activations = activations_from_chart(chart)
    chart_png = render_bodygraph_png(activations, width=1400)
    log.info(f"  Bodygraph PNG: {len(chart_png) // 1024} KB")

    PDFS_DIR.mkdir(parents=True, exist_ok=True)
    name_slug = chart["customer_name"].lower().replace(" ", "-")
    paths = {}

    # Essenziale
    ess_path = PDFS_DIR / f"{job_id}-essenziale.pdf"
    ess_sections = {k: sections[k] for k in ESSENZIALE_KEYS if k in sections}
    _build_pdf(
        ess_sections, ESSENZIALE_KEYS,
        ess_path,
        tier_name="Essenziale",
        tier_subtitle="Identità energetica",
        chart=chart,
        chart_png=chart_png,
    )
    paths["essenziale"] = str(ess_path)
    log.info(f"  PDF Essenziale: {ess_path} ({ess_path.stat().st_size // 1024} KB)")

    # Completo
    com_path = PDFS_DIR / f"{job_id}-completo.pdf"
    com_sections = {k: sections[k] for k in COMPLETO_KEYS if k in sections}
    _build_pdf(
        com_sections, COMPLETO_KEYS,
        com_path,
        tier_name="Completo",
        tier_subtitle="Identità energetica + Magnetic Marketing",
        chart=chart,
        chart_png=chart_png,
    )
    paths["completo"] = str(com_path)
    log.info(f"  PDF Completo: {com_path} ({com_path.stat().st_size // 1024} KB)")

    return paths


# ─── JOB PROCESSOR ────────────────────────────────────────────────────────────

def process_job(job_file: Path, dry_run: bool = False) -> bool:
    from prompts import COMPLETO_KEYS

    job = json.loads(job_file.read_text(encoding="utf-8"))
    job_id = job["id"]
    customer = job.get("customer_name", "?")
    tier = job.get("tier", "completo").lower()  # "essenziale" o "completo"
    log.info(f"Elaboro job {job_id} — {customer} (tier: {tier})")

    if dry_run:
        log.info(f"  [DRY RUN] saltato")
        return True

    # Sposta in processing/
    proc_dir = JOBS_DIR / "processing"
    proc_dir.mkdir(exist_ok=True)
    proc_file = proc_dir / job_file.name
    job_file.rename(proc_file)

    try:
        # 1. Carica chart dal job (pre-calcolato)
        log.info("  Caricamento chart...")
        chart = load_chart(job)

        # 2. Genera tutte le 19 sezioni con Claude (genera sempre tutte,
        #    il PDF tier filtra le chiavi)
        log.info("  Generazione 19 sezioni AI...")
        sections = generate_sections(chart, COMPLETO_KEYS)
        job["sections"] = sections

        # 3. Salva output JSON intermedio (utile per debug e rebuild)
        output_json = proc_dir / f"{job_id}-output.json"
        output_json.write_text(json.dumps(
            {"chart": chart, "sections": sections},
            ensure_ascii=False, indent=2,
        ), encoding="utf-8")
        log.info(f"  Output JSON: {output_json}")

        # 4. Assembla PDF branded
        log.info("  Assemblaggio PDF branded...")
        pdf_paths = build_branded_pdfs(chart, sections, job_id)
        job["pdf_paths"] = pdf_paths

        # 5. Sposta in review/
        review_dir = JOBS_DIR / "review"
        review_dir.mkdir(exist_ok=True)
        job["status"] = "ready_for_review"
        job["generated_at"] = datetime.now().isoformat()

        review_file = review_dir / proc_file.name
        review_file.write_text(
            json.dumps(job, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        # Sposta anche il JSON output nella review dir
        output_json.rename(review_dir / output_json.name)
        proc_file.unlink()

        log.info(f"  Job {job_id} -> review/")
        return True

    except Exception as e:
        log.error(f"  ERRORE job {job_id}: {e}", exc_info=True)
        failed_dir = JOBS_DIR / "failed"
        failed_dir.mkdir(exist_ok=True)
        job["status"] = "failed"
        job["error"] = str(e)
        (failed_dir / proc_file.name).write_text(
            json.dumps(job, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        proc_file.unlink(missing_ok=True)
        return False


def main():
    parser = argparse.ArgumentParser(description="BG5 Blueprint Generator")
    parser.add_argument("job_id", nargs="?", help="ID job specifico (opzionale)")
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    if not ANTHROPIC_API_KEY and not args.dry_run:
        log.error("ANTHROPIC_API_KEY non impostata. Esporta la variabile e riprova.")
        sys.exit(1)

    pending_dir = JOBS_DIR / "pending"
    pending_dir.mkdir(parents=True, exist_ok=True)

    if args.job_id:
        job_file = pending_dir / f"{args.job_id}.json"
        if not job_file.exists():
            log.error(f"Job non trovato: {job_file}")
            sys.exit(1)
        process_job(job_file, args.dry_run)
    else:
        jobs = sorted(pending_dir.glob("*.json"))
        if not jobs:
            log.info("Nessun job in coda.")
            return
        log.info(f"Trovati {len(jobs)} job da elaborare.")
        for jf in jobs:
            process_job(jf, args.dry_run)


if __name__ == "__main__":
    main()
