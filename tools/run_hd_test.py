import json, subprocess, sys

# Load reference data
with open('tools/test-reference-data.json') as f:
    profiles = json.load(f)

print(f"Testing {len(profiles)} profiles...")
print(f"{'Name':<25} {'Type':>5} {'Prof':>5} {'Variable':>10} | {'Status'}")
print("-" * 75)

# We'll output a JS script that can be run in the browser
# For now, just verify the data format
for p in profiles:
    # Parse variable: DRL-PRR -> dig=R, env=L, per=R, awa=R
    var = p.get('variable', '')
    if len(var) == 7 and var[0] == 'D' and var[4] == 'P':
        dig = var[1]  # R or L
        env = var[2]  # R or L  
        per = var[5]  # R or L
        awa = var[6]  # R or L
        arrows = f"{dig}{env}-{per}{awa}"
    else:
        arrows = "???"
    
    print(f"{p['name']:<25} {p.get('type_code','?'):>5} {p.get('profile','?'):>5} {var:>10} | arrows={arrows}")

print(f"\nTotal: {len(profiles)} profiles ready for testing")
