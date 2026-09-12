#!/usr/bin/env python3
"""
deck.html -> corso-base-hd.pptx

Misura ogni slide nel browser (Playwright) e ricostruisce la pagina in
PowerPoint con python-pptx: i testi diventano caselle di testo modificabili
nella posizione esatta, con font, corpo, colore e allineamento letti dal
CSS calcolato; grafici, foto, cornici e anelli diventano immagini ritagliate
dallo screenshot dell'elemento. Il PPTX si importa in Canva senza perdere
l'impaginato.

    py tools/deck-corso/to_pptx.py            -> tools/deck-corso/out/corso-base-hd.pptx
"""
from __future__ import annotations

import io
import re
from pathlib import Path

from PIL import Image
from playwright.sync_api import sync_playwright
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.dml import MSO_LINE
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Emu, Pt

QUI = Path(__file__).resolve().parent
SORGENTE = QUI / "deck.html"
USCITA = QUI / "out" / "corso-base-hd.pptx"

W, H = 1920, 1080
LARGH_IN = 13.3333
EMU_PER_PX = LARGH_IN * 914400 / W

FONT = {"Playfair": "Playfair Display", "Outfit": "Outfit", "Fell": "Playfair Display"}

# Elementi che diventano immagini (screenshot), con tutto quello che contengono
RASTER = [".telaio-chart", ".cornice", ".duotone", ".ovale", "svg.anello", "svg.anello-grande",
          "svg.anello-agenda", "svg.passi", "img.marchio"]
# Elementi di testo: foglie con testo proprio
TESTO = ("h1, h2, p, li, figcaption, span.nota-nostra, span.scadenza, span.link, span.num-slide, "
         "span.firma, .scala b, .scala span, .legenda span, .prezzi div, .prezzi b, .passo, .op")

