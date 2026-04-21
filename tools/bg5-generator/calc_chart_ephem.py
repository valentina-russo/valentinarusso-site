#!/usr/bin/env python3
"""
Calcola la carta HD usando ephem (disponibile senza C compiler).
Output: JSON del chart nel formato atteso da generator.py

Uso: python calc_chart_ephem.py <nome> <YYYY-MM-DD> <HH:MM> <tz_offset> <luogo>
     python calc_chart_ephem.py "Valentina Russo" 1988-09-21 00:55 2 "Saluzzo, Piemonte"
"""

import sys
import math
import json
import ephem

sys.stdout.reconfigure(encoding='utf-8')
sys.stderr.reconfigure(encoding='utf-8')

# ─── Ruota Rave I-Ching ───────────────────────────────────────────────────────

GATE_WHEEL = [
    41,19,13,49,30,55,37,63,22,36,25,17,21,51,42, 3,27,24, 2,23,
     8,20,16,35,45,12,15,52,39,53,62,56,31,33, 7, 4,29,59,40,64,
    47, 6,46,18,48,57,32,50,28,44, 1,43,14,34, 9, 5,26,11,10,58,
    38,54,61,60,
]
GATE_SIZE   = 360.0 / 64
LINE_SIZE   = GATE_SIZE / 6
COLOR_SIZE  = LINE_SIZE / 6
TONE_SIZE   = COLOR_SIZE / 6
RAVE_OFFSET = 58.0

CHANNELS = [
    (64,47),(61,24),(63, 4),(17,62),(43,23),(11,56),(16,48),(20,57),
    (31, 7),( 8, 1),(33,13),(10,20),(45,21),(35,36),(12,22),(20,34),
    (26,44),(51,25),(40,37),( 2,14),( 5,15),(29,46),(27,50),(34,57),
    (59, 6),( 3,60),( 9,52),(42,53),(18,58),(28,38),(32,54),
    (30,41),(39,55),(19,49),
]

GATE_CENTER = {}
for g in [64,61,63]:                                     GATE_CENTER[g]='HEAD'
for g in [47,24,4,17,43,11]:                             GATE_CENTER[g]='AJNA'
for g in [62,23,56,16,31,8,33,45,35,12,20]:              GATE_CENTER[g]='THROAT'
for g in [7,13,1,10,25,15,2,46]:                         GATE_CENTER[g]='G'
for g in [21,51,26,40]:                                  GATE_CENTER[g]='HEART'
for g in [48,57,44,50,32,28,18]:                         GATE_CENTER[g]='SPLEEN'
for g in [36,22,37,6,30,55,49]:                          GATE_CENTER[g]='SOLAR'
for g in [34,5,14,29,3,42,9,27,59]:                      GATE_CENTER[g]='SACRAL'
for g in [53,60,52,54,58,38,41,39,19]:                   GATE_CENTER[g]='ROOT'

CENTER_IT = {
    'HEAD':'Testa','AJNA':'Ajna','THROAT':'Gola','G':'G/Se',
    'HEART':'Cuore/Ego','SPLEEN':'Milza','SOLAR':'Plesso Solare',
    'SACRAL':'Sacrale','ROOT':'Radice',
}

CROSS_NAMES = {
    41:"dell'Inaspettato",19:"dell'Alpha",13:"del Perfezionamento",49:"della Spiegazione",
    30:"del Contagio",55:"del Rinnovamento",37:"della Pianificazione",63:"della Consapevolezza",
    22:"del Governo",36:"dell'Esperienza",25:"dell'Amore",17:"del Servizio",21:"della Tensione",
    51:"della Penetrazione",42:"del Mondo Materiale",3:"delle Leggi",
    27:"dell'Inaspettato",24:"della Bussola",2:"della Direzione",23:"della Spiegazione",
    8:"del Contagio",20:"del Rinnovamento",16:"della Pianificazione",35:"della Consapevolezza",
    45:"del Governo",12:"dell'Esperienza",15:"dell'Amore",52:"del Servizio",39:"della Tensione",
    53:"della Penetrazione",62:"del Mondo Materiale",56:"delle Leggi",
    31:"dell'Inaspettato",33:"dell'Alpha",7:"della Bussola",4:"della Direzione",
    29:"della Spiegazione",59:"del Contagio",40:"del Rinnovamento",64:"della Pianificazione",
    47:"della Consapevolezza",6:"del Governo",46:"dell'Esperienza",18:"dell'Amore",
    48:"del Servizio",57:"della Tensione",32:"della Penetrazione",50:"delle Leggi",
    28:"dell'Inaspettato",44:"della Bussola",1:"della Direzione",43:"della Spiegazione",
    14:"del Contagio",34:"del Rinnovamento",9:"della Pianificazione",5:"della Consapevolezza",
    26:"del Governo",11:"dell'Esperienza",10:"dell'Amore",58:"del Servizio",
    38:"della Tensione",54:"della Penetrazione",61:"del Mondo Materiale",60:"delle Leggi",
}
QUARTER_NAMES = {1:'Iniziazione', 2:'Civilizzazione', 3:'Dualità', 4:'Mutazione'}

