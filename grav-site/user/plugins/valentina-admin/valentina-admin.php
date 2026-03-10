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

/* Pulsanti PUBBLICA / BOZZA nel titlebar delle pagine articolo */
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
/* Overlay riscrivi */
#vb-rewrite-overlay{display:none;position:fixed;inset:0;background:rgba(10,20,40,.85);
  z-index:99999;flex-direction:column;align-items:center;justify-content:center;gap:18px;}
#vb-rewrite-overlay.show{display:flex;}
#vb-rewrite-overlay .vb-spinner{width:48px;height:48px;border:4px solid rgba(255,255,255,.2);
  border-top-color:#fff;border-radius:50%;animation:vb-spin .9s linear infinite;}
@keyframes vb-spin{to{transform:rotate(360deg)}}
#vb-rewrite-overlay p{color:#fff;font-size:1rem;font-weight:600;}
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

  /* ── SUBMIT INTERCEPTOR ───────────────
   * Capture phase: si esegue PRIMA del jQuery submit handler di Grav.
   * Garantisce che title/date siano valorizzati nel momento esatto in cui
   * il browser (o Grav) serializza i dati — immune ai re-render di Vue.
   * ────────────────────────────────────── */
  function attachSubmitInterceptor(form){
    if(form._vbInterceptor) return;
    form._vbInterceptor = true;
    form.addEventListener('submit', function(){
      var f = this;
      // Sincronizza tutti gli editor CodeMirror → textarea (data[content])
      document.querySelectorAll('.CodeMirror').forEach(function(w){
        if(w.CodeMirror) w.CodeMirror.save();
      });
      // title
      var ti = f.querySelector('input[name="data[title]"]');
      var jt = document.querySelector('input[name="data[_json][header][title]"]');
      if(ti && !ti.value.trim() && jt && jt.value){
        try{ ti.value = JSON.parse(jt.value); }
        catch(e){ ti.value = jt.value.replace(/^"|"$/g,''); }
      }
      // date (formato blueprint: Y-m-d H:i:s)
      var di = f.querySelector('input[name="data[date]"]');
      var jd = document.querySelector('input[name="data[_json][header][date]"]');
      if(di && !di.value.trim()){
        var ts = parseInt(jd ? jd.value : '', 10);
        var dt = !isNaN(ts) ? new Date(ts*1000) : new Date();
        di.value = dt.getFullYear()+'-'+pad2(dt.getMonth()+1)+'-'+pad2(dt.getDate())
          +' '+pad2(dt.getHours())+':'+pad2(dt.getMinutes())+':00';
      }
      // data[name] = item (evita reset del template a "default")
      var ni = f.querySelector('input[name="data[name]"]');
      if(!ni){
        ni = document.createElement('input');
        ni.type='hidden'; ni.name='data[name]'; f.appendChild(ni);
      }
      if(!ni.value) ni.value = 'item';
    }, true); // true = capture phase
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

    // Registra intercettore submit (fill title/date in capture phase)
    attachSubmitInterceptor(form);

    // 1. Aggiorna stato published
    form.querySelectorAll('input[type="radio"][name="data[published]"]').forEach(function(r){
      r.checked = (r.value == publishedValue);
    });
    document.querySelectorAll('[data-value]').forEach(function(opt){
      if(opt.getAttribute('data-value') == publishedValue){ opt.click(); }
    });
    var jsonPub = document.querySelector('input[name="data[_json][header][published]"]');
    if(jsonPub) jsonPub.value = (publishedValue == 1) ? 'true' : 'false';

    // 2. CRITICO: disabilita HTML5 validation del browser
    //    data[title] e data[date] hanno required=true; senza noValidate il browser
    //    blocca il submit PRIMA che il capture-phase interceptor possa scattare
    form.noValidate = true;

    // 3. Rimuovi popup "Lascia sito"
    window.onbeforeunload = null;

    // 4. Clicca Salva nativo dopo che Vue ha gestito il toggle (~200ms)
    //    Con noValidate=true il submit event scatta → interceptor riempie title/date
    //    → jQuery handler di Grav serializza e invia
    setTimeout(function(){
      var saveBtn = document.querySelector('button[name="task"][value="save"]');
      if(saveBtn){ saveBtn.click(); }
      else {
        var fb = document.querySelector('#titlebar button.success');
        if(fb){ fb.click(); } else { form.submit(); }
      }
    }, 200);
  }

  /* ── RISCRIVI CON IA ─────────────────── */
  function vbRewriteArticle(){
    // Trova il CodeMirror del corpo articolo
    var contentCM = null;
    document.querySelectorAll('.CodeMirror').forEach(function(el){
      if(el.CodeMirror){
        var v = el.CodeMirror.getValue();
        if(v && v.length > 80 && !v.trim().startsWith('title:')){ contentCM = el.CodeMirror; }
      }
    });
    if(!contentCM){ alert('Editor articolo non trovato. Apri il tab Contenuto.'); return; }

    var currentContent = contentCM.getValue().trim();
    if(!currentContent){ alert('Il corpo dell\'articolo è vuoto.'); return; }

    if(!confirm('Riscrivi il corpo dell\'articolo con Claude Opus?\n\nSEO, FAQ, immagine e meta-dati NON vengono toccati.\nIl testo attuale verrà sostituito.')){return;}

    // Mostra overlay
    var overlay = document.getElementById('vb-rewrite-overlay');
    overlay.classList.add('show');

    var fd = new FormData();
    fd.append('content', currentContent);

    fetch('/ai-rewrite.php', { method: 'POST', body: fd, credentials: 'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        overlay.classList.remove('show');
        if(!data.ok){ alert('Errore: ' + (data.error || 'sconosciuto')); return; }
        contentCM.setValue(data.content);
        contentCM.save();
        // Flash verde per conferma visiva
        var wrap = contentCM.getWrapperElement();
        wrap.style.transition='box-shadow .3s';
        wrap.style.boxShadow='0 0 0 3px #27ae60';
        setTimeout(function(){ wrap.style.boxShadow=''; }, 2000);
      })
      .catch(function(err){
        overlay.classList.remove('show');
        alert('Errore di rete: ' + err);
      });
  }

  /* ── DOM READY ───────────────────────── */
  document.addEventListener('DOMContentLoaded', function(){
    // Aggiungi overlay al DOM
    var overlay = document.createElement('div');
    overlay.id = 'vb-rewrite-overlay';
    overlay.innerHTML = '<div class="vb-spinner"></div><p>✨ Claude sta riscrivendo l\'articolo…</p>';
    document.body.appendChild(overlay);

    /* 1. Pulsanti + Privati / + Aziende nel titlebar globale */
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

    /* 2. Pulsanti PUBBLICA / SALVA BOZZA nelle pagine di edit articolo */
    var url = window.location.href;
    var isArticle = url.match(/\/admin\/pages\/(blog\/|aziende\/blog\/).+/);
    if(!isArticle) return;

    // Attendi che il titlebar sia popolato
    setTimeout(function(){
      var titlebar = document.querySelector('#titlebar .button-bar');
      if(!titlebar) return;

      // Nascondi il bottone Salva nativo (rimane nel DOM per il click programmato)
      var saveBtn = titlebar.querySelector('button[data-task="save"], .button.save');
      if(saveBtn) saveBtn.style.display = 'none';

      var btnPub = document.createElement('button');
      btnPub.type = 'button';
      btnPub.className = 'vb-pubblica';
      btnPub.innerHTML = '<i class="fa fa-check"></i> PUBBLICA';
      btnPub.title = 'Pubblica l\'articolo sul sito';
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
      btnRewrite.innerHTML = '<i class="fa fa-magic"></i> Riscrivi con IA';
      btnRewrite.title = 'Riscrive il corpo dell\'articolo con Claude Opus (mantiene SEO/FAQ/meta)';
      btnRewrite.addEventListener('click', function(){ vbRewriteArticle(); });

      titlebar.appendChild(btnBozza);
      titlebar.appendChild(btnPub);
      titlebar.appendChild(btnRewrite);
    }, 300);

  });
})();
</script>
HTML;

        $this->grav->output = str_replace('</body>', $inject . "\n</body>", $this->grav->output);
    }
}
