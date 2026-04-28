<?php
/**
 * Stripe Config — Libretto d'Istruzioni Human Design
 *
 * !! ISTRUZIONI !!
 *
 * 1. Copia questo file e rinominalo stripe-config.php (SENZA .example)
 * 2. Sostituisci le chiavi con quelle reali dalla dashboard Stripe
 * 3. Carica stripe-config.php su Aruba via FTP in: libretto-dati/stripe-config.php
 *
 * stripe-config.php NON deve mai essere committato in git (già in .gitignore).
 *
 * Dashboard Stripe → Developers → API Keys
 *
 * Usa sk_test_... finché non hai verificato che tutto funziona.
 * Poi sostituisci con sk_live_... per andare live.
 */

// Chiave segreta Stripe (secret key — inizia con sk_test_ o sk_live_)
putenv('STRIPE_SECRET_KEY=sk_test_SOSTITUISCI_CON_LA_TUA_CHIAVE_SEGRETA');

// Webhook secret (da configurare quando registri il webhook su Stripe)
// Dashboard Stripe → Developers → Webhooks → Add endpoint → copia il signing secret
// putenv('STRIPE_WEBHOOK_SECRET=whsec_SOSTITUISCI_CON_IL_TUO_WEBHOOK_SECRET');
