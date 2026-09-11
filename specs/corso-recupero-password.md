# Recupero password del corso — decisioni

Chi perdeva la password non aveva strada: la pagina di accesso non offriva
niente e il meccanismo del token serviva solo alla prima attivazione. Doveva
scrivere a Valentina, che azzerava a mano dal pannello.

## Scelte

- **Attrezzo**: token monouso in `hd_users.reset_token` + `reset_expires`, lo
  stesso che usa l'attivazione dopo il pagamento. A riposo se ne salva solo
  l'impronta sha256: il token in chiaro esiste unicamente nel link.
- **Durata**: due ore, non i sette giorni dell'attivazione. Qui la richiesta
  l'ha fatta la persona un attimo prima.
- **Dove si scrive la password**: `corso/attiva.php`, la pagina che c'era gia'.
  Col parametro `r=1` accetta anche il token della docente; senza, resta
  ristretta alle allieve, cosi' il giro del pagamento non puo' generare, nemmeno
  per errore, un accesso a un account amministratore. `r=1` non e' un permesso:
  il segreto resta il token, che deve combaciare con quell'account.
- **Chi puo' recuperare**: allieve e amministratrici. Se Valentina perde la sua
  password non esiste nessun altro che possa aprirgliela.
- **Enumerazione**: la risposta e' identica per un indirizzo iscritto e per uno
  sconosciuto. Altrimenti la pagina diventa un modo per sapere chi frequenta.
- **Abuso**: ogni richiesta passa da `hdIsRateLimited` e viene contata come
  tentativo (5 per indirizzo e 15 per IP in 15 minuti), lo stesso freno del
  login. Nessun contatore nuovo, nessun file da tenere scrivibile.
- **Sessioni aperte**: `corsoAttivaAccount` alza `session_ver`, quindi al
  cambio password le sessioni vecchie cadono.
- **Posta**: `mail()` dal server, testo semplice, intestazioni separate da
  CRLF (l'a-capo semplice aveva gia' fatto sparire le mail della lezione
  gratuita). Mittente `info@valentinarussobg5.com`.

## Cosa NON fa

- Non dice mai se un indirizzo esista.
- Non manda password generate da noi: la scrive la persona.
- Non tocca il giro del pagamento.
