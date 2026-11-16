// GET /api/formularios/:slug — devolve a definição publicada (nunca respostas nem dados internos).
import type { Env } from '../types.ts';
import { buscarPorSlug, type LinhaPublicada } from '../services/supabase.ts';
import { cicloEm, statusPublico, topicosDoCiclo } from '../services/ciclos.ts';
import { erro, json } from '../middlewares/http.ts';

const SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
export const CACHE_SEGUNDOS = 60;

export function montarDefinicaoPublica(linha: LinhaPublicada, agora = Date.now()) {
  const d = linha.definicao;
  const status = statusPublico(linha.estado, linha.inicio_em, linha.fim_em, agora);
  const ciclo = status === 'PUBLICADO' ? cicloEm(linha.inicio_em, d.recorrencia, agora, d.fuso_horario || 'America/Cuiaba') : null;
  const base = {
    id: d.id,
    slug: d.slug,
    versao: linha.versao,
    titulo: d.titulo,
    descricao: d.descricao,
    status,
    inicio_em: linha.inicio_em,
    fim_em: linha.fim_em,
    recorrencia: d.recorrencia,
    topico_por_ciclo: !!d.topico_por_ciclo,
    fuso_horario: d.fuso_horario,
    ciclo_atual: ciclo ? { numero: ciclo.numero, inicio: ciclo.inicio, fim: ciclo.fim } : null,
    cor: d.cor,
    capa: d.capa,
  };
  // Agendado, encerrado ou despublicado: só o aviso, sem campos.
  if (status !== 'PUBLICADO') return base;
  return {
    ...base,
    segmentado: d.segmentado,
    exige_identificacao: d.exige_identificacao,
    permite_multiplas: d.permite_multiplas,
    formato: d.formato,
    mensagem_apresentacao: d.mensagem_apresentacao,
    mensagem_conclusao: d.mensagem_conclusao,
    areas: d.segmentado ? d.areas : [],
    gestores: d.segmentado ? d.gestores : [],
    topicos: topicosDoCiclo(d, ciclo ? ciclo.numero : null),
  };
}

export async function obterDefinicao(req: Request, env: Env, ctx: ExecutionContext, slug: string): Promise<Response> {
  if (!SLUG_RE.test(slug) || slug.length > 80) return erro(env, req, 404, 'Formulário não encontrado.');

  // Cache de borda de até 60 s (a resposta em cache não carrega cabeçalhos CORS de outra origem).
  const cache = (caches as unknown as { default: Cache }).default;
  const chave = new Request(`https://cache.pulse.internal/definicao/${slug}`);
  let corpo: string | null = null;
  const emCache = await cache.match(chave);
  if (emCache) corpo = await emCache.text();

  if (corpo === null) {
    const linha = await buscarPorSlug(env, slug);
    if (!linha) return erro(env, req, 404, 'Formulário não encontrado.');
    corpo = JSON.stringify(montarDefinicaoPublica(linha));
    ctx.waitUntil(cache.put(chave, new Response(corpo, { headers: { 'Cache-Control': `public, max-age=${CACHE_SEGUNDOS}` } })));
  }
  return json(env, req, JSON.parse(corpo), 200, { 'Cache-Control': `public, max-age=${CACHE_SEGUNDOS}` });
}
