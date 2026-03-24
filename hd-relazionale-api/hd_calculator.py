"""
Human Design Calculator — motore di calcolo HD puro
Usa pyswisseph con Moshier ephemeris (nessun file dati, copre 3000 AC – 3000 DC)
"""
import swisseph as swe
from datetime import datetime, timezone, timedelta

# ---------------------------------------------------------------------------
# Ruota Rave I-Ching: 64 gate in ordine crescente di longitudine eclittica
# Inizia a 0° Ariete = Gate 41 (linea 1)
# ---------------------------------------------------------------------------
GATE_WHEEL = [
    41, 19, 13, 49, 30, 55, 37, 63, 22, 36,
    25, 17, 21, 51, 42,  3, 27, 24,  2, 23,
     8, 20, 16, 35, 45, 12, 15, 52, 39, 53,
    62, 56, 31, 33,  7,  4, 29, 59, 40, 64,
    47,  6, 46, 18, 48, 57, 32, 50, 28, 44,
     1, 43, 14, 34,  9,  5, 26, 11, 10, 58,
    38, 54, 61, 60,
]

GATE_SIZE  = 360.0 / 64   # 5.625° per gate
LINE_SIZE  = GATE_SIZE / 6 # 0.9375° per linea

# ---------------------------------------------------------------------------
# Canali: 36 coppie (gate_a, gate_b)
# ---------------------------------------------------------------------------
CHANNELS = [
    # HEAD → AJNA
    (64, 47), (61, 24), (63,  4),
    # AJNA → THROAT
    (17, 62), (43, 23), (11, 56),
    # THROAT → SPLEEN
    (16, 48), (20, 57),
    # THROAT → G
    (31,  7), ( 8,  1), (33, 13), (10, 20),
    # THROAT → HEART
    (45, 21),
    # THROAT → SOLAR
    (35, 36), (12, 22),
    # THROAT → SACRAL
    (20, 34),
    # HEART → SPLEEN
    (26, 44),
    # HEART → G
    (51, 25),
    # HEART → SOLAR
    (40, 37),
    # G → SACRAL
    ( 2, 14), ( 5, 15), (29, 46),
    # SACRAL → SPLEEN
    (27, 50), (34, 57),
    # SACRAL → SOLAR
    (59,  6),
    # SACRAL → ROOT
    ( 3, 60), ( 9, 52), (42, 53),
    # SPLEEN → ROOT
    (18, 58), (28, 38), (32, 54),
    # SOLAR → ROOT
    (30, 41), (39, 55), (19, 49),
]

# Gate → Centro
GATE_CENTER = {}
for g in [64, 61, 63]:                                          GATE_CENTER[g] = 'HEAD'
for g in [47, 24, 4, 17, 43, 11]:                               GATE_CENTER[g] = 'AJNA'
for g in [62, 23, 56, 16, 31, 8, 33, 45, 35, 12, 20]:          GATE_CENTER[g] = 'THROAT'
for g in [7, 13, 1, 10, 25, 15, 2, 46]:                         GATE_CENTER[g] = 'G'
for g in [21, 51, 26, 40]:                                      GATE_CENTER[g] = 'HEART'
for g in [48, 57, 44, 50, 32, 28, 18]:                          GATE_CENTER[g] = 'SPLEEN'
for g in [36, 22, 37, 6, 30, 55, 49]:                           GATE_CENTER[g] = 'SOLAR'
for g in [34, 5, 14, 29, 3, 42, 9, 27, 59]:                     GATE_CENTER[g] = 'SACRAL'
for g in [53, 60, 52, 54, 58, 38, 41, 39, 19]:                  GATE_CENTER[g] = 'ROOT'

# Pianeti HD (id swe, nome italiano, nome API)
PLANETS = [
    (swe.SUN,        'Sole',          'Sun'),
    (swe.EARTH,      'Terra',         'Earth'),
    (swe.MOON,       'Luna',          'Moon'),
    (swe.MEAN_NODE,  'Nodo Nord',     'NorthNode'),
    (-1,             'Nodo Sud',      'SouthNode'),   # calcolato come 180° da NN
    (swe.MERCURY,    'Mercurio',      'Mercury'),
    (swe.VENUS,      'Venere',        'Venus'),
    (swe.MARS,       'Marte',         'Mars'),
    (swe.JUPITER,    'Giove',         'Jupiter'),
    (swe.SATURN,     'Saturno',       'Saturn'),
    (swe.URANUS,     'Urano',         'Uranus'),
    (swe.NEPTUNE,    'Nettuno',       'Neptune'),
    (swe.PLUTO,      'Plutone',       'Pluto'),
]