# Quarti del Mandala — fonte: KB hd-system-prompt.md (lista gate autorevole)
GATE_QUARTERS = {
    # Q1 — Iniziazione
    13:1, 49:1, 30:1, 55:1, 37:1, 63:1, 22:1, 36:1, 25:1, 17:1, 21:1, 51:1, 42:1, 3:1, 27:1, 24:1,
    # Q2 — Civilizzazione
    2:2, 23:2, 8:2, 20:2, 16:2, 35:2, 45:2, 12:2, 15:2, 52:2, 39:2, 53:2, 62:2, 56:2, 31:2, 33:2,
    # Q3 — Dualità
    7:3, 4:3, 29:3, 59:3, 40:3, 64:3, 47:3, 6:3, 46:3, 18:3, 48:3, 57:3, 32:3, 50:3, 28:3, 44:3,
    # Q4 — Mutazione
    1:4, 43:4, 14:4, 34:4, 9:4, 5:4, 26:4, 11:4, 10:4, 58:4, 38:4, 54:4, 61:4, 60:4, 41:4, 19:4,
}

CAREER_TYPE = {
    'Generator':           'Generatore',
    'ManifestingGenerator':'Generatore Manifestante',
    'Manifestor':          'Manifestatore',
    'Projector':           'Proiettore',
    'Reflector':           'Riflettore',
}
AUTHORITY_IT = {
    'Emotional': 'Plesso Solare',
    'Sacral':    'Sacrale',
    'Splenic':   'Splenica',
    'Ego':       'Ego / Cuore',
    'G/Self':    'G / Sé',
    'Mental':    'Mentale',
    'Lunar':     'Lunare',
}
DEFINITION_IT = {
    'Single':'Singola','Split':'Doppia','TripleSplit':'Tripla Divisione',
    'QuadrupleSplit':'Quadrupla Divisione','None':'Nessuna',
}
PROFILE_NAMES = {
    '1/3':'Investigatore / Martire','1/4':'Investigatore / Opportunista',
    '2/4':'Eremita / Opportunista','2/5':'Eremita / Eretico',
    '3/5':'Martire / Eretico','3/6':'Martire / Modello di Ruolo',
    '4/6':'Opportunista / Modello di Ruolo','4/1':'Opportunista / Investigatore',
    '5/1':'Eretico / Investigatore','5/2':'Eretico / Eremita',
    '6/2':'Modello di Ruolo / Eremita','6/3':'Modello di Ruolo / Martire',
}
SIGNATURES = {
    'Generator':{'sig':'Soddisfazione','ns':'Frustrazione'},
    'ManifestingGenerator':{'sig':'Soddisfazione','ns':'Frustrazione / Rabbia'},
    'Manifestor':{'sig':'Pace','ns':'Rabbia'},
    'Projector':{'sig':'Successo','ns':'Amarezza'},
    'Reflector':{'sig':'Meraviglia','ns':'Delusione'},
}
STRATEGIES = {
    'Generator':'Rispondere',
    'ManifestingGenerator':'Rispondere, poi Informare',
    'Manifestor':'Informare prima di agire',
    'Projector':"Aspettare l'invito",
    'Reflector':'Attendere un ciclo lunare',
}
# PHS: Diet = COLOR del Nodo Nord Design, Environment = TONE del Nodo Nord Design
# Fonte: hd-system-prompt.md KB
PHS_TYPES = {1:'Appetito', 2:'Gusto', 3:'Sete', 4:'Tatto', 5:'Suono', 6:'Luce'}
PHS_VARIANTS = {  # (variante_destra/alta, variante_sinistra/bassa)
    1: ('Consecutivo', 'Alternato'),
    2: ('Aperto',      'Chiuso'),
    3: ('Caldo',       'Freddo'),
    4: ('Calmo',       'Nervoso'),
    5: ('Alto',        'Basso'),
    6: ('Diretto',     'Indiretto'),
}
ENV_MAP = {1:'Grotte', 2:'Mercati', 3:'Cucine', 4:'Montagne', 5:'Valli', 6:'Coste'}
CHANNEL_NAMES = {
    '64-47':"Canale dell'Astrazione",'61-24':'Canale della Consapevolezza','63-4':'Canale della Logica',
    '17-62':"Canale dell'Accettazione",'43-23':'Canale della Struttura','11-56':'Canale della Curiosita',
    '16-48':"Canale della Lunghezza d'Onda",'20-57':'Canale del Presente',
    '31-7':'Canale della Leadership','8-1':"Canale dell'Ispirazione",
    '33-13':'Canale del Testimone','10-20':'Canale del Risveglio',
    '45-21':'Canale della Conquista del Denaro','35-36':'Canale del Transeunte',
    '12-22':"Canale dell'Apertura",'20-34':'Canale del Carisma',
    '26-44':'Canale della Resa','51-25':"Canale dell'Iniziazione",
    '40-37':'Canale della Comunita','2-14':'Canale della Direzione del Se',
    '5-15':'Canale del Ritmo','29-46':'Canale della Scoperta',
    '27-50':'Canale della Preservazione','34-57':'Canale di Potere',
    '59-6':'Canale della Maturazione','3-60':'Canale della Mutazione',
    '9-52':'Canale della Concentrazione','42-53':'Canale del Ciclo',
    '18-58':'Canale del Giudizio','28-38':'Canale della Lotta',
    '32-54':'Canale della Trasformazione','30-41':'Canale del Riconoscimento',
    '39-55':"Canale dell'Emozione",'19-49':'Canale della Sensibilita',
}