JS_MISURA = r"""
(sel) => {
  const out = [];
  document.querySelectorAll('section.slide').forEach((sec, si) => {
    const sr = sec.getBoundingClientRect();
    const cs = getComputedStyle(sec);
    const slide = {i: si, bg: cs.backgroundColor, nome: sec.dataset.nome, raster: [], testi: [], linee: [], punti: []};
    sec.querySelectorAll(sel.raster).forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.width < 2 || r.height < 2) return;
      if (el.tagName === 'IMG' && el.closest('.telaio-chart, .cornice, .duotone, .ovale')) return;
      slide.raster.push({x: r.left - sr.left, y: r.top - sr.top, w: r.width, h: r.height});
    });
    sec.querySelectorAll(sel.testo).forEach(el => {
      if (el.closest(sel.raster)) return;
      const runs = []; let own = '';
      const stile = (e) => { const k = getComputedStyle(e); return {ff: k.fontFamily, fs: parseFloat(k.fontSize), fw: k.fontWeight, fi: k.fontStyle, col: k.color, up: k.textTransform === 'uppercase'}; };
      const base = stile(el);
      el.childNodes.forEach(n => {
        if (n.nodeType === 3) {
          const t = n.textContent; if (!t.trim()) return;
          const rr = document.createRange(); let riga = '', top = null;
          for (let k = 0; k < t.length; k++) {
            rr.setStart(n, k); rr.setEnd(n, k + 1);
            const q = rr.getBoundingClientRect();
            if (q.width === 0 && q.height === 0) { riga += t[k]; continue; }
            if (top !== null && q.top > top + q.height * 0.5) { runs.push({t: riga, ...base}); runs.push({t: '', br: true, ...base}); riga = ''; }
            top = q.top; riga += t[k];
          }
          runs.push({t: riga, ...base}); own += t;
        }
        else if (n.nodeType === 1 && n.tagName === 'BR') { runs.push({t: '', br: true, ...base}); own += String.fromCharCode(10); }
        else if (n.nodeType === 1 && !n.matches(sel.testo)) {
          const t = n.innerText; if (!t.trim()) return;
          const blocco = getComputedStyle(n).display === 'block';
          if (blocco) runs.push({t: '', br: true, ...base});
          const st = stile(n); const rr = document.createRange(); let riga = '', top = null;
          const nodi = []; const walker = document.createTreeWalker(n, NodeFilter.SHOW_TEXT); while (walker.nextNode()) nodi.push(walker.currentNode);
          nodi.forEach(tn => { const tt = tn.textContent; for (let k = 0; k < tt.length; k++) {
            rr.setStart(tn, k); rr.setEnd(tn, k + 1); const q = rr.getBoundingClientRect();
            if (q.width === 0 && q.height === 0) { riga += tt[k]; continue; }
            if (top !== null && q.top > top + q.height * 0.5) { runs.push({t: riga, ...st}); runs.push({t: '', br: true, ...st}); riga = ''; }
            top = q.top; riga += tt[k]; } });
          runs.push({t: riga, ...st}); own += (blocco ? String.fromCharCode(10) : '') + t;
        }
      });
      own = own.replace(/[ \t]+/g, ' ').replace(/ *\n */g, '\n').trim();
      if (!own) return;
      const c = getComputedStyle(el);
      const rg = document.createRange(); let R = null;
      el.childNodes.forEach(n => {
        if (n.nodeType === 3 && n.textContent.trim()) rg.selectNodeContents(n);
        else if (n.nodeType === 1 && n.tagName !== 'BR' && !n.matches(sel.testo) && n.innerText.trim()) rg.selectNodeContents(n);
        else return;
        const q = rg.getBoundingClientRect();
        if (q.width < 1) return;
        R = R ? {left: Math.min(R.left, q.left), top: Math.min(R.top, q.top), right: Math.max(R.right, q.right), bottom: Math.max(R.bottom, q.bottom)} : {left: q.left, top: q.top, right: q.right, bottom: q.bottom};
      });
      if (!R) return;
      const r = {left: R.left, top: R.top, width: R.right - R.left, height: R.bottom - R.top};
      if (r.width < 2 || r.height < 2) return;
      let txt = own;
      if (c.textTransform === 'uppercase') txt = txt.toUpperCase();
      const bT = c.borderTopStyle !== 'none' && parseFloat(c.borderTopWidth) > 0;
      const bL = c.borderLeftStyle !== 'none' && parseFloat(c.borderLeftWidth) > 0;
      if (bT && !bL) { const rr = el.getBoundingClientRect(); slide.linee.push({x: rr.left - sr.left, y: rr.top - sr.top, w: rr.width, h: parseFloat(c.borderTopWidth), col: c.borderTopColor}); }
      const bB = c.borderBottomStyle !== 'none' && parseFloat(c.borderBottomWidth) > 0;
      if (bB && !bL) { const rr = el.getBoundingClientRect(); slide.linee.push({x: rr.left - sr.left, y: rr.bottom - sr.top - 1, w: rr.width, h: parseFloat(c.borderBottomWidth), col: c.borderBottomColor}); }
      slide.testi.push({x: r.left - sr.left, y: r.top - sr.top, w: r.width, h: r.height, t: txt, runs: runs,
        ff: c.fontFamily, fs: parseFloat(c.fontSize), fw: c.fontWeight, fi: c.fontStyle, col: c.color,
        al: c.textAlign, lh: c.lineHeight, bg: c.backgroundColor, pad: bL || (c.backgroundColor !== 'rgba(0, 0, 0, 0)'),
        bordo: (bT && bL) ? {st: c.borderTopStyle, col: c.borderTopColor} : null});
      if (el.tagName === 'LI') {
        const rr = el.getBoundingClientRect();
        const b = getComputedStyle(el, '::before');
        const n = [...el.parentElement.children].indexOf(el) + 1;
        if (el.closest('.elenco.numerata')) {
          slide.testi.push({x: rr.left - sr.left, y: rr.top - sr.top, w: 50, h: 30, t: String(n).padStart(2, '0'), ff: 'Outfit', fs: 20, fw: '600', fi: 'normal', col: 'rgb(122, 79, 94)', al: 'left', lh: 'normal', bg: 'rgba(0, 0, 0, 0)', pad: false, bordo: null});
        } else if (el.closest('.agenda')) {
          slide.testi.push({x: rr.left - sr.left, y: r.top - sr.top + 6, w: 50, h: 26, t: String(n).padStart(2, '0'), ff: 'Outfit', fs: 22, fw: '700', fi: 'normal', col: 'rgb(122, 79, 94)', al: 'left', lh: 'normal', bg: 'rgba(0, 0, 0, 0)', pad: false, bordo: null});
        } else if (el.closest('.flusso')) {
          slide.testi.push({x: rr.left - sr.left, y: rr.top - sr.top + 22, w: 120, h: 56, t: String(n).padStart(2, '0'), ff: 'Playfair', fs: 52, fw: '700', fi: 'normal', col: 'rgb(122, 79, 94)', al: 'left', lh: 'normal', bg: 'rgba(0, 0, 0, 0)', pad: false, bordo: null});
        } else if (el.closest('.elenco')) {
          slide.linee.push({x: rr.left - sr.left, y: rr.top - sr.top + parseFloat(c.fontSize) * 0.64, w: 14, h: 1.5, col: '#C48A3A'});
        }
      }
    });
    sec.querySelectorAll('.legenda i').forEach(el => { const r = el.getBoundingClientRect(); const c = getComputedStyle(el); slide.linee.push({x: r.left - sr.left, y: r.top - sr.top, w: r.width, h: r.height, col: c.backgroundColor}); });
    sec.querySelectorAll('.filetto').forEach(el => { const r = el.getBoundingClientRect(); slide.linee.push({x: r.left - sr.left, y: r.top - sr.top, w: Math.max(1, r.width), h: r.height, col: '#C48A3A'}); });
    sec.querySelectorAll('.punto-fine').forEach(el => { const r = el.getBoundingClientRect(); slide.punti.push({x: r.left - sr.left, y: r.top - sr.top, w: r.width, h: r.height, col: '#FEBD59'}); });
    out.push(slide);
  });
  return out;
}
"""