# Nomi italiani
TYPE_IT = {
    'Generator':             'Generatore',
    'ManifestingGenerator':  'Generatore Manifestante',
    'Projector':             'Proiettore',
    'Manifestor':            'Manifestatore',
    'Reflector':             'Riflettore',
}
AUTHORITY_IT = {
    'Emotional':  'Emotiva',
    'Sacral':     'Sacrale',
    'Splenic':    'Splenica',
    'Ego':        'Ego / Cuore',
    'G/Self':     'Sé',
    'Mental':     'Mentale',
    'Lunar':      'Lunare',
    'None':       'Nessuna',
}
DEFINITION_IT = {
    'Single':         'Singola',
    'Split':          'Doppia',
    'TripleSplit':    'Tripla Divisione',
    'QuadrupleSplit': 'Quadrupla Divisione',
    'None':           'Nessuna',
}

# ---------------------------------------------------------------------------
# Utilità
# ---------------------------------------------------------------------------

def degrees_to_gate_line(deg: float) -> tuple[int, int]:
    deg = deg % 360.0
    idx = int(deg / GATE_SIZE)
    gate = GATE_WHEEL[idx]
    rem  = deg - idx * GATE_SIZE
    line = min(int(rem / LINE_SIZE) + 1, 6)
    return gate, line


def datetime_to_jd(year: int, month: int, day: int,
                   hour: int, minute: int, tz_offset: float) -> float:
    """Converte data/ora locale in Julian Day (UTC)."""
    dt_local = datetime(year, month, day, hour, minute, tzinfo=timezone.utc)
    dt_utc   = dt_local - timedelta(hours=tz_offset)
    return swe.julday(dt_utc.year, dt_utc.month, dt_utc.day,
                      dt_utc.hour + dt_utc.minute / 60.0)


def calc_planets(jd: float) -> list[dict]:
    """Calcola gate/linea per tutti i pianeti HD a un dato JD."""
    results = []
    nn_lon  = None

    for planet_id, name_it, name_en in PLANETS:
        if planet_id == -1:
            # Nodo Sud = opposto Nodo Nord
            lon = (nn_lon + 180.0) % 360.0 if nn_lon is not None else 0.0
        elif planet_id == swe.EARTH:
            # Terra = opposto Sole
            sun_res = next((r for r in results if r['planet_en'] == 'Sun'), None)
            lon = (sun_res['degrees'] + 180.0) % 360.0 if sun_res else 0.0
        else:
            flags = swe.FLG_MOSEPH  # Moshier: nessun file dati richiesto
            ret, _ = swe.calc_ut(jd, planet_id, flags)
            lon = ret[0]
            if planet_id == swe.MEAN_NODE:
                nn_lon = lon

        gate, line = degrees_to_gate_line(lon)
        results.append({
            'planet_it': name_it,
            'planet_en': name_en,
            'gate':      gate,
            'line':      line,
            'degrees':   round(lon, 4),
        })

    return results


# ---------------------------------------------------------------------------
# Logica HD: centri, tipo, profilo, autorità, definizione
# ---------------------------------------------------------------------------

def get_defined_centers(active_gates: set[int]) -> set[str]:
    """Restituisce i centri definiti (entrambi i gate di almeno un canale attivi)."""
    defined = set()
    for g1, g2 in CHANNELS:
        if g1 in active_gates and g2 in active_gates:
            defined.add(GATE_CENTER.get(g1, ''))
            defined.add(GATE_CENTER.get(g2, ''))
    defined.discard('')
    return defined


def get_defined_channels(active_gates: set[int]) -> list[tuple[int, int]]:
    return [(g1, g2) for g1, g2 in CHANNELS
            if g1 in active_gates and g2 in active_gates]


def calc_type(defined_centers: set[str], defined_channels: list) -> str:
    has_sacral  = 'SACRAL'  in defined_centers
    has_throat  = 'THROAT'  in defined_centers
    has_heart   = 'HEART'   in defined_centers
    has_solar   = 'SOLAR'   in defined_centers
    has_root    = 'ROOT'    in defined_centers
    has_spleen  = 'SPLEEN'  in defined_centers

    # Motori: SACRAL, HEART, SOLAR, ROOT
    motors = {'SACRAL', 'HEART', 'SOLAR', 'ROOT'}

    if not defined_centers:
        return 'Reflector'

    # Riflettore: nessun centro definito con motore
    if not (defined_centers & motors):
        return 'Reflector'

    # Determina se un motore è connesso direttamente o indirettamente a THROAT
    def motor_connected_to_throat(start_center: str, visited=None) -> bool:
        if visited is None:
            visited = set()
        if start_center in visited:
            return False
        visited.add(start_center)
        if start_center == 'THROAT':
            return True
        # Cerca centri adiacenti tramite canali definiti
        adjacent = set()
        for g1, g2 in defined_channels:
            c1 = GATE_CENTER.get(g1, '')
            c2 = GATE_CENTER.get(g2, '')
            if c1 == start_center:
                adjacent.add(c2)
            elif c2 == start_center:
                adjacent.add(c1)
        return any(motor_connected_to_throat(c, visited) for c in adjacent)

    throat_connected_to_sacral = has_sacral and motor_connected_to_throat('SACRAL')
    throat_connected_to_motor  = any(
        motor_connected_to_throat(m)
        for m in (motors - {'SACRAL'}) & defined_centers
    )

    if has_sacral:
        if throat_connected_to_sacral:
            return 'ManifestingGenerator'
        return 'Generator'

    # Senza Sacrale
    if throat_connected_to_motor:
        return 'Manifestor'

    return 'Projector'


