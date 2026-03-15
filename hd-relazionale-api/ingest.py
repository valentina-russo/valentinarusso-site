"""
ingest.py — Script di ingestion PDF → Supabase pgvector
Esegui UNA SOLA VOLTA in locale dopo aver configurato .env

Uso:
    python ingest.py --dir /percorso/ai/tuoi/pdf

Prima di eseguire, crea la tabella su Supabase (vedi README.md).
"""

import os
import argparse
import glob
from pathlib import Path
from dotenv import load_dotenv
import anthropic
from supabase import create_client

load_dotenv()

ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "")
SUPABASE_URL = os.getenv("SUPABASE_URL", "")
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")

EMBEDDING_MODEL = "voyage-3"
CHUNK_SIZE = 500       # caratteri per chunk (approssimativo)
CHUNK_OVERLAP = 50     # sovrapposizione tra chunk

ai_client = anthropic.Anthropic(api_key=ANTHROPIC_API_KEY)
supabase_client = create_client(SUPABASE_URL, SUPABASE_KEY)


# ---------------------------------------------------------------------------
# Estrazione testo PDF
# ---------------------------------------------------------------------------
def extract_text_from_pdf(pdf_path: str) -> str:
    """Estrae il testo da un PDF con PyMuPDF."""
    import fitz  # pymupdf
    text_parts = []
    with fitz.open(pdf_path) as doc:
        for page in doc:
            text_parts.append(page.get_text())
    return "\n".join(text_parts)


# ---------------------------------------------------------------------------
# Chunking
# ---------------------------------------------------------------------------
def chunk_text(text: str, chunk_size: int = CHUNK_SIZE, overlap: int = CHUNK_OVERLAP) -> list[str]:
    """Divide il testo in chunk con overlap."""
    chunks = []
    start = 0
    text = text.strip()
    while start < len(text):
        end = start + chunk_size
        chunk = text[start:end].strip()
        if chunk:
            chunks.append(chunk)
        start += chunk_size - overlap
    return chunks


# ---------------------------------------------------------------------------
# Embedding
# ---------------------------------------------------------------------------
def get_embedding(text: str) -> list[float]:
    """Genera embedding voyage-3."""
    resp = ai_client.embeddings.create(
        model=EMBEDDING_MODEL,
        input=[text],
    )
    return resp.embeddings[0].values


# ---------------------------------------------------------------------------
# Upsert su Supabase
# ---------------------------------------------------------------------------
def upsert_chunks(chunks: list[str], source_name: str) -> int:
    """Inserisce i chunk con embedding nella tabella hd_chunks."""
    inserted = 0
    for i, chunk in enumerate(chunks):
        try:
            embedding = get_embedding(chunk)
            supabase_client.table("hd_chunks").insert({
                "content": chunk,
                "embedding": embedding,
                "source": source_name,
            }).execute()
            inserted += 1
            print(f"  [{i+1}/{len(chunks)}] chunk inserito ({len(chunk)} chars)")
        except Exception as e:
            print(f"  ERRORE chunk {i+1}: {e}")
    return inserted


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    parser = argparse.ArgumentParser(description="Indicizza PDF in Supabase pgvector")
    parser.add_argument("--dir", required=True, help="Cartella contenente i PDF")
    parser.add_argument("--pattern", default="*.pdf", help="Pattern file (default: *.pdf)")
    args = parser.parse_args()

    pdf_files = glob.glob(str(Path(args.dir) / "**" / args.pattern), recursive=True)
    if not pdf_files:
        print(f"Nessun PDF trovato in: {args.dir}")
        return

    print(f"Trovati {len(pdf_files)} PDF da indicizzare.\n")
    total_chunks = 0

    for pdf_path in pdf_files:
        name = Path(pdf_path).name
        print(f"→ {name}")
        try:
            text = extract_text_from_pdf(pdf_path)
            if not text.strip():
                print("  (nessun testo estratto, skip)")
                continue
            chunks = chunk_text(text)
            print(f"  {len(chunks)} chunk generati")
            n = upsert_chunks(chunks, source_name=name)
            total_chunks += n
            print(f"  ✓ {n}/{len(chunks)} chunk inseriti\n")
        except Exception as e:
            print(f"  ERRORE: {e}\n")

    print(f"\nIngestion completata. Totale chunk inseriti: {total_chunks}")


if __name__ == "__main__":
    main()
