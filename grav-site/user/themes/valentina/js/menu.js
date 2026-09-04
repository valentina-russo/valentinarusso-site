(function () {
  var apri = document.getElementById('apri-menu');
  var pannello = document.getElementById('pannello');
  if (!apri || !pannello) return;
  function chiudi() {
    document.body.classList.remove('menu-aperto');
    apri.setAttribute('aria-expanded', 'false');
  }
  apri.addEventListener('click', function () {
    var aperto = document.body.classList.toggle('menu-aperto');
    apri.setAttribute('aria-expanded', aperto ? 'true' : 'false');
    apri.setAttribute('aria-label', aperto ? 'Chiudi il menu' : 'Apri il menu');
  });
  pannello.addEventListener('click', function (ev) { if (ev.target.closest('a')) chiudi(); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') chiudi(); });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) chiudi(); });
})();
