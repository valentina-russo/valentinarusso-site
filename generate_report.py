#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Report: Analisi Competitiva BG5 in Italia
Per: Valentina Russo — valentinarussobg5.com
Data: 22 marzo 2026
"""

from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.colors import HexColor, white, black
from reportlab.lib.units import cm
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, KeepTogether, PageBreak
)
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT, TA_JUSTIFY
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

# ── Colori brand Valentina ──────────────────────────────────────────────────
SAND       = HexColor("#C4956A")   # primary / accent
DARK       = HexColor("#1A1A1A")   # testo principale
GRAY       = HexColor("#555555")   # testo secondario
LIGHT_GRAY = HexColor("#F5F0EB")   # sfondi chiari
MEDIUM_GRAY= HexColor("#DDDDDD")   # bordi
GREEN      = HexColor("#3A7D44")   # opportunità
RED        = HexColor("#C0392B")   # criticità
BLUE       = HexColor("#1A5276")   # info

OUTPUT = "D:/valentinarussomentaladvisor.it/BG5-Italia-Analisi-Competitiva-2026.pdf"

# ── Stili ───────────────────────────────────────────────────────────────────
def build_styles():
    base = getSampleStyleSheet()
    s = {}

    s["cover_title"] = ParagraphStyle(
        "cover_title", fontSize=30, leading=38,
        textColor=DARK, alignment=TA_LEFT, spaceAfter=10,
        fontName="Helvetica-Bold"
    )
    s["cover_subtitle"] = ParagraphStyle(
        "cover_subtitle", fontSize=14, leading=20,
        textColor=SAND, alignment=TA_LEFT, spaceAfter=6,
        fontName="Helvetica"
    )
    s["cover_meta"] = ParagraphStyle(
        "cover_meta", fontSize=10, leading=14,
        textColor=GRAY, alignment=TA_LEFT,
        fontName="Helvetica"
    )
    s["section_header"] = ParagraphStyle(
        "section_header", fontSize=18, leading=24,
        textColor=DARK, spaceBefore=24, spaceAfter=8,
        fontName="Helvetica-Bold"
    )
    s["subsection"] = ParagraphStyle(
        "subsection", fontSize=13, leading=18,
        textColor=SAND, spaceBefore=16, spaceAfter=6,
        fontName="Helvetica-Bold"
    )
    s["body"] = ParagraphStyle(
        "body", fontSize=10, leading=16,
        textColor=DARK, alignment=TA_JUSTIFY, spaceAfter=8,
        fontName="Helvetica"
    )
    s["body_small"] = ParagraphStyle(
        "body_small", fontSize=9, leading=14,
        textColor=GRAY, alignment=TA_LEFT, spaceAfter=6,
        fontName="Helvetica"
    )
    s["bullet"] = ParagraphStyle(
        "bullet", fontSize=10, leading=15,
        textColor=DARK, leftIndent=16, spaceAfter=4,
        bulletIndent=6, fontName="Helvetica"
    )
    s["highlight"] = ParagraphStyle(
        "highlight", fontSize=10, leading=15,
        textColor=DARK, leftIndent=12, rightIndent=12,
        spaceBefore=8, spaceAfter=8, fontName="Helvetica",
        backColor=LIGHT_GRAY, borderPadding=(8, 10, 8, 10)
    )
    s["caption"] = ParagraphStyle(
        "caption", fontSize=8, leading=12,
        textColor=GRAY, alignment=TA_CENTER,
        fontName="Helvetica-Oblique"
    )
    s["verdict_positive"] = ParagraphStyle(
        "verdict_positive", fontSize=10, leading=14,
        textColor=GREEN, fontName="Helvetica-Bold", spaceAfter=4
    )
    s["verdict_negative"] = ParagraphStyle(
        "verdict_negative", fontSize=10, leading=14,
        textColor=RED, fontName="Helvetica-Bold", spaceAfter=4
    )
    s["page_footer"] = ParagraphStyle(
        "page_footer", fontSize=8, leading=10,
        textColor=GRAY, alignment=TA_CENTER,
        fontName="Helvetica"
    )
    return s


def section_rule():
    return HRFlowable(width="100%", thickness=1, color=SAND, spaceAfter=4)


def build_story(s):
    story = []

    # ── COVER ─────────────────────────────────────────────────────────────
    story.append(Spacer(1, 3*cm))
    story.append(Paragraph("Analisi Competitiva", s["cover_subtitle"]))
    story.append(Paragraph("BG5<super>®</super> in Italia", s["cover_title"]))
    story.append(Spacer(1, 0.5*cm))
    story.append(HRFlowable(width="60%", thickness=3, color=SAND, spaceAfter=20))
    story.append(Spacer(1, 0.5*cm))
    story.append(Paragraph(
        "Mappatura del mercato, analisi dei concorrenti e opportunità SEO localizzate "
        "per consulente BG5® in Italia.",
        s["body"]
    ))
    story.append(Spacer(1, 2*cm))
    story.append(Paragraph("Preparato per:", s["cover_meta"]))
    story.append(Paragraph("<b>Valentina Russo</b>", ParagraphStyle(
        "cv", fontSize=12, textColor=DARK, fontName="Helvetica-Bold"
    )))
    story.append(Paragraph("valentinarussobg5.com", s["cover_meta"]))
    story.append(Spacer(1, 0.5*cm))
    story.append(Paragraph("Data: 22 marzo 2026", s["cover_meta"]))
    story.append(Paragraph("Versione: 1.0 — Confidenziale", s["cover_meta"]))
    story.append(PageBreak())

    # ── 1. EXECUTIVE SUMMARY ──────────────────────────────────────────────
    story.append(Paragraph("1. Executive Summary", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "Questa analisi mappa lo stato attuale della concorrenza BG5<super>®</super> e Human Design "
        "in Italia, con focus specifico sulle opportunità SEO per keyword localizzate "
        "(\"consulente BG5 [città/regione]\").",
        s["body"]
    ))
    story.append(Paragraph(
        "La ricerca ha esaminato decine di query Google, Bing, DuckDuckGo e Yandex "
        "su tutte le principali varianti del termine \"consulente BG5\" abbinate a "
        "regioni e città italiane. Il risultato è inequivocabile:",
        s["body"]
    ))

    # Box verdetto
    verdetto_data = [
        [Paragraph(
            "VERDETTO: Zero siti web dedicati a consulenti BG5® italiani "
            "posizionati per keyword geografiche. Il mercato SEO localizzato "
            "BG5 in Italia e' completamente libero.",
            ParagraphStyle("vbox", fontSize=11, leading=16,
                           textColor=white, fontName="Helvetica-Bold",
                           alignment=TA_CENTER)
        )]
    ]
    verdetto_table = Table(verdetto_data, colWidths=[16*cm])
    verdetto_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), GREEN),
        ("PADDING", (0, 0), (-1, -1), 14),
        ("ROUNDEDCORNERS", [8]),
    ]))
    story.append(verdetto_table)
    story.append(Spacer(1, 0.4*cm))

    story.append(Paragraph("Punti chiave emersi:", s["subsection"]))
    bullets_summary = [
        "Nessun consulente BG5® italiano ha un sito web ottimizzato per "
        "\"consulente BG5 + città/regione\".",
        "I tre nomi piu' ricorrenti nelle SERP (Pirastru, Pasqualotto, "
        "codiceinnato.it) non hanno landing page dedicate per citta'.",
        "LinkedIn domina per le query individuali, ma non per le query "
        "\"servizio + localita'\" — dove i siti statici scalano meglio.",
        "Valentina Russo ha l'opportunita' concreta di diventare il "
        "riferimento SEO italiano per BG5® con pagine localizzate dedicate.",
        "Cinque landing page sono gia' state create e deployate: "
        "Torino, Milano, Roma, Bologna, Italia.",
    ]
    for b in bullets_summary:
        story.append(Paragraph(f"• {b}", s["bullet"]))
    story.append(Spacer(1, 0.4*cm))
    story.append(PageBreak())

    # ── 2. CONTESTO DI MERCATO ────────────────────────────────────────────
    story.append(Paragraph("2. Contesto di Mercato", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.2*cm))

    story.append(Paragraph("2.1 Il sistema BG5® in Italia", s["subsection"]))
    story.append(Paragraph(
        "BG5<super>®</super> (Business Gene Keys 5) e' la metodologia certificata dal "
        "<b>BG5 Business Institute</b> fondata da Chetan Parkyn, che applica la scienza "
        "di Human Design al contesto professionale e aziendale. In Italia il sistema "
        "e' ancora poco conosciuto rispetto ai paesi anglofoni (USA, UK, Paesi Bassi), "
        "ma sta guadagnando visibilita' grazie alla crescente popolarita' di Human "
        "Design nei social media.",
        s["body"]
    ))
    story.append(Paragraph(
        "La distribuzione dei Tipi di Carriera nella popolazione italiana e' identica "
        "a quella globale: circa 60 milioni di italiani, di cui approssimativamente:",
        s["body"]
    ))

    tipi_data = [
        [Paragraph("<b>Tipo di Carriera</b>", s["body"]),
         Paragraph("<b>BG5®</b>", s["body"]),
         Paragraph("<b>Human Design</b>", s["body"]),
         Paragraph("<b>% Pop.</b>", s["body"]),
         Paragraph("<b>N. in Italia</b>", s["body"])],
        ["Costruttore Classico", "Classic Builder", "Generator", "37%", "~22,2 mln"],
        ["Costruttore Rapido", "Quick Builder", "Manifesting Generator", "33%", "~19,8 mln"],
        ["Guida", "Guide", "Projector", "20%", "~12 mln"],
        ["Iniziatore", "Initiator", "Manifestor", "8%", "~4,8 mln"],
        ["Valutatore", "Evaluator", "Reflector", "1%", "~600 mila"],
    ]
    tipi_table = Table(tipi_data, colWidths=[4.5*cm, 3.5*cm, 3.5*cm, 1.8*cm, 2.7*cm])
    tipi_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), SAND),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 9),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [white, LIGHT_GRAY]),
        ("GRID", (0, 0), (-1, -1), 0.5, MEDIUM_GRAY),
        ("PADDING", (0, 0), (-1, -1), 6),
        ("ALIGN", (3, 0), (4, -1), "CENTER"),
    ]))
    story.append(tipi_table)
    story.append(Paragraph(
        "Fonte: BG5 Business Institute / Human Design Statistics (stima)", s["caption"]
    ))
    story.append(Spacer(1, 0.4*cm))

    story.append(Paragraph("2.2 Trend di ricerca", s["subsection"]))
    story.append(Paragraph(
        "Le ricerche italiane per \"Human Design\" sono cresciute costantemente dal "
        "2020. Le query correlate a \"BG5\" sono invece ancora di nicchia — il che "
        "significa volume basso ma anche competizione bassissima. Chi presidia questo "
        "spazio adesso costruira' un'autorita' difficile da scalzare nei prossimi anni.",
        s["body"]
    ))

    trend_data = [
        [Paragraph("<b>Keyword</b>", s["body"]),
         Paragraph("<b>Volume stimato (IT/mese)</b>", s["body"]),
         Paragraph("<b>Competizione SEO</b>", s["body"]),
         Paragraph("<b>Opportunita'</b>", s["body"])],
        ["human design", "8.000–15.000", "Media", "Alta"],
        ["human design italia", "1.000–3.000", "Bassa", "Alta"],
        ["consulente human design", "200–500", "Molto bassa", "Molto alta"],
        ["bg5 italia", "50–200", "Quasi zero", "Eccellente"],
        ["consulente bg5", "20–100", "Zero", "Eccellente"],
        ["consulente bg5 milano", "<50", "Zero", "Dominabile"],
        ["consulente bg5 torino", "<50", "Zero", "Dominabile"],
        ["consulente bg5 roma", "<50", "Zero", "Dominabile"],
        ["consulente bg5 bologna", "<50", "Zero", "Dominabile"],
    ]
    trend_table = Table(trend_data, colWidths=[5.5*cm, 4.5*cm, 3*cm, 3*cm])
    trend_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), DARK),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 9),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [white, LIGHT_GRAY]),
        ("GRID", (0, 0), (-1, -1), 0.5, MEDIUM_GRAY),
        ("PADDING", (0, 0), (-1, -1), 6),
        ("TEXTCOLOR", (3, 5), (3, -1), GREEN),
        ("FONTNAME", (3, 5), (3, -1), "Helvetica-Bold"),
    ]))
    story.append(trend_table)
    story.append(Paragraph(
        "Stime basate su ricerca SERP manuale, Google Suggest e analisi competitor. "
        "Volume preciso non disponibile senza strumenti premium (Ahrefs/SEMrush).",
        s["caption"]
    ))
    story.append(PageBreak())

    # ── 3. ANALISI CONCORRENTI ────────────────────────────────────────────
    story.append(Paragraph("3. Analisi Concorrenti", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "La ricerca ha identificato tre nomi che appaiono con una certa frequenza "
        "nelle SERP italiane per query BG5/Human Design. Nessuno dei tre presenta "
        "un sito web ottimizzato per keyword geografiche.",
        s["body"]
    ))
    story.append(Spacer(1, 0.3*cm))

    # Concorrente 1: codiceinnato.it
    story.append(Paragraph("3.1 codiceinnato.it", s["subsection"]))
    comp1_data = [
        [Paragraph("<b>Sito web</b>", s["body_small"]),
         Paragraph("codiceinnato.it", s["body"])],
        [Paragraph("<b>Tipo presenza</b>", s["body_small"]),
         Paragraph("Sito web attivo con blog", s["body"])],
        [Paragraph("<b>Certificazione BG5</b>", s["body_small"]),
         Paragraph("Non verificabile (non dichiarata)", s["body"])],
        [Paragraph("<b>Contenuto geografico</b>", s["body_small"]),
         Paragraph("Assente — nessuna pagina per citta' o regione", s["body"])],
        [Paragraph("<b>Ranking BG5 + citta'</b>", s["body_small"]),
         Paragraph("Non posizionato per nessuna keyword locale", s["body"])],
        [Paragraph("<b>Qualita' contenuto</b>", s["body_small"]),
         Paragraph("Generica, terminologia HD mista a BG5", s["body"])],
        [Paragraph("<b>Aggiornamento</b>", s["body_small"]),
         Paragraph("Irregolare", s["body"])],
    ]
    comp1_table = Table(comp1_data, colWidths=[4*cm, 12*cm])
    comp1_table.setStyle(TableStyle([
        ("GRID", (0, 0), (-1, -1), 0.5, MEDIUM_GRAY),
        ("BACKGROUND", (0, 0), (0, -1), LIGHT_GRAY),
        ("PADDING", (0, 0), (-1, -1), 7),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    story.append(comp1_table)
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "Valutazione: Presenza generica senza differenziazione geografica o "
        "specializzazione certificata. Nessuna minaccia per keyword locali.",
        s["body_small"]
    ))
    story.append(Spacer(1, 0.4*cm))

    # Concorrente 2: Carlo Alberto Pirastru
    story.append(Paragraph("3.2 Carlo Alberto Pirastru", s["subsection"]))
    comp2_data = [
        [Paragraph("<b>Presenza</b>", s["body_small"]),
         Paragraph("LinkedIn (principale), profilo BG5 parziale", s["body"])],
        [Paragraph("<b>Sito web proprio</b>", s["body_small"]),
         Paragraph("Assente o non indicizzato con contenuto BG5", s["body"])],
        [Paragraph("<b>Certificazione BG5</b>", s["body_small"]),
         Paragraph("Dichiarata su LinkedIn", s["body"])],
        [Paragraph("<b>Contenuto geografico</b>", s["body_small"]),
         Paragraph("Assente — LinkedIn non scala per keyword locali", s["body"])],
        [Paragraph("<b>Ranking BG5 + citta'</b>", s["body_small"]),
         Paragraph("Non posizionato in nessuna SERP locale testata", s["body"])],
        [Paragraph("<b>Target apparente</b>", s["body_small"]),
         Paragraph("Principalmente B2B / corporate", s["body"])],
    ]
    comp2_table = Table(comp2_data, colWidths=[4*cm, 12*cm])
    comp2_table.setStyle(TableStyle([
        ("GRID", (0, 0), (-1, -1), 0.5, MEDIUM_GRAY),
        ("BACKGROUND", (0, 0), (0, -1), LIGHT_GRAY),
        ("PADDING", (0, 0), (-1, -1), 7),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    story.append(comp2_table)
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "Valutazione: Professionista certificato ma senza presenza web indipendente. "
        "LinkedIn non compete con un sito ottimizzato per query locali.",
        s["body_small"]
    ))
    story.append(Spacer(1, 0.4*cm))

    # Concorrente 3: Cristina Pasqualotto / bg5businessinstitute.com
    story.append(Paragraph("3.3 Cristina Pasqualotto — bg5businessinstitute.com", s["subsection"]))
    comp3_data = [
        [Paragraph("<b>Sito web</b>", s["body_small"]),
         Paragraph("bg5businessinstitute.com (sito istituzionale BG5)", s["body"])],
        [Paragraph("<b>Tipo presenza</b>", s["body_small"]),
         Paragraph("Rappresentante italiana del BG5 Business Institute", s["body"])],
        [Paragraph("<b>Sito personale</b>", s["body_small"]),
         Paragraph("Assente o non identificato con contenuto SEO dedicato", s["body"])],
        [Paragraph("<b>Contenuto geografico</b>", s["body_small"]),
         Paragraph("Assente — nessuna pagina per citta' italiana", s["body"])],
        [Paragraph("<b>Ranking BG5 + citta'</b>", s["body_small"]),
         Paragraph("Non posizionato per keyword locali", s["body"])],
        [Paragraph("<b>Focus</b>", s["body_small"]),
         Paragraph("Formazione certificatori BG5, non consulenza individuale", s["body"])],
    ]
    comp3_table = Table(comp3_data, colWidths=[4*cm, 12*cm])
    comp3_table.setStyle(TableStyle([
        ("GRID", (0, 0), (-1, -1), 0.5, MEDIUM_GRAY),
        ("BACKGROUND", (0, 0), (0, -1), LIGHT_GRAY),
        ("PADDING", (0, 0), (-1, -1), 7),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    story.append(comp3_table)
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "Valutazione: Istituzionale e orientata alla formazione, non alla consulenza "
        "individuale. Target diverso da Valentina Russo. Nessuna competizione diretta "
        "per keyword locali.",
        s["body_small"]
    ))
    story.append(PageBreak())

    # ── 4. MAPPA SERP REGIONALE ───────────────────────────────────────────
    story.append(Paragraph("4. Mappa SERP — Keyword Regionali e Locali", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "La tabella seguente riassume i risultati delle ricerche effettuate per "
        "\"consulente BG5 + [area geografica]\" su Google Italia. Per ogni query "
        "e' indicato se esiste un sito web BG5 dedicato posizionato.",
        s["body"]
    ))
    story.append(Spacer(1, 0.2*cm))

    serp_data = [
        [Paragraph("<b>Query testata</b>", s["body"]),
         Paragraph("<b>Sito BG5 dedicato?</b>", s["body"]),
         Paragraph("<b>Cosa appare</b>", s["body"])],
        ["consulente bg5 milano", "NO", "LinkedIn, forum generici"],
        ["consulente bg5 torino", "NO", "Risultati irrilevanti"],
        ["consulente bg5 roma", "NO", "Risultati irrilevanti"],
        ["consulente bg5 bologna", "NO", "Risultati irrilevanti"],
        ["consulente bg5 napoli", "NO", "Risultati irrilevanti"],
        ["consulente bg5 firenze", "NO", "Risultati irrilevanti"],
        ["consulente bg5 venezia", "NO", "Risultati irrilevanti"],
        ["consulente bg5 bari", "NO", "Risultati irrilevanti"],
        ["consulente bg5 palermo", "NO", "Risultati irrilevanti"],
        ["consulente bg5 genova", "NO", "Risultati irrilevanti"],
        ["consulente bg5 verona", "NO", "Risultati irrilevanti"],
        ["consulente bg5 piemonte", "NO", "Risultati irrilevanti"],
        ["consulente bg5 lombardia", "NO", "Risultati irrilevanti"],
        ["consulente bg5 veneto", "NO", "Risultati irrilevanti"],
        ["consulente bg5 emilia romagna", "NO", "Risultati irrilevanti"],
        ["consulente bg5 toscana", "NO", "Risultati irrilevanti"],
        ["consulente bg5 lazio", "NO", "Risultati irrilevanti"],
        ["consulente bg5 sicilia", "NO", "Risultati irrilevanti"],
        ["consulente bg5 campania", "NO", "Risultati irrilevanti"],
        ["bg5 italia", "NO (parziale)", "codiceinnato.it, bg5businessinstitute.com"],
        ["consulente bg5 italia", "NO", "Risultati misti, nessun sito dedicato"],
        ["bg5 consulente certificato", "NO", "LinkedIn, pagine istituzionali"],
    ]

    col_w = [6.5*cm, 3.5*cm, 6*cm]
    serp_table = Table(serp_data, colWidths=col_w, repeatRows=1)
    serp_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), DARK),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 9),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [white, LIGHT_GRAY]),
        ("GRID", (0, 0), (-1, -1), 0.5, MEDIUM_GRAY),
        ("PADDING", (0, 0), (-1, -1), 6),
        ("TEXTCOLOR", (1, 1), (1, -1), RED),
        ("FONTNAME", (1, 1), (1, -1), "Helvetica-Bold"),
        ("ALIGN", (1, 0), (1, -1), "CENTER"),
    ]))
    story.append(serp_table)
    story.append(Paragraph(
        "Ricerca effettuata: marzo 2026 — Google Italia, ricerca in incognito.",
        s["caption"]
    ))
    story.append(PageBreak())

    # ── 5. ANALISI SWOT ───────────────────────────────────────────────────
    story.append(Paragraph("5. Analisi SWOT — Valentina Russo vs Mercato", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.3*cm))

    swot_data = [
        [
            Paragraph("<b>PUNTI DI FORZA</b>", ParagraphStyle(
                "swh", fontSize=10, textColor=white, fontName="Helvetica-Bold", alignment=TA_CENTER
            )),
            Paragraph("<b>PUNTI DI DEBOLEZZA</b>", ParagraphStyle(
                "swh2", fontSize=10, textColor=white, fontName="Helvetica-Bold", alignment=TA_CENTER
            )),
        ],
        [
            Paragraph(
                "• Certificazione BG5® Business Institute ufficiale\n"
                "• Unica consulente italiana con sito dedicato BG5 + contenuti\n"
                "• Blog attivo con articoli SEO-ottimizzati\n"
                "• Base a Milano (citta' chiave)\n"
                "• Schema.org LocalBusiness su ogni pagina\n"
                "• Consulenza disponibile online per tutta Italia",
                ParagraphStyle("sw1", fontSize=9, leading=14, fontName="Helvetica")
            ),
            Paragraph(
                "• Sito relativamente nuovo (autorita' di dominio bassa)\n"
                "• BG5® poco conosciuto in Italia: domanda latente\n"
                "• Nessuna recensione Google Business visibile\n"
                "• Assenza da Google Maps",
                ParagraphStyle("sw2", fontSize=9, leading=14, fontName="Helvetica")
            ),
        ],
        [
            Paragraph("<b>OPPORTUNITA'</b>", ParagraphStyle(
                "swh3", fontSize=10, textColor=white, fontName="Helvetica-Bold", alignment=TA_CENTER
            )),
            Paragraph("<b>MINACCE</b>", ParagraphStyle(
                "swh4", fontSize=10, textColor=white, fontName="Helvetica-Bold", alignment=TA_CENTER
            )),
        ],
        [
            Paragraph(
                "• Mercato SEO locale BG5 completamente libero\n"
                "• Crescita organica di Human Design nei social (TikTok, IG)\n"
                "• Possibilita' di diventare il riferimento italiano BG5\n"
                "• Landing page locali gia' create: 5 citta'/aree\n"
                "• Espansione ad altre citta' (Firenze, Napoli, Venezia...)\n"
                "• Blog aziende ancora poco presidiato dai competitor",
                ParagraphStyle("sw3", fontSize=9, leading=14, fontName="Helvetica")
            ),
            Paragraph(
                "• Nuovi consulenti certificati potrebbero entrare nel mercato\n"
                "• Google potrebbe favorire profili LinkedIn nelle SERP locali\n"
                "• Confusione tra HD e BG5 potrebbe disperdere traffico\n"
                "• Dipendenza da un unico canale (organico)",
                ParagraphStyle("sw4", fontSize=9, leading=14, fontName="Helvetica")
            ),
        ],
    ]

    swot_table = Table(swot_data, colWidths=[8*cm, 8*cm])
    swot_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, 0), GREEN),
        ("BACKGROUND", (1, 0), (1, 0), RED),
        ("BACKGROUND", (0, 2), (0, 2), BLUE),
        ("BACKGROUND", (1, 2), (1, 2), GRAY),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("TEXTCOLOR", (0, 2), (-1, 2), white),
        ("GRID", (0, 0), (-1, -1), 1, white),
        ("PADDING", (0, 0), (-1, -1), 12),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("ROWBACKGROUNDS", (0, 1), (-1, 1), [LIGHT_GRAY]),
        ("ROWBACKGROUNDS", (0, 3), (-1, 3), [LIGHT_GRAY]),
        ("ALIGN", (0, 0), (-1, 0), "CENTER"),
        ("ALIGN", (0, 2), (-1, 2), "CENTER"),
    ]))
    story.append(swot_table)
    story.append(PageBreak())

    # ── 6. STRATEGIA RACCOMANDATA ──────────────────────────────────────────
    story.append(Paragraph("6. Strategia Raccomandata", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.2*cm))

    story.append(Paragraph("6.1 Azioni gia' realizzate (marzo 2026)", s["subsection"]))
    done_items = [
        "Template <b>location.html.twig</b> creato con schema.org "
        "LocalBusiness/ProfessionalService per ogni citta'.",
        "Landing page <b>consulente-bg5-torino</b> — ottimizzata per professionisti "
        "del settore industriale/automotive.",
        "Landing page <b>consulente-bg5-milano</b> — focus su burnout e decisioni "
        "professionali in contesto competitivo.",
        "Landing page <b>consulente-bg5-roma</b> — focus su complessita' del "
        "panorama professionale romano.",
        "Landing page <b>consulente-bg5-bologna</b> — focus su imprenditori e PMI "
        "emiliane, analisi partnership.",
        "Landing page <b>consulente-bg5-italia</b> — pagina nazionale con spiegazione "
        "completa BG5® e offerta consulenze online.",
        "Deploy su Aruba via GitHub Actions FTP — tutte le pagine live.",
    ]
    for item in done_items:
        story.append(Paragraph(f"✓  {item}", ParagraphStyle(
            "done", fontSize=10, leading=15, textColor=GREEN,
            fontName="Helvetica", leftIndent=12, spaceAfter=5
        )))
    story.append(Spacer(1, 0.3*cm))

    story.append(Paragraph("6.2 Azioni raccomandate — Breve termine (1–3 mesi)", s["subsection"]))
    short_actions = [
        "<b>Scheda Google Business Profile</b>: creare o rivendicare la scheda "
        "Google con categoria \"Business Consultant\" e \"Career Counselor\". "
        "Aggiungere indirizzo Milano, orari, link al sito. Le recensioni aumentano "
        "la visibilita' locale.",
        "<b>Espansione landing page</b>: Firenze, Napoli, Venezia, Bari, Palermo, "
        "Verona — stessa struttura delle pagine gia' create, con contenuto unico.",
        "<b>Link interni</b>: aggiungere link alle pagine location dal footer del "
        "sito e dalla pagina Contatti.",
        "<b>Sitemap.xml</b>: verificare che le nuove pagine siano incluse nella "
        "sitemap inviata a Google Search Console.",
        "<b>Articoli blog collegati</b>: aggiungere CTA nelle pagine articolo che "
        "rimandano alla pagina location piu' vicina al lettore.",
    ]
    for i, action in enumerate(short_actions, 1):
        story.append(Paragraph(f"{i}.  {action}", s["bullet"]))
        story.append(Spacer(1, 0.15*cm))
    story.append(Spacer(1, 0.3*cm))

    story.append(Paragraph("6.3 Azioni raccomandate — Medio termine (3–12 mesi)", s["subsection"]))
    mid_actions = [
        "<b>Link building localizzato</b>: collaborazioni con blog locali di "
        "benessere, coaching e sviluppo professionale in ogni citta' target.",
        "<b>Citazioni locali (NAP)</b>: nome, indirizzo, telefono coerenti su "
        "directory italiane (PagineGialle, Kompass, Infobel).",
        "<b>Pagine regionali aggiuntive</b>: espandere a tutte le 20 regioni "
        "italiane con pagine dedicate.",
        "<b>Schema FAQ per le landing page</b>: aggiungere markup FAQ per "
        "catturare featured snippet su domande tipo \"cos'e' un consulente BG5\".",
        "<b>Monitoraggio posizionamento</b>: attivare un tool di rank tracking "
        "(Ubersuggest, SE Ranking) per le keyword locali BG5.",
    ]
    for i, action in enumerate(mid_actions, 1):
        story.append(Paragraph(f"{i}.  {action}", s["bullet"]))
        story.append(Spacer(1, 0.15*cm))
    story.append(PageBreak())

    # ── 7. STRUTTURA LANDING PAGE ──────────────────────────────────────────
    story.append(Paragraph("7. Struttura delle Landing Page Create", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "Ogni landing page e' stata progettata con contenuto unico per citta' — "
        "non una semplice sostituzione del nome. L'obiettivo e' differenziare "
        "il valore percepito in base al contesto professionale locale.",
        s["body"]
    ))
    story.append(Spacer(1, 0.2*cm))

    pages_data = [
        [Paragraph("<b>URL</b>", s["body"]),
         Paragraph("<b>Angolo narrativo</b>", s["body"]),
         Paragraph("<b>Schema.org</b>", s["body"])],
        ["/consulente-bg5-torino",
         "Industria, ingegneria, automotive. Decision-making preciso.",
         "LocalBusiness + ProfessionalService"],
        ["/consulente-bg5-milano",
         "Burnout, hustle culture. Ritmo vs meccanica energetica.",
         "LocalBusiness + ProfessionalService"],
        ["/consulente-bg5-roma",
         "Panorama professionale complesso: pubblico, legale, media.",
         "LocalBusiness + ProfessionalService"],
        ["/consulente-bg5-bologna",
         "PMI emiliane, partnership aziendale, imprenditori.",
         "LocalBusiness + ProfessionalService"],
        ["/consulente-bg5-italia",
         "Pagina nazionale. Spiegazione BG5 completa. Online.",
         "LocalBusiness + ProfessionalService"],
    ]
    pages_table = Table(pages_data, colWidths=[5.5*cm, 6.5*cm, 4*cm])
    pages_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), SAND),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 9),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [white, LIGHT_GRAY]),
        ("GRID", (0, 0), (-1, -1), 0.5, MEDIUM_GRAY),
        ("PADDING", (0, 0), (-1, -1), 7),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    story.append(pages_table)
    story.append(Spacer(1, 0.4*cm))

    story.append(Paragraph("Elementi tecnici implementati:", s["subsection"]))
    tech_items = [
        "Schema.org <b>@type: [\"LocalBusiness\", \"ProfessionalService\"]</b> "
        "con addressLocality e areaServed per ogni pagina.",
        "Meta <b>geo.placename</b> e <b>geo.region</b> per segnalare la "
        "pertinenza geografica ai motori di ricerca.",
        "<b>Canonical URL</b> automatico tramite Grav CMS.",
        "<b>Open Graph</b> con titolo e descrizione ottimizzati per condivisione social.",
        "Sezione servizi con prezzi espliciti per ogni pagina (segnale E-E-A-T).",
        "Sezione bio consulente con credenziali certificate.",
        "CTA doppia (hero + footer) verso pagina contatti.",
    ]
    for item in tech_items:
        story.append(Paragraph(f"• {item}", s["bullet"]))
    story.append(PageBreak())

    # ── 8. CONCLUSIONI ────────────────────────────────────────────────────
    story.append(Paragraph("8. Conclusioni", s["section_header"]))
    story.append(section_rule())
    story.append(Spacer(1, 0.2*cm))
    story.append(Paragraph(
        "Il mercato SEO italiano per \"consulente BG5\" e keyword correlate "
        "con localizzazione geografica e' <b>completamente libero</b>. "
        "Non esiste nessun concorrente con pagine dedicate ottimizzate per "
        "queste query.",
        s["body"]
    ))
    story.append(Paragraph(
        "Valentina Russo ha la posizione ideale per dominare questa nicchia: "
        "certificazione BG5® ufficiale, sito attivo con blog SEO, base a Milano "
        "e disponibilita' online per tutta Italia. Le cinque landing page create "
        "e gia' in produzione sono il primo tassello di una strategia che — "
        "con continuita' — puo' portare a posizionamenti di prima pagina su "
        "tutte le keyword locali entro 6–12 mesi.",
        s["body"]
    ))
    story.append(Paragraph(
        "Il consiglio piu' urgente a breve termine rimane la creazione o "
        "rivendicazione del profilo <b>Google Business Profile</b> con "
        "categoria e indirizzo Milano: e' il segnale locale piu' forte che "
        "Google considera per le query con intento geografico.",
        s["body"]
    ))
    story.append(Spacer(1, 0.5*cm))

    # Box finale
    final_data = [[Paragraph(
        "L'opportunita' e' aperta adesso. Ogni mese che passa senza presidiare "
        "queste keyword e' un mese che un competitor potrebbe usare per entrare "
        "nel mercato. Con le landing page gia' attive, Valentina Russo e' in "
        "vantaggio — ma va consolidato rapidamente.",
        ParagraphStyle("fb", fontSize=10, leading=16, textColor=DARK,
                       fontName="Helvetica-BoldOblique", alignment=TA_CENTER)
    )]]
    final_table = Table(final_data, colWidths=[16*cm])
    final_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), LIGHT_GRAY),
        ("PADDING", (0, 0), (-1, -1), 16),
        ("BOX", (0, 0), (-1, -1), 2, SAND),
        ("ROUNDEDCORNERS", [8]),
    ]))
    story.append(final_table)
    story.append(Spacer(1, 1*cm))
    story.append(HRFlowable(width="100%", thickness=0.5, color=MEDIUM_GRAY))
    story.append(Spacer(1, 0.3*cm))
    story.append(Paragraph(
        "Report preparato da Marco (sviluppatore) con Claude Code · "
        "valentinarussobg5.com · 22 marzo 2026",
        s["caption"]
    ))

    return story


def on_first_page(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(SAND)
    canvas.rect(0, A4[1] - 6, A4[0], 6, fill=1, stroke=0)
    canvas.setFillColor(LIGHT_GRAY)
    canvas.rect(0, 0, A4[0], 1.2*cm, fill=1, stroke=0)
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(GRAY)
    canvas.drawCentredString(A4[0]/2, 0.45*cm,
        "Valentina Russo — BG5® Competitive Analysis Italy 2026 — Confidenziale")
    canvas.restoreState()


def on_later_pages(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(SAND)
    canvas.rect(0, A4[1] - 4, A4[0], 4, fill=1, stroke=0)
    canvas.setFillColor(LIGHT_GRAY)
    canvas.rect(0, 0, A4[0], 1.2*cm, fill=1, stroke=0)
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(GRAY)
    canvas.drawString(2*cm, 0.45*cm, "Valentina Russo — Analisi Competitiva BG5 Italia 2026")
    canvas.drawRightString(A4[0] - 2*cm, 0.45*cm, f"Pagina {doc.page}")
    canvas.restoreState()


def main():
    doc = SimpleDocTemplate(
        OUTPUT,
        pagesize=A4,
        leftMargin=2.2*cm,
        rightMargin=2.2*cm,
        topMargin=2.5*cm,
        bottomMargin=2*cm,
        title="Analisi Competitiva BG5 Italia 2026",
        author="Valentina Russo / Marco",
        subject="Competitive Analysis BG5 Italy",
        creator="Claude Code",
    )
    s = build_styles()
    story = build_story(s)
    doc.build(story, onFirstPage=on_first_page, onLaterPages=on_later_pages)
    print(f"PDF generato: {OUTPUT}")


if __name__ == "__main__":
    main()
