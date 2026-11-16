// Integração com o Supabase via PostgREST, usando a chave service_role (secret do Worker).
import type { DefinicaoArmazenada, Env } from '../types.ts';

export class SupabaseError extends Error {
  readonly status: number;
  constructor(message: string, status = 502) { super(message); this.status = status; }
}

export interface LinhaPublicada {
  id: string;
  slug: string;
  versao: number;
  estado: 'ATIVO' | 'ENCERRADO' | 'DESPUBLICADO';
  inicio_em: string | null;
  fim_em: string | null;
  recorrencia: string;
  definicao: DefinicaoArmazenada;
}

const TIMEOUT_MS = 8000;

async function chamar(env: Env, caminho: string, init: RequestInit = {}): Promise<Response> {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    return await fetch(`${env.SUPABASE_URL.replace(/\/$/, '')}${caminho}`, {
      ...init,
      signal: ctrl.signal,
      headers: {
        apikey: env.SUPABASE_SERVICE_ROLE_KEY,
        Authorization: `Bearer ${env.SUPABASE_SERVICE_ROLE_KEY}`,
        'Content-Type': 'application/json',
        ...(init.headers ?? {}),
      },
    });
  } catch (e) {
    throw new SupabaseError(ctrl.signal.aborted ? 'Tempo esgotado ao contatar o banco intermediário.' : 'Falha de rede ao contatar o banco intermediário.', 503);
  } finally {
    clearTimeout(t);
  }
}

const COLUNAS = 'id,slug,versao,estado,inicio_em,fim_em,recorrencia,definicao';

export async function buscarPorSlug(env: Env, slug: string): Promise<LinhaPublicada | null> {
  const r = await chamar(env, `/rest/v1/formularios_publicados?slug=eq.${encodeURIComponent(slug)}&select=${COLUNAS}&limit=1`);
  if (!r.ok) throw new SupabaseError(`Supabase respondeu ${r.status} ao buscar a definição.`);
  const linhas = (await r.json()) as LinhaPublicada[];
  return linhas[0] ?? null;
}

export async function buscarPorId(env: Env, id: string): Promise<LinhaPublicada | null> {
  const r = await chamar(env, `/rest/v1/formularios_publicados?id=eq.${encodeURIComponent(id)}&select=${COLUNAS}&limit=1`);
  if (!r.ok) throw new SupabaseError(`Supabase respondeu ${r.status} ao buscar a definição.`);
  const linhas = (await r.json()) as LinhaPublicada[];
  return linhas[0] ?? null;
}

export async function enfileirar(env: Env, id: string, formularioId: string, payload: unknown): Promise<{ novo: boolean }> {
  const r = await chamar(env, '/rest/v1/rpc/enfileirar_envio', {
    method: 'POST',
    body: JSON.stringify({ p_id: id, p_formulario_id: formularioId, p_payload: payload }),
  });
  if (!r.ok) throw new SupabaseError(`Supabase respondeu ${r.status} ao gravar o envio.`);
  const res = (await r.json()) as { novo: boolean };
  return { novo: !!res.novo };
}
