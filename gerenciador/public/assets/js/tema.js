// Aplica o tema antes de desenhar a página (evita "piscar").
(function () {
  var el = document.documentElement;
  try {
    var pref = localStorage.getItem('pulse-tema');
    if (pref === 'light' || pref === 'dark') el.setAttribute('data-theme', pref);
  } catch (e) { /* armazenamento indisponível */ }
  var meta = document.querySelector('meta[name="cor-primaria"]');
  var escuro = el.getAttribute('data-theme') === 'dark' || (!el.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
  if (meta && /^#[0-9a-f]{6}$/i.test(meta.content) && !escuro) {
    var h = meta.content.slice(1);
    el.style.setProperty('--primary', [0, 2, 4].map(function (i) { return parseInt(h.substr(i, 2), 16); }).join(' '));
  }
})();
