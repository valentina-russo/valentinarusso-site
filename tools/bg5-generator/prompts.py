"""
BG5 Blueprint — 19 prompt template per qualsiasi cliente.
I prompt sono generici: i dati specifici del cliente vengono iniettati via {placeholder}.
Claude usa la KB HD nel system prompt per elaborare i dettagli.
"""

from pathlib import Path

# ─── SYSTEM PROMPT BUILDER ───────────────────────────────────────────────────

def load_kb() -> str:
    kb_path = Path("D:/HDcalcolatoreitaliano/hd-system-prompt.md")
    return kb_path.read_text(encoding="utf-8") if kb_path.exists() else ""

def load_writing_rules() -> str:
    rules_path = Path(__file__).parent / "writing_rules.md"
    return rules_path.read_text(encoding="utf-8") if rules_path.exists() else ""

def build_system_prompt(kb: str = "", writing_rules: str = "") -> str:
    if not kb:
        kb = load_kb()
    if not writing_rules:
        writing_rules = load_writing_rules()
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
LESSICO BG5 — REGOLA OBBLIGATORIA (VALENTINA)
================================================================

Usa SEMPRE la terminologia BG5 come termine principale. Alla PRIMA OCCORRENZA in ogni sezione, aggiungi tra parentesi tonde l'equivalente Human Design. Nelle occorrenze successive della stessa sezione usa solo il termine BG5.

Esempi di applicazione corretta:
- Prima volta: "Sei un Costruttore Classico (Generatore Puro in Human Design), il che significa..."
- Dopo: "Il Costruttore Classico costruisce valore nel tempo..."

GLOSSARIO BG5 → Human Design (obbligatorio):
- Tipo di Carriera → Tipo (HD)
  - Costruttore Classico → Generatore Puro (HD)
  - Costruttore Rapido → Generatore Manifestante (HD)
  - Iniziatore → Manifestatore (HD)
  - Guida → Proiettore (HD)
  - Valutatore → Riflettore (HD)
- Risorsa Energetica → Sacrale (HD)
- Intelligenza Emotiva → Plesso Solare (HD)
- Volontà / Ego (BG5) → Cuore (HD)
- Analisi BG5 / Sessione BG5 → Reading Human Design (HD)

================================================================
REGOLA CRITICA — BG5 / HUMAN DESIGN PURO:
Questo documento è un BG5 Business Blueprint. NON è un documento Gene Keys.
NON usare MAI la terminologia Gene Keys:
- NO "Shadow / Gift / Siddhi", NO "frequenze" delle porte
- NO "Genius Sequence", "Pearl Sequence", "Activation Sequence"
- NO riferimenti a Richard Rudd
USA SOLO terminologia BG5 / Human Design classica.

Questo documento viene venduto a €90-€147. Il cliente deve sentire che ha in mano qualcosa di personale e concreto, non un testo generico.

KNOWLEDGE BASE BG5 / HUMAN DESIGN:
{kb[:8000]}
"""


# ─── CHART BLOCK (iniettato in ogni prompt) ──────────────────────────────────

CHART_BLOCK = """DATI DELLA CARTA:
- Nome: {name}
- Tipo di Carriera BG5: {career_type}
- Tipo Energetico: {type}
- Strategia: {strategy}
- Autorità: {authority}
- Profilo: {profile} ({profile_name})
- Definizione: {definition}
- Tema di Vita (Croce): {life_theme}
- Variabile: {variable}
- Dieta cognitiva: {diet}
- Ambiente: {environment}
- Firma di allineamento: {signature}
- Tema del non-Sé: {non_self}

CENTRI DEFINITI: {defined_centers}
CENTRI APERTI: {undefined_centers}

CANALI ATTIVI:
{channels}

CROCE DI INCARNAZIONE: {cross}
"""


# ─── 19 SECTION PROMPTS ─────────────────────────────────────────────────────

SECTION_PROMPTS = {

# =========================================================
# PARTE 1 — IDENTITÀ ENERGETICA
# =========================================================

"intro": CHART_BLOCK + """

Scrivi la sezione di APERTURA del Blueprint per {name}.
Titolo interno: "Il tuo Business by Design".

Lunghezza: 400-500 parole. Paragrafi discorsivi, niente elenchi.

