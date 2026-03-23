"""
ingest_kb.py — Indicizza i file markdown della knowledge-base locale
Esegui UNA SOLA VOLTA dopo aver configurato .env con ANTHROPIC_API_KEY.

Uso:
    python ingest_kb.py --dir ../grav-site/root/knowledge-base

Legge file .md (e .txt) dalla cartella specificata, li chunka e li
inserisce in Supabase pgvector con embedding voyage-3.
"""

import os
import argparse
import glob
import time
from pathlib import Path
from dotenv import load_dotenv
import voyageai
from supabase import create_client

load_dotenv(override=True)

VOYAGE_API_KEY = os.getenv("VOYAGE_API_KEY", "")
SUPABASE_URL = os.getenv("SUPABASE_URL", "")
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")

EMBEDDING_MODEL = "voyage-3"
CHUNK_SIZE = 500
CHUNK_OVERLAP = 50

voyage_client = voyageai.Client(api_key=VOYAGE_API_KEY)
supabase_client = create_client(SUPABASE_URL, SUPABASE_KEY)


def extract_text_from_markdown(path: str) -> str:
    with open(path, "r", encoding="utf-8") as f:
        return f.read()


def chunk_text(text: str, chunk_size: int = CHUNK_SIZE, overlap: int = CHUNK_OVERLAP) -> list[str]:
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


def get_embeddings_batch(texts: list[str]) -> list[list[float]]:
    """Embedding batch — una sola chiamata API per tutti i chunk del file."""
    result = voyage_client.embed(texts, model=EMBEDDING_MODEL)
    return result.embeddings


def upsert_chunks(chunks: list[str], source_name: str) -> int:
    inserted = 0
    try:
        print(f"  Generando embeddings batch ({len(chunks)} chunk)...")
        embeddings = get_embeddings_batch(chunks)
    except Exception as e:
        print(f"  ERRORE embedding batch: {e}")
        return 0

    for i, (chunk, embedding) in enumerate(zip(chunks, embeddings)):
        try:
            supabase_client.table("hd_chunks").insert({
                "content": chunk,
                "embedding": embedding,
                "source": source_name,
            }).execute()
            inserted += 1
            print(f"  [{i+1}/{len(chunks)}] ok ({len(chunk)} chars)")
        except Exception as e:
            print(f"  ERRORE insert chunk {i+1}: {e}")
    return inserted


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--dir", required=True, help="Cartella con i file .md")
    args = parser.parse_args()

    if not VOYAGE_API_KEY:
        print("ERRORE: VOYAGE_API_KEY non configurata in .env")
        return
    if not SUPABASE_URL or not SUPABASE_KEY:
        print("ERRORE: SUPABASE_URL o SUPABASE_KEY non configurate in .env")
        return

    files = glob.glob(str(Path(args.dir) / "**" / "*.md"), recursive=True)
    files += glob.glob(str(Path(args.dir) / "**" / "*.txt"), recursive=True)
    files = sorted(set(files))

    if not files:
        print(f"Nessun file trovato in: {args.dir}")
        return

    print(f"Trovati {len(files)} file da indicizzare.\n")
    total = 0

    for path in files:
        name = Path(path).name
        print(f">> {name}")
        try:
            text = extract_text_from_markdown(path)
            if not text.strip():
                print("  (vuoto, skip)")
                continue
            chunks = chunk_text(text)
            print(f"  {len(chunks)} chunk generati")
            n = upsert_chunks(chunks, source_name=name)
            total += n
            print(f"  OK {n}/{len(chunks)} inseriti\n")
        except Exception as e:
            print(f"  ERRORE: {e}\n")
        else:
            # rispetta 3 RPM Voyage AI free tier (una chiamata batch per file)
            if path != files[-1]:
                time.sleep(21)

    print(f"\nIngestion completata. Totale chunk inseriti: {total}")


if __name__ == "__main__":
    main()
