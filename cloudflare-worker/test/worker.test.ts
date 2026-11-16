import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cicloEm, statusPublico } from '../src/services/ciclos.ts';
import { validarEnvio } from '../src/validators/envio.ts';
import { montarDefinicaoPublica } from '../src/controllers/definicaoController.ts';
import { receberEnvio } from '../src/controllers/envioController.ts';

const U = (n: number) => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`;

const def: any = {
  id: U(1), slug: 'pulse-nossa-gente', versao: 3, titulo: 'Pulse: Nossa Gente', descricao: '',
  inicio_em: '2026-08-12T07:00:00-04:00', fim_em: '2027-08-13T18:00:00-04:00', recorrencia: 'QUINZENAL',
  fuso_horario: 'America/Cuiaba', segmentado: true, exige_identificacao: false, permite_multiplas: false,
  formato: 'ETAPAS', cor: '#032B4C', mensagem_apresentacao: '', mensagem_conclusao: 'Obrigado', capa: { tipo: 'INSTITUCIONAL' },
  areas: [{ id: U(10), nome: 'Produção' }], gestores: [{ id: U(20), nome: 'Gustavo', area_id: null }],
  topicos: [{ id: U(30), titulo: 'Liderança', descricao: '', perguntas: [
    { id: U(40), tipo: 'ESCALA', titulo: 'Nota', orientacao: '', obrigatoria: true, minimo: 0, maximo: 10, rotulo_minimo: 'Nada', rotulo_maximo: 'Totalmente', aparencia: 'ZONAS', opcoes: [] },
    { id: U(41), tipo: 'LONGA', titulo: 'Sugestão', orientacao: '', obrigatoria: false, minimo: null, maximo: null, rotulo_minimo: '', rotulo_maximo: '', aparencia: 'NEUTRA', opcoes: [] },
    { id: U(42), tipo: 'MULTIPLA', titulo: 'Quais?', orientacao: '', obrigatoria: false, minimo: null, maximo: null, rotulo_minimo: '', rotulo_maximo: '', aparencia: 'NEUTRA', opcoes: [{ id: U(50), rotulo: 'A' }, { id: U(51), rotulo: 'B' }] },
  ] }],
};

test('ciclo quinzenal: N começa em início + (N−1)×14 dias', () => {
  const ini = Date.parse(def.inicio_em);
  const c1 = cicloEm(def.inicio_em, 'QUINZENAL', ini)!;
  assert.equal(c1.numero, 1);
  const quase = ini + 14 * 864e5 - 1;
  assert.equal(cicloEm(def.inicio_em, 'QUINZENAL', quase)!.numero, 1, 'um instante antes do próximo ainda é o ciclo 1');
  assert.equal(cicloEm(def.inicio_em, 'QUINZENAL', quase + 1)!.numero, 2, 'virada de ciclo');
  assert.equal(cicloEm(def.inicio_em, 'QUINZENAL', ini + 90 * 864e5)!.numero, 7);
  assert.equal(cicloEm(def.inicio_em, 'NENHUMA', ini), null);
  assert.equal(cicloEm(def.inicio_em, 'QUINZENAL', ini - 1), null);
});

test('status calculado pelas datas', () => {
  const ini = Date.parse(def.inicio_em), fim = Date.parse(def.fim_em);
  assert.equal(statusPublico('ATIVO', def.inicio_em, def.fim_em, ini - 1), 'AGENDADO');
  assert.equal(statusPublico('ATIVO', def.inicio_em, def.fim_em, ini + 1), 'PUBLICADO');
  assert.equal(statusPublico('ATIVO', def.inicio_em, def.fim_em, fim + 1), 'ENCERRADO');
  assert.equal(statusPublico('ENCERRADO', def.inicio_em, def.fim_em, ini + 1), 'ENCERRADO');
  assert.equal(statusPublico('DESPUBLICADO', def.inicio_em, def.fim_em, ini + 1), 'RASCUNHO');
});

test('validação: obrigatória, escala, opções, área e gestor, tamanho do texto', () => {
  const base = { formulario_id: U(1), versao: 3, area_id: U(10), gestor_id: U(20), turnstile_token: 't' };
  assert.equal(validarEnvio(def, { ...base, respostas: [{ pergunta_id: U(40), valor: 8 }] }).ok, true);
  const r1 = validarEnvio(def, { ...base, respostas: [] });
  assert.equal(r1.erros[U(40)], 'Esta pergunta é obrigatória.');
  assert.ok(validarEnvio(def, { ...base, respostas: [{ pergunta_id: U(40), valor: 11 }] }).erros[U(40)]);
  assert.ok(validarEnvio(def, { ...base, respostas: [{ pergunta_id: U(40), valor: 7.5 }] }).erros[U(40)]);
  assert.ok(validarEnvio(def, { ...base, respostas: [{ pergunta_id: U(40), valor: 8 }, { pergunta_id: U(42), opcoes: [U(99)] }] }).erros[U(42)]);
  assert.ok(validarEnvio(def, { ...base, area_id: U(11), respostas: [{ pergunta_id: U(40), valor: 8 }] }).erros.area_id);
  assert.ok(validarEnvio(def, { ...base, respostas: [{ pergunta_id: U(40), valor: 8 }, { pergunta_id: U(41), valor: 'x'.repeat(2001) }] }).erros[U(41)]);
  assert.ok(validarEnvio(def, { ...base, respostas: [{ pergunta_id: U(40), valor: 8 }, { pergunta_id: U(77), valor: 1 }] }).erros[U(77)]);
});

test('definição pública: agendado não expõe campos', () => {
  const linha: any = { id: def.id, slug: def.slug, versao: 3, estado: 'ATIVO', inicio_em: def.inicio_em, fim_em: def.fim_em, recorrencia: 'QUINZENAL', definicao: def };
  const pub = montarDefinicaoPublica(linha, Date.parse(def.inicio_em) - 1000) as any;
  assert.equal(pub.status, 'AGENDADO');
  assert.equal(pub.topicos, undefined);
  const aberto = montarDefinicaoPublica(linha, Date.parse(def.inicio_em) + 20 * 864e5) as any;
  assert.equal(aberto.status, 'PUBLICADO');
  assert.equal(aberto.ciclo_atual.numero, 2);
  assert.equal(aberto.topicos.length, 1);
});

// ---- POST com fetch simulado (Turnstile + Supabase) ----
function ambiente(definicao: any = def) {
  const kv = new Map<string, string>();
  const fila = new Map<string, any>();
  const env: any = {
    SUPABASE_URL: 'https://x.supabase.co', SUPABASE_SERVICE_ROLE_KEY: 'k', TURNSTILE_SECRET_KEY: 's', ALLOWED_ORIGINS: 'https://dominio.com.br',
    RATE_LIMIT_PER_HOUR: '10', RATE_KV: { get: async (k: string) => kv.get(k) ?? null, put: async (k: string, v: string) => { kv.set(k, v); } },
  };
  const linha = { id: def.id, slug: def.slug, versao: 3, estado: 'ATIVO', inicio_em: def.inicio_em, fim_em: def.fim_em, recorrencia: 'QUINZENAL', definicao };
  (globalThis as any).fetch = async (url: string, init: any) => {
    if (url.includes('turnstile')) {
      const t = init.body.get('response');
      return new Response(JSON.stringify({ success: t === 'ok' || t === 'outro-site', hostname: t === 'outro-site' ? 'site-falso.com' : 'dominio.com.br' }));
    }
    if (url.includes('formularios_publicados')) return new Response(JSON.stringify([linha]));
    if (url.includes('rpc/enfileirar_envio')) {
      const b = JSON.parse(init.body); const novo = !fila.has(b.p_id); if (novo) fila.set(b.p_id, b.p_payload);
      return new Response(JSON.stringify({ id: b.p_id, novo }));
    }
    return new Response('{}', { status: 404 });
  };
  return { env, fila };
}
const post = (corpo: unknown, ip = '1.2.3.4') => new Request('https://api/api/formularios/enviar', {
  method: 'POST', headers: { 'Content-Type': 'application/json', 'CF-Connecting-IP': ip, Origin: 'https://dominio.com.br' }, body: JSON.stringify(corpo),
});

test('POST: grava na fila com ciclo, sem token; reenvio com o mesmo UUID não duplica', async () => {
  const { env, fila } = ambiente();
  const agora = Date.parse(def.inicio_em) + 15 * 864e5;
  const corpo = { envio_id: U(900), formulario_id: U(1), versao: 3, area_id: U(10), gestor_id: U(20), respostas: [{ pergunta_id: U(40), valor: 9 }], turnstile_token: 'ok' };
  const r = await receberEnvio(post(corpo), env, agora);
  assert.equal(r.status, 200);
  assert.deepEqual(await r.json(), { success: true, message: 'Formulário recebido com sucesso', id: U(900) });
  const p = fila.get(U(900));
  assert.equal(p.ciclo, 2);
  assert.equal(p.status, 'COMPLETA');
  assert.equal(p.turnstile_token, undefined);
  assert.equal(JSON.stringify(p).includes('1.2.3.4'), false, 'IP nunca vai para a fila');
  const r2 = await receberEnvio(post(corpo), env, agora);
  assert.equal(r2.status, 200);
  assert.equal(fila.size, 1);
});

test('POST: Turnstile inválido, fora do período e limite por IP', async () => {
  const { env } = ambiente();
  const base = { formulario_id: U(1), versao: 3, area_id: U(10), gestor_id: U(20), respostas: [{ pergunta_id: U(40), valor: 9 }] };
  assert.equal((await receberEnvio(post({ ...base, turnstile_token: 'ruim' }), env, Date.parse(def.inicio_em) + 1000)).status, 403);
  assert.equal((await receberEnvio(post({ ...base, turnstile_token: 'outro-site' }), env, Date.parse(def.inicio_em) + 1000)).status, 403,
    'token válido gerado em outro domínio é recusado');
  assert.equal((await receberEnvio(post({ ...base, turnstile_token: 'ok' }), env, Date.parse(def.inicio_em) - 1000)).status, 409);
  let ultimo = 0;
  for (let i = 0; i < 11; i++) ultimo = (await receberEnvio(post({ ...base, turnstile_token: 'ok' }, '9.9.9.9'), env, Date.now())).status;
  assert.equal(ultimo, 429);
});

// ---- Rodízio: um tópico por ciclo ----
const defRodizio: any = {
  ...def, topico_por_ciclo: true,
  topicos: [
    def.topicos[0],
    { id: U(31), titulo: 'Benefícios', descricao: '', perguntas: [
      { id: U(60), tipo: 'ESCALA', titulo: 'Benefícios atendem?', orientacao: '', obrigatoria: true, minimo: 0, maximo: 10, rotulo_minimo: 'Nada', rotulo_maximo: 'Totalmente', aparencia: 'ZONAS', opcoes: [] },
    ] },
    { id: U(32), titulo: 'Sem perguntas', descricao: '', perguntas: [] },
  ],
};
const emCiclo = (n: number) => Date.parse(def.inicio_em) + (n - 1) * 14 * 864e5 + 3600e3;

test('rodízio: cada ciclo libera um tópico, na ordem, e recomeça (tópico vazio é pulado)', () => {
  const linha: any = { id: def.id, slug: def.slug, versao: 3, estado: 'ATIVO', inicio_em: def.inicio_em, fim_em: def.fim_em, recorrencia: 'QUINZENAL', definicao: defRodizio };
  const titulos = (n: number) => (montarDefinicaoPublica(linha, emCiclo(n)) as any).topicos.map((t: any) => t.titulo);
  assert.deepEqual(titulos(1), ['Liderança']);
  assert.deepEqual(titulos(2), ['Benefícios']);
  assert.deepEqual(titulos(3), ['Liderança'], 'depois do último tópico recomeça');
  const semRodizio: any = { ...linha, definicao: { ...defRodizio, topico_por_ciclo: false } };
  assert.equal((montarDefinicaoPublica(semRodizio, emCiclo(2)) as any).topicos.length, 3, 'sem rodízio, todos os tópicos');
});

test('rodízio: envio aceita só o tópico do ciclo; resposta do tópico anterior pede recarregar', async () => {
  const { env, fila } = ambiente(defRodizio);
  const base = { formulario_id: U(1), versao: 3, area_id: U(10), gestor_id: U(20), turnstile_token: 'ok' };
  const ok = await receberEnvio(post({ ...base, envio_id: U(901), respostas: [{ pergunta_id: U(60), valor: 7 }] }, '5.5.5.5'), env, emCiclo(2));
  assert.equal(ok.status, 200, 'no 2º ciclo basta responder Benefícios (as obrigatórias de Liderança não são exigidas)');
  assert.equal(fila.get(U(901)).ciclo, 2);
  const velho = await receberEnvio(post({ ...base, envio_id: U(902), respostas: [{ pergunta_id: U(40), valor: 9 }] }, '6.6.6.6'), env, emCiclo(2));
  assert.equal(velho.status, 409);
  assert.equal(((await velho.json()) as any).code, 'VERSAO_DESATUALIZADA');
});