Cosa includere:
1. Un'apertura personale (2-3 frasi) in cui spieghi cosa tiene in mano il cliente: una mappa energetica personalizzata basata sulla sua carta BG5/Human Design, non un oroscopo né un test della personalità
2. A cosa serve davvero questo documento: capire come è progettato per lavorare, decidere, comunicare ed esistere nel mondo professionale
3. Una panoramica sintetica (3-4 frasi) di cosa troverà nelle pagine successive
4. Un invito a leggerlo con calma, senza fretta, tornandoci più volte

Tono: caldo, diretto, accogliente. Inizia direttamente col contenuto, non riscrivere il titolo.""",

"carta_spiegata": CHART_BLOCK + """

Scrivi la sezione "La tua Carta BG5 spiegata" per {name}.

Lunghezza: 400-500 parole.
Obiettivo: dare al lettore gli strumenti minimi per capire cosa vede quando guarda il proprio grafico.

Cosa includere:
1. Cos'è il Bodygraph: i 9 centri, i canali, le porte
2. Differenza tra elementi "consci" (in nero, Personalità) e "inconsci" (in rosso, Design)
3. Cos'è un centro definito vs uno aperto (in parole semplici, senza giri)
4. Cos'è un canale completo (due porte attive ai due estremi) e cos'è una porta appesa
5. Una nota finale: "questi sono meccaniche che il tuo corpo mette già in atto ogni giorno, descritte con un linguaggio preciso"

Tono: didattico ma mai scolastico. Inizia col contenuto.""",

"tipo_strategia": CHART_BLOCK + """

Scrivi la sezione "Il tuo Tipo di Carriera e la tua Strategia" per {name}.
Tipo di Carriera BG5: {career_type}
Tipo Energetico: {type}
Strategia: {strategy}

Lunghezza: 800-1000 parole. Paragrafi discorsivi.

Cosa includere:
1. Cosa significa essere un {career_type} ({type} in HD) nel mondo del lavoro: la meccanica energetica concreta di come questa persona costruisce valore. Usa le tue conoscenze BG5/HD per descrivere accuratamente questo tipo specifico.
2. Cosa distingue questo tipo dagli altri: quali sono le sue capacità uniche, i suoi limiti strutturali, la sua modalità operativa naturale
3. La Strategia "{strategy}": spiega ogni passaggio della strategia in modo pratico e concreto. Come si applica nelle situazioni lavorative reali (riunioni, proposte, decisioni di carriera, collaborazioni)
4. Come il corpo di {name} reagisce quando la strategia è rispettata (apertura, energia, firma di allineamento) e quando è tradita (tema del non-sé: {non_self})
5. Un esempio concreto di una giornata lavorativa "allineata" vs una "non allineata" per un {career_type}
6. Come orientare la propria settimana, le riunioni, le scelte di business a partire da questa meccanica

Inizia col contenuto, senza riscrivere il titolo.""",

"autorita": CHART_BLOCK + """

Scrivi la sezione "La tua Autorità Interiore" per {name}.
Autorità: {authority}

Lunghezza: 500-650 parole.

Cosa includere:
1. Cos'è l'Autorità nel sistema Human Design: il modo affidabile che il corpo ha di sapere ciò che è giusto PER questa specifica persona (non una decisione mentale, non un ragionamento)
2. Come funziona l'autorità "{authority}" nello specifico: descrivi la meccanica precisa di come questa autorità opera nel corpo. Come si manifesta fisicamente, quali sono i segnali, qual è il timing delle decisioni
3. Perché un {career_type} con questa autorità deve rispettare il suo timing decisionale: come interagisce col tipo e la strategia
4. Come usarla in contesti lavorativi reali: quando arriva una proposta di progetto, un nuovo cliente, un cambio di collaborazione. Cosa fare concretamente
5. Una pratica concreta quotidiana per allenarsi a riconoscere i segnali della propria autorità
6. Il segnale che stai ignorando la tua autorità: pattern tipici di decisioni sbagliate

Tono diretto, pragmatico. Inizia col contenuto.""",

"profilo": CHART_BLOCK + """

Scrivi la sezione "Il tuo Profilo {profile} — {profile_name}" per {name}.

{profile_coaching}

Lunghezza: 800-1000 parole.

Cosa includere:
1. Come la prima linea del profilo opera nella vita professionale: il ruolo, il modo di imparare, le necessità strutturali
2. Come la seconda linea del profilo opera: le relazioni, il networking, il modo di crescere professionalmente
3. Come le due linee interagiscono: il ritmo specifico che creano nella vita di questa persona, le tensioni e le sinergie
4. Come gli altri percepiscono questa persona in ambito lavorativo
5. Come costruire la propria attività con questo profilo: quali strategie funzionano e quali no
6. Un esempio concreto di profilo {profile} al lavoro: come gestisce richieste, decisioni, e alterna le due modalità

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
Firma: {signature}
Non-Sé: {non_self}