# ─── Funzioni calcolo ─────────────────────────────────────────────────────────

def deg_to_gate_line_color(deg):
    d = (deg + RAVE_OFFSET) % 360.0
    idx = int(d / GATE_SIZE)
    gate = GATE_WHEEL[idx]
    rem = d - idx * GATE_SIZE
    line = min(int(rem / LINE_SIZE) + 1, 6)
    rem2 = rem - (line - 1) * LINE_SIZE
    color = min(int(rem2 / COLOR_SIZE) + 1, 6)
    rem3 = rem2 - (color - 1) * COLOR_SIZE
    tone = min(int(rem3 / TONE_SIZE) + 1, 6)
    return gate, line, color, tone


def get_ecliptic_lon(body, date_ephem):
    """Longitudine eclittica geocentrica in gradi."""
    body.compute(date_ephem)
    ecl = ephem.Ecliptic(body, epoch=date_ephem)
    return math.degrees(float(ecl.lon)) % 360.0


def mean_north_node(jd):
    """Longitudine media del Nodo Nord (formula approssimata, errore < 2 gradi)."""
    jd_j2000 = 2451545.0
    days = jd - jd_j2000
    return (125.04455501 + days * (-0.05295376)) % 360.0


def ephem_date_to_jd(d):
    """Converte ephem.Date in Julian Day."""
    return float(d) + 2415020.0


def jd_to_ephem_date(jd):
    """Converte Julian Day in ephem.Date."""
    return ephem.Date(jd - 2415020.0)


def find_design_jd(sun_birth_lon, jd_birth):
    """
    Trova il JD in cui il Sole era a esattamente 88 gradi prima
    della longitudine di nascita (Design = 88 gradi solari prima).
    Metodo: ricerca Newton-Raphson (converge in 3-5 iterazioni).
    """
    target = (sun_birth_lon - 88.0) % 360.0
    jd = jd_birth - 88.0  # stima iniziale (~1 grad/giorno)
    sun = ephem.Sun()
    for _ in range(20):
        edate = jd_to_ephem_date(jd)
        lon = get_ecliptic_lon(sun, edate)
        diff = (lon - target + 180) % 360 - 180  # differenza con segno
        if abs(diff) < 0.0001:
            break
        # Il Sole si muove ~0.9856 gradi/giorno
        jd -= diff / 0.9856
    return jd


