<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

class ValentinaAdminPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onAdminMenu'       => ['onAdminMenu', 0],
            'onOutputGenerated' => ['onOutputGenerated', 0],
        ];
    }

    public function onAdminMenu(): void
    {
        $nav = &$this->grav['twig']->plugins_hooked_nav;

        $nav['Articoli Privati'] = [
            'route'     => 'pages/blog/articoli',
            'icon'      => 'fa-pencil',
            'authorize' => ['admin.pages', 'admin.super'],
        ];

        $nav['Articoli Aziende'] = [
            'route'     => 'pages/aziende/blog',
            'icon'      => 'fa-briefcase',
            'authorize' => ['admin.pages', 'admin.super'],
        ];
    }

    public function onOutputGenerated(): void
    {
        if (!isset($this->grav['admin'])) return;

        $base = json_encode($this->grav['base_url_relative'] ?? '');

        $inject = <<<HTML
<style>
/* Pulsanti + Nuovo nel titlebar */
.vb {
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 14px;border-radius:20px;
  font-size:.78rem;font-weight:700;
  color:#fff!important;text-decoration:none!important;
  cursor:pointer;border:none;margin-left:6px;
  transition:opacity .2s;
}
.vb:hover{opacity:.82;}
.vb-p{background:#B68397;}
.vb-a{background:#1e3a5f;}
.vb-ai{background:#7c3aed;}

/* Barra azioni articolo — fissa in basso */
#vb-action-bar{
  position:fixed;bottom:0;left:0;right:0;z-index:9000;
  background:#0f172a;border-top:1px solid #1e293b;
  display:flex;align-items:center;justify-content:space-between;
  padding:8px 20px;gap:12px;
  box-shadow:0 -4px 16px rgba(0,0,0,.4);
}
#vb-action-bar .vb-ab-left{
  display:flex;align-items:center;gap:8px;
  color:#64748b;font-size:.78rem;flex-shrink:0;
}
#vb-action-bar .vb-ab-left code{
  color:#38bdf8;font-size:.82rem;letter-spacing:.01em;
}
#vb-action-bar .vb-ab-rename{
  background:#1e293b;color:#94a3b8;border:1px solid #334155;
  border-radius:4px;padding:2px 9px;font-size:.72rem;cursor:pointer;
}
#vb-action-bar .vb-ab-rename:hover{background:#334155;color:#e2e8f0;}
#vb-action-bar .vb-ab-right{display:flex;align-items:center;gap:8px;}
#vb-action-bar button{
  border:none;border-radius:6px;cursor:pointer;
  font-size:.85rem;font-weight:600;padding:7px 18px;
  display:inline-flex;align-items:center;gap:5px;
  transition:opacity .15s;
}
#vb-action-bar button:hover{opacity:.85;}
.vb-ab-bozza  {background:#475569;color:#f1f5f9;}
.vb-ab-pubblica{background:#16a34a;color:#fff;}
.vb-ab-rewrite {background:#d97706;color:#fff;}
.vb-ab-full    {background:#0f766e;color:#fff;}
.vb-ab-elimina {background:#dc2626;color:#fff;}
/* Sezione privati — bordo rosa */
#vb-action-bar.vb-s-privati{ border-top-color:#B68397; }
/* Sezione aziende — bordo blu */
#vb-action-bar.vb-s-aziende{ border-top-color:#1e3a5f; }
/* Badge sezione */
.vb-ab-badge{
  display:inline-flex;align-items:center;gap:4px;
  border-radius:12px;padding:2px 10px;font-size:.72rem;font-weight:700;
  letter-spacing:.03em;flex-shrink:0;
}
.vb-ab-badge-p{background:#B68397;color:#fff;}
.vb-ab-badge-a{background:#1e3a5f;color:#fff;}
/* Pulsante Sposta */
#vb-action-bar .vb-ab-sposta{
  background:#1e293b;color:#94a3b8;border:1px solid #334155;
  border-radius:4px;padding:2px 9px;font-size:.72rem;cursor:pointer;
}
#vb-action-bar .vb-ab-sposta:hover{background:#334155;color:#e2e8f0;}
/* Padding pagina per non coprire il contenuto */
body.grav-admin-page{ padding-bottom:58px!important; }

/* Overlay loading */
#vb-rewrite-overlay{display:none;position:fixed;inset:0;background:rgba(10,20,40,.88);
  z-index:99999;flex-direction:column;align-items:center;justify-content:center;gap:18px;}
#vb-rewrite-overlay.show{display:flex;}
#vb-rewrite-overlay .vb-spinner{width:48px;height:48px;border:4px solid rgba(255,255,255,.2);
  border-top-color:#fff;border-radius:50%;animation:vb-spin .9s linear infinite;}
@keyframes vb-spin{to{transform:rotate(360deg)}}
#vb-rewrite-overlay p{color:#fff;font-size:1rem;font-weight:600;max-width:360px;text-align:center;}

/* Image prompt panel */
#vb-img-prompt{
  position:fixed;bottom:24px;right:24px;z-index:99999;
  background:#1e293b;color:#f1f5f9;border-radius:12px;
  padding:20px;max-width:500px;width:calc(100vw - 48px);
  box-shadow:0 8px 32px rgba(0,0,0,.6);
}
#vb-img-prompt .vb-ip-label{
  font-weight:700;font-size:.72rem;text-transform:uppercase;
  letter-spacing:.06em;color:#64748b;margin-bottom:8px;
}
#vb-img-prompt .vb-ip-text{
  font-size:.83rem;line-height:1.55;background:#0f172a;
  padding:10px 12px;border-radius:6px;margin-bottom:12px;
  user-select:all;
}
#vb-img-prompt .vb-ip-actions{display:flex;gap:8px;}
#vb-img-prompt button{
  border:none;border-radius:6px;padding:6px 14px;
  cursor:pointer;font-size:.82rem;font-weight:600;
}
</style>
<script>
(function(){
  var BASE = {$base};

  /* ── SLUGIFY ─────────────────────────── */
  function slugify(s){
    var m={'à':'a','á':'a','â':'a','ä':'a','è':'e','é':'e','ê':'e','ë':'e',
           'ì':'i','í':'i','î':'i','ï':'i','ò':'o','ó':'o','ô':'o','ö':'o',
           'ù':'u','ú':'u','û':'u','ü':'u','ñ':'n','ç':'c'};
    return s.toLowerCase()
      .replace(/[àáâäèéêëìíîïòóôöùúûüñç]/g,function(c){return m[c]||c;})
      .replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  }

  /* ── NONCE ───────────────────────────── */
  function getNonce(){
    var btn = document.querySelector('[data-clear-cache]');
    if(btn){var r=btn.getAttribute('data-clear-cache').match(/admin-nonce:([a-f0-9]+)/);if(r)return r[1];}
    var inp = document.querySelector('input[name="admin-nonce"]');
    return inp ? inp.value : '';
  }

  /* ── CREA ARTICOLO ───────────────────── */
  function nuovoArticolo(route, emoji){
    var title = prompt(emoji + '  Titolo del nuovo articolo:');
    if(!title || !title.trim()) return;
    title = title.trim();
    var slug = slugify(title) || ('articolo-' + Date.now());
    var nonce = getNonce();

    var f = document.createElement('form');
    f.method = 'POST';
    f.action = BASE + '/admin/pages' + route;
    f.style.display = 'none';

    [['data[title]',title],['data[folder]',slug],['data[route]',route],
     ['data[name]','item'],['task','continue'],['admin-nonce',nonce]]
    .forEach(function(p){
      var i=document.createElement('input');
      i.type='hidden';i.name=p[0];i.value=p[1];f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
  }

  /* ── PADDING DATA ────────────────────── */
  function pad2(n){ return n < 10 ? '0'+n : ''+n; }

  /* ── SUBMIT INTERCEPTOR ─────────────── */
  function attachSubmitInterceptor(form){
    if(form._vbInterceptor) return;
    form._vbInterceptor = true;
    form.addEventListener('submit', function(){
      var f = this;
      document.querySelectorAll('.CodeMirror').forEach(function(w){
        if(w.CodeMirror) w.CodeMirror.save();
      });
      var ti = f.querySelector('input[name="data[title]"]');
      var jt = document.querySelector('input[name="data[_json][header][title]"]');
      if(ti && !ti.value.trim() && jt && jt.value){
        try{ ti.value = JSON.parse(jt.value); }
        catch(e){ ti.value = jt.value.replace(/^"|"$/g,''); }
      }
      var di = f.querySelector('input[name="data[date]"]');
      var jd = document.querySelector('input[name="data[_json][header][date]"]');
      if(di && !di.value.trim()){
        var ts = parseInt(jd ? jd.value : '', 10);
        var dt = !isNaN(ts) ? new Date(ts*1000) : new Date();
        di.value = dt.getFullYear()+'-'+pad2(dt.getMonth()+1)+'-'+pad2(dt.getDate())
          +' '+pad2(dt.getHours())+':'+pad2(dt.getMinutes())+':00';
      }
      var ni = f.querySelector('input[name="data[name]"]');
      if(!ni){
        ni = document.createElement('input');
        ni.type='hidden'; ni.name='data[name]'; f.appendChild(ni);
      }
      if(!ni.value) ni.value = 'item';
    }, true);
  }

  /* ── SALVA CON STATO PUBBLICAZIONE ───── */
  function saveWithState(publishedValue){
    var form = document.getElementById('blueprints');
    if(!form){
      var forms = document.querySelectorAll('form[method="post"]');
      for(var i=0;i<forms.length;i++){
        if(forms[i].action.indexOf('/admin/pages/')!==-1){ form=forms[i]; break; }
      }
    }
    if(!form){ alert('Form non trovata.'); return; }
    attachSubmitInterceptor(form);
    form.querySelectorAll('input[type="radio"][name="data[published]"]').forEach(function(r){
      r.checked = (r.value == publishedValue);
    });
    document.querySelectorAll('[data-value]').forEach(function(opt){
      if(opt.getAttribute('data-value') == publishedValue){ opt.click(); }
    });
    var jsonPub = document.querySelector('input[name="data[_json][header][published]"]');
    if(jsonPub) jsonPub.value = (publishedValue == 1) ? 'true' : 'false';
    form.noValidate = true;
    window.onbeforeunload = null;
    setTimeout(function(){
      var saveBtn = document.querySelector('button[name="task"][value="save"]');
      if(saveBtn){ saveBtn.click(); }
      else {
        var fb = document.querySelector('#titlebar button.success');
        if(fb){ fb.click(); } else { form.submit(); }
      }
    }, 200);
  }

  /* ── RINOMINA SLUG ───────────────────── */
  function rinominaSlug(currentSlug){
    var newSlug = prompt('Nuovo slug (URL) per questo articolo:\\nMinuscolo, trattini, senza spazi o accenti.', currentSlug);
    if(!newSlug || !newSlug.trim()) return;
    newSlug = slugify(newSlug.trim());
    if(!newSlug){ alert('Slug non valido.'); return; }
    if(newSlug === currentSlug){ return; }

    var parts          = window.location.pathname.split('/');
    var pIdx           = parts.indexOf('pages');
    var parentSegments = parts.slice(pIdx + 1, -1);
    var parentPath     = parentSegments.join('/');
    var parentAdminUrl = BASE + '/admin/pages/' + parentPath;

    overlayShow('Rinomina slug in corso...');

    var fd = new FormData();
    fd.append('pass',        'ValeAdmin2026');
    fd.append('parent_path', parentPath);
    fd.append('old_slug',    currentSlug);
    fd.append('new_slug',    newSlug);

    fetch('/vb-rename.php', { method:'POST', body:fd, credentials:'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        overlayHide();
        if(data.ok){
          window.location.href = parentAdminUrl;
        } else {
          alert('Errore rinomina: ' + (data.error || 'sconosciuto'));
        }
      })
      .catch(function(err){ overlayHide(); alert('Errore: ' + err); });
  }

  /* ── SPOSTA ARTICOLO ─────────────────── */
  function spostaArticolo(slug, isAziende){
    var dest      = isAziende ? 'blog/articoli' : 'aziende/blog';
    var destLabel = isAziende ? 'Privati' : 'Aziende';
    if(!confirm('Sposta in sezione ' + destLabel + '?\\n\\nL URL cambiera. Se l articolo era pubblicato, aggiungi un redirect in site.yaml.')){return;}

    overlayShow('Spostamento in corso...');

    var fd = new FormData();
    fd.append('pass',       'ValeAdmin2026');
    fd.append('old_parent', isAziende ? 'aziende/blog' : 'blog/articoli');
    fd.append('new_parent', dest);
    fd.append('slug',       slug);

    fetch('/vb-move.php', { method:'POST', body:fd, credentials:'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        overlayHide();
        if(data.ok){
          window.location.href = BASE + '/admin/pages/' + dest;
        } else {
          alert('Errore spostamento: ' + (data.error || 'sconosciuto'));
        }
      })
      .catch(function(err){ overlayHide(); alert('Errore: ' + err); });
  }

  /* ── HELPER: overlay ─────────────────── */
  function overlayShow(msg){
    var o = document.getElementById('vb-rewrite-overlay');
    var p = o ? o.querySelector('p') : null;
    if(p) p.textContent = msg;
    if(o) o.classList.add('show');
  }
  function overlayHide(){
    var o = document.getElementById('vb-rewrite-overlay');
    if(o) o.classList.remove('show');
  }

  /* ── HELPER: escape HTML ─────────────── */
  function escHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── HELPER: trova entrambi i CodeMirror ─ */
  function getCMs(){
    var front = null, body = null;
    document.querySelectorAll('.CodeMirror').forEach(function(el){
      if(!el.CodeMirror) return;
      var v = el.CodeMirror.getValue();
      if(!v) return;
      if(v.trim().startsWith('title:')){ front = el.CodeMirror; }
      else if(v.length > 80){ body = el.CodeMirror; }
    });
    return { front: front, body: body };
  }

  /* ── HELPER: aggiorna campo form Vue ─── */
  function setField(name, value){
    var sel = 'input[name="' + name + '"],textarea[name="' + name + '"]';
    var el = document.querySelector(sel);
    if(!el) return false;
    var proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    var desc = Object.getOwnPropertyDescriptor(proto, 'value');
    if(desc && desc.set){ desc.set.call(el, value); }
    else { el.value = value; }
    el.dispatchEvent(new Event('input',  { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }

  /* ── PANEL: image prompt ─────────────── */
  function showImagePrompt(prompt){
    var existing = document.getElementById('vb-img-prompt');
    if(existing) existing.remove();

    var panel = document.createElement('div');
    panel.id = 'vb-img-prompt';
    panel.innerHTML =
      '<div class="vb-ip-label">Image Prompt (Midjourney / DALL·E)</div>' +
      '<div class="vb-ip-text" id="vb-ip-text">' + escHtml(prompt) + '</div>' +
      '<div class="vb-ip-actions">' +
        '<button id="vb-ip-copy" style="background:#3b82f6;color:#fff;">Copia Prompt</button>' +
        '<button id="vb-ip-close" style="background:#475569;color:#fff;">Chiudi</button>' +
      '</div>';
    document.body.appendChild(panel);

    document.getElementById('vb-ip-copy').addEventListener('click', function(){
      var txt = document.getElementById('vb-ip-text').textContent;
      navigator.clipboard.writeText(txt).then(function(){
        var btn = document.getElementById('vb-ip-copy');
        if(btn){ btn.textContent = 'Copiato!'; setTimeout(function(){ if(btn) btn.textContent = 'Copia Prompt'; }, 1600); }
      });
    });
    document.getElementById('vb-ip-close').addEventListener('click', function(){
      var p = document.getElementById('vb-img-prompt');
      if(p) p.remove();
    });
  }

  /* ── RISCRIVI SOLO TESTO ─────────────── */
  function vbRewriteArticle(){
    var cms = getCMs();
    if(!cms.body){ alert('Editor articolo non trovato. Apri il tab Contenuto.'); return; }
    var body = cms.body.getValue().trim();
    if(!body){ alert("Il corpo dell'articolo e' vuoto."); return; }
    if(!confirm('Riscrivi il testo con Claude Opus?\\n\\nSEO, FAQ e meta-dati NON vengono toccati.')){return;}

    overlayShow('Claude sta riscrivendo il testo...');

    var fd = new FormData();
    fd.append('content', body);
    fd.append('mode', 'body');
    fd.append('pass', 'ValeAdmin2026');

    fetch('/ai-rewrite.php', { method: 'POST', body: fd, credentials: 'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        overlayHide();
        if(!data.ok){ alert('Errore: ' + (data.error || 'sconosciuto')); return; }
        cms.body.setValue(data.content);
        cms.body.save();
        var wrap = cms.body.getWrapperElement();
        wrap.style.transition='box-shadow .3s';
        wrap.style.boxShadow='0 0 0 3px #27ae60';
        setTimeout(function(){ wrap.style.boxShadow=''; }, 2000);
      })
      .catch(function(err){ overlayHide(); alert('Errore di rete: ' + err); });
  }

  /* ── RIGENERA TUTTO ──────────────────── */
  function vbFullRewrite(){
    var cms = getCMs();
    if(!cms.body){ alert('Editor articolo non trovato. Apri il tab Contenuto.'); return; }
    var body = cms.body.getValue().trim();
    if(!body){ alert("Il corpo dell'articolo e' vuoto."); return; }

    var frontText = cms.front ? cms.front.getValue() : '';
    var titleEl   = document.querySelector('input[name="data[title]"]');
    var title     = titleEl ? titleEl.value.trim() : '';

    if(!confirm('Rigenera TUTTO con Claude Opus?\\n\\nVerranno riscritti: testo, description, SEO, tag, FAQ.\\nContenuto attuale sostituito.')){return;}

    overlayShow('Claude sta rigenerando testo + SEO + FAQ... Puo richiedere 1-2 minuti.');

    var fd = new FormData();
    fd.append('content',     body);
    fd.append('frontmatter', frontText);
    fd.append('title',       title);
    fd.append('mode',        'full');
    fd.append('pass',        'ValeAdmin2026');

    fetch('/ai-rewrite.php', { method: 'POST', body: fd, credentials: 'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        overlayHide();
        if(!data.ok){ alert('Errore: ' + (data.error || 'sconosciuto')); return; }

        // 1. Corpo articolo
        if(data.body && cms.body){
          cms.body.setValue(data.body);
          cms.body.save();
        }

        // 2a. Expert Mode — aggiorna frontmatter YAML direttamente nel CM
        if(data.frontmatter && cms.front){
          cms.front.setValue(data.frontmatter);
          cms.front.save();
        }

        // 2b. Normal Mode fallback — aggiorna i campi form individuali
        if(!cms.front && data.data){
          var d = data.data;
          var scalars = {
            'description': d.description,
            'seo_title':   d.seo_title,
            'seo_desc':    d.seo_desc,
            'aeo_answer':   d.aeo_answer,
            'geo_content':  d.geo_content,
            'image_alt':    d.image_alt,
            'image_title':  d.image_title,
            'image_caption':d.image_caption,
            'image_desc':   d.image_desc,
            'tags':         d.tags
          };
          Object.keys(scalars).forEach(function(k){
            if(!scalars[k]) return;
            // Prova data[header][k] (Grav standard) poi data[k] (Flex Objects)
            if(!setField('data[header][' + k + ']', scalars[k])){
              setField('data[' + k + ']', scalars[k]);
            }
          });
          // FAQ: aggiorna voci lista
          if(d.faq && d.faq.length){
            d.faq.forEach(function(item, i){
              var qSet = setField('data[header][faq][' + i + '][question]', item.question || '');
              if(!qSet) setField('data[faq][' + i + '][question]', item.question || '');
              var aSet = setField('data[header][faq][' + i + '][answer]', item.answer || '');
              if(!aSet) setField('data[faq][' + i + '][answer]', item.answer || '');
            });
          }
        }

        // Flash verde
        if(cms.body){
          var wrap = cms.body.getWrapperElement();
          wrap.style.transition='box-shadow .3s';
          wrap.style.boxShadow='0 0 0 3px #0f766e';
          setTimeout(function(){ wrap.style.boxShadow=''; }, 2500);
        }

        // 3. Image prompt panel
        if(data.image_prompt){
          showImagePrompt(data.image_prompt);
        } else {
          alert('Rigenerazione completata! Controlla e premi PUBBLICA o Salva Bozza.');
        }
      })
      .catch(function(err){ overlayHide(); alert('Errore di rete: ' + err); });
  }

  /* ── DOM READY ───────────────────────── */
  document.addEventListener('DOMContentLoaded', function(){
    var overlay = document.createElement('div');
    overlay.id = 'vb-rewrite-overlay';
    overlay.innerHTML = '<div class="vb-spinner"></div><p>Elaborazione in corso...</p>';
    document.body.appendChild(overlay);

    /* 1. Pulsanti globali nel titlebar */
    var bar = document.querySelector('#titlebar .button-bar');
    if(bar){
      var btnA = document.createElement('a');
      btnA.className='vb vb-a'; btnA.href='#';
      btnA.innerHTML='<i class="fa fa-briefcase"></i> + Aziende';
      btnA.addEventListener('click',function(e){e.preventDefault();nuovoArticolo('/aziende/blog','💼');});

      var btnP = document.createElement('a');
      btnP.className='vb vb-p'; btnP.href='#';
      btnP.innerHTML='<i class="fa fa-pencil"></i> + Privati';
      btnP.addEventListener('click',function(e){e.preventDefault();nuovoArticolo('/blog/articoli','✏️');});

      var btnAI = document.createElement('a');
      btnAI.className='vb vb-ai'; btnAI.href='/ai-editor.php'; btnAI.target='_blank';
      btnAI.innerHTML='<i class="fa fa-magic"></i> AI Editor';

      bar.insertBefore(btnA, bar.firstChild);
      bar.insertBefore(btnP, bar.firstChild);
      bar.insertBefore(btnAI, bar.firstChild);
    }

    /* 2. Action bar fissa in basso per le pagine di edit articolo */
    var url = window.location.href;
    var isArticle = url.match(/\/admin\/pages\/(blog\/articoli\/|aziende\/blog\/).+/);
    if(!isArticle) return;

    setTimeout(function(){
      /* Nascondi il pulsante Salva nativo di Grav */
      var titlebar = document.querySelector('#titlebar .button-bar');
      if(titlebar){
        var saveBtn = titlebar.querySelector('button[data-task="save"], .button.save');
        if(saveBtn) saveBtn.style.display = 'none';
      }

      /* Rileva sezione */
      var isAziende  = !!url.match(/\/admin\/pages\/aziende\/blog\//);

      /* Estrai slug dall'URL */
      var slugMatch  = url.match(/\/admin\/pages\/(?:aziende\/blog|blog\/articoli)\/([^/?#]+)/);
      var currentSlug = slugMatch ? slugMatch[1] : '';

      /* Costruisci action bar */
      var bar = document.createElement('div');
      bar.id = 'vb-action-bar';
      bar.classList.add(isAziende ? 'vb-s-aziende' : 'vb-s-privati');

      /* — Sinistra: badge sezione + slug + rinomina + sposta — */
      var left = document.createElement('div');
      left.className = 'vb-ab-left';

      var badge = document.createElement('span');
      badge.className = 'vb-ab-badge ' + (isAziende ? 'vb-ab-badge-a' : 'vb-ab-badge-p');
      badge.innerHTML = isAziende ? '<i class="fa fa-briefcase"></i> Aziende' : '<i class="fa fa-pencil"></i> Privati';
      left.appendChild(badge);

      left.insertAdjacentHTML('beforeend', ' <i class="fa fa-link"></i> <span>Slug:</span> <code>' + currentSlug + '</code>');

      if(currentSlug){
        var renBtn = document.createElement('button');
        renBtn.className = 'vb-ab-rename';
        renBtn.textContent = 'Rinomina';
        renBtn.addEventListener('click', function(){ rinominaSlug(currentSlug); });
        left.appendChild(renBtn);

        var spoBtn = document.createElement('button');
        spoBtn.className = 'vb-ab-sposta';
        spoBtn.textContent = isAziende ? 'Sposta \u2192 Privati' : 'Sposta \u2192 Aziende';
        spoBtn.addEventListener('click', function(){ spostaArticolo(currentSlug, isAziende); });
        left.appendChild(spoBtn);
      }

      /* — Destra: azioni — */
      var right = document.createElement('div');
      right.className = 'vb-ab-right';

      var bBozza = document.createElement('button');
      bBozza.className = 'vb-ab-bozza';
      bBozza.innerHTML = '<i class="fa fa-save"></i> Salva Bozza';
      bBozza.addEventListener('click', function(){ saveWithState(0); });

      var bPub = document.createElement('button');
      bPub.className = 'vb-ab-pubblica';
      bPub.innerHTML = '<i class="fa fa-check"></i> PUBBLICA';
      bPub.addEventListener('click', function(){ saveWithState(1); });

      var bRewrite = document.createElement('button');
      bRewrite.className = 'vb-ab-rewrite';
      bRewrite.innerHTML = '<i class="fa fa-magic"></i> Riscrivi Testo';
      bRewrite.addEventListener('click', function(){ vbRewriteArticle(); });

      var bFull = document.createElement('button');
      bFull.className = 'vb-ab-full';
      bFull.innerHTML = '<i class="fa fa-refresh"></i> Rigenera Tutto';
      bFull.addEventListener('click', function(){ vbFullRewrite(); });

      var bElimina = document.createElement('button');
      bElimina.className = 'vb-ab-elimina';
      bElimina.innerHTML = '<i class="fa fa-trash"></i> Elimina';
      bElimina.addEventListener('click', function(){
        if(!confirm('Eliminare definitivamente questo articolo?')) return;
        /* Clicca il bottone Elimina nativo di Grav (href="#delete") */
        var nativeDelete = document.querySelector('a[href="#delete"]');
        if(nativeDelete){ nativeDelete.click(); return; }
        /* Fallback: form POST con task delete */
        var form = document.querySelector('form#blueprints');
        if(!form) return;
        var inp = document.createElement('input');
        inp.type='hidden'; inp.name='task'; inp.value='delete';
        form.appendChild(inp);
        form.submit();
      });

      right.appendChild(bBozza);
      right.appendChild(bPub);
      right.appendChild(bRewrite);
      right.appendChild(bFull);
      right.appendChild(bElimina);

      bar.appendChild(left);
      bar.appendChild(right);
      document.body.appendChild(bar);
    }, 300);

  });
})();
</script>
HTML;

        $this->grav->output = str_replace('</body>', $inject . "\n</body>", $this->grav->output);
    }
}
