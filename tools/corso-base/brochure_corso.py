# -*- coding: utf-8 -*-
"""Brochure PDF del Corso Base di Human Design, piu' una PNG per pagina.

I fatti vengono dalla pagina del corso: due semestri da dieci lezioni, due ore
a lezione dal vivo su Zoom, dal 12 ottobre 2026 ogni lunedi' alle 18, 700 euro
a semestre o 1.200 il percorso completo, Klarna al checkout, si parte con
almeno tre iscritti. Niente e' inventato.

    py tools/corso-base/brochure_corso.py
"""
import sys
from pathlib import Path

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.platypus import Paragraph
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas as pdfcanvas
from reportlab.lib.utils import ImageReader
from PIL import Image

RADICE = Path(__file__).resolve().parent.parent.parent
FONT = RADICE / "tools" / "bg5-generator" / "fonts"
FOTO = RADICE / "grav-site" / "user" / "pages" / "assets" / "valentina.jpg"
OUT = RADICE / "grav-site" / "user" / "pages" / "assets" / "corso-base" / "brochure"
PDF = OUT / "brochure-corso-base-human-design.pdf"

W, H = A4
M = 18 * mm
NAVY = colors.HexColor("#1A2332")
BRASS = colors.HexColor("#8A5A12")
BRASS_L = colors.HexColor("#C48A3A")
PAPER = colors.HexColor("#F2EEE3")
CARTA = colors.white
INK = colors.HexColor("#1A2332")
MUTO = colors.HexColor("#6B625D")
LINEA = colors.HexColor("#DED6CB")
SITO = "valentinarussobg5.com/corso-base-human-design"
TEL = "+39 379 103 7653"

for nome, file in [("PF-B", "PlayfairDisplay-Bold.ttf"), ("PF-I", "PlayfairDisplay-Italic.ttf"),
                   ("OUT", "Outfit-Regular.ttf"), ("OUT-M", "Outfit-Medium.ttf"),
                   ("OUT-SB", "Outfit-SemiBold.ttf"), ("OUT-B", "Outfit-Bold.ttf")]:
    pdfmetrics.registerFont(TTFont(nome, str(FONT / file)))


def stile(font, dim, colore, inter=None, dopo=0, prima=0):
    return ParagraphStyle("s", fontName=font, fontSize=dim, leading=inter or dim * 1.42,
                          textColor=colore, alignment=TA_LEFT, spaceAfter=dopo, spaceBefore=prima)


S_CORPO = stile("OUT", 10.5, INK, 15.5, dopo=7)
S_MUTO = stile("OUT", 10, MUTO, 14.5, dopo=6)
S_H2 = stile("PF-B", 22, INK, 27, dopo=8)
S_H3 = stile("PF-B", 13.5, INK, 17, dopo=3, prima=4)
S_VOCE_T = stile("OUT-SB", 10.5, INK, 14)
S_VOCE_D = stile("OUT", 9.5, MUTO, 13, dopo=5)


def para(c, testo, st, x, y, larg):
    """Disegna un paragrafo col bordo alto in y; ritorna la y sotto."""
    p = Paragraph(testo, st)
    _, h = p.wrap(larg, H)
    p.drawOn(c, x, y - h)
    return y - h - st.spaceAfter


def etichetta(c, testo, x, y, colore=BRASS):
    c.setFont("OUT-B", 8.5); c.setFillColor(colore); c.drawString(x, y, testo.upper())
    return y - 14


def filetto(c, x, y, larg=26 * mm, colore=BRASS):
    c.setStrokeColor(colore); c.setLineWidth(1.2); c.line(x, y, x + larg, y)


def piede(c, n, totale):
    c.setFillColor(PAPER); c.rect(0, 0, W, H, fill=1, stroke=0)
    c.setStrokeColor(LINEA); c.setLineWidth(0.6); c.line(M, 14 * mm, W - M, 14 * mm)
    c.setFont("OUT-M", 8.5); c.setFillColor(MUTO)
    c.drawString(M, 9 * mm, "Corso Base di Human Design  ·  Valentina Russo  ·  " + SITO)
    c.setFillColor(BRASS); c.setFont("OUT-B", 8.5); c.drawRightString(W - M, 9 * mm, "%d / %d" % (n, totale))


