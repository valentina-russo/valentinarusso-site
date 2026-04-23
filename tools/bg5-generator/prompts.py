"""
BG5 Blueprint — 19 prompt template per qualsiasi cliente.
I prompt sono generici: i dati specifici del cliente vengono iniettati via {placeholder}.
Claude usa la KB HD nel system prompt per elaborare i dettagli.
"""

import json
from pathlib import Path

# ─── SYSTEM PROMPT BUILDER ───────────────────────────────────────────────────

def load_kb() -> str:
    kb_path = Path("D:/HDcalcolatoreitaliano/hd-system-prompt.md")
    return kb_path.read_text(encoding="utf-8") if kb_path.exists() else ""

def load_gates_db() -> dict:
    """Carica gates.json (64 gate HD con nome italiano e dati linee 1-6)."""
    # Prima cerca nella cartella data/ relativa a questo file (funziona su GitHub Actions)
    local_path = Path(__file__).parent / "data" / "gates.json"
    # Poi fallback al percorso assoluto locale Windows
    fallback_path = Path("D:/HDcalcolatoreitaliano/src/data/library/gates.json")
    path = local_path if local_path.exists() else (fallback_path if fallback_path.exists() else None)
    if path is None:
        return {}
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return {}

def load_writing_rules() -> str:
    rules_path = Path(__file__).parent / "writing_rules.md"
    return rules_path.read_text(encoding="utf-8") if rules_path.exists() else ""

def load_voice() -> str:
    voice_path = Path(__file__).parent / "valentina_voice.md"
    return voice_path.read_text(encoding="utf-8") if voice_path.exists() else ""

def build_system_prompt(kb: str = "", writing_rules: str = "", voice: str = "") -> str:
    if not kb:
        kb = load_kb()
    if not writing_rules:
        writing_rules = load_writing_rules()
    if not voice:
        voice = load_voice()
    # Excerpt the most actionable sections from the voice file
    voice_excerpt = voice[:4000] if voice else ""
    return f"""Sei Valentina Russo, consulente BG5 certificata. Scrivi in italiano, in prima persona, rivolgendoti direttamente al cliente ("tu", "ti", "il tuo"). Valentina non è psicologa.

================================================================
REGOLE DI SCRITTURA ITALIANA (VINCOLI ASSOLUTI — MAI VIOLARE)
================================================================

{writing_rules}

================================================================
CONTROLLO PRIMA DI CONSEGNARE OGNI SEZIONE
================================================================

Prima di restituire il testo, rileggilo e verifica punto per punto:

1. Nessuna frase con "non X ma Y" in nessuna delle sue varianti (anche con il punto al posto della virgola, anche con parole sinonimiche). Se trovi una negazione trampolino, riscrivi la tesi in forma affermativa diretta.

2. Nessuna apertura di paragrafo ripetuta ("C'è un X che...", "Esiste un X che...", "Quando un...", "Ho notato che..."). Ogni paragrafo parte con un registro diverso: scena concreta, affermazione diretta, domanda implicita, fatto verificabile, dettaglio sensoriale, nome proprio di una situazione, scelta che qualcuno deve fare.

3. Ogni parola astratta (consapevolezza, presenza, autenticità, profondo, allineamento, responsabilità) deve essere ancorata a un dettaglio concreto nelle righe accanto, altrimenti va tolta.

4. Nessuna tripletta. Mai tre aggettivi, tre verbi, tre elementi di alcun tipo in sequenza. Due o uno.

5. Nessuna meta-frase ("è importante", "è chiaro che", "è fondamentale", "è essenziale", "è cruciale", "va detto che", "vale la pena sottolineare").

6. I pensieri si sviluppano per tre o quattro righe prima del punto. Congiunzioni naturali (perché, quando, mentre, dove, finché, così che) legano i periodi. Nessuna sequenza di frasi telegrafiche.

7. Nessuna difesa preventiva ("so che sembra strano ma...", "qualcuno potrebbe obiettare...", "attenzione però...").

8. Le chiusure contengono un gesto osservabile, un numero, una scelta pratica, una conseguenza nel tempo. Niente chiusure da comunicato stampa ("questo è il cuore di tutto", "ecco perché conta").

9. Nessun em-dash nel testo che generi. Usa virgole, punti, parentesi tonde, o il middle dot (·) per separatori visivi.

10. Se stai applicando la stessa strategia di riformulazione per il decimo paragrafo di fila, cambiala. La varietà delle forme conta più dell'aderenza meccanica a una regola singola.

================================================================
LESSICO — REGOLA ASSOLUTA: HUMAN DESIGN PURO
================================================================

Usa ESCLUSIVAMENTE la terminologia Human Design classica. Zero termini BG5.

Terminologia corretta:
- Tipo Energetico: Generatore, Generatore Manifestante, Manifestatore, Proiettore, Riflettore
- Centri: Sacrale, Plesso Solare, Milza, Cuore/Ego, G/Sé, Gola, Ajna, Testa, Radice
- Autorità: Plesso Solare, Sacrale, Splenica, Ego, G/Sé, Mentale, Lunare
- Strategia: Rispondere, Rispondere poi Informare, Informare prima di agire, Aspettare l'invito, Attendere un ciclo lunare
- Canale, Porta, Linea, Profilo, Definizione, Croce di Incarnazione, Autorità Interiore

NON usare: Costruttore Classico/Rapido, Guida, Iniziatore, Valutatore, Risorsa Energetica, Intelligenza Emotiva, BG5 Business Blueprint.
NON usare terminologia Gene Keys (Shadow/Gift/Siddhi, Genius Sequence, Richard Rudd).

================================================================
REGOLA CRITICA — CONOSCENZA HD: VERIFICA SEMPRE NELLA KB
================================================================

PRIMA di affermare qualunque meccanica Human Design (come funziona un tipo, cosa fa un centro, cosa significa un canale, come opera un'autorità, cosa indica una porta), VERIFICA nella Knowledge Base HD in fondo a questo prompt.

Se il dato NON è nella KB, non inventare. Usa formulazioni come "in Human Design, questo tipo è associato a..." oppure attieniti a ciò che la KB documenta esplicitamente.

Questo vale in particolare per:
- Centri aperti: NON inventare "domande trappola" non documentate
- Porte sospese: priorità ai pianeti personali (Sole, Terra, Luna, Mercurio, Venere, Marte). Evita porte da pianeti lenti (Urano, Nettuno, Plutone, Nodi) a meno che non siano le uniche presenti
- Autorità Mentale/Ambientale (Proiettore Mentale): la lettura della carta funziona SEMPRE e non richiede invito. È la COMUNICAZIONE degli insight all'altro che richiede l'invito. Non confondere le due cose.
- Porte sospese: si attivano per connessione elettromagnetica con qualcuno che ha la porta opposta, MA ANCHE quando il centro si attiva tramite transito o connessione con qualcuno

Questo documento viene venduto a €90-€147. Il cliente deve sentire che ha in mano qualcosa di personale e concreto, non un testo generico.

================================================================
VOCE DI VALENTINA — COME SCRIVE E PARLA (estratto dal canale YouTube)
================================================================

{voice_excerpt}

================================================================
KNOWLEDGE BASE BG5 / HUMAN DESIGN:
================================================================
{kb[:8000]}
"""


