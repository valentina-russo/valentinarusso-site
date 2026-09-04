(function () {
  var b = document.getElementById('su');
  if (!b) return;
  var soglia = 600;
  function aggiorna() { b.hidden = window.scrollY < soglia; }
  window.addEventListener('scroll', aggiorna, { passive: true });
  aggiorna();
  b.addEventListener('click', function () {
    var dolce = !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
    window.scrollTo({ top: 0, behavior: dolce ? 'smooth' : 'auto' });
  });
})();