Lunghezza: 400-550 parole.

Cosa includere:
1. Cos'è la "Firma" in BG5/HD: il segnale emotivo-corporeo che si prova quando si vive in accordo con il proprio design. Per un {career_type} ({type} in HD) la firma è {signature}. Descrivi cosa si prova fisicamente e emotivamente
2. Cos'è il "Non-Sé": il segnale che qualcosa è fuori asse. Per un {career_type} il non-sé è {non_self}. Spiega le diverse sfumature di questo tema del non-sé
3. Come riconoscere la firma in una giornata lavorativa concreta: segnali corporei e mentali della {signature}
4. Come riconoscere il non-sé: manifestazioni concrete di {non_self} nel corpo e nel comportamento
5. Cosa fare quando compare il tema del non-sé: azioni pratiche per riallinearsi
6. Perché la firma non è un obiettivo ("devo essere X"), è un feedback in tempo reale ("questo è un dato")

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

Scrivi la sezione "I tuoi Centri Aperti: saggezza e trappole" per {name}.

Centri aperti del cliente: {undefined_centers}

Lunghezza: 1000-1200 parole (se i centri aperti sono pochi, riduci proporzionalmente a 400-600 parole).

Struttura:
1. Introduzione (1 paragrafo): un centro aperto è uno spazio ricettivo dove assorbi l'energia degli altri, la amplifichi, e (quando non te ne accorgi) la scambi per tua. Ogni centro aperto diventa una fonte di saggezza unica col passare del tempo.

2. Un paragrafo DEDICATO a ciascun centro aperto (in ordine):
   - Per ogni centro, spiega:
     a) La pressione/condizionamento tipico (cosa assorbi dagli altri)
     b) La "domanda trappola" del non-sé
     c) Come si trasforma in saggezza quando riconosci la meccanica
     d) Un esempio lavorativo concreto di come NON farti travolgere

Mai dire "stai attento a", dì sempre "osserva come". Inizia col contenuto.""",

"attrazione": CHART_BLOCK + """

Scrivi la sezione "Il tuo Campo di Attrazione" per {name}.

Lunghezza: 400-500 parole.

Cosa includere:
1. Il principio: dove sei DEFINITO, attrai naturalmente persone che hanno quel centro APERTO. Dove sei APERTO, attrai persone che hanno quel centro DEFINITO.

2. Applicato concretamente:
   - I centri definiti di {name} ({defined_centers}) attraggono clienti, colleghi e collaboratori con quegli stessi centri aperti che cercano quella stabilità
   - I centri aperti di {name} ({undefined_centers}) attraggono persone con quei centri definiti

3. Un esempio pratico: cosa significa questo per il lavoro di {name}? Che tipo di cliente o collaboratore si sente naturalmente bene vicino a lei/lui?

4. Chiusura: "Quando costruisci un'attività, un team, una rete di collaboratori, questa mappa ti dice dove stai offrendo valore strutturale e dove stai ricevendo."

Inizia col contenuto.""",

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
1. Introduzione: una porta sospesa è una porta attiva che NON forma un canale. È un'aspirazione, un tema ricorrente, un invito a completarsi con qualcuno che ha la porta opposta.

2. Commenta in modo specifico 5-6 porte più rilevanti per questa persona. Per ciascuna spiega brevemente il tema e il ruolo nella vita professionale. Usa le tue conoscenze HD per descrivere accuratamente ogni porta.

3. Scegli quelle che meglio si integrano con il resto del profilo ({career_type} {profile} con {definition}, canali attivi già descritti).

Chiusura: non serve "attivare" queste porte facendo qualcosa di specifico. Sono già attive. Servono come lente per capire perché certe cose ti chiamano ricorrentemente.

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

4. Il quartiere (Quarter) di appartenenza di questa croce e cosa significa per il modo in cui lo scopo emerge nella vita di {name}.

5. Applicazione al lavoro: in quali contesti {name} porta naturalmente il suo contributo più grande? Che tipo di rinnovamento/cambiamento/contributo porta nei contesti professionali?

Tono quasi poetico ma concreto. Inizia col contenuto.""",

# =========================================================
# PARTE 3 — CONTESTO OTTIMALE
# =========================================================

