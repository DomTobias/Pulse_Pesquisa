// Pulse — página pública do participante.
// As respostas ficam somente em memória (nada em localStorage/sessionStorage) até o envio ao Worker.
import { esc, urlSegura, uuid, maskPhone, fmtDataHora, SCALE_COLORS, icon, SVG } from './util.js';
import { validarPergunta, montarRespostas, vazio, MAX_TEXTO } from './validacao.js';
import { buscarDefinicao, enviarRespostas, ErroApi } from './api.js';

const app = document.getElementById('app');
const PREVIEW = !!window.PULSE_PREVIEW;
const ehToque = window.matchMedia('(pointer: coarse)').matches;

const S = {
  def: null,
  tela: 'carregando', // carregando | aviso | capa | fluxo | enviado
  aviso: null,
  passos: [],
  i: 0,
  resp: {},
  errs: {},
  area: '',
  gestor: '',
  ident: { nome: '', email: '' },
  anim: '',
  envioId: uuid(),
  enviando: false,
  erroEnvio: null, // { msg, acao: 'tentar' | 'recarregar' }
  token: null,
};

// ------------------------------------------------------------------ carga
function slugDaUrl() {
  const m = /\/formulario\/([a-z0-9-]+)\/?$/i.exec(location.pathname);
  if (m) return m[1].toLowerCase();
  return new URLSearchParams(location.search).get('f') || '';
}

async function iniciar() {
  if (PREVIEW) {
    if (window.PULSE_DEFINICAO) carregar(window.PULSE_DEFINICAO);
    window.addEventListener('message', (ev) => {
      if (ev.origin !== location.origin) return;
      const d = ev.data || {};
      if (d.tipo === 'pulse:definicao' && d.definicao) carregar(d.definicao, true);
    });
    if (!window.PULSE_DEFINICAO) { S.tela = 'carregando'; render(); }
    return;
  }
  const slug = slugDaUrl();
  if (!slug) return mostrarAviso('alert', 'Formulário não encontrado', 'Confira o link que você recebeu.');
  try {
    carregar(await buscarDefinicao(slug));
  } catch (e) {
    if (e instanceof ErroApi && e.status === 404) return mostrarAviso('alert', 'Formulário não encontrado', 'Confira o link que você recebeu ou fale com quem o enviou.');
    mostrarAviso('alert', 'Não foi possível abrir o formulário', e.message || 'Tente novamente em instantes.', true);
  }
}