def intestazione(c, etic, titolo):
    x, larg = M, W - 2 * M; y = H - M - 4 * mm
    y = etichetta(c, etic, x, y)
    y = para(c, titolo, S_H2, x, y, larg)
    filetto(c, x, y - 2 * mm)
    return x, larg, y - 9 * mm


def copertina(c):
    c.setFillColor(NAVY); c.rect(0, 0, W, H, fill=1, stroke=0)
    c.setStrokeColor(BRASS_L); c.setLineWidth(1)
    c.rect(M * 0.55, M * 0.55, W - 1.1 * M, H - 1.1 * M, fill=0, stroke=1)
    c.setFillColor(BRASS_L); c.setFont("OUT-B", 10)
    c.drawCentredString(W / 2, H - 42 * mm, "VENTI LEZIONI DAL VIVO  ·  DAL 12 OTTOBRE 2026")
    c.setFillColor(CARTA); c.setFont("PF-B", 40)
    c.drawCentredString(W / 2, H - 64 * mm, "Corso Base di")
    c.drawCentredString(W / 2, H - 80 * mm, "Human Design")
    filetto(c, W / 2 - 13 * mm, H - 88 * mm, colore=BRASS_L)
    c.setFillColor(colors.HexColor("#F4EFE9")); c.setFont("PF-I", 15)
    c.drawCentredString(W / 2, H - 100 * mm, "Imparare a leggere un Bodygraph, il tuo e quello degli altri.")
    if FOTO.is_file():
        im = Image.open(FOTO).convert("RGB")
        lato = min(im.size); l, t = (im.width - lato) // 2, int((im.height - lato) * 0.15)
        im = im.crop((l, t, l + lato, t + lato)).resize((900, 900))
        d = 74 * mm; x, y = W / 2 - d / 2, H - 190 * mm
        c.saveState()
        p = c.beginPath(); p.circle(x + d / 2, y + d / 2, d / 2); c.clipPath(p, stroke=0)
        c.drawImage(ImageReader(im), x, y, d, d)
        c.restoreState()
        c.setStrokeColor(BRASS_L); c.setLineWidth(1.5); c.circle(x + d / 2, y + d / 2, d / 2 + 2, fill=0)
    c.setFillColor(CARTA); c.setFont("PF-B", 15)
    c.drawCentredString(W / 2, H - 202 * mm, "Valentina Russo")
    c.setFillColor(BRASS_L); c.setFont("OUT-M", 9.5)
    c.drawCentredString(W / 2, H - 209 * mm, "Analista Certificata BG5® (Business Group 5) e Human Design")
    c.setFillColor(colors.HexColor("#B9AFA6")); c.setFont("OUT", 9)
    c.drawCentredString(W / 2, 22 * mm, SITO + "   ·   " + TEL)


def pag_cos_e(c):
    x, larg, y = intestazione(c, "Da dove si parte", "Cos'è lo Human Design")
    y = para(c, "Lo Human Design descrive come sei fatto: dove prendi le decisioni, come assorbi "
             "l'energia di chi ti sta intorno, cosa ti stanca e cosa ti rimette in moto. Il disegno, "
             "il Bodygraph, si calcola da data, ora e luogo di nascita: nove centri, trentasei canali, "
             "sessantaquattro porte. Imparare a leggerlo vuol dire smettere di indovinare e cominciare "
             "a riconoscere quello che hai davanti.", S_CORPO, x, y, larg)
    y -= 4 * mm
    y = para(c, "Perché un corso, e non solo una lettura", S_H3, x, y, larg)
    y = para(c, "Chi arriva a questo corso di solito ha già incontrato il sistema, in una lettura "
             "individuale, in una conversazione o in un articolo, e sente che c'è dell'altro sotto. "
             "La lettura individuale è una fotografia del momento che stai vivendo. Il corso è il "
             "sistema completo: gli strumenti per leggere la tua carta in autonomia, e quella di "
             "chiunque altro.", S_CORPO, x, y, larg)
    y -= 3 * mm
    col = (larg - 8 * mm) / 2
    schede = [
        ("UN PUNTO DI PARTENZA", "Lettura Foundation individuale",
         ["Una sessione 1:1 sulla tua carta", "Applicata al momento che vivi adesso",
          "Una fotografia, non un sistema da portarti a casa"]),
        ("IL SISTEMA COMPLETO", "Corso Base di Human Design",
         ["Nove centri, cinque Tipologie, i temi del Non-Sé, i circuiti, i dodici Profili",
          "Venti lezioni dal vivo in due semestri, anche a rate",
          "Gli strumenti per leggere in autonomia"]),
    ]
    alt = 50 * mm
    for i, (etic, tit, righe) in enumerate(schede):
        bx = x + i * (col + 8 * mm)
        c.setFillColor(CARTA); c.setStrokeColor(LINEA); c.setLineWidth(0.8)
        c.roundRect(bx, y - alt, col, alt, 3 * mm, fill=1, stroke=1)
        yy = y - 8 * mm
        c.setFillColor(BRASS); c.setFont("OUT-B", 8.5); c.drawString(bx + 6 * mm, yy, etic); yy -= 7 * mm
        yy = para(c, tit, stile("PF-B", 13, INK, 16, dopo=4), bx + 6 * mm, yy, col - 12 * mm)
        for r in righe:
            yy = para(c, "·  " + r, stile("OUT", 9.3, MUTO, 12.5, dopo=2), bx + 6 * mm, yy, col - 12 * mm)
    y -= alt + 5 * mm
    y = para(c, "Molte persone iniziano con la Lettura e arrivano al Corso quando cercano il metodo, "
             "non solo la fotografia del momento.", stile("PF-I", 10.5, MUTO, 14, dopo=10), x, y, larg)
    per_chi(c, x, larg, y)


