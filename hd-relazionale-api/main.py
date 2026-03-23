"""
Consulente Relazionale HD — FastAPI Backend
Flusso: form → RAG (Supabase pgvector) → Claude haiku → risposta 3 sezioni
"""

import os
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel, field_validator
import anthropic
import voyageai
from supabase import create_client, Client
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.util import get_remote_address
from slowapi.errors import RateLimitExceeded

load_dotenv(override=True)

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "")
VOYAGE_API_KEY = os.getenv("VOYAGE_API_KEY", "")
SUPABASE_URL = os.getenv("SUPABASE_URL", "")
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")
ALLOWED_ORIGINS = os.getenv("ALLOWED_ORIGINS", "*").split(",")

EMBEDDING_MODEL = "voyage-3"       # dimensione 1024
CLAUDE_MODEL = "claude-haiku-4-5"
TOP_K_CHUNKS = 5

# ---------------------------------------------------------------------------
# Clients
# ---------------------------------------------------------------------------
ai_client = anthropic.Anthropic(api_key=ANTHROPIC_API_KEY)
voyage_client = voyageai.Client(api_key=VOYAGE_API_KEY) if VOYAGE_API_KEY else None
supabase: Client = create_client(SUPABASE_URL, SUPABASE_KEY) if SUPABASE_URL else None

# ---------------------------------------------------------------------------
# App
# ---------------------------------------------------------------------------
limiter = Limiter(key_func=get_remote_address)

app = FastAPI(title="HD Relazionale API", version="1.0.0")
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

app.add_middleware(
    CORSMiddleware,
    allow_origins=ALLOWED_ORIGINS,
    allow_methods=["POST", "GET"],
    allow_headers=["*"],
)

# ---------------------------------------------------------------------------
# Models
# ---------------------------------------------------------------------------
TIPI_HD = {"Generatore", "Generatore Manifestante", "Proiettore", "Manifestatore", "Riflettore"}
RELAZIONI = {"Partner Sentimentale", "Genitore/Figlio", "Colleghi/Lavoro", "Amicizia"}

class AnalisiRequest(BaseModel):
    tipo_utente: str
    tipo_altro: str
    relazione: str
    problema: str

    @field_validator("tipo_utente", "tipo_altro")
    @classmethod
    def valida_tipo(cls, v: str) -> str:
        if v not in TIPI_HD:
            raise ValueError(f"Tipo non valido: {v}")
        return v

    @field_validator("relazione")
    @classmethod
    def valida_relazione(cls, v: str) -> str:
        if v not in RELAZIONI:
            raise ValueError(f"Relazione non valida: {v}")
        return v

    @field_validator("problema")
    @classmethod
    def valida_problema(cls, v: str) -> str:
        v = v.strip()
        if not v:
            raise ValueError("Il problema non può essere vuoto.")
        if len(v) > 300:
            raise ValueError("Il problema non può superare i 300 caratteri.")
        return v

# ---------------------------------------------------------------------------
# RAG helpers
# ---------------------------------------------------------------------------
def embed_query(text: str) -> list[float]:
    """Embedding voyage-3 via voyageai SDK. Fallback zero-vector se chiave mancante."""
    if voyage_client is None:
        return [0.0] * 1024
    try:
        result = voyage_client.embed([text], model=EMBEDDING_MODEL)
        return result.embeddings[0]
    except Exception:
        return [0.0] * 1024


def retrieve_context(query: str) -> str:
    """Cerca i top-K chunk più rilevanti in Supabase pgvector."""
    if supabase is None:
        return "[Supabase non configurato — modalità demo]"
    try:
        vec = embed_query(query)
        result = supabase.rpc(
            "match_hd_chunks",
            {"query_embedding": vec, "match_count": TOP_K_CHUNKS},
        ).execute()
        if not result.data:
            return ""
        return "\n\n---\n\n".join(row["content"] for row in result.data)
    except Exception as e:
        # Non bloccare la risposta se il RAG fallisce
        return f"[Contesto RAG non disponibile: {e}]"


