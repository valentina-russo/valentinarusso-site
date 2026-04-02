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
.vb-stat{background:#0f766e;}

/* Barra azioni articolo — fissa in basso */
#vb-action-bar{
  position:fixed;bottom:0;left:0;right:0;z-index:9000;
  background:#0f172a;border-top:1px solid #1e293b;
  display:flex;align-items:center;justify-content:space-between;
  padding:8px 20px;gap:8px 12px;
  box-shadow:0 -4px 16px rgba(0,0,0,.4);
  flex-wrap:wrap;
}
#vb-action-bar .vb-ab-left{
  display:flex;align-items:center;gap:8px;
  color:#64748b;font-size:.78rem;flex-shrink:0;
}
#vb-action-bar .vb-ab-left code{
  color:#38bdf8;font-size:.82rem;letter-spacing:.01em;
}
#vb-action-bar .vb-ab-title{
    background:#1e40af;color:#fff;border:none;border-radius:4px;
    padding:4px 10px;cursor:pointer;font-size:.72rem;font-weight:700;text-transform:uppercase;
}
#vb-action-bar .vb-ab-title:hover{background:#1d4ed8;}
#vb-action-bar .vb-ab-rename{
  background:#1e293b;color:#94a3b8;border:1px solid #334155;
  border-radius:4px;padding:2px 9px;font-size:.72rem;cursor:pointer;
}
#vb-action-bar .vb-ab-rename:hover{background:#334155;color:#e2e8f0;}
#vb-action-bar .vb-ab-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
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
.vb-ab-meta    {background:#7c3aed;color:#fff;}
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

/* LinkedIn modal */
#vb-linkedin-modal{
  display:none;position:fixed;inset:0;z-index:99998;
  background:rgba(10,20,40,.85);
  align-items:center;justify-content:center;
}
#vb-linkedin-modal.show{display:flex;}
#vb-linkedin-modal .vb-li-card{
  background:#fff;border-radius:16px;padding:28px 24px;
  max-width:560px;width:calc(100vw - 48px);
  box-shadow:0 12px 48px rgba(0,0,0,.4);
}
#vb-linkedin-modal .vb-li-header{
  display:flex;align-items:center;gap:10px;margin-bottom:16px;
}
#vb-linkedin-modal .vb-li-header svg{width:28px;height:28px;}
#vb-linkedin-modal .vb-li-header span{
  font-size:1.1rem;font-weight:700;color:#0a66c2;
}
#vb-linkedin-modal textarea{
  width:100%;min-height:200px;border:1.5px solid #d1d5db;
  border-radius:10px;padding:12px;font-family:inherit;
  font-size:.9rem;line-height:1.55;resize:vertical;
  color:#1a1a2e;box-sizing:border-box;
}
#vb-linkedin-modal textarea:focus{outline:none;border-color:#0a66c2;}
#vb-linkedin-modal .vb-li-note{
  font-size:.75rem;color:#888;margin-top:8px;line-height:1.4;
}
#vb-linkedin-modal .vb-li-actions{
  display:flex;gap:10px;margin-top:16px;
}
#vb-linkedin-modal .vb-li-actions button{
  border:none;border-radius:8px;padding:10px 20px;
  cursor:pointer;font-size:.88rem;font-weight:700;
  display:inline-flex;align-items:center;gap:6px;
  transition:opacity .15s;
}
#vb-linkedin-modal .vb-li-actions button:hover{opacity:.85;}
.vb-li-copy{background:#0a66c2;color:#fff;}
.vb-li-publish{background:#16a34a;color:#fff;}
.vb-li-publish:disabled{opacity:.5;cursor:not-allowed;}
.vb-li-open{background:#64748b;color:#fff;}
.vb-li-close{background:#e5e7eb;color:#475569;margin-left:auto;}
.vb-li-status{font-size:.75rem;padding:4px 10px;border-radius:6px;margin-bottom:12px;display:inline-block;}
.vb-li-status-ok{background:#dcfce7;color:#166534;}
.vb-li-status-no{background:#fef2f2;color:#991b1b;}
/* LinkedIn button in action bar */
.vb-ab-linkedin{background:#0a66c2;color:#fff;}

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

/* ── Social Media Generator ── */
.vb-ab-social{
  background:linear-gradient(135deg,#f09433 0%,#dc2743 50%,#bc1888 100%);
  color:#fff;
}
#vb-social-modal{
  display:none;position:fixed;inset:0;z-index:99997;
  background:rgba(5,10,20,.92);
  align-items:flex-start;justify-content:center;
  overflow-y:auto;padding:24px 16px;
}
#vb-social-modal.show{display:flex;}
#vb-social-modal .vb-sm-card{
  background:#0f172a;border-radius:20px;
  width:min(840px,calc(100vw - 32px));
  box-shadow:0 24px 80px rgba(0,0,0,.65);
  border:1px solid #1e293b;margin:auto;
}
#vb-social-modal .vb-sm-header{
  display:flex;align-items:center;gap:10px;
  padding:20px 24px 0;
}
#vb-social-modal .vb-sm-header h3{
  font-size:1.12rem;font-weight:700;margin:0;flex:1;
  background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
#vb-social-modal .vb-sm-close{
  background:none;border:none;color:#64748b;font-size:1.5rem;
  cursor:pointer;padding:4px 8px;line-height:1;border-radius:6px;
}
#vb-social-modal .vb-sm-close:hover{color:#f1f5f9;background:#1e293b;}
.vb-sm-tabs{
  display:flex;gap:4px;padding:14px 24px 0;
  border-bottom:1px solid #1e293b;margin-bottom:0;
}
.vb-sm-tabs button{
  border:none;background:none;color:#64748b;
  font-size:.84rem;font-weight:600;padding:8px 16px;
  border-bottom:2px solid transparent;cursor:pointer;
  border-radius:6px 6px 0 0;transition:color .15s;
}
.vb-sm-tabs button.active{color:#f1f5f9;border-bottom-color:#B68397;}
.vb-sm-tabs button:hover:not(.active){color:#94a3b8;}
.vb-sm-panel{padding:16px 24px 0;}
.vb-sm-panel[hidden]{display:none;}
.vb-sm-panel textarea{
  width:100%;min-height:150px;background:#1e293b;
  border:1.5px solid #334155;border-radius:10px;
  padding:12px;color:#f1f5f9;font-family:inherit;
  font-size:.88rem;line-height:1.55;resize:vertical;
  box-sizing:border-box;
}
.vb-sm-panel textarea:focus{outline:none;border-color:#B68397;}
.vb-sm-article-info{
  background:#1e293b;border-radius:10px;padding:16px;
  color:#94a3b8;font-size:.85rem;line-height:1.55;
}
.vb-sm-article-info strong{color:#f1f5f9;display:block;margin-bottom:6px;font-size:.9rem;}
.vb-sm-load-btn{
  margin-top:10px;background:#B68397;color:#fff;
  border:none;border-radius:8px;padding:8px 18px;
  font-size:.84rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;
}
.vb-sm-load-btn:hover{opacity:.85;}
.vb-sm-config{
  padding:12px 24px;display:flex;gap:12px;flex-wrap:wrap;
  border-top:1px solid #1e293b;margin-top:14px;
}
.vb-sm-config-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.vb-sm-config-row label{
  color:#64748b;font-size:.75rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;
}
.vb-sm-config-row button{
  border:1.5px solid #334155;background:none;color:#94a3b8;
  border-radius:20px;padding:4px 14px;font-size:.8rem;
  font-weight:600;cursor:pointer;transition:all .15s;
}
.vb-sm-config-row button.active{background:#1e3a5f;border-color:#1e3a5f;color:#fff;}
.vb-sm-config-row button:hover:not(.active){border-color:#B68397;color:#f1f5f9;}
.vb-sm-gen-wrap{padding:12px 24px 20px;border-top:1px solid #1e293b;}
#vb-sm-generate{
  width:100%;background:linear-gradient(135deg,#B68397,#1e3a5f);
  color:#fff;border:none;border-radius:10px;
  padding:14px;font-size:1rem;font-weight:700;
  cursor:pointer;transition:opacity .15s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
#vb-sm-generate:hover{opacity:.88;}
#vb-sm-generate:disabled{opacity:.45;cursor:not-allowed;}
#vb-sm-preview{padding:0 24px 24px;}
.vb-sm-preview-hdr{
  display:flex;align-items:center;padding:14px 0 10px;
  border-top:1px solid #1e293b;
}
.vb-sm-preview-hdr h4{color:#f1f5f9;font-size:.92rem;font-weight:700;margin:0;}
.vb-sm-slides-strip{
  display:flex;gap:8px;overflow-x:auto;padding:4px 0 10px;
  scrollbar-width:thin;scrollbar-color:#334155 transparent;
}
.vb-sm-slides-strip::-webkit-scrollbar{height:4px;}
.vb-sm-slides-strip::-webkit-scrollbar-thumb{background:#334155;border-radius:4px;}
.vb-sm-slide-wrap{
  flex-shrink:0;cursor:pointer;border-radius:8px;overflow:hidden;
  border:2px solid transparent;transition:border-color .15s;
}
.vb-sm-slide-wrap.active{border-color:#B68397;}
.vb-sm-slide-wrap canvas{display:block;width:175px;height:175px;}
.vb-sm-cap-wrap{margin-top:12px;}
.vb-sm-cap-wrap label{
  color:#64748b;font-size:.74rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:6px;
}
#vb-sm-caption{
  width:100%;min-height:90px;background:#1e293b;
  border:1.5px solid #334155;border-radius:8px;
  padding:10px 12px;color:#f1f5f9;font-size:.84rem;
  line-height:1.55;resize:vertical;font-family:inherit;box-sizing:border-box;
}
.vb-sm-ht-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.vb-sm-ht{
  background:#1e293b;color:#B68397;border:1px solid #334155;
  border-radius:20px;padding:3px 12px;font-size:.8rem;font-weight:600;
}
.vb-sm-dl-row{display:flex;gap:10px;margin-top:14px;}
#vb-sm-regen{
  background:#475569;color:#f1f5f9;border:none;border-radius:8px;
  padding:10px 20px;font-size:.88rem;font-weight:700;cursor:pointer;
  display:flex;align-items:center;gap:6px;
}
#vb-sm-regen:hover{background:#64748b;}
#vb-sm-download{
  background:#16a34a;color:#fff;border:none;border-radius:8px;
  padding:10px 20px;font-size:.88rem;font-weight:700;cursor:pointer;
  display:flex;align-items:center;gap:6px;
}
#vb-sm-download:hover{background:#15803d;}
#vb-sm-download:disabled{opacity:.5;cursor:not-allowed;}
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

    var now = new Date();
    var nowStr = now.getFullYear()+'-'+pad2(now.getMonth()+1)+'-'+pad2(now.getDate())
      +' '+pad2(now.getHours())+':'+pad2(now.getMinutes())+':00';

    [['data[title]',title],['data[folder]',slug],['data[route]',route],
     ['data[name]','item'],['data[date]',nowStr],['task','continue'],['admin-nonce',nonce]]
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
      if(di && !di.value.trim()){
        // Data mancante: articolo nuovo o articolo con data non valida.
        // Comportamento WordPress: la data di creazione si imposta automaticamente
        // e non cambia mai. Se il datepicker l'ha già valorizzata, di.value non
        // sarà vuoto e questo blocco non scatta.
        var jd = document.querySelector('input[name="data[_json][header][date]"]');
        var ts = parseInt(jd ? jd.value : '', 10);
        var dt;
        if(!isNaN(ts) && ts > 0){
          // Timestamp Unix da Grav (campo nascosto) — converti in stringa leggibile
          dt = new Date(ts*1000);
        } else {
          // Nessun timestamp disponibile → imposta la data corrente (primo salvataggio)
          dt = new Date();
        }
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

  /* ── CAMBIA TITOLO ──────────────────── */
  function cambiaTitolo(currentSlug){
    // Legge titolo attuale dal campo Grav o dal JSON header
    var titleEl = document.querySelector('input[name="data[title]"]');
    var jsonEl  = document.querySelector('input[name="data[_json][header][title]"]');
    var curr = '';
    if(titleEl && titleEl.value.trim()) curr = titleEl.value.trim();
    else if(jsonEl && jsonEl.value){
      try{ curr = JSON.parse(jsonEl.value); } catch(e){ curr = jsonEl.value.replace(/^"|"$/g,''); }
    }

    var newTitle = prompt('Modifica il titolo dell\'articolo:', curr);
    if(newTitle === null) return;
    newTitle = newTitle.trim();
    if(!newTitle){ alert('Il titolo non può essere vuoto.'); return; }
    if(newTitle === curr) return;

    var parts          = window.location.pathname.split('/');
    var pIdx           = parts.indexOf('pages');
    var parentSegments = parts.slice(pIdx + 1, -1);
    var parentPath     = parentSegments.join('/');

    overlayShow('Salvataggio titolo in corso...');

    var fd = new FormData();
    fd.append('pass',        'ValeAdmin2026');
    fd.append('parent_path', parentPath);
    fd.append('slug',        currentSlug);
    fd.append('new_title',   newTitle);

    fetch('/vb-set-title.php', { method:'POST', body:fd, credentials:'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        overlayHide();
        if(data.ok){
          // Aggiorna anche il campo titolo visibile nella pagina
          if(titleEl) titleEl.value = newTitle;
          if(jsonEl)  jsonEl.value  = JSON.stringify(newTitle);
          alert('✅ Titolo aggiornato: ' + newTitle);
          location.reload();
        } else {
          alert('Errore: ' + (data.error || 'sconosciuto'));
        }
      })
      .catch(function(err){ overlayHide(); alert('Errore: ' + err); });
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

  /* ── HELPER: tronca stringa a lunghezza massima ─ */
  function trunc(s, max){ return (s && s.length > max) ? s.substring(0, max) : s; }

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

  /* ── LINKEDIN POST ──────────────────── */
  function vbLinkedIn(){
    var url   = window.location.href;
    var isAz  = !!url.match(/\/admin\/pages\/aziende\/blog\//);
    var slugM = url.match(/\/admin\/pages\/(?:aziende\/blog|blog\/articoli)\/([^/?#]+)/);
    var slug  = slugM ? slugM[1] : '';

    /* Leggi titolo */
    var titleEl = document.querySelector('input[name="data[title]"]');
    var jsonEl  = document.querySelector('input[name="data[_json][header][title]"]');
    var title = '';
    if(titleEl && titleEl.value.trim()) title = titleEl.value.trim();
    else if(jsonEl && jsonEl.value){
      try{ title = JSON.parse(jsonEl.value); }catch(e){ title = jsonEl.value.replace(/^"|"$/g,''); }
    }

    /* Leggi description */
    var descEl = document.querySelector('input[name="data[header][description]"],textarea[name="data[header][description]"]');
    var descJ  = document.querySelector('input[name="data[_json][header][description]"]');
    var desc = '';
    if(descEl && descEl.value.trim()) desc = descEl.value.trim();
    else if(descJ && descJ.value){
      try{ desc = JSON.parse(descJ.value); }catch(e){ desc = descJ.value.replace(/^"|"$/g,''); }
    }

    /* Leggi tags */
    var tagsEl = document.querySelector('input[name="data[header][tags]"],input[name="data[_json][header][tags]"]');
    var tags = '';
    if(tagsEl && tagsEl.value){
      try{
        var arr = JSON.parse(tagsEl.value);
        if(Array.isArray(arr)){
          tags = arr.map(function(t){return '#'+t.replace(/[^a-zA-Z0-9àèéìòùÀÈÉÌÒÙ]+/g,'');}).join(' ');
        }
      }catch(e){ tags = '#HumanDesign #BG5'; }
    }
    if(!tags) tags = '#HumanDesign #BG5 #CarrieraConsapevole';

    /* Leggi immagine */
    var imgEl = document.querySelector('input[name="data[header][featured_image]"],input[name="data[_json][header][featured_image]"]');
    var featImg = '';
    if(imgEl && imgEl.value){
      var iv = imgEl.value.replace(/^"|"$/g,'');
      if(iv && iv.indexOf('placeholder') === -1){
        featImg = iv.indexOf('/') !== -1
          ? 'https://valentinarussobg5.com' + iv
          : 'https://valentinarussobg5.com/' + (isAz ? 'aziende/blog/' : 'blog/articoli/') + slug + '/' + iv;
      }
    }
    /* Fallback: cerca la prima immagine nella pagina admin (Dropzone thumbnails di Grav) */
    if(!featImg){
      var adminImg = document.querySelector('[data-dz-thumbnail], .dz-image img, .thumbs-list img, .page-media img');
      if(adminImg && adminImg.src && adminImg.src.indexOf('placeholder') === -1) featImg = adminImg.src.replace(/\?.*$/,'');
    }

    /* Costruisci URL articolo */
    var articleUrl = isAz
      ? 'https://valentinarussobg5.com/aziende/blog/' + slug
      : 'https://valentinarussobg5.com/blog/articoli/' + slug;

    /* Genera testo post LinkedIn */
    var post = title + '\\n\\n';
    if(desc) post += desc + '\\n\\n';
    post += '\\u{1F449} Leggi l\\u2019articolo completo \\u2192 ' + articleUrl + '\\n\\n';
    post += tags;

    /* Mostra modale */
    var existing = document.getElementById('vb-linkedin-modal');
    if(existing) existing.remove();

    var modal = document.createElement('div');
    modal.id = 'vb-linkedin-modal';
    modal.className = 'show';
    modal.innerHTML =
      '<div class="vb-li-card">' +
        '<div class="vb-li-header">' +
          '<svg viewBox="0 0 24 24" fill="#0a66c2"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>' +
          '<span>Post LinkedIn</span>' +
        '</div>' +
        '<div id="vb-li-status-wrap"></div>' +
        '<textarea id="vb-li-text">' + escHtml(post) + '</textarea>' +
        '<div class="vb-li-note">Modifica il testo, poi pubblica direttamente o copia e incolla manualmente.</div>' +
        '<div class="vb-li-actions">' +
          '<button class="vb-li-publish" id="vb-li-publish"><i class="fa fa-paper-plane"></i> Pubblica su LinkedIn</button>' +
          '<button class="vb-li-copy" id="vb-li-copy"><i class="fa fa-clipboard"></i> Copia</button>' +
          '<button class="vb-li-open" id="vb-li-open"><i class="fa fa-external-link"></i> Manuale</button>' +
          '<button class="vb-li-close" id="vb-li-close">Chiudi</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);

    /* Check connessione LinkedIn */
    fetch('/linkedin-post.php', {method:'POST', body:new URLSearchParams({pass:'ValeAdmin2026',text:'test',dry:'1'}), credentials:'include'})
      .then(function(r){return r.json();})
      .then(function(d){
        var wrap = document.getElementById('vb-li-status-wrap');
        if(!wrap) return;
        if(d.needsAuth){
          wrap.innerHTML = '<span class="vb-li-status vb-li-status-no">LinkedIn non connesso</span> <a href="/linkedin-callback.php" style="font-size:.75rem;color:#0a66c2;font-weight:600;">Connetti ora</a>';
          var pb = document.getElementById('vb-li-publish');
          if(pb) pb.disabled = true;
        } else {
          wrap.innerHTML = '<span class="vb-li-status vb-li-status-ok">LinkedIn connesso</span>';
        }
      }).catch(function(){});

    /* Pubblica direttamente */
    document.getElementById('vb-li-publish').addEventListener('click', function(){
      var btn = this;
      var txt = document.getElementById('vb-li-text').value;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pubblicazione...';

      var fd = new URLSearchParams();
      fd.append('pass', 'ValeAdmin2026');
      fd.append('text', txt);
      fd.append('articleUrl', articleUrl);
      fd.append('title', title);
      fd.append('description', desc);
      if(featImg) fd.append('imageUrl', featImg);

      fetch('/linkedin-post.php', {method:'POST', body:fd, credentials:'include'})
        .then(function(r){return r.json();})
        .then(function(d){
          if(d.ok){
            var imgInfo = (d.debug && d.debug.length) ? '\\n\\nDebug: ' + d.debug.join(' | ') : '';
            btn.innerHTML = '<i class="fa fa-check"></i> Pubblicato!';
            btn.style.background = '#166534';
            setTimeout(function(){
              var m = document.getElementById('vb-linkedin-modal');
              if(m) m.remove();
            }, 2000);
          } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane"></i> Pubblica su LinkedIn';
            if(d.needsAuth){
              window.open('/linkedin-callback.php', '_blank');
            } else {
              var dbg = (d.debug && d.debug.length) ? '\\n\\nDebug: ' + d.debug.join(' | ') : '';
              alert('Errore: ' + d.error + dbg);
            }
          }
        })
        .catch(function(err){
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-paper-plane"></i> Pubblica su LinkedIn';
          alert('Errore di rete: ' + err);
        });
    });

    /* Copia */
    document.getElementById('vb-li-copy').addEventListener('click', function(){
      var txt = document.getElementById('vb-li-text').value;
      navigator.clipboard.writeText(txt).then(function(){
        var btn = document.getElementById('vb-li-copy');
        if(btn){ btn.innerHTML = '<i class="fa fa-check"></i> Copiato!'; setTimeout(function(){ if(btn) btn.innerHTML = '<i class="fa fa-clipboard"></i> Copia'; }, 2000); }
      });
    });
    /* Apri manuale */
    document.getElementById('vb-li-open').addEventListener('click', function(){
      window.open('https://www.linkedin.com/feed/?shareActive=true', '_blank');
    });
    document.getElementById('vb-li-close').addEventListener('click', function(){
      var m = document.getElementById('vb-linkedin-modal');
      if(m) m.remove();
    });
    modal.addEventListener('click', function(e){
      if(e.target === modal) modal.remove();
    });
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
            'description':  d.description,
            'seo_title':    trunc(d.seo_title, 60),
            'seo_desc':     trunc(d.seo_desc, 160),
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
          // FAQ: popola lista
          if(d.faq && d.faq.length){
            applyFaqNormalMode(d.faq, null);
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

  /* ── GENERA SOLO METADATI ────────────── */
  function vbGenerateMeta(){
    var cms = getCMs();
    if(!cms.body){ alert('Editor articolo non trovato. Apri il tab Contenuto.'); return; }
    var body = cms.body.getValue().trim();
    if(!body){ alert("Il corpo del post e' vuoto."); return; }
    if(!confirm('Genera metadati SEO/FAQ con AI?\\n\\nIl testo del post NON viene modificato.\\nVengono aggiornati: description, SEO, tag, FAQ, AEO, metadati immagine.')){return;}

    overlayShow('Claude sta generando i metadati... Attendi qualche secondo.');

    var titleEl   = document.querySelector('input[name="data[title]"]');
    var title     = titleEl ? titleEl.value.trim() : '';
    var frontText = cms.front ? cms.front.getValue() : '';

    var fd = new FormData();
    fd.append('content',     body);
    fd.append('frontmatter', frontText);
    fd.append('title',       title);
    fd.append('mode',        'meta');
    fd.append('pass',        'ValeAdmin2026');

    fetch('/ai-rewrite.php', { method:'POST', body:fd, credentials:'include' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        overlayHide();
        if(!data.ok){ alert('Errore: ' + (data.error || 'sconosciuto')); return; }

        // Expert Mode: aggiorna frontmatter YAML
        if(data.frontmatter && cms.front){
          cms.front.setValue(data.frontmatter);
          cms.front.save();
        }

        // Normal Mode fallback: aggiorna campi form individuali
        if(!cms.front && data.data){
          var d = data.data;
          var scalars = {
            'description':  d.description,
            'seo_title':    trunc(d.seo_title, 60),
            'seo_desc':     trunc(d.seo_desc, 160),
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
            if(!setField('data[header][' + k + ']', scalars[k])){
              setField('data[' + k + ']', scalars[k]);
            }
          });
          if(d.faq && d.faq.length){
            applyFaqNormalMode(d.faq, null);
          }
        }

        // Flash verde sull'editor body (confermando che il testo e' intatto)
        if(cms.body){
          var wrap = cms.body.getWrapperElement();
          wrap.style.transition='box-shadow .3s';
          wrap.style.boxShadow='0 0 0 3px #7c3aed';
          setTimeout(function(){ wrap.style.boxShadow=''; }, 2500);
        }

        alert('Metadati generati! Controlla i campi e premi PUBBLICA o Salva Bozza.');
      })
      .catch(function(err){ overlayHide(); alert('Errore di rete: ' + err); });
  }

  /* ── FAQ: popola lista in Normal Mode ───────────────── */
  function applyFaqNormalMode(faqs, done){
    function countFaqRows(){
      var seen = {};
      document.querySelectorAll('input[name*="[faq]["],textarea[name*="[faq]["]').forEach(function(el){
        var m = el.name.match(/\[faq\]\[(\d+)\]/);
        if(m) seen[m[1]] = true;
      });
      return Object.keys(seen).length;
    }

    function findFaqAddBtn(){
      // Prova 1: partendo da un input già esistente, risali al container del list
      var anyInput = document.querySelector('input[name*="[faq]["],textarea[name*="[faq]["]');
      if(anyInput){
        var wrap = anyInput;
        while(wrap && wrap !== document.body){
          var btn = wrap.querySelector('[data-list-add],[data-grav-list-add],.list-add-button,a.button--green,button.button--small');
          if(btn) return btn;
          wrap = wrap.parentElement;
        }
      }
      // Prova 2: cerca il pulsante per testo ("+ Aggiungi domanda" in Grav)
      var allBtns = document.querySelectorAll('button');
      for(var i=0;i<allBtns.length;i++){
        var t = allBtns[i].textContent.toLowerCase();
        if(t.indexOf('aggiungi domanda')!==-1 || t.indexOf('aggiungi faq')!==-1 || t.indexOf('add item')!==-1){
          return allBtns[i];
        }
      }
      return null;
    }

    var needed  = faqs.length;
    var toAdd   = Math.max(0, needed - countFaqRows());

    function addAndFill(){
      if(toAdd > 0){
        var btn = findFaqAddBtn();
        if(btn){ btn.click(); toAdd--; setTimeout(addAndFill, 200); return; }
      }
      faqs.forEach(function(item,i){
        var qSet = setField('data[header][faq]['+i+'][question]', item.question||'');
        if(!qSet) setField('data[faq]['+i+'][question]', item.question||'');
        var aSet = setField('data[header][faq]['+i+'][answer]', item.answer||'');
        if(!aSet) setField('data[faq]['+i+'][answer]', item.answer||'');
      });
      if(done) done();
    }
    addAndFill();
  }

  /* ── AUTO-POPULATE "Nome file immagine" dopo upload ── */
  function initMediaAutoFill() {
    var isArticlePage = !!window.location.href.match(
      /\/admin\/pages\/(blog\/articoli|aziende\/blog)\/.+/
    );
    if (!isArticlePage) return;

    var mo = new MutationObserver(function(mutations) {
      mutations.forEach(function(m) {
        m.addedNodes.forEach(function(node) {
          if (node.nodeType !== 1) return;
          var imgs = (node.tagName === 'IMG') ? [node]
            : (node.querySelectorAll ? Array.from(node.querySelectorAll('img')) : []);
          imgs.forEach(function(img) {
            var alt = img.getAttribute('alt') || '';
            // Solo filename semplici con estensione immagine (niente path /foo/bar)
            if (!alt || alt.indexOf('/') !== -1 || !/\\.(png|jpg|jpeg|webp|gif)$/i.test(alt)) return;
            var field = document.querySelector(
              'input[name="data[header][featured_image]"], input[name="data[featured_image]"]'
            );
            if (!field) return;
            setField(field.name, alt);
            field.style.transition = 'box-shadow .3s';
            field.style.boxShadow = '0 0 0 3px #27ae60';
            setTimeout(function(){ field.style.boxShadow = ''; }, 2000);
          });
        });
      });
    });

    setTimeout(function(){
      var container = document.querySelector('form#blueprints')
        || document.querySelector('main form')
        || document.body;
      mo.observe(container, { childList: true, subtree: true });
    }, 800);
  }

  /* ── DOM READY ───────────────────────── */
  document.addEventListener('DOMContentLoaded', function(){
    /* Escludi admin dal tracking GA4 su tutto il sito */
    try{ localStorage.setItem('vr_no_track','1'); }catch(e){}

    var overlay = document.createElement('div');
    overlay.id = 'vb-rewrite-overlay';
    overlay.innerHTML = '<div class="vb-spinner"></div><p>Elaborazione in corso...</p>';
    document.body.appendChild(overlay);

    /* 0. Auto-populate campo "Nome file immagine" */
    initMediaAutoFill();

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

      var btnStat = document.createElement('a');
      btnStat.className='vb vb-stat'; btnStat.href='/ga4-stats.php'; btnStat.target='_blank';
      btnStat.innerHTML='<i class="fa fa-bar-chart"></i> Statistiche';

      bar.insertBefore(btnA, bar.firstChild);
      bar.insertBefore(btnP, bar.firstChild);
      bar.insertBefore(btnAI, bar.firstChild);
      bar.insertBefore(btnStat, bar.firstChild);
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
        var titBtn = document.createElement('button');
        titBtn.className = 'vb-ab-title';
        titBtn.innerHTML = '<i class="fa fa-pencil"></i> Titolo';
        titBtn.addEventListener('click', function(){ cambiaTitolo(currentSlug); });
        left.appendChild(titBtn);

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

      var bMeta = document.createElement('button');
      bMeta.className = 'vb-ab-meta';
      bMeta.innerHTML = '<i class="fa fa-tags"></i> Genera Metadati';
      bMeta.addEventListener('click', function(){ vbGenerateMeta(); });

      var bLinkedin = document.createElement('button');
      bLinkedin.className = 'vb-ab-linkedin';
      bLinkedin.innerHTML = '<i class="fa fa-linkedin"></i> LinkedIn';
      bLinkedin.addEventListener('click', function(){ vbLinkedIn(); });

      var bSocial = document.createElement('button');
      bSocial.className = 'vb-ab-social';
      bSocial.innerHTML = '<i class="fa fa-instagram"></i> Social';
      bSocial.addEventListener('click', function(){ vbSocial(); });

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
      right.appendChild(bLinkedin);
      right.appendChild(bSocial);
      right.appendChild(bRewrite);
      right.appendChild(bFull);
      right.appendChild(bMeta);
      right.appendChild(bElimina);

      bar.appendChild(left);
      bar.appendChild(right);
      document.body.appendChild(bar);
    }, 300);

  });

  /* ── SOCIAL GENERATOR — state ── */
  var _smSlides   = [];
  var _smCaption  = '';
  var _smHashtags = [];
  var _smFontsOk  = false;

  function vbEnsureFonts(cb) {
    if (_smFontsOk) { cb(); return; }
    if (!document.getElementById('vb-gf')) {
      var lk = document.createElement('link');
      lk.id = 'vb-gf'; lk.rel = 'stylesheet';
      lk.href = 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Outfit:wght@400;700;800&display=swap';
      document.head.appendChild(lk);
    }
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function(){ _smFontsOk = true; cb(); });
    } else {
      setTimeout(function(){ _smFontsOk = true; cb(); }, 1800);
    }
  }

  function smWrap(ctx, text, x, y, maxW, font, lhMul, color, align) {
    if (!text) return y;
    ctx.font = font; ctx.fillStyle = color; ctx.textAlign = align || 'left';
    var fsize = parseInt((font.match(/(\d+)px/) || ['0','34'])[1], 10);
    var lh = fsize * lhMul;
    var words = String(text).split(' ');
    var line = ''; var curY = y;
    for (var i = 0; i < words.length; i++) {
      var test = line + words[i] + ' ';
      if (ctx.measureText(test).width > maxW && i > 0) {
        ctx.fillText(line.trim(), x, curY); line = words[i] + ' '; curY += lh;
      } else { line = test; }
    }
    if (line.trim()) ctx.fillText(line.trim(), x, curY);
    return curY + lh;
  }

  function smRender(canvas, slide, idx) {
    var ctx = canvas.getContext('2d');
    var W = 1080, H = 1080;
    canvas.width = W; canvas.height = H;
    var isGrad = (slide.type === 'hook' || slide.type === 'cta');
    if (isGrad) {
      var g = ctx.createLinearGradient(0, 0, W, H);
      g.addColorStop(0, '#B68397'); g.addColorStop(1, '#1E3A5F');
      ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    } else {
      ctx.fillStyle = '#FAF5F8'; ctx.fillRect(0, 0, W, H);
      var strip = ctx.createLinearGradient(0, 0, W, 0);
      strip.addColorStop(0, '#B68397'); strip.addColorStop(1, '#1E3A5F');
      ctx.fillStyle = strip; ctx.fillRect(0, 0, W, 10);
      ctx.font = '800 28px Outfit'; ctx.fillStyle = 'rgba(30,58,95,0.22)';
      ctx.textAlign = 'left'; ctx.fillText(String(idx + 1).padStart(2,'0'), 80, 110);
      ctx.font = '400 25px Outfit'; ctx.fillStyle = 'rgba(30,58,95,0.35)';
      ctx.textAlign = 'right'; ctx.fillText('@valentinarussobg5', W - 80, H - 48);
    }
    var PAD = 80; var CX = PAD; var CW = W - PAD * 2;
    if (slide.type === 'hook') {
      smWrap(ctx, slide.title || '', W/2, 330, CW, '700 84px "Playfair Display"', 1.15, '#FFFFFF', 'center');
      smWrap(ctx, slide.subtitle || '', W/2, 560, CW, '400 35px Outfit', 1.5, 'rgba(255,255,255,0.82)', 'center');
      ctx.font = '800 27px Outfit'; ctx.fillStyle = 'rgba(255,255,255,0.6)';
      ctx.textAlign = 'center'; ctx.fillText('Scorri \u2192', W/2, H - 72);
    } else if (slide.type === 'cta') {
      smWrap(ctx, slide.ask || '', W/2, 350, CW, '700 70px "Playfair Display"', 1.15, '#FFFFFF', 'center');
      ctx.fillStyle = 'rgba(182,131,151,0.75)'; ctx.fillRect(W/2 - 60, 468, 120, 3);
      ctx.font = '400 31px Outfit'; ctx.fillStyle = 'rgba(255,255,255,0.78)';
      ctx.textAlign = 'center'; ctx.fillText(slide.instruction || '', W/2, 540);
      ctx.font = '800 27px Outfit'; ctx.fillStyle = 'rgba(255,255,255,0.42)';
      ctx.fillText('@valentinarussobg5', W/2, H - 72);
    } else if (slide.layout === 'quote') {
      ctx.fillStyle = '#B68397'; ctx.fillRect(CX, 155, 6, 220);
      smWrap(ctx, '\u201C' + (slide.quote || '') + '\u201D', CX + 28, 210, CW - 28, 'italic 700 64px "Playfair Display"', 1.25, '#2C2C2C', 'left');
    } else if (slide.layout === 'list') {
      smWrap(ctx, slide.headline || '', CX, 170, CW, '700 56px "Playfair Display"', 1.15, '#2C2C2C', 'left');
      var by = 340;
      (slide.bullets || []).slice(0,3).forEach(function(b) {
        ctx.fillStyle = '#B68397'; ctx.beginPath();
        ctx.arc(CX + 14, by - 7, 9, 0, Math.PI * 2); ctx.fill();
        ctx.font = '400 33px Outfit'; ctx.fillStyle = '#2C2C2C'; ctx.textAlign = 'left';
        ctx.fillText(String(b), CX + 40, by); by += 92;
      });
    } else {
      ctx.fillStyle = '#B68397'; ctx.fillRect(CX, 145, 6, 270);
      smWrap(ctx, slide.headline || '', CX + 24, 200, CW - 24, '700 56px "Playfair Display"', 1.15, '#2C2C2C', 'left');
      smWrap(ctx, slide.body || '', CX + 24, 450, CW - 24, '400 31px Outfit', 1.62, '#2C2C2C', 'left');
    }
  }

  function vbSocial() {
    var existing = document.getElementById('vb-social-modal');
    if (existing) { existing.classList.add('show'); return; }
    var modal = document.createElement('div');
    modal.id = 'vb-social-modal'; modal.className = 'show';
    modal.innerHTML =
      '<div class="vb-sm-card">' +
        '<div class="vb-sm-header">' +
          '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="url(#smi)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<defs><linearGradient id="smi" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#f09433"/><stop offset="50%" stop-color="#dc2743"/><stop offset="100%" stop-color="#bc1888"/></linearGradient></defs>' +
            '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>' +
            '<path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>' +
            '<line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>' +
          '</svg>' +
          '<h3>Social Media Generator</h3>' +
          '<button class="vb-sm-close" id="vb-sm-close-btn">\u00D7</button>' +
        '</div>' +
        '<div class="vb-sm-tabs">' +
          '<button class="active" data-tab="text"><i class="fa fa-pencil"></i> Testo libero</button>' +
          '<button data-tab="article"><i class="fa fa-file-text-o"></i> Da questo articolo</button>' +
        '</div>' +
        '<div class="vb-sm-panel" id="vb-sm-panel-text">' +
          '<textarea id="vb-sm-input" placeholder="Scrivi un\'idea, incolla un concetto BG5, descrivi un tema..."></textarea>' +
        '</div>' +
        '<div class="vb-sm-panel" id="vb-sm-panel-article" hidden>' +
          '<div class="vb-sm-article-info">' +
            '<strong id="vb-sm-art-title">Nessun articolo caricato</strong>' +
            '<span id="vb-sm-art-preview">Apri un articolo nell\'editor e clicca il bottone qui sotto.</span>' +
          '</div>' +
          '<button class="vb-sm-load-btn" id="vb-sm-load-art"><i class="fa fa-download"></i> Carica articolo corrente</button>' +
        '</div>' +
        '<div class="vb-sm-config">' +
          '<div class="vb-sm-config-row">' +
            '<label>Tono</label>' +
            '<button class="active" data-tone="professional">Professionale</button>' +
            '<button data-tone="warm">Caldo</button>' +
            '<button data-tone="direct">Diretto</button>' +
          '</div>' +
        '</div>' +
        '<div class="vb-sm-gen-wrap">' +
          '<button id="vb-sm-generate"><i class="fa fa-magic"></i> Genera Carosello (7 slide)</button>' +
        '</div>' +
        '<div id="vb-sm-preview" hidden>' +
          '<div class="vb-sm-preview-hdr"><h4><i class="fa fa-picture-o"></i> Anteprima &mdash; 7 slide 1080&times;1080px</h4></div>' +
          '<div class="vb-sm-slides-strip" id="vb-sm-slides-strip"></div>' +
          '<div class="vb-sm-cap-wrap">' +
            '<label>Caption</label>' +
            '<textarea id="vb-sm-caption"></textarea>' +
            '<label style="margin-top:10px;">Hashtag</label>' +
            '<div class="vb-sm-ht-row" id="vb-sm-ht-row"></div>' +
          '</div>' +
          '<div class="vb-sm-dl-row">' +
            '<button id="vb-sm-regen"><i class="fa fa-refresh"></i> Rigenera</button>' +
            '<button id="vb-sm-download"><i class="fa fa-download"></i> Scarica ZIP</button>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);

    var activeTone = 'professional';
    var activeTab  = 'text';
    var articleData = null;

    document.getElementById('vb-sm-close-btn').addEventListener('click', function(){ modal.classList.remove('show'); });
    modal.addEventListener('click', function(e){ if (e.target === modal) modal.classList.remove('show'); });

    modal.querySelectorAll('.vb-sm-tabs button').forEach(function(btn){
      btn.addEventListener('click', function(){
        modal.querySelectorAll('.vb-sm-tabs button').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active'); activeTab = btn.getAttribute('data-tab');
        document.getElementById('vb-sm-panel-text').hidden    = (activeTab !== 'text');
        document.getElementById('vb-sm-panel-article').hidden = (activeTab !== 'article');
      });
    });

    modal.querySelectorAll('[data-tone]').forEach(function(btn){
      btn.addEventListener('click', function(){
        modal.querySelectorAll('[data-tone]').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active'); activeTone = btn.getAttribute('data-tone');
      });
    });

    document.getElementById('vb-sm-load-art').addEventListener('click', function(){
      var cms = getCMs();
      var titleEl = document.querySelector('input[name="data[title]"]');
      var title   = (titleEl && titleEl.value.trim()) ? titleEl.value.trim() : 'Articolo';
      var body    = cms.body ? cms.body.getValue().trim() : '';
      if (!body) { alert('Editor corpo articolo non trovato. Apri il tab Contenuto prima.'); return; }
      articleData = { title: title, body: body };
      document.getElementById('vb-sm-art-title').textContent   = title;
      document.getElementById('vb-sm-art-preview').textContent = body.substring(0,200).replace(/[#*\[\]_]/g,'').trim() + (body.length > 200 ? '\u2026' : '');
    });

    document.getElementById('vb-sm-generate').addEventListener('click', function(){
      var btn = this; var inputText = '';
      if (activeTab === 'article') {
        if (!articleData) { alert('Clicca prima "Carica articolo corrente".'); return; }
        inputText = articleData.title + '\\n\\n' + articleData.body;
      } else {
        inputText = (document.getElementById('vb-sm-input').value || '').trim();
      }
      if (!inputText) { alert('Inserisci del testo prima di generare.'); return; }
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generazione in corso...';
      var fd = new FormData();
      fd.append('pass',       'ValeAdmin2026');
      fd.append('input_type', activeTab === 'article' ? 'article' : 'text');
      fd.append('input_text', inputText);
      fd.append('format',     'carousel');
      fd.append('tone',       activeTone);
      fetch('/social-generate.php', { method:'POST', body:fd, credentials:'include' })
        .then(function(r){ return r.json(); })
        .then(function(data){
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-magic"></i> Genera Carosello (7 slide)';
          if (!data.ok) { alert('Errore: ' + data.error); return; }
          _smSlides   = data.slides   || [];
          _smCaption  = data.caption  || '';
          _smHashtags = data.hashtags || [];
          renderPreview();
        })
        .catch(function(err){
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-magic"></i> Genera Carosello (7 slide)';
          alert('Errore di rete: ' + err);
        });
    });

    function renderPreview() {
      document.getElementById('vb-sm-preview').hidden = false;
      var strip = document.getElementById('vb-sm-slides-strip');
      strip.innerHTML = '';
      vbEnsureFonts(function(){
        _smSlides.forEach(function(slide, i){
          var wrap = document.createElement('div');
          wrap.className = 'vb-sm-slide-wrap' + (i === 0 ? ' active' : '');
          var cv = document.createElement('canvas');
          smRender(cv, slide, i); wrap.appendChild(cv); strip.appendChild(wrap);
          wrap.addEventListener('click', function(){
            strip.querySelectorAll('.vb-sm-slide-wrap').forEach(function(w){ w.classList.remove('active'); });
            wrap.classList.add('active');
          });
        });
      });
      var capEl = document.getElementById('vb-sm-caption');
      if (capEl) capEl.value = _smCaption;
      var htRow = document.getElementById('vb-sm-ht-row');
      if (htRow) {
        htRow.innerHTML = '';
        _smHashtags.forEach(function(ht){
          var span = document.createElement('span');
          span.className = 'vb-sm-ht'; span.textContent = '#' + ht;
          htRow.appendChild(span);
        });
      }
    }

    document.getElementById('vb-sm-regen').addEventListener('click', function(){
      document.getElementById('vb-sm-generate').click();
    });

    document.getElementById('vb-sm-download').addEventListener('click', function(){
      var btn = this;
      if (!_smSlides.length) { alert('Genera prima il carosello.'); return; }
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Preparazione ZIP...';
      vbEnsureFonts(function(){
        function doZip() {
          var zip = new JSZip();
          var promises = _smSlides.map(function(slide, i){
            return new Promise(function(resolve){
              var cv = document.createElement('canvas');
              smRender(cv, slide, i);
              cv.toBlob(function(blob){
                zip.file('slide-' + String(i+1).padStart(2,'0') + '.png', blob);
                resolve();
              }, 'image/png');
            });
          });
          var capEl  = document.getElementById('vb-sm-caption');
          var capTxt = (capEl ? capEl.value : _smCaption) + '\\n\\n' + _smHashtags.map(function(h){ return '#' + h; }).join(' ');
          zip.file('caption.txt', capTxt);
          Promise.all(promises).then(function(){
            return zip.generateAsync({ type:'blob' });
          }).then(function(blob){
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a'); a.href = url;
            a.download = 'carosello-instagram.zip'; a.click();
            setTimeout(function(){ URL.revokeObjectURL(url); }, 3000);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-download"></i> Scarica ZIP';
          }).catch(function(err){
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-download"></i> Scarica ZIP';
            alert('Errore ZIP: ' + err);
          });
        }
        if (typeof JSZip === 'undefined') {
          var s = document.createElement('script');
          s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
          s.onload  = doZip;
          s.onerror = function(){ btn.disabled=false; btn.innerHTML='<i class="fa fa-download"></i> Scarica ZIP'; alert('Errore caricamento JSZip.'); };
          document.head.appendChild(s);
        } else { doZip(); }
      });
    });
  }

})();
</script>
HTML;

        $this->grav->output = str_replace('</body>', $inject . "\n</body>", $this->grav->output);
    }
}
