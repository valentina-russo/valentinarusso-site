import re, json

raw = [
{"gate":41,"RA":"Distraction (Transpersonal Focus)","J":"The Unexpected (Personal Focus)","LA":"The Unexpected (Personal Focus)"},
{"gate":19,"RA":"The Alpha (Transpersonal Focus)","J":"The Way (Personal Focus)","LA":"The Way (Personal Focus)"},
{"gate":13,"RA":"Refinement (Transpersonal Focus)","J":"Direction (Personal Focus)","LA":"Direction (Personal Focus)"},
{"gate":49,"RA":"Explanation (Personal Focus)","J":"Explanation (Personal Focus)","LA":"Explanation (Personal Focus)"},
{"gate":30,"RA":"Revolution (Transpersonal Focus)","J":"Contagion (Personal Focus)","LA":"Contagion (Personal Focus)"},
{"gate":55,"RA":"Evolve (Personal Focus)","J":"Evolve (Personal Focus)","LA":"Evolve (Personal Focus)"},
{"gate":37,"RA":"Planning (Personal Focus)","J":"Planning (Personal Focus)","LA":"Planning (Personal Focus)"},
{"gate":63,"RA":"Migration (Transpersonal Focus)","J":"Consciousness (Personal Focus)","LA":"Consciousness (Personal Focus)"},
{"gate":22,"RA":"Rulership (Personal Focus)","J":"Rulership (Personal Focus)","LA":"Rulership (Personal Focus)"},
{"gate":36,"RA":"Experience (Personal Focus)","J":"Experience (Personal Focus)","LA":"Crisis (Steadfast Focus)"},
{"gate":25,"RA":"Love (Personal Focus)","J":"Love (Personal Focus)","LA":"Love (Personal Focus)"},
{"gate":17,"RA":"Service (Personal Focus)","J":"Service (Personal Focus)","LA":"Service (Personal Focus)"},
{"gate":21,"RA":"Tension (Personal Focus)","J":"Tension (Personal Focus)","LA":"Endeavor (Transpersonal Focus)"},
{"gate":51,"RA":"Penetration (Personal Focus)","J":"Penetration (Personal Focus)","LA":"Penetration (Personal Focus)"},
{"gate":42,"RA":"Material World (Personal Focus)","J":"Material World (Personal Focus)","LA":"Material World (Personal Focus)"},
{"gate":3,"RA":"Laws (Personal Focus)","J":"Laws (Personal Focus)","LA":"Mutation (Steadfast Focus)"},
{"gate":27,"RA":"The Unexpected (Personal Focus)","J":"The Unexpected (Personal Focus)","LA":"Alignment (Transpersonal Focus)"},
{"gate":24,"RA":"The Way (Personal Focus)","J":"The Way (Personal Focus)","LA":"The Way (Personal Focus)"},
{"gate":2,"RA":"Direction (Personal Focus)","J":"Direction (Personal Focus)","LA":"Direction (Personal Focus)"},
{"gate":23,"RA":"Explanation (Personal Focus)","J":"Explanation (Personal Focus)","LA":"Explanation (Personal Focus)"},
{"gate":8,"RA":"Dedication (Transpersonal Focus)","J":"Contagion (Personal Focus)","LA":"Contagion (Personal Focus)"},
{"gate":20,"RA":"Uncertainty (Transpersonal Focus)","J":"Evolve (Personal Focus)","LA":"Evolve (Personal Focus)"},
{"gate":16,"RA":"Planning (Personal Focus)","J":"Planning (Personal Focus)","LA":"Planning (Personal Focus)"},
{"gate":35,"RA":"Identification (Transpersonal Focus)","J":"Consciousness (Personal Focus)","LA":"Consciousness (Personal Focus)"},
{"gate":45,"RA":"Separation (Transpersonal Focus)","J":"Rulership (Personal Focus)","LA":"Rulership (Personal Focus)"},
{"gate":12,"RA":"Confrontation (Transpersonal Focus)","J":"Experience (Personal Focus)","LA":"Experience (Personal Focus)"},
{"gate":15,"RA":"Education (Transpersonal Focus)","J":"Love (Personal Focus)","LA":"Love (Personal Focus)"},
{"gate":52,"RA":"Prevention (Transpersonal Focus)","J":"Service (Personal Focus)","LA":"Service (Personal Focus)"},
{"gate":39,"RA":"Demands (Transpersonal Focus)","J":"Tension (Personal Focus)","LA":"Tension (Personal Focus)"},
{"gate":53,"RA":"Individualism (Transpersonal Focus)","J":"Penetration (Personal Focus)","LA":"Penetration (Personal Focus)"},
{"gate":62,"RA":"Beginnings (Steadfast Focus)","J":"Material World (Personal Focus)","LA":"Material World (Personal Focus)"},
{"gate":56,"RA":"Obscuration (Transpersonal Focus)","J":"Laws 2 (Personal Focus)","LA":"Laws 2 (Personal Focus)"},
{"gate":31,"RA":"Distraction (Transpersonal Focus)","J":"The Unexpected (Personal Focus)","LA":"The Unexpected (Personal Focus)"},
{"gate":33,"RA":"The Alpha (Transpersonal Focus)","J":"The Alpha (Transpersonal Focus)","LA":"The Way (Personal Focus)"},
{"gate":7,"RA":"The Way (Personal Focus)","J":"Refinement (Transpersonal Focus)","LA":"Direction (Personal Focus)"},
{"gate":4,"RA":"Direction (Personal Focus)","J":"Masks (Transpersonal Focus)","LA":"Explanation (Personal Focus)"},
{"gate":29,"RA":"Explanation (Personal Focus)","J":"Revolution (Transpersonal Focus)","LA":"Contagion (Personal Focus)"},
{"gate":59,"RA":"Contagion (Personal Focus)","J":"Industry (Transpersonal Focus)","LA":"Industry (Transpersonal Focus)"},
{"gate":40,"RA":"Evolve (Personal Focus)","J":"Spirit (Transpersonal Focus)","LA":"Planning (Personal Focus)"},
{"gate":64,"RA":"Planning (Personal Focus)","J":"Migration (Transpersonal Focus)","LA":"Consciousness (Personal Focus)"},
{"gate":47,"RA":"Consciousness (Personal Focus)","J":"Dominion (Transpersonal Focus)","LA":"Dominion (Transpersonal Focus)"},
{"gate":6,"RA":"Rulership (Personal Focus)","J":"Informing (Transpersonal Focus)","LA":"Informing (Transpersonal Focus)"},
{"gate":46,"RA":"Experience (Personal Focus)","J":"The Material Plane (Transpersonal Focus)","LA":"The Material Plane (Transpersonal Focus)"},
{"gate":18,"RA":"Love (Personal Focus)","J":"Healing (Transpersonal Focus)","LA":"Service (Personal Focus)"},
{"gate":48,"RA":"Service (Personal Focus)","J":"Upheaval (Transpersonal Focus)","LA":"Upheaval (Transpersonal Focus)"},
{"gate":57,"RA":"Tension (Personal Focus)","J":"Endeavor (Transpersonal Focus)","LA":"Endeavor (Transpersonal Focus)"},
{"gate":32,"RA":"Penetration (Personal Focus)","J":"Intuition (Steadfast Focus)","LA":"Material World (Personal Focus)"},
{"gate":50,"RA":"Material World (Personal Focus)","J":"Limitation (Transpersonal Focus)","LA":"Limitation (Transpersonal Focus)"},
{"gate":28,"RA":"Laws (Personal Focus)","J":"Wishes (Transpersonal Focus)","LA":"Wishes (Transpersonal Focus)"},
{"gate":44,"RA":"The Unexpected (Personal Focus)","J":"Alignment (Transpersonal Focus)","LA":"The Way (Personal Focus)"},
{"gate":1,"RA":"The Way (Personal Focus)","J":"Incarnation (Transpersonal Focus)","LA":"Direction (Personal Focus)"},
{"gate":43,"RA":"Direction (Personal Focus)","J":"Defiance (Transpersonal Focus)","LA":"Defiance (Transpersonal Focus)"},
{"gate":14,"RA":"Explanation (Personal Focus)","J":"Dedication (Transpersonal Focus)","LA":"Contagion (Personal Focus)"},
{"gate":34,"RA":"Contagion (Personal Focus)","J":"Uncertainty (Transpersonal Focus)","LA":"Evolve (Personal Focus)"},
{"gate":9,"RA":"Evolve (Personal Focus)","J":"Duality (Transpersonal Focus)","LA":"Planning (Personal Focus)"},
{"gate":5,"RA":"Planning (Personal Focus)","J":"Identification (Transpersonal Focus)","LA":"Consciousness (Personal Focus)"},
{"gate":26,"RA":"Habits (Steadfast Focus)","J":"Rulership (Personal Focus)","LA":"Rulership (Personal Focus)"},
{"gate":11,"RA":"Rulership (Personal Focus)","J":"Confrontation (Transpersonal Focus)","LA":"Experience (Personal Focus)"},
{"gate":10,"RA":"Ideas (Steadfast Focus)","J":"Love (Personal Focus)","LA":"Love (Personal Focus)"},
{"gate":58,"RA":"Prevention (Transpersonal Focus)","J":"Service 4 (Personal Focus)","LA":"Service 4 (Personal Focus)"},
{"gate":38,"RA":"Demands (Transpersonal Focus)","J":"Tension (Personal Focus)","LA":"Tension (Personal Focus)"},
{"gate":54,"RA":"Individualism (Transpersonal Focus)","J":"Penetration (Personal Focus)","LA":"Penetration (Personal Focus)"},
{"gate":61,"RA":"Cycles (Transpersonal Focus)","J":"Material World (Personal Focus)","LA":"Material World (Personal Focus)"},
{"gate":60,"RA":"Obscuration (Transpersonal Focus)","J":"Laws (Personal Focus)","LA":"Laws (Personal Focus)"},
]