# ─── CHART BLOCK (iniettato in ogni prompt) ──────────────────────────────────

CHART_BLOCK = """DATI DELLA CARTA:
- Nome: {name}
- Tipo Energetico: {career_type}
- Strategia: {strategy}
- Autorità: {authority}
- Profilo: {profile} ({profile_name})
- Definizione: {definition}
- Firma di allineamento: {signature}
- Tema del non-Sé: {non_self}

REGOLA DI GENERE (VINCOLANTE): {gender_rule}

CENTRI DEFINITI: {defined_centers}
CENTRI APERTI: {undefined_centers}

CANALI ATTIVI:
{channels}

ARCHITETTURA COGNITIVA (frecce Variable):
{variable_arrows}
"""


# ─── 19 SECTION PROMPTS ─────────────────────────────────────────────────────

SECTION_PROMPTS = {

# =========================================================
# PARTE 1 — IDENTITÀ ENERGETICA
# =========================================================

"intro": CHART_BLOCK + """

Scrivi la sezione di APERTURA della Carta Human Design per {name}.
Questa sezione fa due cose in uno: accoglie il lettore E insegna le fondamenta necessarie per capire tutte le sezioni successive.

IMPORTANTE:
- Non interpretare ancora la carta di {name} in modo specifico qui. Serve prima la lezione base.
- La carta personale di {name} verrà interpretata nelle sezioni successive (Tipo, Autorità, Profilo, Centri, Canali, Porte). Qui spieghi solo cosa sono questi elementi in generale.

Lunghezza: 850-1000 parole. Paragrafi discorsivi, niente elenchi puntati.

Struttura in due blocchi fluidi:

BLOCCO 1 — L'apertura personale (300-350 parole):
1. Cosa tiene in mano {name}: una mappa energetica personalizzata, non un test della personalità né un oroscopo. Basata su data, ora e luogo di nascita esatti.
2. A cosa serve davvero questo documento: capire come {name} è progettata per lavorare, decidere, comunicare, esistere nel mondo.
3. Un invito a leggerlo senza fretta, tornandoci più volte. La prima lettura stupisce, la seconda volta si riconosce.

BLOCCO 2 — "Come si legge il tuo Bodygraph" — mini-lezione di base (550-650 parole):

Questo è il manuale d'istruzioni della grafica che {name} ha davanti. Spiega ciascuno di questi elementi, in paragrafi discorsivi, come se stessi guidando {name} a guardare la carta per la prima volta:

1. Cos'è il Bodygraph: una figura umana stilizzata con nove forme geometriche (i centri), collegate da linee (i canali) composte da 64 porte numerate. Ogni carta è unica perché combina date, ora e luogo di nascita in un'impronta irripetibile.

2. I 9 Centri energetici: elenca i nove centri (Testa, Ajna, Gola, G/Sé, Cuore/Ego, Plesso Solare, Sacrale, Milza, Radice) spiegando brevemente di cosa si occupa ciascuno. Un centro COLORATO è definito (produce energia costante e affidabile). Un centro BIANCO è aperto (riceve e amplifica l'energia degli altri).

3. Le linee NERE e ROSSE — i due lati della carta: spiega che ogni carta contiene due livelli sovrapposti. Il livello NERO è la Personalità (conscia): ciò che la persona riconosce come "io sono così", le energie con cui si identifica. Il livello ROSSO è il Design (inconscio): energie che agiscono sotto la soglia della consapevolezza, che gli altri vedono in noi prima di noi stessi. Il Design si calcola 88 gradi solari prima della nascita (circa 88 giorni prima). Quando una porta è attiva sia in Personalità (nero) sia in Design (rosso), appare come una striscia bicolore.

4. Le Porte: ciascuno dei 9 centri ha un certo numero di porte numerate da 1 a 64 (sono 64 in totale, distribuite sulla superficie della carta). Una porta è come una soglia di un certo tema archetipico (coraggio, ascolto, direzione, limiti, ecc.). Quando è ATTIVA, quella porta trasmette il suo tema nella vita della persona.

5. I Canali: un canale è il filo che collega due porte in due centri diversi. Se ENTRAMBE le porte ai capi del canale sono attive, il canale si dice DEFINITO: il flusso tra i due centri è costante, sempre acceso, e diventa un talento strutturale. Se una sola porta è attiva e l'altra è vuota, si parla di PORTA SOSPESA: è un tema presente ma che cerca completamento all'esterno (si "accende" quando si incontra qualcuno con la porta opposta, o durante certi transiti planetari).

6. Le Linee: ogni porta ha 6 linee interne (1-6), ciascuna con un tema specifico. Le linee più basse (1-2) tendono al personale e all'introspettivo, le linee più alte (5-6) al transpersonale e al ruolo pubblico. Le linee saranno rilevanti soprattutto nella parte dedicata al Profilo.

Chiusura del blocco: queste sono meccaniche che il corpo mette già in atto ogni giorno. Dargli un nome non le crea, le rende visibili. Da qui in poi ogni sezione del documento entra nel dettaglio dei pezzi specifici della carta di {name}.

Tono: caldo, diretto, accogliente, ma con il rigore di chi sta spiegando un sistema preciso. Inizia direttamente col contenuto, non riscrivere il titolo.""",

"tipo_strategia": CHART_BLOCK + """

Scrivi la sezione "Il tuo Tipo Energetico e la Strategia" per {name}.
Tipo Energetico: {career_type}
Strategia: {strategy}

IMPORTANTE — terminologia:
- Se {career_type} è "Proiettore" e {authority} è "Mentale", questa persona si chiama "Proiettore Mentale" — usa questo termine. MAI dire "Proiettore con autorità mentale" o "Proiettore ad autorità mentale".
- La Firma di allineamento e il Tema del Non-Sé hanno una sezione dedicata più avanti: NON trattarli qui.

Lunghezza: 700-900 parole. Paragrafi discorsivi.

Cosa includere:
1. Cosa significa essere un {career_type} nel mondo del lavoro: la meccanica energetica concreta. Basati sulla KB HD. Se il tipo è Proiettore, distingui tra Proiettore Energetico e Proiettore Mentale (nessun centro motore definito): per il Mentale la lettura della carta è SEMPRE disponibile, è la comunicazione degli insight all'altro che richiede l'invito.
2. Cosa distingue questo tipo dagli altri: capacità uniche, limiti strutturali, modalità operativa naturale.
3. La Strategia "{strategy}": spiega ogni passaggio in modo pratico — riunioni, proposte, decisioni di carriera, collaborazioni.
4. Un esempio concreto di una giornata lavorativa "allineata" vs una "non allineata".
5. Come orientare settimana e scelte di business a partire da questa meccanica.

Inizia col contenuto, senza riscrivere il titolo.""",

"autorita": CHART_BLOCK + """

Scrivi la sezione "La tua Autorità Interna" per {name}.
Autorità: {authority}

IMPORTANTE — terminologia:
- Usa "Autorità Interna" (NON "Interiore").
- Descrivi l'Autorità come "il tuo modo affidabile di prendere decisioni" (NON "di decidere", NON "di sapere ciò che è giusto").
- Se {authority} è "Mentale", la persona va chiamata "{career_type} Mentale" (es. "Proiettore Mentale"). MAI dire "Proiettore con autorità mentale".

Lunghezza: 500-650 parole.

Cosa includere:
1. Cos'è l'Autorità Interna nel sistema Human Design: il tuo modo affidabile di prendere decisioni, un meccanismo corporeo che opera prima del ragionamento mentale.
2. Come funziona l'autorità "{authority}" nello specifico: descrivi la meccanica precisa di come questa autorità opera nel corpo. Come si manifesta fisicamente, quali sono i segnali, qual è il timing delle decisioni.
3. Perché un {career_type} con questa autorità deve rispettare il suo timing decisionale: come interagisce col tipo e la strategia.
4. Come usarla in contesti lavorativi reali: quando arriva una proposta di progetto, un nuovo cliente, un cambio di collaborazione. Cosa fare concretamente per prendere decisioni allineate.
5. Una pratica concreta quotidiana per allenarsi a riconoscere i segnali della propria Autorità Interna.
6. Il segnale che stai ignorando la tua Autorità Interna: pattern tipici di decisioni prese nel modo sbagliato.

Tono diretto, pragmatico. Inizia col contenuto.""",

"profilo": CHART_BLOCK + """

Scrivi la sezione "Il tuo Profilo {profile} — {profile_name}" per {name}.
Profilo: {profile} ({profile_name})

IMPORTANTE — formato e struttura:
- Il titolo DEVE contenere sempre il numero del profilo "{profile}" (es. "Il tuo Profilo 2/4"), non solo i nomi delle linee.
- Devi separare CHIARAMENTE la linea conscia (la prima cifra di {profile}, che deriva dal Sole di Personalità) dalla linea inconscia (la seconda cifra, che deriva dal Sole di Design).
- La struttura del corpo del testo deve essere: prima UN BLOCCO dedicato alla linea conscia, poi UN BLOCCO dedicato alla linea inconscia, poi l'interazione tra le due.

{profile_coaching}

Lunghezza: 800-1000 parole.

Cosa includere:
1. Apertura (1 paragrafo): spiega che il profilo {profile} è composto da una linea conscia (la prima cifra, che la persona riconosce in sé) e da una linea inconscia (la seconda cifra, che gli altri vedono prima che lei se ne accorga).

2. BLOCCO LINEA CONSCIA — "La tua parte conscia: linea [PRIMA CIFRA DI {profile}]"
   - Come questa linea opera nella vita professionale: il ruolo, il modo di imparare, le necessità strutturali.
   - Come la persona sperimenta questa linea dall'interno.
   - Un esempio lavorativo concreto.

3. BLOCCO LINEA INCONSCIA — "La tua parte inconscia: linea [SECONDA CIFRA DI {profile}]"
   - Come questa linea opera: le relazioni, il networking, il modo di crescere professionalmente.
   - Come gli altri la percepiscono (anche prima che lei la riconosca in sé).
   - Un esempio lavorativo concreto.

4. BLOCCO INTERAZIONE — come le due linee dialogano: il ritmo specifico, le tensioni, le sinergie. Come costruire la propria attività con questo profilo: quali strategie funzionano.

Inizia col contenuto.""",

"definizione": CHART_BLOCK + """

Scrivi la sezione "La tua Definizione: {definition}" per {name}.
Definizione: {definition}

Lunghezza: 500-650 parole.

Cosa includere:
1. Cosa significa avere una Definizione "{definition}": spiega la meccanica energetica di questa definizione specifica. Come sono collegati (o separati) i centri definiti di {name}. Quanti gruppi di centri ci sono, come comunicano tra loro
2. Come questo si sente nel quotidiano: la sensazione interna, il rapporto con l'autonomia vs il bisogno degli altri
3. Il vantaggio nel lavoro: come questa definizione influenza la capacità di lavorare da soli o in team
4. La potenziale trappola: dove questa definizione può creare fraintendimenti con gli altri o limitazioni non necessarie
5. Come interagisce con il profilo {profile}: sinergie specifiche tra definizione e profilo
6. Come questa definizione si manifesta nelle collaborazioni e nelle relazioni professionali

Inizia col contenuto.""",


"firma_nonself": CHART_BLOCK + """

Scrivi la sezione "La tua Firma di Allineamento e il Tema del Non-Sé" per {name}.
Tipo Energetico: {career_type}
Firma: {signature}
Non-Sé: {non_self}

Lunghezza: 600-800 parole.

Cosa includere:
1. Apertura (1 paragrafo): in Human Design ogni tipo energetico ha due segnali interni che funzionano come bussola biologica — una Firma che si accende quando vivi allineata al tuo design, e un Tema del Non-Sé che emerge quando stai forzando qualcosa che non ti appartiene. Non sono emozioni fra tante, sono indicatori meccanici.

2. La tua Firma: {signature}
   - Descrivi cos'è fisicamente e emotivamente questa Firma per un {career_type}.
   - Dove la senti nel corpo, come si manifesta in una giornata di lavoro concreta, quali segnali dà nelle relazioni professionali.
   - Un esempio specifico di situazione lavorativa in cui la Firma appare.
   - Perché è un feedback in tempo reale (non un obiettivo da raggiungere).

3. Il tuo Tema del Non-Sé: {non_self}
   - Descrivi cos'è fisicamente e emotivamente questo Non-Sé per un {career_type}.
   - Le manifestazioni concrete nel corpo e nel comportamento quando compare.
   - Un esempio specifico di situazione professionale in cui il Non-Sé prende il sopravvento.
   - Cosa fare quando lo riconosci: azioni pratiche per riallinearsi (non "respira" generico, ma gesti osservabili).

4. Chiusura (1 paragrafo): imparare a distinguere Firma e Non-Sé è la pratica più concreta che questo documento può offrire. Un esercizio semplice per allenarsi: a fine giornata, una riga per la Firma, una riga per il Non-Sé.

Inizia col contenuto.""",


# =========================================================
# PARTE 2 — MECCANICA ENERGETICA
# =========================================================

"centri_definiti": CHART_BLOCK + """

Scrivi la sezione "I tuoi Centri Definiti: i tuoi superpoteri costanti" per {name}.

Centri definiti del cliente: {defined_centers}

Lunghezza: 1000-1200 parole.

Struttura:
1. Introduzione (1 paragrafo): cosa vuol dire avere un centro definito — è un'energia che emetti 24/7, che le persone intorno a te sentono e da cui sono influenzate. È la tua costante.

2. Un paragrafo DEDICATO a ciascun centro definito (in ordine):
   - Per ogni centro, spiega:
     a) Cosa ti dà SEMPRE, in modo affidabile
     b) Come gli altri lo sentono quando sei in una stanza
     c) Come si manifesta nel lavoro quotidiano (esempi concreti di situazioni)
     d) La potenziale trappola (dove puoi forzare questa energia e bruciarti)

{centers_coaching}

Tono: concreto, applicato al lavoro. Niente teoria astratta. Inizia col contenuto.""",

"centri_aperti": CHART_BLOCK + """

Scrivi la sezione "I tuoi Centri Aperti e il Campo di Attrazione" per {name}.

Centri aperti del cliente: {undefined_centers}

Lunghezza: 1000-1200 parole (se i centri aperti sono pochi, riduci proporzionalmente a 500-700 parole).

Struttura:
1. Introduzione (1 paragrafo): un centro aperto è uno spazio ricettivo dove assorbi l'energia degli altri, la amplifichi, e (quando non te ne accorgi) la scambi per tua. Ogni centro aperto diventa una fonte di saggezza unica col passare del tempo.

2. Un paragrafo DEDICATO a ciascun centro aperto (in ordine):
   - Per ogni centro, spiega:
     a) La pressione/condizionamento tipico (cosa assorbi dagli altri)
     b) La "domanda trappola" del non-sé
     c) Come si trasforma in saggezza quando riconosci la meccanica
     d) Un esempio lavorativo concreto di come NON farti travolgere

3. Il tuo Campo di Attrazione (1 paragrafo finale, 150-200 parole):
   - Il principio: dove sei DEFINITO attrai naturalmente chi ha quel centro APERTO, e viceversa
   - I centri definiti di {name} ({defined_centers}) attraggono chi cerca quella stabilità
   - I centri aperti ({undefined_centers}) attraggono chi ha quei centri definiti
   - Cosa significa concretamente per il tipo di cliente e collaboratore che gravita naturalmente verso {name}
   - Chiudi con: costruire un'attività consapevole significa sapere dove stai offrendo valore strutturale e dove stai ricevendo

Mai dire "stai attento a", dì sempre "osserva come". Inizia col contenuto.""",


# attrazione è incorporata in centri_aperti — non più sezione separata


"canali": CHART_BLOCK + """

Scrivi la sezione "I tuoi Canali Attivi — i tuoi talenti strutturali" per {name}.

Canali attivi del cliente:
{channels}

Lunghezza: 900-1100 parole.

Struttura:
1. Introduzione (1 paragrafo): cos'è un canale in BG5/HD — due porte attive ai due estremi di un flusso energetico tra due centri. Un canale è un "filo diretto" sempre acceso, un talento strutturale.

2. Un paragrafo dedicato a ciascun canale (in ordine), spiegando per ciascuno:
   - Il dono specifico del canale (cosa permette di fare nel concreto)
   - Come si manifesta nel lavoro quotidiano (un esempio reale)
   - La trappola tipica (dove questo dono si può corrompere se non è usato in risposta)

{channels_coaching}

Chiusura (1 paragrafo): la combinazione di questi canali dice qualcosa di specifico su come {name} costruisce valore nel mondo. Descrivi il quadro complessivo che emerge dalla combinazione unica di canali.

Inizia col contenuto.""",

"porte": CHART_BLOCK + """

Scrivi la sezione "Le Porte più rilevanti: ciò che trasmetti oltre i canali" per {name}.

Porte sospese (hanging gates) del cliente:
{gates_detail}

Lunghezza: 600-800 parole.

Cosa includere:
1. Introduzione: una porta sospesa è una porta attiva che NON forma un canale completo. Si attiva quando qualcuno con la porta opposta entra in campo, MA ANCHE quando il centro corrispondente si attiva tramite transito o connessione. È un tema ricorrente, un'aspirazione strutturale.

2. Commenta 5-6 porte più rilevanti per questa persona. Per ciascuna: il tema e il ruolo nella vita professionale. Basati sulla KB HD per ogni porta — non inventare temi non documentati.

CRITERI DI SELEZIONE (in ordine di priorità):
a) Pianeti personali per primi: porte da Sole, Terra, Luna, Mercurio, Venere, Marte (sia Personalità che Design).
b) Poi Giove e Saturno se rilevanti per il tema professionale.
c) Nodi Nord/Sud solo se non ci sono abbastanza porte personali.
d) Urano, Nettuno, Plutone: evita, a meno che la porta non sia fortemente coerente con il resto del profilo.
e) Dentro un centro, privilegia la porta col numero di linea più basso (linee 1-2 sono più stabili).

3. Integra la scelta col resto del profilo ({career_type}, profilo {profile}, definizione {definition}, canali attivi già descritti).

Chiusura: non serve "attivare" queste porte facendo qualcosa di specifico. Sono già attive. Servono come lente per capire perché certe cose ti chiamano ricorrentemente.

Inizia col contenuto.""",

# =========================================================
# PORTE LUMINARI — L'Analisi del tuo Genio (6 porte chiave)
# Sole, Terra e Luna × Personalità (conscio) + Design (inconscio)
# =========================================================

"porta_sole_p": CHART_BLOCK + """

Scrivi la sezione "Il tuo Lavoro di Vita — Sole di Personalità" per {name}.

DATI DELLA PORTA (Sole Personalità):
{porta_sole_p_data}

Il Sole di Personalità è la porta che irradia da te in modo cosciente: è ciò che altre persone sentono quando entri in una stanza, l'energia che porti senza accorgertene. Nel contesto del lavoro, rappresenta il "Lavoro di Vita" — la direzione in cui la tua espressione diventa più nitida, più utile, più riconosciuta.

Lunghezza: 450-550 parole. Paragrafi discorsivi.

Cosa includere:
1. Apertura: cosa significa avere questa porta specifica come Sole di Personalità. Parti dal nome della porta e dal significato del centro in cui si trova.
2. Il tema della linea nel contesto professionale: come questo si manifesta nelle scelte di carriera, nel modo di posizionarsi, nel tipo di contributo che emerge naturalmente.
3. Un esempio concreto di situazione lavorativa in cui questa porta si esprime al meglio.
4. Il segnale che stai allineando questa porta al tuo lavoro (cosa senti fisicamente, cosa notano gli altri).
5. Il segnale che stai tradendo questa porta (cosa sente il corpo quando forzi un ruolo che non ti appartiene).

Inizia col contenuto, senza riscrivere il titolo.""",

"porta_terra_p": CHART_BLOCK + """

Scrivi la sezione "La tua Evoluzione — Terra di Personalità" per {name}.

DATI DELLA PORTA (Terra Personalità):
{porta_terra_p_data}

La Terra di Personalità è sempre opposta al Sole di Personalità: è la base cosciente che ti tiene radicata mentre il Sole irradia. Nel lavoro rappresenta la "Evoluzione" — la direzione in cui cresci nel tempo, la terra su cui poggi i piedi quando costruisci qualcosa di duraturo.

Lunghezza: 450-550 parole. Paragrafi discorsivi.

Cosa includere:
1. Apertura: cosa significa avere questa porta specifica come Terra di Personalità. Il nome della porta, il centro, il rapporto complementare col Sole di Personalità.
2. Il tema della linea come elemento di stabilità: cosa ti ancora, cosa ti mantiene coerente nei momenti di cambiamento.
3. Come questa porta indica il tipo di evoluzione professionale più naturale per te (non una carriera lineare, ma il modo specifico in cui maturi nel lavoro).
4. Un esempio concreto di momento di crescita professionale in cui questa porta si è manifestata.
5. La trappola: cosa succede quando ignori questa base e insegui solo il Sole senza la Terra.

Inizia col contenuto.""",

"porta_luna_p": CHART_BLOCK + """

Scrivi la sezione "Il tuo Ritmo Consapevole — Luna di Personalità" per {name}.

DATI DELLA PORTA (Luna Personalità):
{porta_luna_p_data}

La Luna di Personalità descrive il ritmo emotivo cosciente: come percepisci il tempo, come riconosci le fasi del lavoro, come fai emergere ciò che sta maturando dentro di te. Non è una porta che irradia (come il Sole) né che radica (come la Terra) — è una porta che cicla.

Lunghezza: 400-500 parole. Paragrafi discorsivi.

Cosa includere:
1. Apertura: cosa significa avere questa porta come Luna di Personalità. Il nome della porta e il centro.
2. Come il tema della linea influenza il tuo ritmo di lavoro: quando l'energia sale, quando va lasciata decantare, quando è il momento di agire.
3. Come si manifesta nelle relazioni professionali continuative (clienti ricorrenti, collaboratori stabili, team di lavoro).
4. Un esempio pratico: un ciclo di progetto o di cliente visto attraverso questa porta.

Inizia col contenuto.""",

"porta_sole_d": CHART_BLOCK + """

Scrivi la sezione "La tua Radiosità — Sole di Design" per {name}.

DATI DELLA PORTA (Sole Design):
{porta_sole_d_data}

Il Sole di Design è la porta che irradia da te in modo inconscio: è ciò che le altre persone sentono ancora prima che tu abbia detto una parola. Non la vedi quando ti guardi allo specchio, ma gli altri sì. Nel contesto del lavoro, rappresenta la "Radiosità" — la tua aura professionale, il campo che precede il tuo messaggio.

Lunghezza: 450-550 parole. Paragrafi discorsivi.

Cosa includere:
1. Apertura: cosa significa avere questa porta come Sole di Design. Il fatto che sia inconscia significa che è un dono che metti in campo senza sforzo ma spesso senza riconoscerlo.
2. Il tema della linea come qualità dell'aura: che tipo di presenza trasmetti quando entri in un contesto professionale.
3. Come gli altri descrivono questa tua qualità (con parole che a te potrebbero suonare strane).
4. Un esempio concreto di situazione in cui questa radiosità ti ha aperto una porta senza che tu abbia dovuto spiegare nulla.
5. Perché è importante non cercare di "replicare" la radiosità del Sole di Personalità: quella è cosciente, questa è strutturale.

Inizia col contenuto.""",

"porta_terra_d": CHART_BLOCK + """

Scrivi la sezione "Il tuo Scopo — Terra di Design" per {name}.

DATI DELLA PORTA (Terra Design):
{porta_terra_d_data}

La Terra di Design è la porta più nascosta e più profonda delle quattro luminari: è la base inconscia che orienta tutta la tua vita senza che tu ne sia consapevole. Nel BG5 rappresenta lo "Scopo" — non come obiettivo da raggiungere, ma come terreno da cui il tuo lavoro prende forma.

Lunghezza: 450-550 parole. Paragrafi discorsivi.

Cosa includere:
1. Apertura: cosa significa avere questa porta come Terra di Design. Il nome della porta, il centro, la sua natura di "sostrato inconscio".
2. Il tema della linea come vocazione profonda: non un mestiere specifico, ma un modo di stare al mondo che cerca sempre di emergere attraverso qualunque cosa tu faccia.
3. Come questa porta influenza le scelte di fondo (perché certi settori ti attraggono e altri ti respingono, al di là della logica).
4. Il segnale che stai lavorando in accordo col tuo Scopo: come si sente a fine giornata, nel corpo.
5. Il segnale che stai contro lo Scopo: il tipo di stanchezza strutturale che nasce quando la Terra di Design viene negata.

Inizia col contenuto.""",

"porta_luna_d": CHART_BLOCK + """

Scrivi la sezione "La tua Risonanza — Luna di Design" per {name}.

DATI DELLA PORTA (Luna Design):
{porta_luna_d_data}

La Luna di Design descrive la risonanza inconscia: il modo in cui il tuo sistema ciclico reagisce al mondo prima che la mente se ne accorga. È la porta che spiega perché certe persone, certi ambienti, certe proposte ti "chiamano" e altri ti respingono senza un motivo razionale.

Lunghezza: 400-500 parole. Paragrafi discorsivi.

Cosa includere:
1. Apertura: cosa significa avere questa porta come Luna di Design. Il nome della porta, il centro, la natura ciclica.
2. Come il tema della linea si manifesta come "sensore" nelle relazioni professionali (chi ti risuona, chi ti prosciuga).
3. Un esempio di intuizione ricorrente che nasce da questa porta: una sensazione che ti coglie prima che la mente abbia analizzato la situazione.
4. Come imparare ad ascoltare questa risonanza senza razionalizzarla subito.

Inizia col contenuto.""",

"croce": CHART_BLOCK + """

Scrivi la sezione "La tua Croce di Incarnazione" per {name}.
Croce: {cross}
Tipo di angolo: {cross_type}
Tema di Vita: {life_theme}

Lunghezza: 500-650 parole.

Cosa includere:
1. Cos'è la Croce di Incarnazione in BG5/HD: la combinazione dei 4 gate dei luminari alla nascita (Sole e Terra di Personalità + Sole e Terra di Design). Rappresenta il "tema di vita".

2. Cosa significa il tipo di angolo "{cross_type}": Right Angle = cammino personale, Left Angle = cammino transpersonale, Juxtaposition = cammino fisso.

3. La croce specifica "{cross}" in particolare: descrivi il tema di questa croce basandoti sulle 4 porte che la compongono. Spiega cosa queste porte significano insieme come tema di vita nel contesto lavorativo.

4. Il quarto (Quarter) di appartenenza di questa croce e cosa significa per il modo in cui lo scopo emerge nella vita di {name}.

5. Applicazione al lavoro: in quali contesti {name} porta naturalmente il suo contributo più grande? Che tipo di rinnovamento/cambiamento/contributo porta nei contesti professionali?

Tono quasi poetico ma concreto. Inizia col contenuto.""",

# =========================================================
# PARTE 3 — ARCHITETTURA COGNITIVA
# =========================================================

"architettura_cognitiva": CHART_BLOCK + """

Scrivi la sezione "La tua Architettura Cognitiva" per {name}.

Le 4 frecce Variable indicano COME la mente e il corpo di {name} elaborano le informazioni:
{variable_arrows}

Legenda delle frecce:
- Sole Design (Digestione): indica l'orientamento del corpo nell'assimilare esperienze (Sinistra = modalità attiva/focalizzata, Destra = modalità ricettiva/adattiva)
- Nodo Design (Ambiente): indica come il corpo si orienta nello spazio e nei contesti (Sinistra = attivo nel costruire il contesto, Destra = recettivo al contesto)
- Sole Personalità (Motivazione): indica cosa muove la mente nelle scelte (Sinistra = orientato dalla visione interna, Destra = orientato dalla risposta all'esterno)
- Nodo Personalità (Prospettiva): indica come la mente percepisce e inquadra la realtà (Sinistra = prospettiva focalizzata/sequenziale, Destra = prospettiva panoramica/omnicomprensiva)

Lunghezza: 500-650 parole.

Cosa includere:
1. Introduzione breve (1 paragrafo): la Variabile in Human Design descrive il modo in cui questa persona è progettata per elaborare informazioni, prendere decisioni e orientarsi. Non è un tipo di personalità: è una meccanica corporea.

2. Commenta ciascuna delle 4 frecce in modo concreto per {name}:
   - Cosa significa quella direzione per il modo in cui lavora, studia, decide
   - Un esempio pratico in un contesto lavorativo reale
   - Come ignorare questa meccanica si traduce in difficoltà concrète

3. Il quadro complessivo: come le 4 frecce di {name} lavorano insieme. Che tipo di processo cognitivo emerge dalla combinazione specifica? Come si applica nel lavoro autonomo, nelle collaborazioni, nelle decisioni importanti?

NON includere interpretazioni di dieta o ambiente fisico. Concentrati sul processo mentale e cognitivo.

Tono: concreto, quasi da manuale operativo. Inizia col contenuto.""",

# =========================================================
# PARTE 4 — APPLICAZIONE AL BUSINESS (solo Completo)
# =========================================================

"offerte_allineate": CHART_BLOCK + """

Scrivi la sezione "Offerte allineate: costruisci dai tuoi Centri Definiti" per {name}.

Centri definiti: {defined_centers}

Lunghezza: 800-1000 parole.

Cosa includere:
1. Principio base: le offerte (servizi, prodotti, contenuti) più solide nascono dai centri DEFINITI, non da quelli aperti. Dai centri definiti proviene l'energia costante che può sostenere a lungo un'attività.

2. Per ciascun centro definito ({defined_centers}), proponi 2-3 tipi concreti di offerta che questa persona può costruire. Per ogni centro, spiega quale valore offre e quali servizi/prodotti nascono naturalmente da quell'energia.

3. Un framework operativo: "Quando stai progettando una nuova offerta, parti sempre chiedendoti da quale centro nasce il valore che stai offrendo."

4. Per {name} nello specifico: con la sua combinazione unica di centri definiti e aperti, quali sono le offerte più naturali? Suggerisci 3 esempi di offerte concrete che mettano insieme i suoi centri definiti.

Inizia col contenuto.""",

"voce_e_mercato": CHART_BLOCK + """

Scrivi la sezione "Voce, Vendita e Contenuti — come ti muovi nel mercato" per {name}.

Profilo: {profile} ({profile_name})
Tipo: {career_type}
Strategia: {strategy}
Autorità: {authority}
Centri definiti: {defined_centers}
Centri aperti: {undefined_centers}

Lunghezza: 1000-1200 parole. Paragrafi discorsivi.

Struttura in tre blocchi fluidi:

BLOCCO 1 — La tua Voce (300-350 parole):
1. Se la Gola è definita: quali canali la alimentano e come si manifesta l'espressione naturale di {name}
   Se la Gola è aperta: come {name} comunica con maggiore impatto quando non cerca di forzare una voce fissa
2. Come comunica un {career_type} con profilo {profile}: il registro naturale, il ritmo, la forma (parola scritta, orale, 1:1, contenuto)
3. 3-4 verbi d'azione che risuonano con questa voce specifica (es. guidare, esplorare, analizzare, costruire, connettere)
4. Il formato comunicativo più naturale per questo design — e il formato che rischia di essere una copia di qualcun altro

BLOCCO 2 — Vendita allineata (350-400 parole):
1. Come un {career_type} vende senza forzare: il processo naturale basato su strategia "{strategy}" e autorità "{authority}"
2. 3-4 cose che non funzionano per questo design specifico nella vendita (concrete, non generiche)
3. Il processo passo-passo di una conversazione di vendita allineata, dal primo contatto alla decisione
4. Come le due linee del profilo {profile} influenzano acquisizione clienti e networking

BLOCCO 3 — Contenuti magnetici (300-350 parole):
1. Principio: i contenuti più magnetici nascono dai centri DEFINITI e parlano ai dolori di chi ha quei centri APERTI
2. Per ciascun centro definito ({defined_centers}): 2-3 temi di contenuto concreti che {name} può creare con energia stabile
3. Ritmo di pubblicazione: come profilo {profile} e autorità "{authority}" suggeriscono la frequenza naturale. Nessun calendario editoriale rigido per questo tipo.

Inizia col contenuto.""",

# =========================================================
# PARTE 5 — CHIUSURA
# =========================================================

"suggerimenti": CHART_BLOCK + """

Scrivi la sezione di chiusura "Sintesi e 7 suggerimenti pratici" per {name}.

Lunghezza: 800-1000 parole.

Struttura:
1. Apertura (1 paragrafo, 80-100 parole): una sintesi in poche frasi di chi è {name} dalla prospettiva Human Design — {career_type} {profile}, con {definition}, i canali attivi, l'autorità {authority}. Questa apertura deve suonare come un ritratto vivo, non come un elenco.

2. SETTE SUGGERIMENTI NUMERATI. Ogni suggerimento ha:
   - Un titolo breve in **grassetto markdown**
   - 3-5 frasi di spiegazione concreta
   - Si riferisce a UN elemento specifico della sua carta, non è generico

I 7 suggerimenti devono coprire, in qualche forma:
a) Come usare la strategia "{strategy}" in pratica ogni settimana
b) Come onorare l'autorità {authority}: il timing decisionale specifico
c) Come alternare il ritmo delle due linee del profilo {profile}: quando ritirarsi e quando essere visibili
d) Come proteggere i centri aperti ({undefined_centers}): non farsi condizionare
e) Come lavorare con la propria architettura cognitiva: orientamento attivo o ricettivo nelle decisioni professionali
f) Come nutrire i canali attivi più importanti nel quotidiano
g) Come riconoscere la firma ({signature}) come feedback in tempo reale, e come distinguere il tema del non-sé ({non_self})

I suggerimenti devono essere AZIONI, non massime. NON scrivere "sii te stesso", "segui il tuo intuito". Scrivi cose come:
- "Prima di accettare un progetto, aspetta 24 ore e osserva il segnale del corpo la mattina dopo"
- "Una volta al mese dedica una giornata fuori ufficio e portaci le decisioni aperte: osserva cosa cambia"
- "Lavora 90 minuti, poi cammina 15 minuti fuori, poi altri 90 minuti"

3. Chiusura (1 paragrafo, 60-80 parole): un saluto finale personale di Valentina, caldo e concreto. Qualcosa del tipo "Questa lettura funziona come uno specchio da rileggere ogni pochi mesi. Torna alle pagine che ti colpiscono di più, prova una cosa alla volta nel tuo corpo e osserva la {signature} quando arriva. Per qualunque cosa, scrivimi a info@valentinarussobg5.com."

Inizia col contenuto.""",
}


