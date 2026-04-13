# BG5 Blueprint Generator

## Struttura job

```
grav-site/root/bg5-blueprint/
├── jobs/
│   ├── pending/     ← Stripe webhook scrive qui
│   ├── processing/  ← generator.py sposta qui durante elaborazione
│   ├── review/      ← bozze pronte per Valentina
│   ├── approved/    ← approvate da Valentina (PDF finale da inviare)
│   ├── sent/        ← inviate al cliente
│   └── failed/      ← errori
├── pdfs/            ← PDF generati
├── logs/
│   ├── webhook.log
│   └── generator.log
├── stripe-webhook.php
└── review.php
```

## Come girare il generator

### Manuale (test)
```bash
export ANTHROPIC_API_KEY=sk-ant-...
python tools/bg5-generator/generator.py
```

### Dry run (senza chiamate API)
```bash
python tools/bg5-generator/generator.py --dry-run
```

### Job specifico
```bash
python tools/bg5-generator/generator.py job_1712345678_abc123
```

## Opzioni di deploy del generator

### Opzione A — Aruba cron job (se Python disponibile)
```
# crontab: ogni 5 minuti
*/5 * * * * /usr/bin/python3 /path/to/generator.py >> /path/to/logs/cron.log 2>&1
```

### Opzione B — GitHub Actions (trigger manuale o webhook)
Creare `.github/workflows/generate-blueprint.yml` che si attiva
via `repository_dispatch` event (chiamato dallo Stripe webhook via curl)

### Opzione C — Servizio esterno (Railway, Render, ecc.)
Worker Python sempre attivo che fa polling su jobs/pending/

## Variabili d'ambiente necessarie

| Variabile | Dove | Valore |
|---|---|---|
| `ANTHROPIC_API_KEY` | server/worker | sk-ant-... |
| `STRIPE_WEBHOOK_SECRET` | Aruba/server | whsec_... |
| `ADMIN_PASSWORD` | Aruba | password review.php |
| `STRIPE_SECRET_KEY` | (opzionale, per creare Checkout) | sk_live_... |

## Costi stimati

- Claude **Opus 4.6**: ~€1.45 per PDF (scelto per qualità massima)
- Stripe: 2.9% + €0.25 = ~€2.86 per transazione
- **Margine netto: €85.69 (95.2%)**

Sonnet costerebbe €0.29 e Haiku €0.08, ma su €90 di vendita la differenza
tra i modelli è <1,5% del prezzo — scelta guidata dalla qualità, non dal costo.
