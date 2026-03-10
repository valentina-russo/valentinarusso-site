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

  /* ── SALVA CON STATO PUBBLICAZIONE ───── */
  function saveWithState(publishedValue){
    // Trova la form di edit della pagina
    var form = document.querySelector('form[method="post"][action*="/admin/pages/"]');
    if(!form){ alert('Form non trovata.'); return; }

    // Toggle published: cerca radio input con name che contiene "published"
    var radios = form.querySelectorAll('input[type="radio"]');
    var found = false;
    radios.forEach(function(r){
      if(r.name && r.name.toLowerCase().indexOf('published') !== -1){
        r.checked = (r.value == publishedValue);
        found = true;
      }
    });

    // Se non ci sono radio, cerca un toggle hidden o select
    if(!found){
      var hidden = form.querySelector('input[name="data[published]"], input[name="published"]');
      if(hidden){
        hidden.value = publishedValue;
      } else {
        // Crea input hidden
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'data[published]';
        inp.value = publishedValue;
        form.appendChild(inp);
      }
    }

    // Clicca il pulsante Salva nativo di Grav
    var saveBtn = form.querySelector('button[data-task="save"], .button.save');
    if(saveBtn){ saveBtn.click(); }
    else {
      // Fallback: trigger submit
      var taskInp = form.querySelector('input[name="task"]');
      if(taskInp) taskInp.value = 'save';
      form.submit();
    }
  }

  /* ── DOM READY ───────────────────────── */
  document.addEventListener('DOMContentLoaded', function(){

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

      bar.insertBefore(btnA, bar.firstChild);
      bar.insertBefore(btnP, bar.firstChild);
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

      titlebar.appendChild(btnBozza);
      titlebar.appendChild(btnPub);
    }, 300);

  });
})();
</script>
HTML;

        $this->grav->output = str_replace('</body>', $inject . "\n</body>", $this->grav->output);
    }
}
