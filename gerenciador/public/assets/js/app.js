// Comportamentos gerais do gerenciador (sem dependências além do Bootstrap).
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];

  window.Pulse = window.Pulse || {};
  Pulse.csrf = () => ($('meta[name="csrf-token"]') || {}).content || '';
  Pulse.base = () => ($('meta[name="base-url"]') || {}).content || '';
  Pulse.esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Toasts
  Pulse.toast = function (msg, tipo = 'sucesso') {
    const wrap = $('#toasts');
    if (!wrap || !window.bootstrap) { alert(msg); return; }
    const div = document.createElement('div');
    div.className = `toast align-items-center border-0 toast-${tipo}`;
    div.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
    div.innerHTML = `<div class="d-flex"><div class="toast-body">${Pulse.esc(msg)}</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>`;
    wrap.appendChild(div);
    const t = new bootstrap.Toast(div, { delay: tipo === 'erro' ? 8000 : 4000 });
    div.addEventListener('hidden.bs.toast', () => div.remove());
    t.show();
  };
  if (window.bootstrap) $$('#toasts .toast').forEach((t) => new bootstrap.Toast(t).show());

  // Menu lateral no celular
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-abrir-menu]')) document.body.classList.add('menu-aberto');
    if (e.target.closest('[data-fechar-menu]')) document.body.classList.remove('menu-aberto');
  });

  // Tema claro/escuro (preferência deste navegador)
  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-alternar-tema]')) return;
    const el = document.documentElement;
    const escuroAgora = el.getAttribute('data-theme') === 'dark' || (!el.getAttribute('data-theme') && matchMedia('(prefers-color-scheme: dark)').matches);
    const novo = escuroAgora ? 'light' : 'dark';
    el.setAttribute('data-theme', novo);
    if (novo === 'dark') el.style.removeProperty('--primary');
    try { localStorage.setItem('pulse-tema', novo); } catch (err) { /* sem armazenamento */ }
    document.dispatchEvent(new CustomEvent('pulse:tema'));
  });

  // Confirmação antes de enviar formulários: <form data-confirmar="Mensagem" data-titulo="..." data-perigo data-digitar="slug">
  Pulse.confirmar = function ({ titulo, mensagem, rotulo = 'Confirmar', perigo = false, digitar = '' }) {
    return new Promise((ok) => {
      const m = $('#modal-confirmar');
      if (!m || !window.bootstrap) { ok(window.confirm(mensagem)); return; }
      $('[data-titulo]', m).textContent = titulo || 'Confirmar';
      $('[data-mensagem]', m).textContent = mensagem || '';
      const extra = $('[data-extra]', m);
      extra.innerHTML = digitar ? `<label class="form-label mt-3 small">Digite <code>${Pulse.esc(digitar)}</code> para confirmar</label><input class="form-control" autocomplete="off">` : '';
      const btn = $('[data-confirmar]', m);
      btn.textContent = rotulo;
      btn.className = `btn ${perigo ? 'btn-perigo' : 'btn-primary'}`;
      const campo = $('input', extra);
      btn.disabled = !!digitar;
      if (campo) campo.addEventListener('input', () => { btn.disabled = campo.value.trim() !== digitar; });
      const modal = bootstrap.Modal.getOrCreateInstance(m);
      let decidido = false;
      btn.onclick = () => { decidido = true; ok(campo ? campo.value.trim() : true); modal.hide(); };
      m.addEventListener('hidden.bs.modal', () => { if (!decidido) ok(false); }, { once: true });
      modal.show();
      if (campo) setTimeout(() => campo.focus(), 300);
    });
  };
  document.addEventListener('submit', async (e) => {
    const f = e.target;
    if (!f.matches('form[data-confirmar]') || f.dataset.confirmado) return;
    e.preventDefault();
    const r = await Pulse.confirmar({ titulo: f.dataset.titulo, mensagem: f.dataset.confirmar, rotulo: f.dataset.rotulo, perigo: 'perigo' in f.dataset, digitar: f.dataset.digitar || '' });
    if (!r) return;
    if (typeof r === 'string') {
      let c = f.querySelector('input[name="confirmacao"]');
      if (!c) { c = document.createElement('input'); c.type = 'hidden'; c.name = 'confirmacao'; f.appendChild(c); }
      c.value = r;
    }
    f.dataset.confirmado = '1';
    f.submit();
  });

  // Copiar texto: <button data-copiar="#seletor"> ou data-copiar-texto="..."
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-copiar], [data-copiar-texto]');
    if (!b) return;
    const alvo = b.dataset.copiar ? $(b.dataset.copiar) : null;
    const texto = b.dataset.copiarTexto || (alvo ? (alvo.value ?? alvo.textContent) : '');
    try { await navigator.clipboard.writeText(texto); Pulse.toast('Copiado.'); }
    catch { if (alvo && alvo.select) { alvo.select(); document.execCommand('copy'); Pulse.toast('Copiado.'); } else Pulse.toast('Não foi possível copiar. Selecione e copie manualmente.', 'erro'); }
  });

  // Selects que enviam o formulário ao mudar
  document.addEventListener('change', (e) => {
    if (e.target.matches('[data-auto-enviar]')) e.target.form.submit();
  });

  // Listas editáveis (áreas, gestores, faixas): botão adiciona linha a partir de <template>
  document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add-linha]');
    if (add) {
      const tpl = $(add.dataset.addLinha);
      const corpo = $(add.dataset.destino);
      const idx = Date.now();
      corpo.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', 'n' + idx));
      const novo = corpo.lastElementChild.querySelector('input');
      if (novo) novo.focus();
    }
    const rem = e.target.closest('[data-remover-linha]');
    if (rem) rem.closest('[data-linha]').remove();
  });
})();