"variabile_phs": CHART_BLOCK + """

Scrivi la sezione "Variabile, Dieta cognitiva, Ambiente: il tuo contesto ottimale" per {name}.

Variabile: {variable}
Dieta: {diet}
Ambiente: {environment}

Lunghezza: 600-750 parole.

Cosa includere:
1. Introduzione breve: la Variabile nel sistema BG5/HD indica COME il corpo e la mente elaborano le informazioni e in che AMBIENTE trovano il funzionamento ottimale. Indicazioni operative per la giornata quotidiana.

2. DIETA COGNITIVA — "{diet}":
   - La dieta in BG5 non è alimentare in senso stretto: riguarda COME il corpo assimila meglio. Spiega cosa significa "{diet}" in termini pratici: dove e come mangiare, quali condizioni ambientali durante i pasti, cosa funziona e cosa no. Basati sulla tua conoscenza del PHS (Primary Health System).
   - Applicazione concreta: dove e come mangiare in una giornata lavorativa

3. AMBIENTE — "{environment}":
   - Spiega cosa significa "{environment}" in termini pratici: in che tipo di spazio fisico {name} rende al meglio, dove vivere, dove lavorare, dove andare per prendere decisioni importanti.
   - Applicazione: consigli specifici per l'ambiente di lavoro e di vita, tenendo conto che {name} è nata a {birth_place}

4. Chiusura: questi due elementi insieme danno indicazioni molto concrete su come organizzare la giornata per massimizzare energia e chiarezza mentale.

Tono: molto concreto, quasi da manuale d'uso. Inizia col contenuto.""",

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

"voce_gola": CHART_BLOCK + """

Scrivi la sezione "La tua Voce di Gola: come comunicare dal tuo design" per {name}.

Profilo: {profile} ({profile_name})
Tipo: {type}

Lunghezza: 700-900 parole.

Cosa includere:
1. Cosa significa avere la Gola definita (se definita) o aperta: come questo influenza la comunicazione
2. Come comunica un {career_type} ({type} in HD): il modo naturale di esprimersi in voce e in scrittura, basato sui canali che collegano (o non collegano) la Gola
3. Come scrive e comunica un Profilo {profile}: le caratteristiche comunicative specifiche delle due linee del profilo
4. Parole e verbi che risuonano naturalmente con questa voce: suggerisci 4-5 verbi d'azione tipici di questa combinazione tipo/profilo/canali
5. Formato comunicativo ideale: quali formati (podcast, scrittura, video, live, 1:1) funzionano meglio per questo design specifico
6. Trappola tipica: quale stile comunicativo NON appartiene a questa persona e rischia di imitare

Inizia col contenuto.""",

"vendita_allineata": CHART_BLOCK + """

Scrivi la sezione "Vendita allineata: come il tuo tipo vende senza forzare" per {name}.

Tipo: {type}
Strategia: {strategy}
Autorità: {authority}
Profilo: {profile}

Lunghezza: 700-900 parole.

Cosa includere:
1. Il principio BG5: come un {career_type} vende in modo allineato alla sua strategia e autorità. Spiega il processo naturale.
2. Come NON funziona la vendita per un {career_type} {profile}: elenca 4-5 approcci concreti che non funzionano per questo design specifico
3. Come funziona davvero: il processo passo-passo di una vendita allineata, rispettando strategia ("{strategy}") e autorità ("{authority}")
4. Per un profilo {profile} in particolare: come le due linee del profilo influenzano il processo di vendita e acquisizione clienti
5. Un protocollo concreto per la prossima chiamata di vendita: prima, durante, e dopo — con attenzione al timing decisionale dell'autorità

Inizia col contenuto.""",

"strategia_contenuti": CHART_BLOCK + """

Scrivi la sezione "Strategia contenuti per i tuoi Centri" per {name}.

Centri definiti: {defined_centers}
Centri aperti: {undefined_centers}

Lunghezza: 700-900 parole.

Cosa includere:
1. Principio: i contenuti più magnetici nascono dai centri DEFINITI (da dove ha energia stabile da donare) e parlano ai dolori delle persone che hanno quei centri APERTI.

2. Per CIASCUN centro definito ({defined_centers}), suggerisci 3-4 idee di contenuto concrete che può creare.

3. TEMI DA EVITARE come contenuti principali: quelli che nascono dai centri aperti ({undefined_centers}). La ragione è tecnica: energeticamente non ha stabilità in quelle aree.

4. Formato: per un {career_type} con profilo {profile}, quali formati comunicativi funzionano meglio? Conversazioni intime, contenuti spontanei, storie personali, live?

5. Ritmo di pubblicazione: come le due linee del profilo {profile} e l'autorità "{authority}" influenzano la frequenza ideale di pubblicazione. NON forzarti in un calendario editoriale rigido.

Inizia col contenuto.""",