def calc_planets(jd):
    """Calcola gate/linea/colore per tutti i pianeti HD a un dato JD."""
    edate = jd_to_ephem_date(jd)
    bodies = [
        (ephem.Sun(),     'Sole',      'Sun'),
        (None,            'Terra',     'Earth'),      # Terra = Sole + 180
        (ephem.Moon(),    'Luna',      'Moon'),
        (None,            'Nodo Nord', 'NorthNode'),  # formula media
        (None,            'Nodo Sud',  'SouthNode'),  # NN + 180
        (ephem.Mercury(), 'Mercurio',  'Mercury'),
        (ephem.Venus(),   'Venere',    'Venus'),
        (ephem.Mars(),    'Marte',     'Mars'),
        (ephem.Jupiter(), 'Giove',     'Jupiter'),
        (ephem.Saturn(),  'Saturno',   'Saturn'),
        (ephem.Uranus(),  'Urano',     'Uranus'),
        (ephem.Neptune(), 'Nettuno',   'Neptune'),
        (ephem.Pluto(),   'Plutone',   'Pluto'),
    ]

    results = []
    sun_lon = None
    nn_lon = None

    for body, name_it, name_en in bodies:
        if name_en == 'Earth':
            lon = (sun_lon + 180.0) % 360.0
        elif name_en == 'NorthNode':
            nn_lon = mean_north_node(jd)
            lon = nn_lon
        elif name_en == 'SouthNode':
            lon = (nn_lon + 180.0) % 360.0
        else:
            lon = get_ecliptic_lon(body, edate)
            if name_en == 'Sun':
                sun_lon = lon

        gate, line, color, tone = deg_to_gate_line_color(lon)
        results.append({
            'planet_it': name_it, 'planet_en': name_en,
            'gate': gate, 'line': line, 'color': color, 'tone': tone,
            'degrees': round(lon, 4),
        })

    return results


def get_defined_channels(active_gates):
    return [(g1, g2) for g1, g2 in CHANNELS
            if g1 in active_gates and g2 in active_gates]


def get_defined_centers(defined_channels):
    centers = set()
    for g1, g2 in defined_channels:
        if g1 in GATE_CENTER: centers.add(GATE_CENTER[g1])
        if g2 in GATE_CENTER: centers.add(GATE_CENTER[g2])
    return centers


def motor_connected_to_throat(start, defined_channels, visited=None):
    if visited is None: visited = set()
    if start in visited: return False
    visited.add(start)
    if start == 'THROAT': return True
    adj = set()
    for g1, g2 in defined_channels:
        c1, c2 = GATE_CENTER.get(g1,''), GATE_CENTER.get(g2,'')
        if c1 == start: adj.add(c2)
        if c2 == start: adj.add(c1)
    return any(motor_connected_to_throat(c, defined_channels, visited) for c in adj)


def calc_type(defined_centers, defined_channels):
    motors = {'SACRAL','HEART','SOLAR','ROOT'}
    # Reflector = ZERO centri definiti (tutti e 9 aperti)
    if not defined_centers:
        return 'Reflector'
    # Ha il Sacrale → Generator o ManifestingGenerator
    if 'SACRAL' in defined_centers:
        return 'ManifestingGenerator' if motor_connected_to_throat('SACRAL', defined_channels) else 'Generator'
    # Nessun Sacrale: motore non-sacrale connesso alla Gola → Manifestor
    non_sacral = (motors - {'SACRAL'}) & defined_centers
    if any(motor_connected_to_throat(m, defined_channels) for m in non_sacral):
        return 'Manifestor'
    # Centri definiti ma nessun motore connesso alla Gola → Projector
    return 'Projector'


def calc_profile(personality, design):
    sun_p = next((p for p in personality if p['planet_en'] == 'Sun'), None)
    sun_d = next((p for p in design if p['planet_en'] == 'Sun'), None)
    return f"{sun_p['line']}/{sun_d['line']}" if sun_p and sun_d else '?/?'


