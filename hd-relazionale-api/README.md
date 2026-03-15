# HD Relazionale — API Backend

FastAPI + RAG (Supabase pgvector) + Claude haiku-4-5.

## Setup iniziale

### 1. Installa dipendenze
```bash
cd hd-relazionale-api
pip install -r requirements.txt
```

### 2. Configura variabili d'ambiente
```bash
cp .env.example .env
# Modifica .env con le tue chiavi
```

Campi necessari in `.env`:
- `ANTHROPIC_API_KEY` — da https://console.anthropic.com
- `SUPABASE_URL` e `SUPABASE_KEY` — da https://supabase.com/dashboard

### 3. Crea la tabella su Supabase
Vai su **Supabase → SQL Editor** ed esegui:

```sql
-- Abilita l'estensione vettoriale
CREATE EXTENSION IF NOT EXISTS vector;

-- Tabella chunk HD
CREATE TABLE IF NOT EXISTS hd_chunks (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  content text NOT NULL,
  embedding vector(1024),
  source text
);

-- Indice per ricerca coseno veloce
CREATE INDEX ON hd_chunks USING ivfflat (embedding vector_cosine_ops)
  WITH (lists = 100);

-- Funzione RPC usata da FastAPI
CREATE OR REPLACE FUNCTION match_hd_chunks(
  query_embedding vector(1024),
  match_count int DEFAULT 5
)
RETURNS TABLE (id uuid, content text, source text, similarity float)
LANGUAGE sql STABLE
AS $$
  SELECT id, content, source,
         1 - (embedding <=> query_embedding) AS similarity
  FROM hd_chunks
  ORDER BY embedding <=> query_embedding
  LIMIT match_count;
$$;
```

### 4. Indicizza i PDF (una sola volta)
```bash
python ingest.py --dir /percorso/ai/tuoi/pdf
```

Questo script:
- Legge tutti i PDF nella cartella (ricorsivo)
- Divide il testo in chunk da ~500 caratteri
- Genera embedding voyage-3 via Anthropic
- Carica tutto su Supabase

### 5. Avvia il server in locale
```bash
uvicorn main:app --reload
```

Server disponibile su: http://localhost:8000
Documentazione Swagger: http://localhost:8000/docs

## Test manuale (Swagger)

1. Apri http://localhost:8000/docs
2. Clicca `POST /analisi` → **Try it out**
3. Inserisci:
```json
{
  "tipo_utente": "Proiettore",
  "tipo_altro": "Generatore",
  "relazione": "Partner Sentimentale",
  "problema": "Mi sento sempre ignorato quando lui è in fase di lavoro intenso."
}
```
4. Verifica che la risposta contenga `[LA PILLOLA]`, `[LA DIAGNOSI]`, `[L'UPSELL]`

## Deploy su Render

1. Crea un account su https://render.com
2. **New → Web Service** → collega il repo GitHub
3. Imposta:
   - **Root Directory**: `hd-relazionale-api`
   - **Build Command**: `pip install -r requirements.txt`
   - **Start Command**: `uvicorn main:app --host 0.0.0.0 --port $PORT`
4. Aggiungi le variabili d'ambiente dalla dashboard Render
5. Copia l'URL del servizio (es. `https://hd-relazionale.onrender.com`)
6. Aggiorna `api_url` nel frontmatter della pagina Grav

## Struttura risposta

```json
{
  "result": "[LA PILLOLA]\n...\n\n[LA DIAGNOSI]\n...\n\n[L'UPSELL]\n..."
}
```

## Aggiornare il modello AI

Per sostituire Claude con un altro LLM, modifica solo la funzione `query_ai()` in `main.py`.