# =========================================================
# PARTE 5 — CHIUSURA
# =========================================================

"suggerimenti": CHART_BLOCK + """

Scrivi la sezione di chiusura "Sintesi e 7 suggerimenti pratici" per {name}.

Lunghezza: 800-1000 parole.

Struttura:
1. Apertura (1 paragrafo, 80-100 parole): una sintesi in poche frasi di chi è {name} dal punto di vista BG5 — {career_type} ({type}) {profile}, con {definition}, i canali attivi, l'autorità {authority} e la Croce "{life_theme}". Questa apertura deve suonare come un ritratto vivo, non come un elenco.

2. SETTE SUGGERIMENTI NUMERATI. Ogni suggerimento ha:
   - Un titolo breve in **grassetto markdown**
   - 3-5 frasi di spiegazione concreta
   - Si riferisce a UN elemento specifico della sua carta, non è generico

I 7 suggerimenti devono coprire, in qualche forma:
a) Come usare la strategia "{strategy}" in pratica ogni settimana
b) Come onorare l'autorità {authority}: il timing decisionale specifico
c) Come alternare il ritmo delle due linee del profilo {profile}: quando ritirarsi e quando essere visibili
d) Come proteggere i centri aperti ({undefined_centers}): non farsi condizionare
e) Come usare l'ambiente "{environment}" come ancoraggio fisico
f) Come nutrire i canali attivi più importanti nel quotidiano
g) Come riconoscere la firma ({signature}) come feedback in tempo reale, e come distinguere il tema del non-sé ({non_self})

I suggerimenti devono essere AZIONI, non massime. NON scrivere "sii te stesso", "segui il tuo intuito". Scrivi cose come:
- "Prima di accettare un progetto, aspetta 24 ore e osserva il segnale del corpo la mattina dopo"
- "Una volta al mese programma una giornata in {environment} e porta con te le decisioni aperte"
- "Lavora 90 minuti, poi cammina 15 minuti fuori, poi altri 90 minuti"

3. Chiusura (1 paragrafo, 60-80 parole): un saluto finale personale di Valentina, caldo e concreto. Qualcosa del tipo "Questo blueprint funziona come uno specchio da rileggere ogni pochi mesi. Torna alle pagine che ti colpiscono di più, prova una cosa alla volta nel tuo corpo e osserva la {signature} quando arriva. Per qualunque cosa, scrivimi a valentina@valentinarussobg5.com."

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

    # Gates detail
    hanging = chart.get("hanging_gates", {})
    gates_lines = []
    for center, gates in hanging.items():
        gates_str = ", ".join(str(g) for g in gates)
        gates_lines.append(f"  - {center}: porte {gates_str}")
    gates_detail = "\n".join(gates_lines) if gates_lines else "(nessuna porta sospesa specificata)"

    return {
        "name":              chart.get("customer_name", "Cliente"),
        "career_type":       chart.get("career_type", ""),
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
        "diet":              chart.get("diet", ""),
        "environment":       chart.get("environment", ""),
        "birth_place":       chart.get("birth_place", ""),
        "defined_centers":   ", ".join(chart.get("defined_centers", [])),
        "undefined_centers": ", ".join(chart.get("undefined_centers", [])),
        "channels":          channels_str,
        "cross":             chart.get("incarnation_cross", ""),
        "cross_type":        chart.get("cross_type", ""),
        # Coaching hints
        "profile_coaching":  profile_coaching,
        "centers_coaching":  centers_coaching,
        "channels_coaching": channels_coaching,
        "gates_detail":      gates_detail,
    }


# ─── CHIAVI PER TIER ────────────────────────────────────────────────────────

ESSENZIALE_KEYS = [
    "intro", "carta_spiegata", "tipo_strategia", "autorita", "profilo",
    "definizione", "firma_nonself", "centri_definiti", "centri_aperti",
    "attrazione", "canali", "porte", "croce", "variabile_phs", "suggerimenti",
]

COMPLETO_KEYS = [
    "intro", "carta_spiegata", "tipo_strategia", "autorita", "profilo",
    "definizione", "firma_nonself", "centri_definiti", "centri_aperti",
    "attrazione", "canali", "porte", "croce", "variabile_phs",
    "offerte_allineate", "voce_gola", "vendita_allineata", "strategia_contenuti",
    "suggerimenti",
]