def calc_authority(defined_centers):
    for c, k in [('SOLAR','Emotional'),('SACRAL','Sacral'),('SPLEEN','Splenic'),('HEART','Ego'),('G','G/Self')]:
        if c in defined_centers: return k
    return 'Mental' if defined_centers else 'Lunar'


def calc_definition(defined_centers, defined_channels):
    if not defined_centers: return 'None'
    adj = {c: set() for c in defined_centers}
    for g1, g2 in defined_channels:
        c1, c2 = GATE_CENTER.get(g1,''), GATE_CENTER.get(g2,'')
        if c1 in adj and c2 in adj:
            adj[c1].add(c2); adj[c2].add(c1)
    visited, components = set(), 0
    for start in defined_centers:
        if start in visited: continue
        components += 1
        stack = [start]
        while stack:
            node = stack.pop()
            if node in visited: continue
            visited.add(node)
            stack.extend(adj[node] - visited)
    return {1:'Single',2:'Split',3:'TripleSplit',4:'QuadrupleSplit'}.get(components,'QuadrupleSplit')


def calc_cross(personality, design, profile):
    sun_p  = next((p for p in personality if p['planet_en'] == 'Sun'), None)
    earth_p = next((p for p in personality if p['planet_en'] == 'Earth'), None)
    sun_d  = next((p for p in design if p['planet_en'] == 'Sun'), None)
    earth_d = next((p for p in design if p['planet_en'] == 'Earth'), None)
    if not all([sun_p, earth_p, sun_d, earth_d]): return None

    gate = sun_p['gate']
    quarter_num = GATE_QUARTERS.get(gate, 1)
    quarter = QUARTER_NAMES.get(quarter_num, '?')
    l1 = int(profile.split('/')[0])
    if l1 == 4 and int(profile.split('/')[1]) == 1: angle = 'Juxtaposition'
    elif l1 >= 5: angle = 'Left Angle'
    else: angle = 'Right Angle'
    variant = GATE_QUARTERS.get(earth_p['gate'], 1) if angle == 'Left Angle' else quarter_num
    theme = CROSS_NAMES.get(gate, '?')
    return {
        'theme': theme, 'variant': variant, 'angle': angle, 'quarter': quarter,
        'gates': [sun_p['gate'], earth_p['gate'], sun_d['gate'], earth_d['gate']],
        'full_name': f"Croce {theme} {variant} ({sun_p['gate']}/{earth_p['gate']} | {sun_d['gate']}/{earth_d['gate']})",
    }


def get_hanging_gates(active_gates, defined_channels):
    channel_gates = set(g for ch in defined_channels for g in ch)
    hanging = {}
    for g in sorted(active_gates):
        if g in channel_gates: continue
        c = GATE_CENTER.get(g)
        if not c: continue
        label = CENTER_IT.get(c, c)
        hanging.setdefault(label, []).append(g)
    return hanging


def get_hanging_gates_rich(active_gates, defined_channels, personality, design):
    """
    Porte sospese con info pianeta inclusa, ordinate per priorità.

    Ordine pianeti: Sole, Terra, Luna, Mercurio, Venere, Marte (personali)
                    → Giove, Saturno (sociali)
                    → Nodo Nord, Nodo Sud (transpersonali)
                    → Urano, Nettuno, Plutone (lenti — da evitare di solito)
    """
    PLANET_PRIORITY = {
        'Sole': 1, 'Terra': 2, 'Luna': 3, 'Mercurio': 4, 'Venere': 5, 'Marte': 6,
        'Giove': 7, 'Saturno': 8,
        'Nodo Nord': 9, 'Nodo Sud': 10,
        'Urano': 11, 'Nettuno': 12, 'Plutone': 13,
    }

    # Gate → lista attivazioni [(planet_it, 'P'/'D', line, priority)]
    gate_acts = {}
    for p in personality:
        g = p['gate']
        gate_acts.setdefault(g, []).append((p['planet_it'], 'P', p['line'], PLANET_PRIORITY.get(p['planet_it'], 99)))
    for p in design:
        g = p['gate']
        gate_acts.setdefault(g, []).append((p['planet_it'], 'D', p['line'], PLANET_PRIORITY.get(p['planet_it'], 99)))

    channel_gates = set(g for ch in defined_channels for g in ch)
    result = []

    for g in sorted(active_gates):
        if g in channel_gates:
            continue
        center_key = GATE_CENTER.get(g)
        if not center_key:
            continue

        acts = sorted(gate_acts.get(g, []), key=lambda x: x[3])  # ordina per priority
        if not acts:
            continue

        best_planet, best_col, best_line, best_prio = acts[0]

        result.append({
            'gate':        g,
            'line':        best_line,
            'center_key':  center_key,
            'center_it':   CENTER_IT.get(center_key, center_key),
            'best_planet': best_planet,
            'best_col':    best_col,       # 'P' = Personalità, 'D' = Design
            'best_priority': best_prio,
            # Tutte le attivazioni (pianeta, colonna, linea)
            'activations': [
                {'planet': pl, 'col': co, 'line': li, 'priority': pr}
                for pl, co, li, pr in acts
            ],
        })

    # Ordina: prima per priorità pianeta, poi per linea
    result.sort(key=lambda x: (x['best_priority'], x['line']))
    return result


