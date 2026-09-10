# Brochure del corso e follow-up a chi ha visto la lezione zero

Stato: in costruzione, 10/09/2026. Proposta di Marco (video del 10/09): copiare
lo schema delle mail della Johns Hopkins, mail breve → pagina con brochure PDF
in linea e colonna fissa con pulsante di iscrizione e telefono.

## Requisiti

- R1  Brochure PDF A4 del Corso Base, 7 pagine, generata da
      `tools/corso-base/brochure_corso.py` con i fatti presi dalla pagina del
      corso (curriculum, formato, date, prezzi). Nessun dato inventato.
- R2  La brochure vive in `grav-site/user/pages/assets/corso-base/brochure/`
      come PDF piu' una PNG per pagina, cosi' la pagina la mostra in linea
      anche da telefono senza dipendere dal visore PDF del browser.
- R3  Pagina `/corso-base-human-design/brochure` (statica in `grav-site/root/`,
      stessa testata e piede della pagina del corso): le pagine della brochure
      una sotto l'altra, un richiamo "Iscriviti" fra una pagina e l'altra, e a
      destra una colonna che resta fissa allo scorrimento con: pulsante
      Iscriviti (→ /corso-base-human-design#investimento), telefono
      +39 379 103 7653 (tel: e WhatsApp), scrivi a Valentina. Da telefono la
      colonna diventa una barra in fondo.
- R4  Link "Scarica la brochure" al PDF.
- R5  Mail di follow-up, testo semplice, con un solo pulsante/link alla pagina
      della brochure con `?da=brochure`. Parte dalla posta del server (SPF/DKIM
      a posto, vedi memoria), mai da Brevo.
- R6  Destinatari: gli indirizzi del registro della lezione gratuita
      (`lezione-zero/iscritti.csv`) che NON compaiono in `course_payments`.
      Esclusi gli indirizzi nostri (info@, Marco, "Prova tecnica").
- R7  L'invio e' uno strumento armato da segreto (`BROCHURE_TOKEN`, stesso
      schema di rinvia.php), con elenco dei serviti per non mandare due volte,
      e va disarmato dopo l'uso togliendo il segreto. Si lancia solo dopo il
      14/09, su ok esplicito di Marco.
- R8  Ogni invio scrive una riga in `lezione-zero/invii.log`.

## Fuori scopo
- Un pannello per rimandare o vedere chi ha cliccato: si guarda `?da=brochure`
  nel registro delle iscrizioni al corso.

## Come e' finita (10/09/2026)

- R1: sei pagine invece di sette, per non lasciare pagine mezze vuote
  (`tools/corso-base/brochure_corso.py`, PDF + sei PNG a 110 dpi).
- R2: `grav-site/user/pages/assets/corso-base/brochure/` va in git e sul
  server col passo FTP degli assets, che era gia' ricorsivo.
- R3: `grav-site/root/corso-base-human-design-brochure.html`, testa/menu/piede
  copiati dalla pagina del corso; rewrite in `grav-site/root/.htaccess`.
  Le sei pagine sono impilate, il tocco su una apre il PNG a dimensione piena
  (su un telefono l'A4 rimpicciolita non si legge). Colonna fissa a destra con
  iscrizione, telefono, WhatsApp e mail; su schermo stretto diventa barra in
  fondo con "Chiama" e "Iscriviti al corso".
- R4: link al PDF in coda alle pagine.
- R5/R6/R8: `grav-site/root/lezione-zero/brochure-mail.php`. Destinatari =
  registro della lezione, meno chi sta in `course_payments`, meno i nostri
  indirizzi, meno chi e' gia' in `brochure-inviati.txt`. Se la banca dati non
  risponde non parte niente. Ogni invio scrive in `invii.log`.
- R7: si arma col segreto `BROCHURE_TOKEN` (secret GitHub, scritto nel .env dal
  deploy). Senza segreto lo script risponde 404. Prima di mandare:
  `?t=SEGRETO&elenco=1` mostra a chi scriverebbe, `?t=SEGRETO&prova=indirizzo`
  manda una copia sola. Si manda a tutti solo con `&vai=1`, dopo il 14/09 e
  solo su via libera di Marco. Disarmare = svuotare il secret.
