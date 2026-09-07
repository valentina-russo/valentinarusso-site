/* Sezioni che compaiono scorrendo. Sta qui una volta sola: se un modello si
   dimentica il suo script, il contenuto resta comunque visibile.

   Nei browser dentro le app (Instagram, Facebook) l'altezza della finestra a
   volte arriva a zero mentre la pagina si apre: l'osservatore non vede nulla
   entrare e il contenuto resta invisibile finche' non scorri. Per questo, oltre
   all'osservatore, ci sono tre reti di sicurezza: un controllo a mano sulle
   posizioni, uno al 'load' e al primo tocco, e un tempo massimo oltre il quale
   si mostra tutto comunque. */
(function () {
  var els = [].slice.call(document.querySelectorAll('.rivela'));
  if (!els.length) return;

  function mostra(e) {
    e.classList.add('in-vista');
  }

  function mostraTutti() {
    els.forEach(mostra);
  }

  var ridotto = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  if (!('IntersectionObserver' in window) || ridotto) {
    mostraTutti();
    return;
  }

  // Chi e' gia' dentro la finestra (o sopra) si mostra subito, senza aspettare
  // l'osservatore: e' il caso in cui i browser delle app si perdono.
  function controllaPosizioni() {
    var h = window.innerHeight || document.documentElement.clientHeight || 0;
    if (!h) return false;
    var visto = false;
    els.forEach(function (e) {
      if (e.classList.contains('in-vista')) return;
      var r = e.getBoundingClientRect();
      if (r.top < h * 0.95) { mostra(e); visto = true; }
    });
    return visto;
  }

  var io = new IntersectionObserver(function (voci) {
    voci.forEach(function (v) {
      if (v.isIntersecting) { mostra(v.target); io.unobserve(v.target); }
    });
  }, { threshold: 0, rootMargin: '0px 0px -8% 0px' });
  els.forEach(function (e) { io.observe(e); });

  controllaPosizioni();
  requestAnimationFrame(controllaPosizioni);
  window.addEventListener('load', controllaPosizioni);
  window.addEventListener('pageshow', controllaPosizioni);
  window.addEventListener('resize', controllaPosizioni, { passive: true });
  window.addEventListener('orientationchange', controllaPosizioni);
  window.addEventListener('scroll', controllaPosizioni, { passive: true, once: true });

  // Ultima rete: passati due secondi, nessun contenuto puo' restare nascosto.
  setTimeout(controllaPosizioni, 400);
  setTimeout(mostraTutti, 2000);
})();