# ─── Funzione principale ──────────────────────────────────────────────────────

def calculate_chart(name, birth_date_str, birth_time_str, tz_offset, birth_place):
    y, mo, d  = map(int, birth_date_str.split('-'))
    h, mi     = map(int, birth_time_str.split(':'))
    utc_hour  = h + mi/60.0 - tz_offset

    # JD nascita
    jd_birth = float(ephem.Date(f"{y}/{mo}/{d} {utc_hour:.6f}")) + 2415020.0

    # JD Design: Sole esattamente 88 gradi prima
    sun = ephem.Sun()
    sun_birth_lon = get_ecliptic_lon(sun, jd_to_ephem_date(jd_birth))
    jd_design = find_design_jd(sun_birth_lon, jd_birth)

    personality = calc_planets(jd_birth)
    design      = calc_planets(jd_design)

    active_gates     = set(p['gate'] for p in personality + design)
    defined_channels = get_defined_channels(active_gates)
    defined_centers  = get_defined_centers(defined_channels)

    hd_type    = calc_type(defined_centers, defined_channels)
    profile    = calc_profile(personality, design)
    authority  = calc_authority(defined_centers)
    definition = calc_definition(defined_centers, defined_channels)
    cross      = calc_cross(personality, design, profile)

    sun_d  = next((p for p in design if p['planet_en'] == 'Sun'), {})
    node_d = next((p for p in design if p['planet_en'] == 'NorthNode'), {})
    sun_p  = next((p for p in personality if p['planet_en'] == 'Sun'), {})
    node_p = next((p for p in personality if p['planet_en'] == 'NorthNode'), {})

    # PHS — Diet: tipo dal COLOR del Nodo Nord Design, variante dal TONE
    # Environment: dal TONE del Nodo Nord Design
    # Fonte: KB hd-system-prompt.md
    diet_color   = node_d.get('color', 1)
    diet_tone    = node_d.get('tone', 1)
    diet_type    = PHS_TYPES.get(diet_color, '?')
    phs_variants = PHS_VARIANTS.get(diet_color)
    if phs_variants:
        diet_variant = phs_variants[0] if diet_tone >= 4 else phs_variants[1]
        dietary = f"{diet_type} {diet_variant}"
    else:
        dietary = diet_type

    env_tone    = node_d.get('tone', 1)
    environment = ENV_MAP.get(env_tone, '?')

    # Architettura cognitiva — 4 frecce Variable (senza interpretazione dieta/ambiente)
    # Sinistra = colori 1-3, Destra = colori 4-6
    arrow_dir = lambda c: 'Sinistra' if c <= 3 else 'Destra'
    arrow     = lambda c: 'L' if c <= 3 else 'R'

    variable = (
        ('P' if arrow(sun_d.get('color', 1)) == 'L' else 'D') +
        ('L' if arrow(node_d.get('color', 1)) == 'L' else 'R') +
        ' ' +
        ('P' if arrow(sun_p.get('color', 1)) == 'L' else 'D') +   # FIXED: era sempre 'D'
        ('L' if arrow(node_p.get('color', 1)) == 'L' else 'R')    # RIMOSSO duplicato
    )

    variable_arrows = (
        f"Sole Design (Digestione): {arrow_dir(sun_d.get('color', 1))} · "
        f"Nodo Design (Ambiente): {arrow_dir(node_d.get('color', 1))} · "
        f"Sole Personalità (Motivazione): {arrow_dir(sun_p.get('color', 1))} · "
        f"Nodo Personalità (Prospettiva): {arrow_dir(node_p.get('color', 1))}"
    )

    # Centri definiti/aperti in italiano
    all_centers_order = ['HEAD','AJNA','THROAT','G','HEART','SPLEEN','SOLAR','SACRAL','ROOT']
    defined_it   = [CENTER_IT[c] for c in all_centers_order if c in defined_centers]
    undefined_it = [CENTER_IT[c] for c in all_centers_order if c not in defined_centers]

    # Canali con nomi
    channels_list = []
    for g1, g2 in defined_channels:
        key = f"{g1}-{g2}"
        key2 = f"{g2}-{g1}"
        cname = CHANNEL_NAMES.get(key) or CHANNEL_NAMES.get(key2) or f"Canale {g1}-{g2}"
        c1 = CENTER_IT.get(GATE_CENTER.get(g1,''), '?')
        c2 = CENTER_IT.get(GATE_CENTER.get(g2,''), '?')
        channels_list.append({'name': key, 'title': cname, 'centers': f"{c1} <-> {c2}"})

    hanging_gates      = get_hanging_gates(active_gates, defined_channels)
    hanging_gates_rich = get_hanging_gates_rich(active_gates, defined_channels, personality, design)

    sig = SIGNATURES.get(hd_type, {'sig':'?','ns':'?'})
    cross_full = cross['full_name'] if cross else '?'
    cross_type = cross['angle'] if cross else '?'
    life_theme = f"{cross['theme']} {cross['variant']}, {cross['quarter']}" if cross else '?'
    profile_name = PROFILE_NAMES.get(profile, profile)

    # Attivazioni nel formato spike
    activations = [
        [p['planet_it'], f"{p['gate']}.{p['line']}", f"{design[i]['gate']}.{design[i]['line']}"]
        for i, p in enumerate(personality)
    ]

    chart = {
        'customer_name': name,
        'birth_date':    birth_date_str,
        'birth_time':    birth_time_str,
        'birth_place':   birth_place,

        'career_type':   CAREER_TYPE.get(hd_type, hd_type),
        'type':          hd_type,
        'strategy':      STRATEGIES.get(hd_type, '?'),
        'authority':     AUTHORITY_IT.get(authority, authority),
        'profile':       profile,
        'profile_name':  profile_name,
        'definition':    DEFINITION_IT.get(definition, definition),
        'life_theme':    life_theme,
        'variable':         variable,
        'variable_arrows':  variable_arrows,
        'diet':             dietary,
        'environment':      environment,
        'signature':     sig['sig'],
        'non_self':      sig['ns'],

        'defined_centers':   defined_it,
        'undefined_centers': undefined_it,
        'channels':          channels_list,
        'activations':          activations,
        'hanging_gates':        hanging_gates,
        'hanging_gates_rich':   hanging_gates_rich,
        'incarnation_cross': cross_full,
        'cross_type':        cross_type,

        '_debug': {
            'sun_personality_lon': round(sun_birth_lon, 4),
            'sun_design_lon':      round(sun_d.get('degrees', 0), 4),
            'jd_birth':            round(jd_birth, 4),
            'jd_design':           round(jd_design, 4),
        }
    }
    return chart


# ─── Main ─────────────────────────────────────────────────────────────────────

if __name__ == '__main__':
    args = sys.argv[1:]
    if len(args) < 5:
        print('Uso: python calc_chart_ephem.py <nome> <YYYY-MM-DD> <HH:MM> <tz_offset> <luogo>')
        sys.exit(1)
    name      = args[0]
    bdate     = args[1]
    btime     = args[2]
    tz_offset = float(args[3])
    bplace    = ' '.join(args[4:])
    chart = calculate_chart(name, bdate, btime, tz_offset, bplace)
    print(json.dumps(chart, ensure_ascii=False, indent=2))
