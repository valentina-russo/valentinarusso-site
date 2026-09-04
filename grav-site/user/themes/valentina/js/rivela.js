/* Sezioni che compaiono scorrendo. Sta qui una volta sola: se un modello si
   dimentica il suo script, il contenuto resta comunque visibile. */
(function () {
  var els = [].slice.call(document.querySelectorAll('.rivela'));
  if (!els.length) return;
  var ridotto = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  if (!('IntersectionObserver' in window) || ridotto) {
    els.forEach(function (e) { e.classList.add('in-vista'); });
    return;
  }
  var io = new IntersectionObserver(function (voci) {
    voci.forEach(function (v) {
      if (v.isIntersecting) { v.target.classList.add('in-vista'); io.unobserve(v.target); }
    });
  }, { threshold: 0.12 });
  els.forEach(function (e) { io.observe(e); });
})();
