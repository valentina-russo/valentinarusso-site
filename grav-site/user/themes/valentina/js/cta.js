/* Barra fissa in basso con l'invito a calcolare la carta.
   Compare dopo il primo schermo, si ritira sul pie' di pagina o quando il
   calcolatore e' gia' in vista, e si puo' chiudere per la sessione. */
(function () {
  var chiusa = false;
  try { chiusa = sessionStorage.getItem('vr_cta_chiusa') === '1'; } catch (e) {}
  if (chiusa) return;

  var calcolatore = document.getElementById('carta-calculator');
  var suQuestaPagina = /calcolo-human-design\.html$/.test(location.pathname);
  var destinazione = calcolatore ? '#carta-calculator' : 'calcolo-human-design.html';

  var barra = document.createElement('div');
  barra.className = 'cta-fissa';
  barra.setAttribute('aria-hidden', 'true');
  barra.innerHTML =
    '<div class="wrap">' +
      '<p>Scopri il tuo Tipo Energetico, il Profilo e l\u2019Autorit\u00e0 in pochi secondi.</p>' +
      '<a class="btn btn-1" href="' + destinazione + '" tabindex="-1">Calcola la tua carta gratis</a>' +
      '<button type="button" class="cta-chiudi" tabindex="-1" aria-label="Chiudi questo invito">\u00d7</button>' +
    '</div>';
  document.body.appendChild(barra);

  var link = barra.querySelector('a');
  var chiudi = barra.querySelector('.cta-chiudi');
  var pie = document.querySelector('footer');
  var visibile = false;

  function mostra(v) {
    if (v === visibile) return;
    visibile = v;
    barra.classList.toggle('vista', v);
    barra.setAttribute('aria-hidden', v ? 'false' : 'true');
    link.tabIndex = v ? 0 : -1;
    chiudi.tabIndex = v ? 0 : -1;
    document.body.classList.toggle('con-cta', v);
  }

  function inVista(el) {
    if (!el) return false;
    var r = el.getBoundingClientRect();
    return r.top < window.innerHeight && r.bottom > 0;
  }

  function controlla() {
    var oltreIlPrimoSchermo = window.scrollY > window.innerHeight * 0.9;
    mostra(oltreIlPrimoSchermo && !inVista(pie) && !inVista(calcolatore));
  }

  chiudi.addEventListener('click', function () {
    mostra(false);
    barra.remove();
    document.body.classList.remove('con-cta');
    try { sessionStorage.setItem('vr_cta_chiusa', '1'); } catch (e) {}
  });

  if (!suQuestaPagina || calcolatore) {
    var attesa = false;
    window.addEventListener('scroll', function () {
      if (attesa) return;
      attesa = true;
      requestAnimationFrame(function () { attesa = false; controlla(); });
    }, { passive: true });
    window.addEventListener('resize', controlla);
    controlla();
  }
})();