# ---------------------------------------------------------------------------
# Prompt
# ---------------------------------------------------------------------------
SYSTEM_PROMPT = """Sei un consulente specializzato in dinamiche relazionali secondo il sistema Human Design e BG5®.
Il tuo ruolo è fornire UNA sola intuizione pratica e invitare alla consulenza a pagamento.

REGOLE DI PERIMETRO — rispettale sempre, senza eccezioni:
1. Rispondi SOLO a domande su dinamiche relazionali tra Tipi Human Design.
2. Se la domanda è off-topic (cucina, notizie, ecc.) → [LA PILLOLA] deve contenere: "Non sono programmato per rispondere a questo. Sono specializzato esclusivamente nelle dinamiche relazionali Human Design."
3. Se l'utente cerca una lettura completa del proprio Design (centri, canali, porte, profilo, autorità dettagliata) → reindirizza alla consulenza senza fornire l'analisi approfondita.
4. Non analizzare mai in dettaglio centri definiti/non definiti, canali, porte, circuiti, linee del profilo.
5. Ogni risposta DEVE seguire ESATTAMENTE questa struttura, con i tag su riga propria:

[LA PILLOLA]
Un consiglio pratico, diretto e comprensibile a tutti. Niente gergo esoterico. Max 3 frasi.

[LA DIAGNOSI]
Spiegazione veloce della frizione energetica tra i due Tipi specifici in questa relazione. Max 4 frasi.

[L'UPSELL]
Call to action persuasiva che invita a prenotare una 'Analisi Sinottica Relazionale' con Valentina Russo, spiegando che questo consiglio è solo l'1% della dinamica completa. Tono caldo ma professionale.

Non aggiungere nulla fuori da queste 3 sezioni. Non usare markdown (grassetto, corsivo, elenchi puntati)."""


def build_prompt(req: AnalisiRequest, context: str) -> str:
    ctx_block = f"\n\nCONTESTODI RIFERIMENTO (estratto dai documenti di Human Design):\n{context}" if context else ""
    return (
        f"Utente 1: {req.tipo_utente}\n"
        f"Altra persona: {req.tipo_altro}\n"
        f"Tipo di relazione: {req.relazione}\n"
        f"Problema descritto: {req.problema}"
        f"{ctx_block}"
    )


# ---------------------------------------------------------------------------
# AI call (modulare — sostituire questa funzione per cambiare LLM)
# ---------------------------------------------------------------------------
async def query_ai(user_prompt: str) -> str:
    """Chiama Claude haiku con il system prompt e il contesto RAG."""
    if not ANTHROPIC_API_KEY:
        # Mock per testing locale senza chiave
        return (
            "[LA PILLOLA]\nQuesto è un esempio di risposta (modalità demo — chiave API non configurata).\n\n"
            "[LA DIAGNOSI]\nI due Tipi hanno dinamiche energetiche complementari ma richiedono rispetto dei tempi.\n\n"
            "[L'UPSELL]\nQuesta è solo la superficie. Prenota la tua Analisi Sinottica Relazionale con Valentina Russo "
            "per scoprire il 99% restante della dinamica tra voi."
        )

    message = ai_client.messages.create(
        model=CLAUDE_MODEL,
        max_tokens=800,
        system=SYSTEM_PROMPT,
        messages=[{"role": "user", "content": user_prompt}],
    )
    return message.content[0].text


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------
@app.get("/health")
async def health():
    return {"status": "ok", "model": CLAUDE_MODEL}


@app.post("/analisi")
@limiter.limit("5/minute;30/hour")
async def genera_analisi(request: Request, req: AnalisiRequest):
    # Costruisce query per RAG
    rag_query = f"dinamica relazionale {req.tipo_utente} {req.tipo_altro} {req.relazione} {req.problema}"
    context = retrieve_context(rag_query)

    user_prompt = build_prompt(req, context)

    try:
        result = await query_ai(user_prompt)
    except anthropic.APIStatusError as e:
        raise HTTPException(status_code=502, detail=f"Errore API Anthropic: {e.message}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

    return {"result": result}