def calc_profile(personality: list[dict]) -> str:
    sun_p = next((p for p in personality if p['planet_en'] == 'Sun'), None)
    # Terra è sempre opposta al Sole, con linea armonica
    earth_p = next((p for p in personality if p['planet_en'] == 'Earth'), None)
    if not sun_p or not earth_p:
        return '?/?'
    return f"{sun_p['line']}/{earth_p['line']}"


def calc_authority(defined_centers: set[str]) -> str:
    order = ['SOLAR', 'SACRAL', 'SPLEEN', 'HEART', 'G']
    auth_map = {
        'SOLAR':  'Emotional',
        'SACRAL': 'Sacral',
        'SPLEEN': 'Splenic',
        'HEART':  'Ego',
        'G':      'G/Self',
    }
    for center in order:
        if center in defined_centers:
            return auth_map[center]
    if defined_centers:
        return 'Mental'
    return 'Lunar'


def calc_definition(defined_centers: set[str], defined_channels: list) -> str:
    if not defined_centers:
        return 'None'

    # Graph connectivity: quanti componenti connesse tra i centri definiti?
    adj: dict[str, set[str]] = {c: set() for c in defined_centers}
    for g1, g2 in defined_channels:
        c1, c2 = GATE_CENTER.get(g1, ''), GATE_CENTER.get(g2, '')
        if c1 in adj and c2 in adj:
            adj[c1].add(c2)
            adj[c2].add(c1)

    visited: set[str] = set()
    components = 0
    for start in defined_centers:
        if start in visited:
            continue
        components += 1
        stack = [start]
        while stack:
            node = stack.pop()
            if node in visited:
                continue
            visited.add(node)
            stack.extend(adj[node] - visited)

    return {1: 'Single', 2: 'Split', 3: 'TripleSplit', 4: 'QuadrupleSplit'}.get(
        components, 'QuadrupleSplit'
    )


# ---------------------------------------------------------------------------
# Funzione principale
# ---------------------------------------------------------------------------

def calculate_chart(year: int, month: int, day: int,
                    hour: int, minute: int, tz_offset: float) -> dict:
    """
    Calcola la Carta HD completa.
    tz_offset: ore da UTC (es. +1 per Italia invernale, +2 per estiva)
    """
    swe.set_ephe_path(None)  # usa Moshier built-in

    jd_birth  = datetime_to_jd(year, month, day, hour, minute, tz_offset)
    jd_design = jd_birth - 88.736          # ~88 gradi solari prima della nascita

    personality = calc_planets(jd_birth)
    design      = calc_planets(jd_design)

    # Gate attivi (tutti i pianeti, entrambe le attivazioni)
    active_gates = set()
    for p in personality + design:
        active_gates.add(p['gate'])

    defined_channels = get_defined_channels(active_gates)
    defined_centers  = get_defined_centers(active_gates)

    hd_type    = calc_type(defined_centers, defined_channels)
    profile    = calc_profile(personality)
    authority  = calc_authority(defined_centers)
    definition = calc_definition(defined_centers, defined_channels)

    # Determina colore gate: P=Personalità, D=Design, PD=entrambi
    p_gates = {p['gate'] for p in personality}
    d_gates = {p['gate'] for p in design}

    gate_colors: dict[int, str] = {}
    for g in p_gates | d_gates:
        if g in p_gates and g in d_gates:
            gate_colors[g] = 'PD'
        elif g in p_gates:
            gate_colors[g] = 'P'
        else:
            gate_colors[g] = 'D'

    return {
        'type':             TYPE_IT.get(hd_type, hd_type),
        'profile':          profile,
        'authority':        AUTHORITY_IT.get(authority, authority),
        'definition':       DEFINITION_IT.get(definition, definition),
        'defined_centers':  sorted(defined_centers),
        'defined_channels': [[g1, g2] for g1, g2 in defined_channels],
        'gate_colors':      {str(k): v for k, v in gate_colors.items()},
        'personality':      personality,
        'design':           design,
    }