def per_chi(c, x, larg, y):
    """Meta' bassa della pagina 2: una pagina a se' restava per meta' vuota."""
    y = para(c, "Per chi è, e per chi non è", S_H3, x, y, larg)
    y = para(c, "Un percorso di venti settimane è un impegno vero. Meglio saperlo prima di iscriversi.",
             S_MUTO, x, y, larg)
    y -= 2 * mm
    col = (larg - 10 * mm) / 2
    blocchi = [
        ("È PER TE SE", BRASS, [
            "Vuoi leggere il tuo Bodygraph in autonomia, oltre la singola lettura individuale.",
            "Lavori con le persone, in HR, come coach, consulente o manager, e vuoi uno strumento in più per capire chi hai davanti.",
            "Hai già fatto la Lettura Foundation e vuoi andare a fondo nel sistema.",
            "Vuoi uno strumento per leggere anche le persone intorno a te, non solo te stesso."]),
        ("NON È PER TE SE", MUTO, [
            "Cerchi un percorso registrato da seguire quando vuoi: le lezioni sono dal vivo, con un calendario fisso su Zoom.",
            "Sai già che non riuscirai a esserci alla maggior parte delle venti lezioni.",
            "Cerchi una risposta rapida su un singolo aspetto della tua carta: per quello c'è la lettura individuale.",
            "Non puoi impegnare circa due ore a settimana per venti settimane."]),
    ]
    for i, (tit, colore, voci) in enumerate(blocchi):
        bx = x + i * (col + 10 * mm); yy = y
        c.setFillColor(colore); c.setFont("OUT-B", 9); c.drawString(bx, yy, tit); yy -= 8 * mm
        for v in voci:
            c.setFillColor(colore); c.circle(bx + 1.2 * mm, yy - 1.4 * mm, 1.1 * mm, fill=1, stroke=0)
            yy = para(c, v, stile("OUT", 10, INK, 14, dopo=5), bx + 5 * mm, yy + 1.8 * mm, col - 5 * mm)


S1 = [("Introduzione allo Human Design", "Le basi del Bodygraph, Personalità e Design, aspetti conscio e inconscio"),
      ("I 9 Centri", "I centri energetici che compongono ogni Bodygraph e le 5 Tipologie che li attraversano"),
      ("Il Generatore", "Una delle 5 Tipologie: Strategia, Autorità, segnali chiave da riconoscere"),
      ("Il Proiettore", "Energia non regolare, riconoscimento e invito come Strategia"),
      ("Il Manifestore", "Come informare invece di convincere, tra resistenza e riconoscimento"),
      ("Il Riflettore", "Sentirsi diversi dagli altri, il ruolo dell'ambiente, meraviglia e delusione come segnali"),
      ("Come elabori le informazioni", "I diversi modi in cui ciascuno assimila e processa ciò che impara"),
      ("Temi del Non-Sé, parte 1", "I primi tre temi e il potenziale di saggezza che nascondono"),
      ("Temi del Non-Sé, parte 2", "Confusione di ruolo, difficoltà a lasciar andare, difesa mentale, perdita di focus"),
      ("Temi del Non-Sé, parte 3 e progetto finale", "Gli ultimi tre temi e sintesi completa del proprio Bodygraph")]