# ─── FORMAT DATA BUILDER ─────────────────────────────────────────────────────

# Descrizioni brevi delle linee del profilo per il coaching hint
PROFILE_LINES = {
    "1": ("Investigatore (Investigator)", "Ha bisogno di costruire una base solida di conoscenza prima di agire. Studia a fondo, ricerca, vuole sentirsi sicura delle fondamenta. Insicurezza quando la base non è solida."),
    "2": ("Eremita (Hermit)", "Ha talenti naturali che spesso non riconosce. Ha bisogno di tempo da sola per ricaricarsi. Non impara studiando a tavolino: il suo processo è l'immersione naturale. Gli altri vedono doni che lei non vede, e la 'chiamano fuori' quando il mondo ne ha bisogno."),
    "3": ("Martire (Martyr)", "Impara attraverso l'esperienza diretta, il trial-and-error. Ha bisogno di provare le cose sulla propria pelle. Ogni 'errore' è un dato. La sua saggezza nasce dall'accumulo di esperienze vissute, non da teorie."),
    "4": ("Opportunista (Opportunist)", "La vita e il lavoro passano attraverso la rete di relazioni. Le opportunità arrivano da persone che già conosce. Ha bisogno di una base relazionale solida prima di esporsi. I cambiamenti avvengono sempre attraverso qualcuno della propria rete."),
    "5": ("Eretico (Heretic)", "Gli altri proiettano su di lei aspettative di soluzione. Vista come 'quella che può risolvere il problema'. Deve gestire le proiezioni: se non le soddisfa, la reputazione ne risente. Ha un impatto universalizzante quando è nel suo elemento."),
    "6": ("Modello di Ruolo (Role Model)", "Vive in tre fasi: sperimentazione (0-30), ritiro sul tetto (30-50), modello di ruolo (50+). Ha una visione panoramica, vede le cose dall'alto. Diventa un riferimento per gli altri con la maturità."),
}

