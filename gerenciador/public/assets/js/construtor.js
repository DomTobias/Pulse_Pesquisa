// Construtor de formulário: estrutura (esquerda), edição (centro) e prévia ao vivo (direita).
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const esc = Pulse.esc;
  const D = JSON.parse($('#dados-construtor').textContent);
  const RO = !!D.somente_leitura;
  const imagens = D.imagens || {};

  const TIPOS = [
    { id: 'ESCALA10', tipo: 'ESCALA', rotulo: 'Escala 0 a 10', ic: 'star', desc: 'Cores por zona, padrão do Pulse' },
    { id: 'CURTA', tipo: 'CURTA', rotulo: 'Resposta curta', ic: 'input-cursor-text', desc: 'Uma linha de texto' },
    { id: 'LONGA', tipo: 'LONGA', rotulo: 'Resposta longa', ic: 'text-paragraph', desc: 'Comentários e sugestões' },
    { id: 'UNICA', tipo: 'UNICA', rotulo: 'Escolha única', ic: 'ui-radios', desc: 'Uma opção entre várias' },
    { id: 'MULTIPLA', tipo: 'MULTIPLA', rotulo: 'Múltipla escolha', ic: 'ui-checks', desc: 'Várias opções' },
    { id: 'LISTA', tipo: 'LISTA', rotulo: 'Lista suspensa', ic: 'menu-down', desc: 'Para listas longas' },
    { id: 'SIM_NAO', tipo: 'SIM_NAO', rotulo: 'Sim ou não', ic: 'hand-thumbs-up', desc: 'Duas opções' },
    { id: 'ESCALA', tipo: 'ESCALA', rotulo: 'Escala', ic: 'sliders2', desc: 'Faixa numérica personalizada' },
    { id: 'NUMERO', tipo: 'NUMERO', rotulo: 'Número', ic: '123', desc: 'Com mínimo e máximo' },
    { id: 'DATA', tipo: 'DATA', rotulo: 'Data', ic: 'calendar3', desc: 'Dia, mês e ano' },
    { id: 'EMAIL', tipo: 'EMAIL', rotulo: 'E-mail', ic: 'envelope', desc: 'Endereço de e-mail' },
    { id: 'TELEFONE', tipo: 'TELEFONE', rotulo: 'Telefone', ic: 'telephone', desc: 'Com DDD' },
    { id: 'INFO', tipo: 'INFO', rotulo: 'Texto informativo', ic: 'info-circle', desc: 'Sem resposta' },
  ];
  const NOME_TIPO = { CURTA: 'Resposta curta', LONGA: 'Resposta longa', NUMERO: 'Número', EMAIL: 'E-mail', TELEFONE: 'Telefone', DATA: 'Data', UNICA: 'Escolha única', MULTIPLA: 'Múltipla escolha', LISTA: 'Lista suspensa', ESCALA: 'Escala', SIM_NAO: 'Sim ou não', INFO: 'Texto informativo' };
  const COM_OPCOES = ['UNICA', 'MULTIPLA', 'LISTA'];

  let seq = 0;
  const chave = () => `k${++seq}`;
  function chavear(f) {
    f.topicos.forEach((t) => { t._k = t._k || chave(); t.perguntas.forEach((p) => { p._k = p._k || chave(); (p.opcoes || []).forEach((o) => { o._k = o._k || chave(); }); }); });
    return f;
  }
  let form = chavear(D.form);
  let sel = null;
  let sujo = false;
  let salvando = false;
  let alvoNovaPergunta = 0;

  // ------------------------------------------------------------------ utilidades
  const getPath = (obj, path) => path.split('.').reduce((o, k) => (o == null ? o : o[k]), obj);
  function setPath(obj, path, v) {
    const ks = path.split('.'); const ult = ks.pop();
    const alvo = ks.reduce((o, k) => o[k], obj);
    alvo[ult] = v;
  }
  function marcarSujo() {
    sujo = true;
    const e = $('[data-estado-salvo]');
    if (e && !RO) { e.textContent = 'Alterações não salvas'; e.classList.add('sujo'); }
    agendarPrevia();
  }
  const dis = RO ? ' disabled' : '';
  const ic = (n) => `<i class="bi bi-${n}" aria-hidden="true"></i>`;
  const acharPergunta = (k) => { for (const t of form.topicos) for (const p of t.perguntas) if (p._k === k) return p; return null; };

  // ------------------------------------------------------------------ estrutura (esquerda)
  function renderEstrutura() {
    const el = $('[data-estrutura]');
    let n = 0;
    el.innerHTML = form.topicos.map((t, ti) => `
      <button type="button" class="estrutura-item topico" data-ir-topico="${ti}" data-drop-topico="${ti}">${ic('folder2')} <span class="text-truncate">${esc(t.titulo || 'Tópico sem título')}</span></button>
      ${t.perguntas.map((p, pi) => { if (p.tipo !== 'INFO') n++; return `
        <button type="button" class="estrutura-item${sel === p._k ? ' sel' : ''}" data-selecionar="${p._k}" ${RO ? '' : 'draggable="true"'} data-arrastar="${ti}.${pi}">
          <span class="num tnum">${p.tipo === 'INFO' ? 'i' : n}</span><span class="text-truncate">${esc(p.titulo || 'Pergunta sem enunciado')}</span></button>`; }).join('')}
      ${t.perguntas.length ? '' : '<div class="xsmall text-muted-2 px-3 py-1">Sem perguntas</div>'}`).join('')
      || '<div class="small text-muted-2 p-3">Adicione um tópico para começar.</div>';
  }

  // ------------------------------------------------------------------ perguntas (centro)
  function editorPergunta(p, b) {
    const campo = (rot, html, cls = 'col-12') => `<div class="${cls}"><label class="form-label small">${rot}</label>${html}</div>`;
    let h = '<div class="row g-3">';
    if (p.tipo === 'INFO') {
      h += campo('Texto exibido ao participante', `<textarea class="form-control" rows="4" data-b="${b}.orientacao"${dis}>${esc(p.orientacao)}</textarea>`);
    } else {
      h += campo('Orientação (opcional)', `<input class="form-control" data-b="${b}.orientacao" value="${esc(p.orientacao)}" placeholder="Ajuda curta abaixo do enunciado"${dis}>`);
    }
    h += campo('Tipo', `<select class="form-select" data-trocar-tipo="${p._k}"${dis}>${Object.entries(NOME_TIPO).map(([k, v]) => `<option value="${k}"${p.tipo === k ? ' selected' : ''}>${v}</option>`).join('')}</select>`, 'col-sm-6');
    if (p.tipo !== 'INFO') {
      h += `<div class="col-sm-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="ob-${p._k}" data-b="${b}.obrigatoria"${p.obrigatoria ? ' checked' : ''}${dis}><label class="form-check-label small" for="ob-${p._k}">Resposta obrigatória</label></div></div>`;
    }
    if (p.tipo === 'ESCALA') {
      h += campo('Mínimo', `<input type="number" step="1" class="form-control" data-b="${b}.minimo" data-num value="${p.minimo ?? 0}"${dis}>`, 'col-6 col-md-3');
      h += campo('Máximo', `<input type="number" step="1" class="form-control" data-b="${b}.maximo" data-num value="${p.maximo ?? 10}"${dis}>`, 'col-6 col-md-3');
      h += campo('Aparência', `<select class="form-select" data-b="${b}.aparencia"${dis}><option value="ZONAS"${p.aparencia === 'ZONAS' ? ' selected' : ''}>Cores por zona</option><option value="NEUTRA"${p.aparencia !== 'ZONAS' ? ' selected' : ''}>Neutra</option></select>`, 'col-md-6');
      h += campo('Rótulo do mínimo', `<input class="form-control" maxlength="80" data-b="${b}.rotulo_minimo" value="${esc(p.rotulo_minimo)}" placeholder="Ex.: Nada"${dis}>`, 'col-md-6');
      h += campo('Rótulo do máximo', `<input class="form-control" maxlength="80" data-b="${b}.rotulo_maximo" value="${esc(p.rotulo_maximo)}" placeholder="Ex.: Totalmente"${dis}>`, 'col-md-6');
    }
    if (p.tipo === 'NUMERO') {
      h += campo('Valor mínimo (opcional)', `<input type="number" class="form-control" data-b="${b}.minimo" data-num value="${p.minimo ?? ''}"${dis}>`, 'col-6');
      h += campo('Valor máximo (opcional)', `<input type="number" class="form-control" data-b="${b}.maximo" data-num value="${p.maximo ?? ''}"${dis}>`, 'col-6');
    }
    if (COM_OPCOES.includes(p.tipo)) {
      const ico = p.tipo === 'MULTIPLA' ? 'square' : p.tipo === 'UNICA' ? 'circle' : 'dot';
      h += `<div class="col-12"><div class="d-flex justify-content-between mb-2"><span class="form-label small mb-0">Alternativas</span><span class="xsmall text-muted-2">${p.opcoes.length} ${p.opcoes.length === 1 ? 'opção' : 'opções'}</span></div>
        ${p.opcoes.map((o, oi) => `<div class="d-flex align-items-center gap-1 mb-2"><span class="text-muted-2 px-1">${ic(ico)}</span>
          <input class="form-control form-control-sm" maxlength="300" data-b="${b}.opcoes.${oi}.rotulo" data-opcao="${p._k}.${oi}" value="${esc(o.rotulo)}" placeholder="Opção ${oi + 1}"${dis}>
          ${RO ? '' : `<button type="button" class="icon-btn sm" data-acao="op-subir" data-k="${p._k}" data-o="${oi}" ${oi === 0 ? 'disabled' : ''} aria-label="Mover para cima">${ic('arrow-up')}</button>
          <button type="button" class="icon-btn sm" data-acao="op-descer" data-k="${p._k}" data-o="${oi}" ${oi === p.opcoes.length - 1 ? 'disabled' : ''} aria-label="Mover para baixo">${ic('arrow-down')}</button>
          <button type="button" class="icon-btn sm" data-acao="op-remover" data-k="${p._k}" data-o="${oi}" ${p.opcoes.length <= 1 ? 'disabled' : ''} aria-label="Remover opção">${ic('x-lg')}</button>`}</div>`).join('')}
        ${RO ? '' : `<div class="d-flex gap-2"><button type="button" class="btn btn-soft btn-sm" data-acao="op-add" data-k="${p._k}">${ic('plus')} Adicionar opção</button>
        ${p.opcoes.some((o) => o.rotulo === 'Outro') ? '' : `<button type="button" class="btn btn-ghost btn-sm" data-acao="op-outro" data-k="${p._k}">Adicionar “Outro”</button>`}</div>
        <p class="xsmall text-muted-2 mt-2 mb-0">Dica: Enter em uma opção cria a próxima.</p>`}</div>`;
    }
    return h + '</div>';
  }

  function renderPerguntas() {
    const el = $('[data-painel="perguntas"]');
    let n = 0;
    el.innerHTML = form.topicos.map((t, ti) => `
      <div class="bloco-topico" id="topico-${t._k}">
        <div class="bloco-topico-head">
          <span class="tag prim">Tópico ${ti + 1}</span>
          <input class="input-titulo flex-grow-1" maxlength="200" data-b="topicos.${ti}.titulo" value="${esc(t.titulo)}" placeholder="Título do tópico" aria-label="Título do tópico"${dis}>
          ${RO ? '' : `<button type="button" class="icon-btn sm" data-acao="t-subir" data-t="${ti}" ${ti === 0 ? 'disabled' : ''} aria-label="Mover tópico para cima">${ic('arrow-up')}</button>
          <button type="button" class="icon-btn sm" data-acao="t-descer" data-t="${ti}" ${ti === form.topicos.length - 1 ? 'disabled' : ''} aria-label="Mover tópico para baixo">${ic('arrow-down')}</button>
          <button type="button" class="icon-btn sm" data-acao="t-remover" data-t="${ti}" aria-label="Remover tópico">${ic('trash')}</button>`}
        </div>
        <div class="px-3 pt-2"><input class="form-control form-control-sm" maxlength="5000" data-b="topicos.${ti}.descricao" value="${esc(t.descricao)}" placeholder="Descrição do tópico (opcional)"${dis}></div>
        ${t.perguntas.map((p, pi) => {
          if (p.tipo !== 'INFO') n++;
          const b = `topicos.${ti}.perguntas.${pi}`;
          const aberto = sel === p._k;
          return `<div class="pergunta-card${aberto ? ' sel' : ''}" id="p-${p._k}">
            <div class="ph" data-selecionar="${p._k}">
              <span class="tnum xsmall text-muted-2" style="min-width:1.5rem">${p.tipo === 'INFO' ? ic('info-circle') : n + '.'}</span>
              <textarea class="input-titulo flex-grow-1" rows="1" maxlength="500" data-b="${b}.titulo" placeholder="${p.tipo === 'INFO' ? 'Título do aviso' : 'Escreva a pergunta'}" aria-label="Enunciado"${dis}>${esc(p.titulo)}</textarea>
              ${p.obrigatoria && p.tipo !== 'INFO' ? '<span class="text-danger-2" title="Obrigatória">*</span>' : ''}
              <span class="tipo">${NOME_TIPO[p.tipo]}${p.tipo === 'ESCALA' ? ` ${p.minimo ?? 0}–${p.maximo ?? 10}` : ''}</span>
              ${RO ? '' : `<span class="d-flex">
                <button type="button" class="icon-btn sm" data-acao="p-subir" data-k="${p._k}" aria-label="Mover para cima">${ic('arrow-up')}</button>
                <button type="button" class="icon-btn sm" data-acao="p-descer" data-k="${p._k}" aria-label="Mover para baixo">${ic('arrow-down')}</button>
                <button type="button" class="icon-btn sm" data-acao="p-duplicar" data-k="${p._k}" aria-label="Duplicar">${ic('copy')}</button>
                <button type="button" class="icon-btn sm" data-acao="p-remover" data-k="${p._k}" aria-label="Remover">${ic('trash')}</button></span>`}
            </div>
            ${aberto ? `<div class="pb">${editorPergunta(p, b)}</div>` : ''}
          </div>`;
        }).join('')}
        ${t.perguntas.length ? '' : '<p class="small text-muted-2 px-3 pt-3 mb-0">Nenhuma pergunta neste tópico.</p>'}
        ${RO ? '<div class="pb-2"></div>' : `<div class="p-3"><button type="button" class="btn btn-light btn-sm" data-acao="p-add" data-t="${ti}">${ic('plus-lg')} Adicionar pergunta</button></div>`}
      </div>`).join('')
      + (RO ? '' : `<button type="button" class="btn btn-soft w-100" data-acao="add-topico">${ic('plus-lg')} Adicionar tópico</button>`);
    $$('textarea.input-titulo', el).forEach(autoAltura);
  }
  function autoAltura(t) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; }

  // ------------------------------------------------------------------ capa e textos
  function campoImagem(rot, chaveCapa, ajuda) {
    const ref = form.capa[chaveCapa];
    const u = ref ? imagens[ref] : '';
    return `<div class="col-md-6"><label class="form-label small">${rot}</label>
      ${u ? `<img class="miniatura mb-2" src="${esc(u)}" alt="">` : ''}
      <div class="d-flex gap-2">${RO ? '' : `<label class="btn btn-light btn-sm mb-0">${ic('upload')} ${u ? 'Trocar' : 'Enviar'}<input type="file" hidden accept="image/png,image/jpeg,image/webp,image/svg+xml" data-upload="${chaveCapa}"></label>
      ${u ? `<button type="button" class="btn btn-ghost btn-sm" data-acao="img-remover" data-c="${chaveCapa}">Remover</button>` : ''}`}</div>
      <div class="form-text">${ajuda} Até 1,5 MB (PNG, JPG, WEBP ou SVG).</div></div>`;
  }
  function renderCapa() {
    const c = form.capa;
    const txt = (k, rot, ph = '', max = 300) => `<div class="col-md-6"><label class="form-label small">${rot}</label><input class="form-control" maxlength="${max}" data-b="capa.${k}" value="${esc(c[k])}" placeholder="${esc(ph)}"${dis}></div>`;
    $('[data-painel="capa"]').innerHTML = `
      <div class="cartao p-3 mb-3"><h2 class="fs-6 fw-semibold mb-3">Capa</h2>
        <div class="row g-3">
          <div class="col-12"><select class="form-select" data-b="capa.tipo" data-rerender${dis}>
            ${[['INSTITUCIONAL', 'Institucional (pôster com marca e foto)'], ['FAIXA', 'Faixa colorida'], ['IMAGEM', 'Imagem de capa'], ['NENHUMA', 'Sem capa']].map(([v, r]) => `<option value="${v}"${c.tipo === v ? ' selected' : ''}>${r}</option>`).join('')}</select></div>
          ${c.tipo === 'INSTITUCIONAL' ? `
            ${txt('titulo', 'Título da marca', 'Ex.: PULSE')}${txt('subtitulo', 'Subtítulo manuscrito', 'Ex.: Nossa Gente')}
            ${txt('chamada', 'Chamada', 'Frase curta em destaque')}${txt('convite', 'Convite', 'Ex.: Contamos com você!')}
            ${txt('destaque_titulo', 'Título do quadro')}${txt('destaque_texto', 'Texto do quadro')}
            ${txt('encerramento', 'Frase de encerramento', 'Aparece sobre a foto e na conclusão')}
            ${campoImagem('Símbolo (canto superior)', 'simbolo', 'Logotipo ou símbolo da empresa.')}
            ${campoImagem('Foto da capa', 'foto', 'Foto horizontal, usada também na lateral.')}` : ''}
          ${c.tipo === 'IMAGEM' ? campoImagem('Imagem de capa', 'imagem', 'Imagem horizontal.') : ''}
          ${c.tipo !== 'INSTITUCIONAL' ? txt('convite', 'Convite (opcional)', 'Texto acima do botão Começar') : ''}
        </div></div>
      <div class="cartao p-3"><h2 class="fs-6 fw-semibold mb-3">Textos</h2>
        <div class="mb-3"><label class="form-label small">Descrição</label><textarea class="form-control" rows="2" maxlength="5000" data-b="descricao"${dis}>${esc(form.descricao)}</textarea></div>
        <div class="mb-3"><label class="form-label small">Mensagem de apresentação</label><textarea class="form-control" rows="3" maxlength="5000" data-b="mensagem_apresentacao"${dis}>${esc(form.mensagem_apresentacao)}</textarea></div>
        <div><label class="form-label small">Mensagem de conclusão</label><textarea class="form-control" rows="3" maxlength="5000" data-b="mensagem_conclusao"${dis}>${esc(form.mensagem_conclusao)}</textarea></div>
      </div>`;
  }

  // ------------------------------------------------------------------ período e opções
  function renderOpcoes() {
    const f = form;
    const chk = (k, rot, ajuda) => `<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="o-${k}" data-b="${k}"${f[k] ? ' checked' : ''}${dis}>
      <label class="form-check-label" for="o-${k}">${rot}</label><div class="form-text">${ajuda}</div></div>`;
    $('[data-painel="opcoes"]').innerHTML = `
      <div class="cartao p-3 mb-3"><h2 class="fs-6 fw-semibold mb-3">Identificação</h2>
        <div class="row g-3">
          <div class="col-12"><label class="form-label small">Título do formulário</label><input class="form-control" maxlength="200" data-b="titulo" value="${esc(f.titulo)}"${dis}></div>
          <div class="col-12"><label class="form-label small">Endereço do link</label>
            <div class="input-group"><span class="input-group-text small text-muted-2" style="background:rgb(var(--surface2));border-color:rgb(var(--line))">${esc(D.urls.link_base)}</span>
            <input class="form-control" maxlength="70" data-b="slug" data-slug value="${esc(f.slug)}"${dis}></div>
            <div class="form-text">Letras minúsculas, números e hífens. Mudar o endereço invalida o link já divulgado.</div></div>
          <div class="col-sm-6"><label class="form-label small">Cor de destaque</label><input type="color" class="form-control form-control-color w-100" data-b="cor" value="${esc(f.cor)}"${dis}></div>
          <div class="col-sm-6"><label class="form-label small">Apresentação das perguntas</label><select class="form-select" data-b="formato"${dis}>
            ${[['AUTO', 'Automático (uma por tela)'], ['ETAPAS', 'Uma pergunta por tela'], ['PAGINA_UNICA', 'Um tópico por tela']].map(([v, r]) => `<option value="${v}"${f.formato === v ? ' selected' : ''}>${r}</option>`).join('')}</select></div>
        </div></div>
      <div class="cartao p-3 mb-3"><h2 class="fs-6 fw-semibold mb-3">Período e recorrência</h2>
        <div class="row g-3">
          <div class="col-sm-6"><label class="form-label small">Abertura</label><input type="datetime-local" class="form-control" data-b="inicio_em" value="${esc(f.inicio_em)}"${dis}></div>
          <div class="col-sm-6"><label class="form-label small">Encerramento</label><input type="datetime-local" class="form-control" data-b="fim_em" value="${esc(f.fim_em)}"${dis}></div>
          <div class="col-12"><label class="form-label small">Frequência de envio</label><select class="form-select" data-b="recorrencia"${dis}>
            ${[['NENHUMA', 'Sem recorrência'], ['SEMANAL', 'Semanal (ciclos de 7 dias)'], ['QUINZENAL', 'Quinzenal (ciclos de 14 dias)'], ['MENSAL', 'Mensal (ciclos de 30 dias)']].map(([v, r]) => `<option value="${v}"${f.recorrencia === v ? ' selected' : ''}>${r}</option>`).join('')}</select>
            <div class="form-text">Em pesquisas recorrentes, o ciclo N começa na abertura + (N − 1) × duração. A abertura é obrigatória. Horários no fuso ${esc(D.base_previa.fuso_horario)}.</div></div>
          <div class="col-12">${f.recorrencia === 'NENHUMA'
            ? '<div class="form-text">Com recorrência, dá para liberar um tópico por ciclo.</div>'
            : chk('topico_por_ciclo', 'Liberar um tópico por ciclo (rodízio)', 'Cada ciclo mostra só um tópico, na ordem da lista: 1º ciclo o 1º tópico, 2º ciclo o 2º, e assim por diante; depois do último, recomeça. A prévia mostra todos os tópicos.')}</div>
        </div></div>
      <div class="cartao p-3"><h2 class="fs-6 fw-semibold mb-3">Participação</h2>
        ${chk('segmentado', 'Pedir área e gestor', 'Permite os relatórios por área e por gestor. O nome do participante não é registrado.')}
        ${chk('exige_identificacao', 'Exigir identificação (nome e e-mail)', 'Desligado, as respostas são anônimas. O Pulse deve continuar anônimo.')}
        ${chk('permite_multiplas', 'Permitir enviar outra resposta', 'Mostra o botão “Enviar outra resposta” ao final.')}
      </div>`;
  }

  function renderTudo() { renderEstrutura(); renderPerguntas(); renderCapa(); renderOpcoes(); }

  // ------------------------------------------------------------------ prévia
  let timerPrevia = null;
  function agendarPrevia() { clearTimeout(timerPrevia); timerPrevia = setTimeout(enviarPrevia, 350); }
  function definicaoPrevia() {
    const capa = { ...form.capa };
    ['simbolo', 'foto', 'imagem'].forEach((k) => { capa[k] = capa[k] ? (imagens[capa[k]] || null) : null; });
    const num = (v) => (v === null || v === '' || v === undefined ? null : Number(v));
    return {
      ...D.base_previa, slug: form.slug, titulo: form.titulo, descricao: form.descricao, status: 'PUBLICADO', ciclo_atual: null,
      inicio_em: null, fim_em: null, recorrencia: form.recorrencia, segmentado: form.segmentado, exige_identificacao: form.exige_identificacao,
      permite_multiplas: form.permite_multiplas, formato: form.formato, cor: form.cor, mensagem_apresentacao: form.mensagem_apresentacao,
      mensagem_conclusao: form.mensagem_conclusao, capa,
      areas: form.segmentado ? D.base_previa.areas : [], gestores: form.segmentado ? D.base_previa.gestores : [],
      topicos: form.topicos.map((t) => ({ id: t.uuid || t._k, titulo: t.titulo, descricao: t.descricao, perguntas: t.perguntas.map((p) => ({
        id: p.uuid || p._k, tipo: p.tipo, titulo: p.titulo, orientacao: p.orientacao, obrigatoria: p.tipo !== 'INFO' && !!p.obrigatoria,
        minimo: num(p.minimo), maximo: num(p.maximo), rotulo_minimo: p.rotulo_minimo || '', rotulo_maximo: p.rotulo_maximo || '', aparencia: p.aparencia,
        opcoes: (p.opcoes || []).filter((o) => o.rotulo.trim()).map((o) => ({ id: o.uuid || o._k, rotulo: o.rotulo })) })) })),
    };
  }
  function enviarPrevia() {
    const fr = $('[data-iframe]');
    if (fr && fr.contentWindow) fr.contentWindow.postMessage({ tipo: 'pulse:definicao', definicao: definicaoPrevia() }, location.origin);
  }
  $('[data-iframe]').addEventListener('load', () => { if (sujo) enviarPrevia(); });

  // ------------------------------------------------------------------ edição
  function novaPergunta(id) {
    const t = TIPOS.find((x) => x.id === id);
    const p = { _k: chave(), uuid: null, tipo: t.tipo, titulo: '', orientacao: '', obrigatoria: t.tipo !== 'INFO', minimo: null, maximo: null, rotulo_minimo: '', rotulo_maximo: '', aparencia: 'NEUTRA', opcoes: [] };
    if (id === 'ESCALA10') Object.assign(p, { minimo: 0, maximo: 10, aparencia: 'ZONAS', rotulo_minimo: 'Nada', rotulo_maximo: 'Totalmente' });
    if (id === 'ESCALA') Object.assign(p, { minimo: 1, maximo: 5 });
    if (COM_OPCOES.includes(t.tipo)) p.opcoes = [{ _k: chave(), uuid: null, rotulo: 'Opção 1' }, { _k: chave(), uuid: null, rotulo: 'Opção 2' }];
    if (t.tipo === 'LONGA' || t.tipo === 'CURTA') p.obrigatoria = false;
    return p;
  }
  function localizar(k) {
    for (let ti = 0; ti < form.topicos.length; ti++) { const pi = form.topicos[ti].perguntas.findIndex((p) => p._k === k); if (pi >= 0) return [ti, pi]; }
    return [-1, -1];
  }
  function mover(lista, i, d) { const j = i + d; if (j < 0 || j >= lista.length) return; [lista[i], lista[j]] = [lista[j], lista[i]]; }

  document.addEventListener('click', async (e) => {
    const s = e.target.closest('[data-selecionar]');
    if (s && !e.target.closest('textarea, input, button[data-acao]')) {
      const k = s.dataset.selecionar;
      sel = sel === k && s.closest('.pergunta-card') ? null : k;
      ativarAba('perguntas');
      renderEstrutura(); renderPerguntas();
      const card = $('#p-' + k);
      if (card && s.classList.contains('estrutura-item')) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    const it = e.target.closest('[data-ir-topico]');
    if (it) { ativarAba('perguntas'); const t = form.topicos[+it.dataset.irTopico]; $('#topico-' + t._k)?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
    const aba = e.target.closest('[data-aba]');
    if (aba) { e.preventDefault(); ativarAba(aba.dataset.aba); return; }
    const pv = e.target.closest('[data-previa]');
    if (pv) {
      e.preventDefault();
      $$('[data-previa]').forEach((a) => a.classList.toggle('active', a === pv));
      $('[data-moldura]').className = `previa-moldura ${pv.dataset.previa}`;
      return;
    }
    const tipoBtn = e.target.closest('[data-tipo-novo]');
    if (tipoBtn) {
      const p = novaPergunta(tipoBtn.dataset.tipoNovo);
      form.topicos[alvoNovaPergunta].perguntas.push(p);
      sel = p._k;
      bootstrap.Modal.getInstance($('#modal-tipos')).hide();
      renderEstrutura(); renderPerguntas(); marcarSujo();
      setTimeout(() => { const c = $('#p-' + p._k); c?.scrollIntoView({ behavior: 'smooth', block: 'center' }); c?.querySelector('textarea')?.focus(); }, 250);
      return;
    }
    if (e.target.closest('[data-salvar]')) { salvar(); return; }
    const a = e.target.closest('[data-acao]');
    if (!a || RO) return;
    const ac = a.dataset.acao;
    const [ti, pi] = a.dataset.k ? localizar(a.dataset.k) : [+a.dataset.t, -1];
    const p = pi >= 0 ? form.topicos[ti].perguntas[pi] : null;
    switch (ac) {
      case 'add-topico': form.topicos.push({ _k: chave(), uuid: null, titulo: `Tópico ${form.topicos.length + 1}`, descricao: '', perguntas: [] }); break;
      case 't-subir': mover(form.topicos, ti, -1); break;
      case 't-descer': mover(form.topicos, ti, 1); break;
      case 't-remover': {
        const t = form.topicos[ti];
        if (t.perguntas.length && !(await Pulse.confirmar({ titulo: 'Remover tópico?', mensagem: `“${t.titulo}” e suas ${t.perguntas.length} perguntas serão removidos ao salvar. Perguntas com respostas ficam guardadas no histórico.`, rotulo: 'Remover', perigo: true }))) return;
        form.topicos.splice(ti, 1); break;
      }
      case 'p-add': alvoNovaPergunta = ti; bootstrap.Modal.getOrCreateInstance($('#modal-tipos')).show(); return;
      case 'p-subir':
        if (pi > 0) mover(form.topicos[ti].perguntas, pi, -1);
        else if (ti > 0) { form.topicos[ti].perguntas.splice(pi, 1); form.topicos[ti - 1].perguntas.push(p); }
        break;
      case 'p-descer':
        if (pi < form.topicos[ti].perguntas.length - 1) mover(form.topicos[ti].perguntas, pi, 1);
        else if (ti < form.topicos.length - 1) { form.topicos[ti].perguntas.splice(pi, 1); form.topicos[ti + 1].perguntas.unshift(p); }
        break;
      case 'p-duplicar': {
        const c = JSON.parse(JSON.stringify(p)); c._k = chave(); c.uuid = null; c.opcoes.forEach((o) => { o._k = chave(); o.uuid = null; });
        form.topicos[ti].perguntas.splice(pi + 1, 0, c); sel = c._k; break;
      }
      case 'p-remover':
        if (p.uuid && !(await Pulse.confirmar({ titulo: 'Remover pergunta?', mensagem: 'Se ela já tiver respostas, continuará no histórico dos relatórios.', rotulo: 'Remover', perigo: true }))) return;
        form.topicos[ti].perguntas.splice(pi, 1); break;
      case 'op-add': p.opcoes.push({ _k: chave(), uuid: null, rotulo: `Opção ${p.opcoes.length + 1}` }); break;
      case 'op-outro': p.opcoes.push({ _k: chave(), uuid: null, rotulo: 'Outro' }); break;
      case 'op-subir': mover(p.opcoes, +a.dataset.o, -1); break;
      case 'op-descer': mover(p.opcoes, +a.dataset.o, 1); break;
      case 'op-remover': p.opcoes.splice(+a.dataset.o, 1); break;
      case 'img-remover': form.capa[a.dataset.c] = null; renderCapa(); marcarSujo(); return;
      default: return;
    }
    renderEstrutura(); renderPerguntas(); marcarSujo();
  });

  document.addEventListener('input', (e) => {
    const el = e.target;
    if (!el.dataset.b || RO) return;
    let v = el.type === 'checkbox' ? el.checked : el.value;
    if ('num' in el.dataset) v = el.value === '' ? null : Number(el.value);
    if ('slug' in el.dataset) { v = v.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-'); if (v !== el.value) el.value = v; }
    setPath(form, el.dataset.b, v);
    if (el.tagName === 'TEXTAREA' && el.classList.contains('input-titulo')) autoAltura(el);
    if (/\.titulo$|^titulo$/.test(el.dataset.b)) {
      renderEstrutura();
      if (el.dataset.b === 'titulo') $('[data-titulo-topo]').textContent = v || 'Formulário sem título';
    }
    marcarSujo();
  });
  document.addEventListener('change', (e) => {
    const el = e.target;
    if (RO) return;
    if (el.dataset.trocarTipo) {
      const p = acharPergunta(el.dataset.trocarTipo);
      p.tipo = el.value;
      if (COM_OPCOES.includes(p.tipo) && !p.opcoes.length) p.opcoes = [{ _k: chave(), uuid: null, rotulo: 'Opção 1' }];
      if (p.tipo === 'ESCALA' && p.minimo === null) { p.minimo = 0; p.maximo = 10; }
      if (p.tipo === 'INFO') p.obrigatoria = false;
      renderEstrutura(); renderPerguntas(); marcarSujo();
    }
    if ('rerender' in el.dataset) { renderCapa(); }
    if (el.dataset.b === 'recorrencia') { if (form.recorrencia === 'NENHUMA') form.topico_por_ciclo = false; renderOpcoes(); }
    if (el.dataset.b && (el.type === 'checkbox' || el.tagName === 'SELECT') && /perguntas\.\d+\.(obrigatoria|aparencia)$/.test(el.dataset.b)) renderPerguntas();
    if (el.dataset.upload) enviarImagem(el);
  });
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); if (!RO) salvar(); }
    const op = e.target.dataset && e.target.dataset.opcao;
    if (op && e.key === 'Enter' && !RO) {
      e.preventDefault();
      const [k, oi] = op.split('.');
      const p = acharPergunta(k);
      p.opcoes.splice(+oi + 1, 0, { _k: chave(), uuid: null, rotulo: `Opção ${p.opcoes.length + 1}` });
      renderPerguntas(); marcarSujo();
      $(`[data-opcao="${k}.${+oi + 1}"]`)?.focus();
    }
  });

  // Arrastar perguntas na estrutura
  let arrastando = null;
  document.addEventListener('dragstart', (e) => { const it = e.target.closest('[data-arrastar]'); if (!it) return; arrastando = it.dataset.arrastar; it.classList.add('arrastando'); e.dataTransfer.effectAllowed = 'move'; });
  document.addEventListener('dragend', () => { arrastando = null; $$('.arrastando, .sobre').forEach((x) => x.classList.remove('arrastando', 'sobre')); });
  document.addEventListener('dragover', (e) => { const alvo = e.target.closest('[data-arrastar], [data-drop-topico]'); if (!arrastando || !alvo) return; e.preventDefault(); $$('.sobre').forEach((x) => x.classList.remove('sobre')); alvo.classList.add('sobre'); });
  document.addEventListener('drop', (e) => {
    const alvo = e.target.closest('[data-arrastar], [data-drop-topico]');
    if (!arrastando || !alvo) return;
    e.preventDefault();
    const [ti, pi] = arrastando.split('.').map(Number);
    const [p] = form.topicos[ti].perguntas.splice(pi, 1);
    if (alvo.dataset.dropTopico !== undefined) form.topicos[+alvo.dataset.dropTopico].perguntas.push(p);
    else {
      let [tj, pj] = alvo.dataset.arrastar.split('.').map(Number);
      if (tj === ti && pj > pi) pj--;
      form.topicos[tj].perguntas.splice(pj, 0, p);
    }
    renderEstrutura(); renderPerguntas(); marcarSujo();
  });

  function ativarAba(nome) {
    $$('[data-aba]').forEach((a) => a.classList.toggle('active', a.dataset.aba === nome));
    $$('[data-painel]').forEach((p) => { p.hidden = p.dataset.painel !== nome; });
  }

  // ------------------------------------------------------------------ imagem e salvamento
  async function enviarImagem(input) {
    const arq = input.files && input.files[0];
    input.value = '';
    if (!arq) return;
    if (arq.size > 1572864) { Pulse.toast('A imagem passa do limite de 1,5 MB.', 'erro'); return; }
    const fd = new FormData();
    fd.append('arquivo', arq);
    try {
      const r = await fetch(D.urls.imagem, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': Pulse.csrf(), Accept: 'application/json' }, credentials: 'same-origin' });
      const j = await r.json();
      if (!j.ok) throw new Error(j.mensagem || 'Falha ao enviar.');
      imagens[j.ref] = j.url;
      form.capa[input.dataset.upload] = j.ref;
      renderCapa(); marcarSujo();
      Pulse.toast('Imagem enviada. Salve para manter a alteração.');
    } catch (err) { Pulse.toast(err.message || 'Não foi possível enviar a imagem.', 'erro'); }
  }

  function limpar(o) {
    return JSON.parse(JSON.stringify(o, (k, v) => (k === '_k' ? undefined : v)));
  }

  async function salvar() {
    if (salvando || RO) return;
    salvando = true;
    const btn = $('[data-salvar]');
    if (btn) btn.disabled = true;
    const caixa = $('[data-erros]');
    try {
      const r = await fetch(D.urls.salvar, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': Pulse.csrf() },
        body: JSON.stringify(limpar(form)),
      });
      const j = await r.json().catch(() => ({}));
      if (!r.ok || !j.ok) {
        const erros = j.erros || [j.mensagem || 'Não foi possível salvar.'];
        caixa.innerHTML = `<b>Corrija antes de salvar:</b><ul class="mb-0 mt-1">${erros.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>`;
        caixa.classList.remove('d-none');
        caixa.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      caixa.classList.add('d-none');
      const selPos = sel ? localizar(sel) : null;
      form = chavear(j.dados);
      if (selPos && selPos[0] >= 0) sel = form.topicos[selPos[0]]?.perguntas[selPos[1]]?._k || null;
      sujo = false;
      $('[data-versao]').textContent = j.versao;
      const e = $('[data-estado-salvo]'); e.textContent = 'Tudo salvo'; e.classList.remove('sujo');
      renderTudo();
      Pulse.toast(j.mensagem || 'Alterações salvas.');
    } catch (err) {
      Pulse.toast('Falha de comunicação com o gerenciador.', 'erro');
    } finally {
      salvando = false;
      if (btn) btn.disabled = false;
    }
  }

  // Publicar só depois de salvar
  const fp = $('form[data-publicar]');
  if (fp) fp.addEventListener('submit', (e) => { if (sujo) { e.preventDefault(); e.stopPropagation(); Pulse.toast('Salve as alterações antes de publicar.', 'erro'); } });
  window.addEventListener('beforeunload', (e) => { if (sujo && !RO) { e.preventDefault(); e.returnValue = ''; } });

  // Tipos no modal
  $('[data-tipos]').innerHTML = TIPOS.map((t) => `<button type="button" class="tipo-opcao" data-tipo-novo="${t.id}"><span class="ic">${ic(t.ic)}</span><span><b>${t.rotulo}</b><small>${t.desc}</small></span></button>`).join('');

  if (form.topicos[0] && form.topicos[0].perguntas[0]) sel = form.topicos[0].perguntas[0]._k;
  renderTudo();
})();