S2 = [("Le grandi tematiche", "Le tematiche di fondo che attraversano ogni Bodygraph"),
      ("Il Circuito di Integrazione", "Le 4 porte e i canali 10-20, 20-34, 34-57, 10-57: forze creative e di leadership"),
      ("Il Circuito della Conoscenza", "Le porte e i canali del sapere istintivo, applicati alla pratica"),
      ("Il Circuito Logico", "Le 7 porte e i canali con cui condividi le tue idee in modo logico e sequenziale"),
      ("Il Circuito Astratto", "Le 7 porte e i canali con cui condividi le tue idee attraverso esperienza e racconto"),
      ("Il Circuito Tribale", "Le 7 porte e i canali con cui sostieni materialmente chi ti sta vicino"),
      ("Le 6 Linee", "Le qualità che colorano ogni linea del tuo Profilo"),
      ("Il Profilo, introduzione", "Il profilo, tra Personalità (conscio) e Design (inconscio)"),
      ("I 12 Profili, karma personale e fisso", "I profili a karma personale e quelli a destino fisso"),
      ("I 12 Profili, karma transpersonale e progetto finale", "I profili a karma transpersonale e sintesi di due Bodygraph completi")]


def pag_semestre(c, n, titolo, sotto, lezioni, sigla, chiusura):
    x, larg, y = intestazione(c, "Semestre %d  ·  dieci lezioni" % n, titolo)
    y = para(c, sotto, S_MUTO, x, y, larg)
    y -= 3 * mm
    st_t = stile("OUT-SB", 11.5, INK, 15); st_d = stile("OUT", 10, MUTO, 14, dopo=8.5)
    for i, (t, d) in enumerate(lezioni, start=1):
        c.setFillColor(BRASS); c.setFont("OUT-B", 9.5)
        c.drawString(x, y - 3.5 * mm, "%s-%02d" % (sigla, i))
        yy = para(c, t, st_t, x + 17 * mm, y, larg - 17 * mm)
        yy = para(c, d, st_d, x + 17 * mm, yy + 1 * mm, larg - 17 * mm)
        c.setStrokeColor(LINEA); c.setLineWidth(0.5); c.line(x, yy + 2 * mm, x + larg, yy + 2 * mm)
        y = yy - 5 * mm
    # chiusura: cosa resta in mano alla fine del semestre
    alt = 24 * mm
    c.setFillColor(NAVY); c.roundRect(x, 18 * mm + 2 * mm, larg, alt, 3 * mm, fill=1, stroke=0)
    c.setFillColor(BRASS_L); c.setFont("OUT-B", 8.5); c.drawString(x + 7 * mm, 18 * mm + alt - 6 * mm, "ALLA FINE DEL SEMESTRE")
    para(c, chiusura, stile("OUT", 10.5, CARTA, 14.5), x + 7 * mm, 18 * mm + alt - 10 * mm, larg - 14 * mm)