function carregar(def, manterPosicao = false) {
  def.topicos = Array.isArray(def.topicos) ? def.topicos : [];
  def.areas = Array.isArray(def.areas) ? def.areas : [];
  def.gestores = Array.isArray(def.gestores) ? def.gestores : [];
  def.capa = def.capa && typeof def.capa === 'object' ? def.capa : { tipo: 'NENHUMA' };
  S.def = def;
  document.title = def.titulo || 'Pesquisa';
  if (/^#[0-9a-f]{6}$/i.test(def.cor || '')) document.documentElement.style.setProperty('--accent', def.cor);

  if (def.status === 'AGENDADO') {
    const quando = def.inicio_em ? fmtDataHora(def.inicio_em, def.fuso_horario) : '';
    return mostrarAviso('clock', 'Este formulário ainda não está aberto', quando ? `Ele abre em ${quando}. Volte nesse horário para responder.` : 'Volte mais tarde para responder.');
  }
  if (def.status === 'ENCERRADO') return mostrarAviso('lock', 'Este formulário foi encerrado', 'O período de respostas terminou. Obrigado pelo interesse.');
  if (def.status !== 'PUBLICADO') return mostrarAviso('lock', 'Este formulário não está disponível', 'Ele ainda não foi publicado ou foi retirado do ar.');

  S.passos = montarPassos(def);
  if (manterPosicao && S.tela === 'fluxo') {
    S.i = Math.min(S.i, S.passos.length - 1);
  } else {
    S.tela = 'capa'; S.i = 0;
  }
  render();
}

function mostrarAviso(ico, titulo, texto, recarregar = false) {
  S.tela = 'aviso';
  S.aviso = { ico, titulo, texto, recarregar };
  render();
}

// ------------------------------------------------------------------ passos
function montarPassos(def) {
  const passos = [];
  if (def.segmentado && def.areas.length) passos.push({ k: 'equipe' });
  if (def.exige_identificacao) passos.push({ k: 'ident' });
  const porTopico = def.formato === 'PAGINA_UNICA';
  def.topicos.forEach((t, ti) => {
    if (!t.perguntas.length) return;
    if (porTopico) passos.push({ k: 'topico', t, ti });
    else t.perguntas.forEach((p, pi) => passos.push({ k: 'perg', t, ti, p, pi }));
  });
  passos.push({ k: 'revisao' });
  return passos;
}

const topicosComPerguntas = () => S.def.topicos.filter((t) => t.perguntas.length);
const perguntasValidas = (t) => t.perguntas.filter((p) => p.tipo !== 'INFO');
const respondidas = (t) => perguntasValidas(t).filter((p) => !vazio(S.resp[p.id])).length;
const totalPerguntas = () => S.def.topicos.reduce((n, t) => n + perguntasValidas(t).length, 0);

// ------------------------------------------------------------------ render
function render() {
  const fn = { carregando: telaCarregando, aviso: telaAviso, capa: telaCapa, fluxo: telaFluxo, enviado: telaEnviado }[S.tela];
  app.innerHTML = fn();
  S.anim = '';
  posRender();
}

const telaCarregando = () => `<div class="pl-carregando"><div class="spinner-border" role="status"><span class="visually-hidden">Carregando…</span></div></div>`;

function telaAviso() {
  const a = S.aviso;
  return `<div class="notice-wrap"><div class="notice-card">
    <div class="ico">${icon(a.ico, 26)}</div>
    <h1>${esc(a.titulo)}</h1>
    <p>${esc(a.texto)}</p>
    ${a.recarregar ? `<button type="button" class="btn-next mx-auto mt-3" data-act="recarregar">${icon('rotate', 16)} Tentar novamente</button>` : ''}
  </div></div>`;
}

function infoTempo() {
  const min = Math.max(1, Math.round(totalPerguntas() * 0.3));
  const anon = S.def.exige_identificacao ? 'identificado' : 'anônimo';
  return `${icon('clock', 14)} Leva cerca de ${min} ${min === 1 ? 'minuto' : 'minutos'} · ${anon}`;
}

function telaCapa() {
  const d = S.def, c = d.capa;
  if (c.tipo === 'INSTITUCIONAL') {
    const foto = urlSegura(c.foto), logo = urlSegura(c.simbolo);
    return `<section class="cover">
      <div class="cover-top">
        ${SVG.waves('cover-waves')}
        <div class="cover-grid">
          <div class="cover-brand">
            ${logo ? `<img class="cover-logo" src="${esc(logo)}" alt="">` : ''}
            ${SVG.heart('cover-heart')}
            <div class="cover-headline display">${esc(c.titulo || d.titulo)}</div>
            ${c.subtitulo ? `<div class="cover-subhead script">${esc(c.subtitulo)}</div>${SVG.swash('cover-swash')}` : ''}
          </div>
          <div class="cover-text">
            ${c.chamada ? `<div class="cover-tagline">${esc(c.chamada)}</div>` : ''}
            ${c.destaque_titulo || c.destaque_texto ? `<div class="cover-box">${c.destaque_titulo ? `<b>${esc(c.destaque_titulo)}</b>` : ''}${c.destaque_texto ? `<p>${esc(c.destaque_texto)}</p>` : ''}</div>` : ''}
            ${!c.destaque_texto && d.mensagem_apresentacao ? `<div class="cover-box"><p>${esc(d.mensagem_apresentacao)}</p></div>` : ''}
            ${c.convite ? `<div class="mt-3 fw-semibold fs-5">${esc(c.convite)}</div>` : ''}
            <button type="button" class="cover-cta" data-act="comecar">Começar ${icon('right', 18)}</button>
            <div class="cover-meta">${infoTempo()}</div>
          </div>
        </div>
      </div>
      <div class="cover-photo">
        ${foto ? `<img src="${esc(foto)}" alt="">` : '<div class="photo-fallback"></div>'}
        <div class="fade-top"></div>
        ${SVG.waveBr('wave-br')}
        ${c.encerramento ? `<div class="cover-closing script">${esc(c.encerramento)}</div>` : ''}
      </div>
    </section>`;
  }
  const img = urlSegura(c.imagem);
  const faixa = c.tipo === 'IMAGEM' && img
    ? `<div class="simple-band"><img src="${esc(img)}" alt=""></div>`
    : c.tipo === 'FAIXA' ? '<div class="simple-band"></div>' : '<div class="simple-band none"></div>';
  return `<section class="simple-cover">${faixa}
    <div class="simple-body"><div class="simple-card">
      <h1>${esc(d.titulo)}</h1>
      ${d.descricao ? `<p class="pl-muted fs-5 mt-2" style="white-space:pre-line">${esc(d.descricao)}</p>` : ''}
      ${d.mensagem_apresentacao ? `<p class="mt-3" style="white-space:pre-line">${esc(d.mensagem_apresentacao)}</p>` : ''}
      ${c.convite ? `<p class="mt-3 fw-semibold">${esc(c.convite)}</p>` : ''}
      <button type="button" class="btn-next mt-3" data-act="comecar">Começar ${icon('right', 18)}</button>
      <div class="cover-meta pl-muted">${infoTempo()}</div>
    </div></div></section>`;
}

// ---- fluxo (perguntas, equipe, identificação, revisão)
function telaFluxo() {
  const d = S.def, c = d.capa, passo = S.passos[S.i];
  const topicos = topicosComPerguntas();
  const tiAtual = passo.k === 'perg' || passo.k === 'topico' ? passo.ti : (passo.k === 'revisao' ? -2 : -1);
  const titulo = c.tipo === 'INSTITUCIONAL' ? (c.titulo || d.titulo) : d.titulo;
  const subtitulo = c.tipo === 'INSTITUCIONAL' ? (c.subtitulo || '') : '';
  const foto = urlSegura(c.foto || (c.tipo === 'IMAGEM' ? c.imagem : ''));

  const itens = topicos.map((t, n) => {
    const ti = d.topicos.indexOf(t);
    const total = perguntasValidas(t).length, feitas = respondidas(t);
    const cls = ti === tiAtual ? 'cur' : (tiAtual === -2 || ti < tiAtual ? 'done' : 'todo');
    return `<li class="aside-item ${cls}"><span class="aside-num">${cls === 'done' ? icon('check', 14, 3) : n + 1}</span>
      <span class="aside-label">${esc(t.titulo)}</span>${total ? `<span class="aside-count tnum">${feitas}/${total}</span>` : ''}</li>`;
  }).join('');

  const progresso = Math.round((S.i / Math.max(1, S.passos.length - 1)) * 100);
  const barras = topicos.map((t) => {
    const ti = d.topicos.indexOf(t);
    const total = perguntasValidas(t).length || 1;
    let w = 0;
    if (tiAtual === -2 || ti < tiAtual) w = 100;
    else if (ti === tiAtual) w = passo.k === 'perg' ? Math.round(((passo.pi + 1) / t.perguntas.length) * 100) : Math.round((respondidas(t) / total) * 100);
    return `<div><span style="width:${w}%"></span></div>`;
  }).join('');

  const { meta, corpo, scroll } = conteudoPasso(passo);
  return `<div class="flow">
    <aside class="flow-aside" aria-label="Etapas">
      <div class="aside-brand">
        <div class="h">${esc(titulo)}</div>
        ${subtitulo ? `<div class="s script">${esc(subtitulo)}</div>` : ''}
        ${c.chamada ? `<div class="t">${esc(c.chamada)}</div>` : ''}
      </div>
      <ol class="aside-list">${itens}</ol>
      <div class="aside-photo">
        ${foto ? `<img class="photo-fill" src="${esc(foto)}" alt="">` : ''}
        <div class="shade"></div>
        <div class="note">${icon('lock', 14)} ${d.exige_identificacao ? 'Suas respostas são tratadas com confidencialidade.' : 'Suas respostas são anônimas.'}</div>
      </div>
    </aside>
    <main class="flow-main">
      <div class="mob-head">
        <div class="row1"><span class="h">${esc(titulo)}</span>${subtitulo ? `<span class="s script">${esc(subtitulo)}</span>` : ''}<span class="pos tnum">${meta.pos}</span></div>
        <div class="mob-bars">${barras}</div>
      </div>
      <div class="flow-progress"><div style="width:${progresso}%"></div></div>
      <div class="flow-meta"><span>${esc(meta.esq)}</span><span class="tnum">${meta.pos}</span></div>
      <div class="flow-body${scroll ? ' scroll' : ''}" id="corpo">
        <div class="flow-inner ${S.anim}" id="passo">${corpo}</div>
      </div>
      <div class="flow-foot">
        <button type="button" class="btn-back" data-act="voltar">${icon('left', 18)} Voltar</button>
        <span class="hint">${dicaTeclado(passo)}</span>
        ${botaoAvancar(passo)}
      </div>
    </main>
  </div>`;
}

function conteudoPasso(passo) {
  const d = S.def;
  const nPerg = S.passos.filter((p) => p.k === 'perg' || p.k === 'topico');
  const pos = nPerg.indexOf(passo);
  const metaPos = pos >= 0 ? `${pos + 1} de ${nPerg.length}` : '';
  switch (passo.k) {
    case 'equipe': return { meta: { esq: 'Antes de começar', pos: '' }, corpo: passoEquipe() };
    case 'ident': return { meta: { esq: 'Identificação', pos: '' }, corpo: passoIdent() };
    case 'revisao': return { meta: { esq: 'Revisão', pos: '' }, corpo: passoRevisao(), scroll: true };
    case 'topico': return {
      meta: { esq: passo.t.titulo, pos: metaPos },
      corpo: `<div class="q-topic">${esc(passo.t.titulo)} <span class="pos">· tópico ${passo.ti + 1} de ${d.topicos.length}</span></div>
        ${passo.t.descricao ? `<p class="q-help mt-0">${esc(passo.t.descricao)}</p>` : ''}
        ${passo.t.perguntas.map((p) => `<div class="mb-5" data-bloco="${esc(p.id)}">${blocoPergunta(p, true)}</div>`).join('')}`,
      scroll: true,
    };
    default: {
      const p = passo.p;
      return {
        meta: { esq: passo.t.titulo, pos: metaPos },
        corpo: `<div class="q-topic">${esc(passo.t.titulo)} <span class="pos">· ${passo.pi + 1} de ${passo.t.perguntas.length}</span></div>${blocoPergunta(p, false)}`,
        scroll: p.tipo === 'MULTIPLA' || p.tipo === 'UNICA' ? p.opcoes.length > 6 : false,
      };
    }
  }
}

function blocoPergunta(p, compacto) {
  const tag = compacto ? 'h3' : 'h1';
  const titulo = `<${tag} class="q-title"${compacto ? ' style="font-size:1.375rem"' : ''} id="t-${esc(p.id)}">${esc(p.titulo)}${p.obrigatoria && p.tipo !== 'INFO' ? '<span class="req" aria-hidden="true">*</span>' : ''}</${tag}>`;
  const ajuda = p.orientacao && p.tipo !== 'INFO' ? `<p class="q-help">${esc(p.orientacao)}</p>` : '';
  const erro = S.errs[p.id] ? `<div class="pl-error" role="alert">${icon('alert', 16)} ${esc(S.errs[p.id])}</div>` : '';
  return `${titulo}${ajuda}<div class="q-control"${compacto ? ' style="margin-top:1.25rem"' : ''}>${controle(p)}</div>${erro}`;
}

function controle(p) {
  const v = S.resp[p.id];
  const id = esc(p.id);
  const aria = `aria-labelledby="t-${id}"`;
  const cls = `pl-input${S.errs[p.id] ? ' err' : ''}`;
  switch (p.tipo) {
    case 'CURTA': return `<input class="${cls}" data-pid="${id}" ${aria} maxlength="${MAX_TEXTO}" value="${esc(v)}" placeholder="Digite sua resposta">`;
    case 'LONGA': return `<textarea class="${cls}" data-pid="${id}" ${aria} rows="5" maxlength="${MAX_TEXTO}" placeholder="Escreva aqui…">${esc(v)}</textarea>
      <div class="text-end small pl-muted mt-1 tnum" data-cont="${id}">${String(v || '').length}/${MAX_TEXTO}</div>`;
    case 'NUMERO': return `<input class="${cls}" data-pid="${id}" ${aria} inputmode="decimal" value="${esc(v)}" placeholder="0" style="max-width:16rem">`;
    case 'EMAIL': return `<input class="${cls}" data-pid="${id}" ${aria} type="email" inputmode="email" autocomplete="email" value="${esc(v)}" placeholder="nome@empresa.com.br">`;
    case 'TELEFONE': return `<input class="${cls}" data-pid="${id}" ${aria} type="tel" inputmode="tel" autocomplete="tel" value="${esc(v)}" placeholder="(00) 00000-0000" style="max-width:20rem">`;
    case 'DATA': return `<input class="${cls}" data-pid="${id}" ${aria} type="date" value="${esc(v)}" style="max-width:16rem">`;
    case 'LISTA': return `<select class="${cls}" data-pid="${id}" ${aria}><option value="">Selecione…</option>${p.opcoes.map((o) => `<option value="${esc(o.id)}"${v === o.id ? ' selected' : ''}>${esc(o.rotulo)}</option>`).join('')}</select>`;
    case 'UNICA': return `<div class="opt-list" role="radiogroup" ${aria}>${p.opcoes.map((o, n) => `
      <label class="opt${v === o.id ? ' sel' : ''}"><input type="radio" name="r-${id}" data-pid="${id}" value="${esc(o.id)}"${v === o.id ? ' checked' : ''}>
      <span class="flex-grow-1">${esc(o.rotulo)}</span>${n < 9 ? `<kbd class="d-none d-md-inline small bg-light text-secondary border">${n + 1}</kbd>` : ''}</label>`).join('')}</div>`;
    case 'MULTIPLA': {
      const sel = Array.isArray(v) ? v : [];
      return `<div class="opt-list" ${aria}>${p.opcoes.map((o) => `
        <label class="opt${sel.includes(o.id) ? ' sel' : ''}"><input type="checkbox" data-pid="${id}" value="${esc(o.id)}"${sel.includes(o.id) ? ' checked' : ''}>
        <span>${esc(o.rotulo)}</span></label>`).join('')}</div>
        <div class="small pl-muted mt-2">Você pode marcar mais de uma opção.</div>`;
    }
    case 'SIM_NAO': return `<div class="yn" role="radiogroup" ${aria}>${[['SIM', 'Sim', 'up'], ['NAO', 'Não', 'down']].map(([val, rot, ic]) => `
      <label class="opt${v === val ? ' sel' : ''}"><input type="radio" class="visually-hidden" name="r-${id}" data-pid="${id}" value="${val}"${v === val ? ' checked' : ''}>${icon(ic, 18)} ${rot}</label>`).join('')}</div>`;
    case 'ESCALA': return escala(p, v);
    case 'INFO': return `<div class="info-box">${icon('info', 20)}<div>${esc(p.orientacao || '')}</div></div>`;
    default: return '';
  }
}

function limitesEscala(p) {
  const min = Number.isFinite(Number(p.minimo)) && p.minimo !== null ? Math.round(Number(p.minimo)) : 0;
  const max = Number.isFinite(Number(p.maximo)) && p.maximo !== null ? Math.round(Number(p.maximo)) : 10;
  return max > min ? [min, max] : [0, 10];
}

function escala(p, v) {
  const [min, max] = limitesEscala(p);
  const zonas = p.aparencia === 'ZONAS';
  const botoes = [];
  for (let n = min; n <= max; n++) {
    const sel = v === n;
    let estilo = '', classe = 'scale-btn';
    if (zonas) {
      const cor = SCALE_COLORS[Math.round(((n - min) / (max - min)) * 10)];
      estilo = `background:${cor};color:#fff;`;
      if (v !== undefined && v !== null && !sel) classe += ' dim';
    } else classe += ' neutral';
    if (sel) classe += ' sel';
    botoes.push(`<button type="button" class="${classe}" style="${estilo}" data-act="escala" data-pid="${esc(p.id)}" data-v="${n}" role="radio" aria-checked="${sel}" aria-label="Nota ${n}">${n}</button>`);
  }
  const escolhido = v !== undefined && v !== null ? `Você escolheu <b>${v}</b>.` : '';
  return `${p.rotulo_minimo || p.rotulo_maximo ? `<div class="scale-labels"><span>${esc(p.rotulo_minimo)}</span><span class="text-end">${esc(p.rotulo_maximo)}</span></div>` : ''}
    <div class="scale-grid" role="radiogroup" aria-labelledby="t-${esc(p.id)}" style="grid-template-columns:repeat(${max - min + 1},minmax(0,1fr))">${botoes.join('')}</div>
    <div class="scale-chosen">${escolhido}</div>`;
}

function passoEquipe() {
  const d = S.def;
  const gestores = d.gestores.filter((g) => !g.area_id || !S.area || g.area_id === S.area);
  const e = S.errs;
  return `<div class="q-topic">${icon('users', 16)} Sua equipe</div>
    <h1 class="q-title">Para começar, conte em qual equipe você trabalha</h1>
    <p class="q-help">Seu nome não é registrado. Usamos a equipe só para agrupar os resultados.</p>
    <div class="q-control row g-3">
      <div class="col-md-6"><label class="pl-label" for="f-area">Área <span class="req">*</span></label>
        <select id="f-area" class="pl-input${e.area ? ' err' : ''}" data-campo="area"><option value="">Selecione…</option>
        ${d.areas.map((a) => `<option value="${esc(a.id)}"${S.area === a.id ? ' selected' : ''}>${esc(a.nome)}</option>`).join('')}</select></div>
      ${d.gestores.length ? `<div class="col-md-6"><label class="pl-label" for="f-gestor">Gestor <span class="req">*</span></label>
        <select id="f-gestor" class="pl-input${e.gestor ? ' err' : ''}" data-campo="gestor"><option value="">Selecione…</option>
        ${gestores.map((g) => `<option value="${esc(g.id)}"${S.gestor === g.id ? ' selected' : ''}>${esc(g.nome)}</option>`).join('')}</select></div>` : ''}
    </div>
    ${e.area || e.gestor ? `<div class="pl-error" role="alert">${icon('alert', 16)} ${esc(e.area || e.gestor)}</div>` : ''}`;
}

function passoIdent() {
  const e = S.errs;
  return `<div class="q-topic">Identificação</div>
    <h1 class="q-title">Quem está respondendo?</h1>
    <p class="q-help">Este formulário pede identificação. Seus dados são usados apenas por quem administra a pesquisa.</p>
    <div class="q-control row g-3">
      <div class="col-md-6"><label class="pl-label" for="f-nome">Nome <span class="req">*</span></label>
        <input id="f-nome" class="pl-input${e.nome ? ' err' : ''}" data-campo="nome" autocomplete="name" maxlength="120" value="${esc(S.ident.nome)}"></div>
      <div class="col-md-6"><label class="pl-label" for="f-email">E-mail <span class="req">*</span></label>
        <input id="f-email" type="email" inputmode="email" class="pl-input${e.email ? ' err' : ''}" data-campo="email" autocomplete="email" maxlength="160" value="${esc(S.ident.email)}"></div>
    </div>
    ${e.nome || e.email ? `<div class="pl-error" role="alert">${icon('alert', 16)} ${esc(e.nome || e.email)}</div>` : ''}`;
}

function passoRevisao() {
  const d = S.def;
  const itens = topicosComPerguntas().map((t) => {
    const total = perguntasValidas(t).length, feitas = respondidas(t);
    const completo = feitas === total;
    const alvo = S.passos.findIndex((p) => (p.k === 'perg' || p.k === 'topico') && p.t === t);
    return `<li><button type="button" class="review-item" data-act="ir" data-i="${alvo}">
      <span class="review-dot" style="background:${completo ? 'var(--pl-ok)' : '#EE8B2A'}">${icon(completo ? 'check' : 'edit', 14, 3)}</span>
      <span class="flex-grow-1"><span class="n">${esc(t.titulo)}</span><span class="c">${total ? `${feitas} de ${total} respondidas` : 'Somente leitura'}</span></span>
      ${icon('right', 16)}</button></li>`;
  }).join('');
  const area = d.areas.find((a) => a.id === S.area);
  const gestor = d.gestores.find((g) => g.id === S.gestor);
  const er = S.erroEnvio;
  return `<div class="q-topic">${icon('send', 16)} Revisão</div>
    <h1 class="q-title">Tudo pronto para enviar</h1>
    <p class="q-help">Confira os tópicos abaixo. Toque em um deles para revisar suas respostas antes de enviar.</p>
    ${area ? `<p class="small pl-muted mt-2 mb-0">Equipe: <b>${esc(area.nome)}</b>${gestor ? ` · Gestor: <b>${esc(gestor.nome)}</b>` : ''}</p>` : ''}
    <ul class="review-list">${itens}</ul>
    ${PREVIEW ? '' : '<div class="turnstile-box" id="ts"></div>'}
    ${er ? `<div class="pl-error" role="alert">${icon('alert', 16)} <span>${esc(er.msg)}</span>
      ${er.acao === 'recarregar' ? '<button type="button" class="btn btn-link" data-act="recarregar">Recarregar</button>' : ''}</div>` : ''}`;
}

function rotuloAvancar(passo) {
  if (passo.k === 'revisao') return S.enviando ? 'Enviando…' : (S.erroEnvio && S.erroEnvio.acao === 'tentar' ? 'Tentar novamente' : 'Enviar respostas');
  const ultimo = S.passos[S.i + 1] && S.passos[S.i + 1].k === 'revisao';
  if (passo.k === 'perg' && passo.p.tipo !== 'INFO' && !passo.p.obrigatoria && vazio(S.resp[passo.p.id])) return 'Pular';
  return ultimo ? 'Concluir' : 'Próximo';
}

function botaoAvancar(passo) {
  const rot = rotuloAvancar(passo);
  const ico = passo.k === 'revisao' ? icon('send', 16) : (rot === 'Concluir' ? icon('check', 18) : icon('right', 18));
  return `<button type="button" class="btn-next${rot === 'Pular' ? ' skip' : ''}" data-act="avancar" id="btn-avancar"${S.enviando ? ' disabled' : ''}>${rot} ${ico}</button>`;
}

function dicaTeclado(passo) {
  if (passo.k === 'revisao') return 'Pressione <kbd>Ctrl</kbd> + <kbd>Enter</kbd> para enviar';
  if (passo.k === 'perg' && passo.p.tipo === 'LONGA') return '<kbd>Ctrl</kbd> + <kbd>Enter</kbd> para continuar · <kbd>Enter</kbd> quebra linha';
  if (passo.k === 'perg' && passo.p.tipo === 'ESCALA') return 'Use os números do teclado · <kbd>Enter</kbd> para continuar · <kbd>←</kbd> volta';
  return 'Pressione <kbd>Enter</kbd> para continuar · <kbd>←</kbd> volta';
}

function telaEnviado() {
  const d = S.def;
  return `<div class="notice-wrap"><div class="notice-card done ${S.anim}">
    <div class="done-icon">${icon('check', 36, 3)}</div>
    <h2 class="display">Respostas enviadas</h2>
    <p>${esc(d.mensagem_conclusao || 'Obrigado por participar.')}</p>
    ${d.capa && d.capa.encerramento ? `<div class="script">${esc(d.capa.encerramento)}</div>` : ''}
    ${d.permite_multiplas ? `<button type="button" class="btn-back mx-auto mt-4" data-act="outra">${icon('rotate', 16)} Enviar outra resposta</button>` : ''}
    ${PREVIEW ? `<div><span class="preview-tag">${icon('eye', 14)} Prévia: nenhuma resposta foi enviada</span></div>` : ''}
  </div></div>`;
}

// ------------------------------------------------------------------ pós-render
function posRender() {
  if (S.tela !== 'fluxo') return;
  const passo = S.passos[S.i];
  if (passo.k === 'revisao' && !PREVIEW) montarTurnstile();
  if (!ehToque) {
    const alvo = app.querySelector('#passo input:not([type=radio]):not([type=checkbox]), #passo textarea, #passo select');
    if (alvo && S.focar !== false) alvo.focus({ preventScroll: true });
  }
  S.focar = true;
}

// ------------------------------------------------------------------ navegação
function irPara(i, dir) {
  S.i = Math.max(0, Math.min(i, S.passos.length - 1));
  S.anim = dir === 'back' ? 'q-in-back' : 'q-in-next';
  S.errs = {};
  S.erroEnvio = null;
  render();
  document.getElementById('corpo')?.scrollTo(0, 0);
}

function sacudir() {
  const el = document.getElementById('passo');
  if (!el) return;
  el.classList.remove('shake', 'q-in-next', 'q-in-back');
  void el.offsetWidth;
  el.classList.add('shake');
}

function validarPasso(passo) {
  const errs = {};
  if (passo.k === 'equipe') {
    if (!S.area) errs.area = 'Selecione a sua área.';
    else if (S.def.gestores.length && !S.gestor) errs.gestor = 'Selecione o seu gestor.';
  } else if (passo.k === 'ident') {
    if (!S.ident.nome.trim()) errs.nome = 'Informe o seu nome.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(S.ident.email.trim())) errs.email = 'Informe um e-mail válido.';
  } else if (passo.k === 'perg') {
    const m = validarPergunta(passo.p, S.resp[passo.p.id]);
    if (m) errs[passo.p.id] = m;
  } else if (passo.k === 'topico') {
    passo.t.perguntas.forEach((p) => { const m = validarPergunta(p, S.resp[p.id]); if (m) errs[p.id] = m; });
  }
  return errs;
}

function avancar() {
  const passo = S.passos[S.i];
  if (passo.k === 'revisao') return enviar();
  const errs = validarPasso(passo);
  if (Object.keys(errs).length) {
    S.errs = errs; S.focar = false;
    render(); sacudir();
    const primeiro = Object.keys(errs)[0];
    app.querySelector(`[data-bloco="${CSS.escape(primeiro)}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    return;
  }
  irPara(S.i + 1, 'next');
}

function voltar() {
  if (S.tela !== 'fluxo') return;
  if (S.i === 0) { S.tela = 'capa'; render(); return; }
  irPara(S.i - 1, 'back');
}

// ------------------------------------------------------------------ Turnstile
let widgetId = null;
let aguardandoToken = null;

function esperarTurnstile(ms = 10000) {
  return new Promise((ok, falha) => {
    const t0 = Date.now();
    (function checar() {
      if (window.turnstile) return ok(window.turnstile);
      if (Date.now() - t0 > ms) return falha(new Error('turnstile'));
      setTimeout(checar, 150);
    })();
  });
}

async function montarTurnstile() {
  const el = document.getElementById('ts');
  if (!el) return;
  try {
    const ts = await esperarTurnstile();
    if (widgetId !== null) { try { ts.remove(widgetId); } catch { /* já removido */ } widgetId = null; }
    if (!document.body.contains(el)) return;
    S.token = null;
    widgetId = ts.render(el, {
      sitekey: (window.PULSE_CONFIG || {}).turnstileSiteKey,
      execution: 'execute',
      appearance: 'interaction-only',
      language: 'pt-br',
      callback: (tok) => { S.token = tok; if (aguardandoToken) { aguardandoToken.ok(tok); aguardandoToken = null; } },
      'error-callback': () => { if (aguardandoToken) { aguardandoToken.falha(new Error('turnstile')); aguardandoToken = null; } return true; },
      'expired-callback': () => { S.token = null; },
    });
  } catch { /* tratado ao enviar */ }
}

function obterToken() {
  if (S.token) return Promise.resolve(S.token);
  return new Promise((ok, falha) => {
    if (!window.turnstile || widgetId === null) return falha(new Error('turnstile'));
    aguardandoToken = { ok, falha };
    try { window.turnstile.execute(widgetId); } catch (e) { aguardandoToken = null; falha(e); }
    setTimeout(() => { if (aguardandoToken) { aguardandoToken = null; falha(new Error('turnstile')); } }, 60000);
  });
}

// ------------------------------------------------------------------ envio
async function enviar() {
  if (S.enviando) return;
  if (PREVIEW) { S.tela = 'enviado'; S.anim = 'q-in-next'; render(); return; }
  S.enviando = true; S.erroEnvio = null;
  atualizarBotao();
  const d = S.def;
  try {
    let token;
    try { token = await obterToken(); } catch {
      throw new ErroApi('Não foi possível confirmar que você não é um robô. Tente novamente.', 403, { code: 'TURNSTILE' });
    }
    const corpo = {
      envio_id: S.envioId, // reaproveitado em novas tentativas: o servidor ignora duplicados
      formulario_id: d.id,
      versao: d.versao,
      area_id: d.segmentado ? (S.area || null) : null,
      gestor_id: d.segmentado ? (S.gestor || null) : null,
      respostas: montarRespostas(d, S.resp),
      turnstile_token: token,
    };
    if (d.exige_identificacao) corpo.identificacao = { nome: S.ident.nome.trim(), email: S.ident.email.trim() };
    await enviarRespostas(corpo);
    S.enviando = false;
    S.resp = {}; S.ident = { nome: '', email: '' };
    S.tela = 'enviado'; S.anim = 'q-in-next';
    render();
  } catch (e) {
    S.enviando = false;
    S.erroEnvio = interpretarErro(e);
    if (S.erroEnvio.aviso) return carregarAviso(S.erroEnvio);
    S.focar = false;
    render(); sacudir();
  } finally {
    S.token = null;
    if (widgetId !== null && window.turnstile && S.tela !== 'fluxo') { try { window.turnstile.remove(widgetId); } catch { /* */ } widgetId = null; }
  }
}

function interpretarErro(e) {
  const st = e instanceof ErroApi ? e.status : 0;
  const code = e instanceof ErroApi ? (e.dados || {}).code : '';
  if (st === 409 && code === 'VERSAO_DESATUALIZADA') return { msg: 'Este formulário foi atualizado enquanto você respondia. Recarregue a página para ver a versão atual.', acao: 'recarregar' };
  if (st === 409 && ['AGENDADO', 'ENCERRADO', 'RASCUNHO'].includes(code)) return { aviso: code, msg: e.message };
  if (st === 403 || st === 429 || st === 422 || st === 400) return { msg: e.message, acao: 'tentar' };
  return { msg: 'Não foi possível enviar suas respostas agora. Elas continuam aqui; tente novamente.', acao: 'tentar' };
}

function carregarAviso(er) {
  if (er.aviso === 'ENCERRADO') mostrarAviso('lock', 'Este formulário foi encerrado', er.msg || 'O período de respostas terminou.');
  else if (er.aviso === 'AGENDADO') mostrarAviso('clock', 'Este formulário ainda não está aberto', er.msg || 'Volte mais tarde.');
  else mostrarAviso('lock', 'Este formulário não está disponível', er.msg || 'Ele foi retirado do ar.');
}

function atualizarBotao() {
  const b = document.getElementById('btn-avancar');
  if (!b || S.tela !== 'fluxo') return;
  b.outerHTML = botaoAvancar(S.passos[S.i]);
}

// ------------------------------------------------------------------ eventos
function definirResposta(pid, valor) {
  S.resp[pid] = valor;
  delete S.errs[pid];
}

app.addEventListener('click', (ev) => {
  const el = ev.target.closest('[data-act]');
  if (!el) return;
  const act = el.dataset.act;
  if (act === 'comecar') { S.tela = 'fluxo'; S.i = 0; S.anim = 'q-in-next'; render(); }
  else if (act === 'avancar') avancar();
  else if (act === 'voltar') voltar();
  else if (act === 'ir') irPara(Number(el.dataset.i), 'back');
  else if (act === 'recarregar') location.reload();
  else if (act === 'outra') {
    S.envioId = uuid(); S.resp = {}; S.errs = {}; S.erroEnvio = null;
    S.passos = montarPassos(S.def); S.i = 0; S.tela = 'capa'; render();
  } else if (act === 'escala') {
    const v = Number(el.dataset.v);
    definirResposta(el.dataset.pid, S.resp[el.dataset.pid] === v ? undefined : v);
    S.focar = false; render();
  }
});

app.addEventListener('input', (ev) => {
  const el = ev.target;
  if (el.dataset.campo === 'nome' || el.dataset.campo === 'email') { S.ident[el.dataset.campo] = el.value; return; }
  const pid = el.dataset.pid;
  if (!pid || el.type === 'radio' || el.type === 'checkbox' || el.tagName === 'SELECT') return;
  const p = acharPergunta(pid);
  if (p && p.tipo === 'TELEFONE') el.value = maskPhone(el.value);
  definirResposta(pid, el.value);
  el.classList.remove('err');
  const cont = app.querySelector(`[data-cont="${CSS.escape(pid)}"]`);
  if (cont) cont.textContent = `${el.value.length}/${MAX_TEXTO}`;
  atualizarBotao();
});

app.addEventListener('change', (ev) => {
  const el = ev.target;
  if (el.dataset.campo === 'area') {
    S.area = el.value;
    const g = S.def.gestores.find((x) => x.id === S.gestor);
    if (g && g.area_id && g.area_id !== S.area) S.gestor = '';
    S.errs = {}; S.focar = false; render(); return;
  }
  if (el.dataset.campo === 'gestor') { S.gestor = el.value; S.errs = {}; return; }
  const pid = el.dataset.pid;
  if (!pid) return;
  if (el.type === 'checkbox') {
    const atual = Array.isArray(S.resp[pid]) ? S.resp[pid] : [];
    definirResposta(pid, el.checked ? [...atual, el.value] : atual.filter((x) => x !== el.value));
  } else definirResposta(pid, el.value);
  if (el.type === 'radio' || el.type === 'checkbox') { S.focar = false; render(); }
  else atualizarBotao();
});

function acharPergunta(pid) {
  for (const t of S.def.topicos) for (const p of t.perguntas) if (p.id === pid) return p;
  return null;
}

let digitos = '';
let digitoTimer = null;
document.addEventListener('keydown', (ev) => {
  if (S.tela === 'capa' && ev.key === 'Enter') { ev.preventDefault(); S.tela = 'fluxo'; S.i = 0; S.anim = 'q-in-next'; render(); return; }
  if (S.tela !== 'fluxo') return;
  const passo = S.passos[S.i];
  const alvo = ev.target;
  const emCampo = alvo && (alvo.tagName === 'INPUT' && !['radio', 'checkbox'].includes(alvo.type) || alvo.tagName === 'TEXTAREA' || alvo.tagName === 'SELECT');

  if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); avancar(); return; }
  if (ev.key === 'Enter' && !ev.shiftKey) {
    if (alvo.tagName === 'TEXTAREA') return;
    if (alvo.tagName === 'BUTTON' && alvo.dataset.act && alvo.dataset.act !== 'escala') return; // botão focado trata o próprio Enter
    if (passo.k === 'revisao') return;
    ev.preventDefault(); avancar(); return;
  }
  if (emCampo) return;
  if (ev.key === 'ArrowLeft' && !ev.altKey) { ev.preventDefault(); voltar(); return; }

  if (passo.k === 'perg' && /^[0-9]$/.test(ev.key)) {
    const p = passo.p;
    if (p.tipo === 'ESCALA') {
      const [min, max] = limitesEscala(p);
      digitos = (digitos + ev.key).slice(-2);
      clearTimeout(digitoTimer);
      digitoTimer = setTimeout(() => { digitos = ''; }, 700);
      let n = Number(digitos);
      if (n < min || n > max) { n = Number(ev.key); digitos = ev.key; }
      if (n >= min && n <= max) { definirResposta(p.id, n); S.focar = false; render(); }
    } else if (p.tipo === 'UNICA' && ev.key !== '0') {
      const o = p.opcoes[Number(ev.key) - 1];
      if (o) { definirResposta(p.id, o.id); S.focar = false; render(); }
    } else if (p.tipo === 'SIM_NAO' && (ev.key === '1' || ev.key === '2')) {
      definirResposta(p.id, ev.key === '1' ? 'SIM' : 'NAO'); S.focar = false; render();
    }
  }
});

window.addEventListener('beforeunload', (ev) => {
  if (S.tela === 'fluxo' && Object.keys(S.resp).some((k) => !vazio(S.resp[k])) && !PREVIEW) { ev.preventDefault(); ev.returnValue = ''; }
});

iniciar();