# Coaching hints per i centri
CENTER_COACHING = {
    "Gola":            "Per la Gola parla della capacità di manifestare in azione e in voce.",
    "G/Sé":            "Per il G/Sé parla del senso di direzione e identità stabile.",
    "Cuore/Ego":       "Per il Cuore/Ego parla della forza di volontà, della capacità di tenere fede alle promesse, del valore che si attribuisce al proprio lavoro.",
    "Sacrale":         "Per il Sacrale, ricorda che è il motore che permette di lavorare a lungo MA solo quando risponde.",
    "Plesso Solare":   "Per il Plesso Solare parla dell'intelligenza emotiva, dell'onda che sale e scende, della profondità che arriva col tempo.",
    "Milza":           "Per la Milza parla dell'intuizione come scansione di sicurezza in tempo reale.",
    "Radice":          "Per la Radice parla della pressione evolutiva, del senso del ritmo, del tempismo.",
    "Testa":           "Per la Testa parla della pressione a trovare risposte e ispirazione.",
    "Ajna":            "Per l'Ajna parla della capacità di concettualizzare e formare opinioni.",
}


def build_format_data(chart: dict) -> dict:
    """Costruisce il dict di placeholder per i prompt a partire dal chart del cliente."""

    channels_str = "\n".join(
        f"  - {c['name']} — {c['title']} ({c['centers']})"
        for c in chart.get("channels", [])
    )

    # Profile coaching
    p = chart.get("profile", "1/3")
    line1, line2 = p.split("/") if "/" in p else ("1", "3")
    l1_name, l1_desc = PROFILE_LINES.get(line1, ("", ""))
    l2_name, l2_desc = PROFILE_LINES.get(line2, ("", ""))
    profile_coaching = f"""Dettagli linee:
- Linea {line1} — {l1_name}: {l1_desc}
- Linea {line2} — {l2_name}: {l2_desc}"""

    # Centers coaching for defined centers
    defined = chart.get("defined_centers", [])
    centers_lines = []
    for c in defined:
        hint = CENTER_COACHING.get(c, "")
        if hint:
            centers_lines.append(hint)
    # Count channels connecting to Gola for coaching
    gola_channels = sum(1 for ch in chart.get("channels", []) if "Gola" in ch.get("centers", ""))
    if gola_channels > 1 and "Gola" in defined:
        centers_lines.append(f"Nota: la Gola è collegata da {gola_channels} canali, il che la rende particolarmente potente nell'espressione.")
    centers_coaching = "Nota importante per ciascun centro:\n" + "\n".join(f"- {l}" for l in centers_lines) if centers_lines else ""

    # Channels coaching — brief hint about the combination
    ch_names = [c.get("title", c.get("name", "")) for c in chart.get("channels", [])]
    channels_coaching = ""
    if ch_names:
        channels_coaching = f"Usa le tue conoscenze BG5/HD per descrivere accuratamente ciascun canale. Questa persona ha {len(ch_names)} canali attivi."

    # Gates detail — usa hanging_gates_rich se disponibile (include pianeti e priorità)
    _SLOW   = {'Urano', 'Nettuno', 'Plutone'}
    _NODES  = {'Nodo Nord', 'Nodo Sud'}
    _SOCIAL = {'Giove', 'Saturno'}

    gates_db = load_gates_db()  # {str(gate_id): {nome, linee: {str(line): {nome, tema, dono, ombra}}}}

    gates_rich = chart.get("hanging_gates_rich", [])
    if gates_rich:
        gates_lines = []
        for entry in gates_rich:
            gate    = entry['gate']
            line    = entry['line']
            center  = entry['center_it']
            acts    = entry['activations']
            best    = entry['best_planet']

            # Formatta tutte le attivazioni: "Sole Personalità, Terra Design"
            acts_str = ', '.join(
                f"{a['planet']} {'Personalità' if a['col'] == 'P' else 'Design'}"
                for a in acts
            )

            # Nota per pianeti lenti o nodi
            if best in _SLOW:
                note = "  ← pianeta lento, usare solo se molto coerente col profilo"
            elif best in _NODES:
                note = "  ← nodo"
            else:
                note = ""

            # Arricchimento da gates_db: nome italiano + tema della linea specifica
            gate_info = gates_db.get(str(gate), {})
            gate_nome = gate_info.get("nome", "")
            linee_data = gate_info.get("linee", {})
            line_data  = linee_data.get(str(line), {})
            line_nome  = line_data.get("nome", "")
            line_tema  = line_data.get("tema", "")

            # Riga principale: "  Porta 61.3 (Testa) «Il Mistero» — Sole Personalità  ← nodo"
            nome_str = f" \u00ab{gate_nome}\u00bb" if gate_nome else ""
            gates_lines.append(f"  Porta {gate}.{line} ({center}){nome_str} — {acts_str}{note}")

            # Riga descrizione linea (se disponibile)
            if line_nome or line_tema:
                desc_parts = []
                if line_nome:
                    desc_parts.append(line_nome)
                if line_tema:
                    # Truncate a 180 chars per mantenere il contesto compatto
                    tema_short = line_tema[:180] + ("..." if len(line_tema) > 180 else "")
                    desc_parts.append(tema_short)
                gates_lines.append(f"    Linea {line} — {': '.join(desc_parts)}")

        gates_detail = "\n".join(gates_lines)

    else:
        # Fallback per chart vecchi senza hanging_gates_rich
        hanging = chart.get("hanging_gates", {})
        gates_lines = []
        for center, gates in hanging.items():
            gates_str = ", ".join(str(g) for g in gates)
            gates_lines.append(f"  - {center}: porte {gates_str}")
        gates_detail = "\n".join(gates_lines) if gates_lines else "(nessuna porta sospesa specificata)"

    # Luminary gates — Sole, Terra, Luna × Personalità + Design
    # Formato attivazioni: [['Sole', '15.2', '10.3'], ['Terra', '10.3', '15.2'], ['Luna', '...']...]
    # Index 1 = Personalità gate.line, index 2 = Design gate.line
    activations = chart.get("activations", [])

    # Mappa centro della porta (da gates.json contenuto → centro)
    def _center_for_gate(gate_id: int) -> str:
        """Ritorna il centro italiano della porta (o '?')."""
        info = gates_db.get(str(gate_id), {})
        return info.get("centro", "?")

    def _build_lum_data(planet_label: str, side: str) -> str:
        """Costruisce il blocco dati per una porta luminaria.
        planet_label: 'Sole', 'Terra', 'Luna'
        side: 'P' (Personalità, col 1) o 'D' (Design, col 2)
        """
        idx_col = 1 if side == "P" else 2
        row = next((a for a in activations if a[0] == planet_label), None)
        if not row or len(row) <= idx_col:
            return f"(dati non disponibili per {planet_label} {side})"

        gl = row[idx_col]  # "15.2"
        try:
            gate_s, line_s = gl.split(".")
            gate, line = int(gate_s), int(line_s)
        except Exception:
            return f"(formato non valido: {gl})"

        info      = gates_db.get(str(gate), {})
        gate_nome = info.get("nome", "")
        centro    = info.get("centro", "?")
        line_info = info.get("linee", {}).get(str(line), {})
        line_nome = line_info.get("nome", "")
        line_tema = line_info.get("tema", "")

        side_label = "Personalità (conscio)" if side == "P" else "Design (inconscio)"
        parts = [
            f"- Pianeta: {planet_label} {side_label}",
            f"- Porta: {gate}.{line} — {centro}" + (f" «{gate_nome}»" if gate_nome else ""),
        ]
        if line_nome or line_tema:
            desc = line_nome
            if line_tema:
                tema_short = line_tema[:400] + ("..." if len(line_tema) > 400 else "")
                desc = f"{line_nome} — {tema_short}" if line_nome else tema_short
            parts.append(f"- Linea {line}: {desc}")
        return "\n".join(parts)

    # ── Genere e flessione del Tipo Energetico ──────────────────────────
    # Valentina: "quando tu parli di proiettore femmina si dice proiettrice"
    # Se nel chart c'è "gender" ('F'|'M') lo usiamo, altrimenti inferiamo dal nome.
    _name_raw = chart.get("customer_name", "") or ""
    _first    = _name_raw.split()[0].lower() if _name_raw else ""
    _gender   = (chart.get("gender") or "").upper()
    if _gender not in ("F", "M"):
        # Euristica semplice sui nomi italiani (finali in 'a'/'e' femm. salvo comuni eccezioni).
        _MALE_EXC = {"andrea","luca","nicola","mattia","elia","enea","tobia","gioele"}
        _FEM_EXC  = {"miriam","ester","ruth","abigail"}
        if _first in _MALE_EXC:
            _gender = "M"
        elif _first in _FEM_EXC:
            _gender = "F"
        elif _first.endswith("a"):
            _gender = "F"
        else:
            _gender = "M"

    _CAREER_FEM = {
        "Proiettore":             "Proiettrice",
        "Generatore":             "Generatrice",
        "Generatore Manifestante":"Generatrice Manifestante",
        "Manifestatore":          "Manifestatrice",
        "Riflettore":             "Riflettrice",
    }
    _career = chart.get("career_type", "")
    career_type_gendered = _CAREER_FEM.get(_career, _career) if _gender == "F" else _career
    gender_rule = (
        f"La cliente è femmina: usa SEMPRE la forma femminile del tipo "
        f"(\"{career_type_gendered}\"), participi e aggettivi al femminile."
        if _gender == "F" else
        f"Il cliente è maschio: usa la forma maschile del tipo (\"{career_type_gendered}\")."
    )

    return {
        "name":                 chart.get("customer_name", "Cliente"),
        "gender":               _gender,
        "gender_rule":          gender_rule,
        "career_type":          career_type_gendered,   # forma flessa al genere
        "career_type_neutral":  _career,                # forma neutra (utile a volte)
        "type":              chart.get("type", ""),
        "strategy":          chart.get("strategy", ""),
        "authority":         chart.get("authority", ""),
        "profile":           chart.get("profile", ""),
        "profile_name":      chart.get("profile_name", ""),
        "definition":        chart.get("definition", ""),
        "signature":         chart.get("signature", ""),
        "non_self":          chart.get("non_self", ""),
        "life_theme":        chart.get("life_theme", ""),
        "variable":          chart.get("variable", ""),
        "variable_arrows":   chart.get("variable_arrows", ""),
        "birth_place":       chart.get("birth_place", ""),
        "defined_centers":   ", ".join(chart.get("defined_centers", [])),
        "undefined_centers": ", ".join(chart.get("undefined_centers", [])),
        "channels":          channels_str,
        # Coaching hints
        "profile_coaching":  profile_coaching,
        "centers_coaching":  centers_coaching,
        "channels_coaching": channels_coaching,
        "gates_detail":      gates_detail,
        # Porte luminari (Sole, Terra, Luna × P/D)
        "porta_sole_p_data":  _build_lum_data("Sole",  "P"),
        "porta_terra_p_data": _build_lum_data("Terra", "P"),
        "porta_luna_p_data":  _build_lum_data("Luna",  "P"),
        "porta_sole_d_data":  _build_lum_data("Sole",  "D"),
        "porta_terra_d_data": _build_lum_data("Terra", "D"),
        "porta_luna_d_data":  _build_lum_data("Luna",  "D"),
    }


# ─── CHIAVI PER TIER ────────────────────────────────────────────────────────

ESSENZIALE_KEYS = [
    "intro", "tipo_strategia", "autorita", "profilo",
    "definizione", "firma_nonself",
    "centri_definiti", "centri_aperti",
    "canali", "porte", "architettura_cognitiva", "suggerimenti",
]

COMPLETO_KEYS = [
    "intro", "tipo_strategia", "autorita", "profilo",
    "definizione", "firma_nonself",
    "centri_definiti", "centri_aperti",
    "canali", "porte",
    # Analisi del tuo Genio — 6 luminari (3 P + 3 D)
    "porta_sole_p", "porta_terra_p", "porta_luna_p",
    "porta_sole_d", "porta_terra_d", "porta_luna_d",
    "architettura_cognitiva",
    "offerte_allineate", "voce_e_mercato", "suggerimenti",
]