def pag_come_funziona(c):
    x, larg, y = intestazione(c, "Come funziona", "Venti lezioni dal vivo, in due semestri")
    voci = [("Quando", "Dal 12 ottobre 2026, ogni lunedì alle 18. Dieci lezioni per semestre."),
            ("Quanto", "Due ore a lezione, dal vivo su Zoom, con le domande in tempo reale."),
            ("Se salti una lezione", "Le lezioni vengono registrate: se ne perdi una, scrivi a Valentina per riceverne la registrazione."),
            ("Il curriculum", "Segue il curriculum ufficiale Foundation del BG5® Business Institute, tradotto in termini di Human Design."),
            ("Cosa non è", "Non è un percorso professionalizzante e non rilascia certificazioni o crediti formativi. È un corso per imparare il metodo e usarlo in autonomia."),
            ("Numero minimo", "Il corso parte con almeno tre iscritti.")]
    for t, d in voci:
        c.setFillColor(BRASS); c.setFont("OUT-B", 8.5); c.drawString(x, y - 3 * mm, t.upper())
        y = para(c, d, S_CORPO, x + 40 * mm, y, larg - 40 * mm) - 1 * mm
    y -= 4 * mm
    y = para(c, "Chi guida il corso", S_H3, x, y, larg)
    if FOTO.is_file():
        im = Image.open(FOTO).convert("RGB")
        lato = min(im.size); l, t = (im.width - lato) // 2, int((im.height - lato) * 0.15)
        im = im.crop((l, t, l + lato, t + lato)).resize((600, 600))
        d = 30 * mm
        c.saveState(); p = c.beginPath(); p.circle(x + d / 2, y - d / 2 - 1 * mm, d / 2); c.clipPath(p, stroke=0)
        c.drawImage(ImageReader(im), x, y - d - 1 * mm, d, d); c.restoreState()
    yy = para(c, "Valentina Russo", stile("PF-B", 14, INK, 17, dopo=2), x + 36 * mm, y, larg - 36 * mm)
    yy = para(c, "Analista Certificata BG5® (Business Group 5) e Consulente Human Design", stile("OUT-M", 9.5, BRASS, 13, dopo=4), x + 36 * mm, yy, larg - 36 * mm)
    yy = para(c, "Applica il sistema nella pratica quotidiana, tra letture individuali e formazione. "
              "Il profilo ufficiale è verificabile sul sito del BG5® Business Institute.", S_MUTO, x + 36 * mm, yy, larg - 36 * mm)
    y = min(yy, y - 34 * mm) - 6 * mm
    y = para(c, "L'investimento", S_H3, x, y, larg)
    y = para(c, "Un semestre alla volta, oppure il percorso completo con uno sconto. Pagamento sicuro via Stripe, "
             "anche a rate con Klarna al checkout.", S_MUTO, x, y, larg)
    offerte_cards(c, x, larg, y - 1 * mm)


def offerte_cards(c, x, larg, y, alt=50 * mm):
    col = (larg - 12 * mm) / 3
    offerte = [("Semestre 1", "700 €", ["10 lezioni dal vivo", "Tipologia, Strategia e Autorità", "Registrazioni su richiesta"], False),
               ("Semestre 2", "700 €", ["10 lezioni dal vivo", "Canali, Porte, Profilo e Linee", "Consigliato dopo il Semestre 1"], False),
               ("Percorso completo", "1.200 €", ["20 lezioni dal vivo", "Invece di 1.400 €: risparmi 200 €", "Registrazioni su richiesta"], True)]
    for i, (tit, prezzo, righe, forte) in enumerate(offerte):
        bx = x + i * (col + 6 * mm)
        c.setFillColor(NAVY if forte else CARTA); c.setStrokeColor(BRASS if forte else LINEA); c.setLineWidth(0.8)
        c.roundRect(bx, y - alt, col, alt, 3 * mm, fill=1, stroke=1)
        tc, mc = (CARTA, colors.HexColor("#D8CFC4")) if forte else (INK, MUTO)
        yy = y - 8 * mm
        yy = para(c, tit, stile("OUT-B", 9.5, BRASS_L if forte else BRASS, 12, dopo=2), bx + 5 * mm, yy, col - 10 * mm)
        yy = para(c, prezzo, stile("PF-B", 22, tc, 26, dopo=4), bx + 5 * mm, yy, col - 10 * mm)
        for r in righe:
            yy = para(c, "·  " + r, stile("OUT", 9, mc, 12.5, dopo=2), bx + 5 * mm, yy, col - 10 * mm)


