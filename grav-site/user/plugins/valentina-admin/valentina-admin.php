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
            'route'     => 'pages/blog',
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

/* Pulsanti articolo nel titlebar */
.vb-pubblica {
  background:#27ae60!important;color:#fff!important;
  font-weight:700!important;font-size:.9rem!important;
  padding:6px 20px!important;border-radius:6px!important;
  border:none!important;cursor:pointer;margin-left:8px;
}
.vb-bozza {
  background:#7f8c8d!important;color:#fff!important;
  font-weight:600!important;font-size:.9rem!important;
  padding:6px 16px!important;border-radius:6px!important;
  border:none!important;cursor:pointer;margin-left:6px;
}
.vb-pubblica:hover{opacity:.88!important;}
.vb-bozza:hover{opacity:.88!important;}
.vb-rewrite{background:#d97706!important;color:#fff!important;
  font-weight:600!important;font-size:.9rem!important;
  padding:6px 16px!important;border-radius:6px!important;
  border:none!important;cursor:pointer;margin-left:6px;}
.vb-rewrite:hover{opacity:.88!important;}
.vb-full{background:#0f766e!important;color:#fff!important;
  font-weight:600!important;font-size:.9rem!important;
  padding:6px 16px!important;border-radius:6px!important;
  border:none!important;cursor:pointer;margin-left:6px;}
.vb-full:hover{opacity:.88!important;}

/* Slug bar */
#vb-slug-bar{
  background:#1e293b;color:#94a3b8;font-size:.78rem;
  padding:5px 16px;border-radius:0 0 6px 6px;
  margin-top:-2px;display:inline-flex;align-items:center;gap:8px;
}
#vb-slug-bar code{color:#38bdf8;font-size:.82rem;}

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

        // 2. Frontmatter (description, seo_title, seo_desc, tags, aeo_answer, faq)
        if(data.frontmatter && cms.front){
          cms.front.setValue(data.frontmatter);
          cms.front.save();
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
      btnP.addEventListener('click',function(e){e.preventDefault();nuovoArticolo('/blog','✏️');});

      var btnAI = document.createElement('a');
      btnAI.className='vb vb-ai'; btnAI.href='/ai-editor.php'; btnAI.target='_blank';
      btnAI.innerHTML='<i class="fa fa-magic"></i> AI Editor';

      bar.insertBefore(btnA, bar.firstChild);
      bar.insertBefore(btnP, bar.firstChild);
      bar.insertBefore(btnAI, bar.firstChild);
    }

    /* 2. Pulsanti nelle pagine di edit articolo */
    var url = window.location.href;
    var isArticle = url.match(/\/admin\/pages\/(blog\/|aziende\/blog\/).+/);
    if(!isArticle) return;

    /* Mostra slug corrente */
    var slugMatch = url.match(/\/admin\/pages\/(?:aziende\/blog|blog)\/([^/?#]+)/);
    if(slugMatch){
      var slugBar = document.createElement('div');
      slugBar.id = 'vb-slug-bar';
      slugBar.innerHTML = '<i class="fa fa-link"></i> Slug: <code>' + slugMatch[1] + '</code>'
        + ' <span style="color:#475569;font-size:.72rem;">(modifica via tab Opzioni &rarr; Nome Cartella)</span>';
      var tb = document.querySelector('#titlebar');
      if(tb) tb.insertAdjacentElement('afterend', slugBar);
    }

    setTimeout(function(){
      var titlebar = document.querySelector('#titlebar .button-bar');
      if(!titlebar) return;

      var saveBtn = titlebar.querySelector('button[data-task="save"], .button.save');
      if(saveBtn) saveBtn.style.display = 'none';

      var btnPub = document.createElement('button');
      btnPub.type = 'button';
      btnPub.className = 'vb-pubblica';
      btnPub.innerHTML = '<i class="fa fa-check"></i> PUBBLICA';
      btnPub.title = "Pubblica l'articolo sul sito";
      btnPub.addEventListener('click', function(){ saveWithState(1); });

      var btnBozza = document.createElement('button');
      btnBozza.type = 'button';
      btnBozza.className = 'vb-bozza';
      btnBozza.innerHTML = '<i class="fa fa-save"></i> Salva Bozza';
      btnBozza.title = 'Salva senza pubblicare';
      btnBozza.addEventListener('click', function(){ saveWithState(0); });

      var btnRewrite = document.createElement('button');
      btnRewrite.type = 'button';
      btnRewrite.className = 'vb-rewrite';
      btnRewrite.innerHTML = '<i class="fa fa-magic"></i> Riscrivi Testo';
      btnRewrite.title = 'Riscrive solo il testo con Claude (SEO e FAQ invariati)';
      btnRewrite.addEventListener('click', function(){ vbRewriteArticle(); });

      var btnFull = document.createElement('button');
      btnFull.type = 'button';
      btnFull.className = 'vb-full';
      btnFull.innerHTML = '<i class="fa fa-refresh"></i> Rigenera Tutto';
      btnFull.title = 'Riscrive testo + SEO + tag + FAQ + description con Claude';
      btnFull.addEventListener('click', function(){ vbFullRewrite(); });

      titlebar.appendChild(btnBozza);
      titlebar.appendChild(btnPub);
      titlebar.appendChild(btnRewrite);
      titlebar.appendChild(btnFull);
    }, 300);

  });
})();
</script>
HTML;

        $this->grav->output = str_replace('</body>', $inject . "\n</body>", $this->grav->output);
    }
}