cross_map = {}
for r in raw:
    g = r["gate"]
    if g not in cross_map:
        cross_map[g] = {}
    for col in ["RA","J","LA"]:
        m = re.match(r"(.+?)\s*\((\w+)\s+Focus\)", r[col])
        if m:
            name, focus = m.group(1).strip(), m.group(2)
            cross_map[g][focus] = name

p = sum(1 for g in cross_map if "Personal" in cross_map[g])
t = sum(1 for g in cross_map if "Transpersonal" in cross_map[g])
s = sum(1 for g in cross_map if "Steadfast" in cross_map[g])
print(f"Personal: {p}/64, Transpersonal: {t}/64, Steadfast: {s}/64")
print(f"Missing Personal: {[g for g in cross_map if 'Personal' not in cross_map[g]]}")
print(f"Missing Transpersonal: {[g for g in cross_map if 'Transpersonal' not in cross_map[g]]}")
print()

# Output JS-ready mapping
print("// CROSS_NAMES[gate] = {P: name, T: name, S: name}")
for g in sorted(cross_map.keys()):
    vals = cross_map[g]
    p_name = vals.get("Personal","?")
    t_name = vals.get("Transpersonal","?")
    s_name = vals.get("Steadfast","?")
    print(f"  {g}: {{P:'{p_name}',T:'{t_name}',S:'{s_name}'}},")
