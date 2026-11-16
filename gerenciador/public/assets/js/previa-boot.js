// Ativa o modo prévia da página pública: nada é enviado, a definição vem do gerenciador.
(function () {
  window.PULSE_PREVIEW = true;
  window.PULSE_CONFIG = { apiBase: '', turnstileSiteKey: '' };
  var el = document.getElementById('pulse-definicao');
  try { window.PULSE_DEFINICAO = JSON.parse(el.textContent); } catch (e) { window.PULSE_DEFINICAO = null; }
})();
