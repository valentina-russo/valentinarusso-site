"""Cosa cerca la gente che ci trova su Google.

Analytics dice chi e' arrivato, Search Console dice cosa ha cercato e a che
posto siamo: sono i due numeri che servono per decidere dove intervenire.
L'account di servizio delle statistiche e' gia' proprietario del sito, quindi
non serve nessuna autenticazione a mano.

    py tools/search_console.py                 ultimi 28 giorni
    py tools/search_console.py 90              ultimi 90 giorni
    py tools/search_console.py 28 /human-design   solo un ramo del sito
"""
import json
import sys
import urllib.parse
import urllib.request
from datetime import date, timedelta
from pathlib import Path

from google.oauth2 import service_account
import google.auth.transport.requests

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

SA_FILE = Path(__file__).resolve().parents[1] / "grav-site/root/ga4-service-account.json"
SITO = "https://valentinarussobg5.com/"
# Search Console consolida i dati con due o tre giorni di ritardo: chiedere
# ieri darebbe una riga vuota e sembrerebbe un crollo.
RITARDO = 3


def client():
    creds = service_account.Credentials.from_service_account_file(
        str(SA_FILE), scopes=["https://www.googleapis.com/auth/webmasters.readonly"])
    creds.refresh(google.auth.transport.requests.Request())
    return creds.token


def interroga(token, inizio, fine, dimensioni=None, filtri=None, righe=25):
    corpo = {
        "startDate": inizio.isoformat(),
        "endDate": fine.isoformat(),
        "rowLimit": righe,
    }
    if dimensioni:
        corpo["dimensions"] = dimensioni
    if filtri:
        corpo["dimensionFilterGroups"] = [{"filters": filtri}]
    req = urllib.request.Request(
        "https://searchconsole.googleapis.com/webmasters/v3/sites/"
        + urllib.parse.quote(SITO, safe="") + "/searchAnalytics/query",
        data=json.dumps(corpo).encode(),
        headers={"Authorization": "Bearer " + token, "Content-Type": "application/json"})
    return json.load(urllib.request.urlopen(req, timeout=30)).get("rows", [])


def riga_totali(righe):
    if not righe:
        return (0, 0, 0.0, 0.0)
    r = righe[0]
    return (int(r["clicks"]), int(r["impressions"]), r["ctr"] * 100, r["position"])


def var(a, b):
    if not b:
        return "nuovo" if a else "-"
    return f"{(a - b) / b * 100:+.0f}%"


def main() -> int:
    giorni = int(sys.argv[1]) if len(sys.argv) > 1 else 28
    ramo = sys.argv[2] if len(sys.argv) > 2 else None
    if ramo:
        # su Git Bash "/human-design" arriva come "C:/Program Files/Git/human-design"
        ramo = "/" + ramo.replace("\\", "/").rstrip("/").rsplit("/", 1)[-1]

    fine = date.today() - timedelta(days=RITARDO)
    inizio = fine - timedelta(days=giorni - 1)
    pre_fine = inizio - timedelta(days=1)
    pre_inizio = pre_fine - timedelta(days=giorni - 1)

    token = client()
    filtri = [{"dimension": "page", "operator": "contains", "expression": ramo}] if ramo else None

    print(f"RICERCA GOOGLE — dal {inizio} al {fine} ({giorni} giorni)"
          + (f", solo {ramo}" if ramo else ""))
    print(f"confronto con {pre_inizio} → {pre_fine}\n")

    c, i, ctr, pos = riga_totali(interroga(token, inizio, fine, None, filtri, 1))
    pc, pi, pctr, ppos = riga_totali(interroga(token, pre_inizio, pre_fine, None, filtri, 1))
    print(f"{'clic':<16}{c:>8}   prima {pc:>7}   {var(c, pc)}")
    print(f"{'impressioni':<16}{i:>8}   prima {pi:>7}   {var(i, pi)}")
    print(f"{'clic su cento':<16}{ctr:>8.1f}%  prima {pctr:>6.1f}%")
    print(f"{'posizione media':<16}{pos:>8.1f}   prima {ppos:>7.1f}")

    print("\nCOSA CERCANO (per impressioni)")
    print(f"  {'parole':<46}{'clic':>6}{'impr.':>8}{'pos.':>7}")
    for r in interroga(token, inizio, fine, ["query"], filtri, 20):
        q = r["keys"][0][:44]
        print(f"  {q:<46}{int(r['clicks']):>6}{int(r['impressions']):>8}{r['position']:>7.1f}")

    print("\nA UN PASSO DALLA PRIMA PAGINA (posizione 5-20, con richiesta vera)")
    vicine = [r for r in interroga(token, inizio, fine, ["query"], filtri, 200)
              if 5 <= r["position"] <= 20 and r["impressions"] >= 5]
    vicine.sort(key=lambda r: -r["impressions"])
    if vicine:
        for r in vicine[:12]:
            print(f"  {r['keys'][0][:44]:<46}{int(r['clicks']):>6}{int(r['impressions']):>8}{r['position']:>7.1f}")
    else:
        print("  nessuna: o siamo gia' in alto, o la richiesta e' ancora troppo bassa")

    print("\nDOMANDE (cosa, come, perche', quale, quando)")
    parole = ("cosa", "come", "perch", "quale", "quali", "quando", "significa", "differenza")
    dom = [r for r in interroga(token, inizio, fine, ["query"], filtri, 500)
           if any(p in r["keys"][0].lower() for p in parole)]
    dom.sort(key=lambda r: -r["impressions"])
    if dom:
        for r in dom[:12]:
            print(f"  {r['keys'][0][:44]:<46}{int(r['clicks']):>6}{int(r['impressions']):>8}{r['position']:>7.1f}")
        tot_dom = sum(int(r["impressions"]) for r in dom)
        print(f"  in tutto {len(dom)} ricerche a domanda, {tot_dom} impressioni")
    else:
        print("  nessuna ancora")

    print("\nLE PAGINE")
    print(f"  {'pagina':<52}{'clic':>6}{'impr.':>8}{'pos.':>7}")
    for r in interroga(token, inizio, fine, ["page"], filtri, 20):
        p = r["keys"][0].replace(SITO.rstrip("/"), "")[:50] or "/"
        print(f"  {p:<52}{int(r['clicks']):>6}{int(r['impressions']):>8}{r['position']:>7.1f}")

    print("\nVISTE MA MAI CLICCATE (titolo o descrizione da rifare)")
    mute = [r for r in interroga(token, inizio, fine, ["page"], filtri, 200)
            if int(r["clicks"]) == 0 and r["impressions"] >= 20]
    mute.sort(key=lambda r: -r["impressions"])
    if mute:
        for r in mute[:10]:
            p = r["keys"][0].replace(SITO.rstrip("/"), "")[:50] or "/"
            print(f"  {p:<52}{int(r['impressions']):>8} impr.{r['position']:>7.1f}")
    else:
        print("  nessuna pagina con richiesta alta e zero clic")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