def rgb(css: str) -> RGBColor | None:
    m = re.match(r"rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)", css or "")
    if not m:
        return None
    if m.group(4) is not None and float(m.group(4)) == 0:
        return None
    return RGBColor(int(m.group(1)), int(m.group(2)), int(m.group(3)))


def hexrgb(h: str) -> RGBColor:
    h = h.lstrip("#")
    return RGBColor(int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16))


def px(v: float) -> Emu:
    return Emu(int(round(v * EMU_PER_PX)))


def famiglia(ff: str) -> str:
    primo = ff.split(",")[0].strip().strip("'\"")
    return FONT.get(primo, primo)


def main() -> None:
    prs = Presentation()
    prs.slide_width = Emu(int(LARGH_IN * 914400))
    prs.slide_height = Emu(int(LARGH_IN * 914400 * H / W))
    vuoto = prs.slide_layouts[6]

    with sync_playwright() as p:
        br = p.chromium.launch()
        pg = br.new_page(viewport={"width": W, "height": H}, device_scale_factor=1)
        pg.goto(SORGENTE.as_uri())
        pg.wait_for_function("document.fonts.ready")
        pg.wait_for_timeout(500)
        misure = pg.evaluate(JS_MISURA, {"raster": ", ".join(RASTER), "testo": TESTO})
        sezioni = pg.query_selector_all("section.slide")

        for m, sec in zip(misure, sezioni):
            sl = prs.slides.add_slide(vuoto)
            fondo = rgb(m["bg"])
            if fondo is not None:
                sl.background.fill.solid()
                sl.background.fill.fore_color.rgb = fondo

            shot = Image.open(io.BytesIO(sec.screenshot()))

            for r in m["raster"]:
                box = (max(0, int(r["x"])), max(0, int(r["y"])), min(W, int(r["x"] + r["w"])), min(H, int(r["y"] + r["h"])))
                if box[2] - box[0] < 2 or box[3] - box[1] < 2:
                    continue
                crop = shot.crop(box)
                buf = io.BytesIO()
                if crop.width * crop.height > 300_000:
                    crop.convert("RGB").save(buf, "JPEG", quality=88)
                else:
                    crop.save(buf, "PNG", optimize=True)
                buf.seek(0)
                sl.shapes.add_picture(buf, px(box[0]), px(box[1]), px(box[2] - box[0]), px(box[3] - box[1]))

            for l in m["linee"]:
                sh = sl.shapes.add_shape(MSO_SHAPE.RECTANGLE, px(l["x"]), px(l["y"]), px(l["w"]), px(max(l["h"], 1)))
                sh.fill.solid(); sh.fill.fore_color.rgb = (rgb(l["col"]) or hexrgb(l["col"])); sh.line.fill.background()
            for d in m["punti"]:
                sh = sl.shapes.add_shape(MSO_SHAPE.OVAL, px(d["x"]), px(d["y"]), px(d["w"]), px(d["h"]))
                sh.fill.solid(); sh.fill.fore_color.rgb = hexrgb(d["col"]); sh.line.fill.background()

            for t in m["testi"]:
                pad = 18 if t.get("pad") else 0
                # Canva rende i caratteri un po' piu' larghi del browser: 12% di aria in piu',
                # e le righe singole non vanno mai a capo.
                una_riga = t["h"] <= t["fs"] * 1.6 and chr(10) not in t["t"]
                largh = t["w"] * 1.25 + 16 + 2 * pad
                if t["al"] == "center":
                    x0 = t["x"] + t["w"] / 2 - largh / 2
                elif t["al"] in ("right", "end"):
                    x0 = t["x"] + t["w"] - largh + pad
                else:
                    x0 = t["x"] - pad - 2
                tb = sl.shapes.add_textbox(px(x0), px(t["y"] - pad * 0.5 - 2), px(largh), px(t["h"] + pad + 6))
                tf = tb.text_frame
                tf.word_wrap = False
                tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0
                tf.vertical_anchor = MSO_ANCHOR.TOP
                sfondo = rgb(t["bg"])
                if sfondo is not None:
                    tb.fill.solid(); tb.fill.fore_color.rgb = sfondo
                    tf.margin_left = tf.margin_right = px(pad); tf.margin_top = tf.margin_bottom = px(pad * 0.5)
                if t["bordo"]:
                    tb.line.color.rgb = rgb(t["bordo"]["col"]) or hexrgb("#0999B3")
                    tb.line.width = Pt(1.5)
                    if t["bordo"]["st"] == "dashed":
                        tb.line.dash_style = MSO_LINE.DASH
                    tf.margin_left = tf.margin_right = px(pad); tf.margin_top = tf.margin_bottom = px(pad * 0.5)
                runs = t.get("runs") or [{"t": riga, "br": i > 0, "ff": t["ff"], "fs": t["fs"], "fw": t["fw"], "fi": t["fi"], "col": t["col"], "up": False}
                                          for i, riga in enumerate(t["t"].split(chr(10)))]
                par = tf.paragraphs[0]
                primo = True
                for rn in runs:
                    if rn.get("br") and not primo:
                        par = tf.add_paragraph()
                    primo = False
                    par.alignment = {"center": PP_ALIGN.CENTER, "right": PP_ALIGN.RIGHT, "end": PP_ALIGN.RIGHT}.get(t["al"], PP_ALIGN.LEFT)
                    lh = t["lh"]
                    if lh and lh != "normal":
                        par.line_spacing = max(0.9, float(lh.replace("px", "")) / t["fs"])
                    testo = " ".join(rn["t"].split())
                    if not testo.strip():
                        continue
                    if rn.get("up"):
                        testo = testo.upper()
                    run = par.add_run()
                    run.text = testo
                    f = run.font
                    f.name = famiglia(rn["ff"])
                    f.size = Pt(rn["fs"] * 0.5)
                    fw = str(rn["fw"])
                    f.bold = int(fw) >= 600 if fw.isdigit() else fw == "bold"
                    f.italic = rn["fi"] == "italic"
                    col = rgb(rn["col"])
                    if col is not None:
                        f.color.rgb = col

        br.close()

    USCITA.parent.mkdir(exist_ok=True)
    prs.save(USCITA)
    print(USCITA, round(USCITA.stat().st_size / 1e6, 2), "MB", len(prs.slides), "slide")


if __name__ == "__main__":
    main()