def pag_domande(c):
    x, larg, y = intestazione(c, "Prima di iscriverti", "Domande frequenti")
    domande = [
        ("Quanto costa e cosa include il prezzo?", "Un semestre alla volta, 700 euro ciascuno, oppure il percorso completo a 1.200 euro invece di 1.400. Il pagamento a rate con Klarna è disponibile direttamente al checkout."),
        ("Cosa succede se salto una lezione dal vivo?", "Il corso è pensato per la partecipazione dal vivo, con le domande in tempo reale e i concetti applicati a casi diversi dal proprio. Le lezioni vengono registrate: se ne perdi una, scrivi a Valentina per riceverne la registrazione."),
        ("Ricevo un certificato o dei crediti formativi?", "No. Non è un percorso professionalizzante e non rilascia certificazioni o crediti formativi. È un corso di approfondimento sul sistema, pensato per imparare il metodo e usarlo in autonomia."),
        ("Il curriculum è quello ufficiale?", "Sì. Le venti lezioni seguono il curriculum ufficiale Foundation del BG5® Business Institute, insegnato da Valentina Russo, Analista Certificata BG5®."),
        ("Come funziona il diritto di recesso?", "Le condizioni di recesso e le modalità di rimborso sono indicate nei Termini e Condizioni del sito e vengono confermate al momento del checkout, prima del pagamento."),
    ]
    for d, r in domande:
        y = para(c, d, stile("OUT-SB", 11.5, INK, 15, dopo=2.5), x, y, larg)
        y = para(c, r, stile("OUT", 10.5, MUTO, 15, dopo=11), x, y, larg)
    y -= 6 * mm
    y = para(c, "Come iscriversi", S_H2, x, y, larg)
    filetto(c, x, y - 2 * mm); y -= 9 * mm
    y = para(c, "Dalla pagina del corso, con il pulsante del semestre o del percorso che scegli. "
             "L'accesso alla piattaforma arriva per email subito dopo il pagamento.", S_CORPO, x, y, larg)
    alt = 34 * mm
    c.setFillColor(CARTA); c.setStrokeColor(BRASS); c.setLineWidth(1)
    c.roundRect(x, y - alt, larg, alt, 3 * mm, fill=1, stroke=1)
    c.setFillColor(BRASS); c.setFont("OUT-B", 8.5)
    c.drawString(x + 7 * mm, y - 8 * mm, "SUL SITO")
    c.setFillColor(INK); c.setFont("OUT-SB", 11.5); c.drawString(x + 7 * mm, y - 14.5 * mm, SITO)
    c.setFillColor(MUTO); c.setFont("OUT", 9); c.drawString(x + 7 * mm, y - 20 * mm, "I pulsanti di iscrizione sono in fondo alla pagina.")
    c.setFillColor(BRASS); c.setFont("OUT-B", 8.5); c.drawString(x + 7 * mm, y - 27 * mm, "PER PARLARNE PRIMA")
    c.setFillColor(INK); c.setFont("OUT-SB", 11.5); c.drawString(x + 48 * mm, y - 27 * mm, TEL)
    c.setFillColor(MUTO); c.setFont("OUT", 9); c.drawString(x + 90 * mm, y - 27 * mm, "anche su WhatsApp, oppure info@valentinarussobg5.com")


def genera():
    OUT.mkdir(parents=True, exist_ok=True)
    pagine = [copertina, pag_cos_e,
              lambda c: pag_semestre(c, 1, "Tipologia, Strategia e Autorità", "Le basi del sistema: come sei fatto, come decidi, dove ti allontani da te senza accorgertene.", S1, "S1", "Sai riconoscere la tua Tipologia, la tua Strategia e la tua Autorità, e i temi del Non-Sé in cui ti allontani da te. Il progetto finale è la sintesi completa del tuo Bodygraph."),
              lambda c: pag_semestre(c, 2, "Canali, Porte, Profilo e Linee", "Il disegno nel dettaglio: i circuiti, le linee, i dodici Profili, fino alla lettura completa di due Bodygraph.", S2, "S2", "Sai leggere porte, canali e circuiti di un disegno, riconoscere le sei linee e i dodici Profili. Il progetto finale è la sintesi di due Bodygraph completi, il tuo e uno di un'altra persona."),
              pag_come_funziona, pag_domande]
    c = pdfcanvas.Canvas(str(PDF), pagesize=A4)
    c.setTitle("Corso Base di Human Design, la brochure"); c.setAuthor("Valentina Russo")
    for n, disegna in enumerate(pagine, start=1):
        if n > 1:
            piede(c, n, len(pagine))
        disegna(c); c.showPage()
    c.save()
    import fitz
    doc = fitz.open(str(PDF))
    for i, pagina in enumerate(doc, start=1):
        pagina.get_pixmap(dpi=110).save(str(OUT / ("pagina-%02d.png" % i)))
    print("brochure: %s (%d pagine, %d kB) + %d PNG" % (PDF.name, len(doc), PDF.stat().st_size // 1024, len(doc)))


if __name__ == "__main__":
    genera()
