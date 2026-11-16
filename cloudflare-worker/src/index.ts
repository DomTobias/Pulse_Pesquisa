// API REST pública do Pulse. Dois endpoints públicos e nenhum administrativo.
import type { Env } from './types.ts';
import { obterDefinicao } from './controllers/definicaoController.ts';
import { receberEnvio } from './controllers/envioController.ts';
import { cabecalhosCors, erro } from './middlewares/http.ts';
import { SupabaseError } from './services/supabase.ts';

export default {
  async fetch(req: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
    const url = new URL(req.url);
    const caminho = url.pathname.replace(/\/+$/, '');

    if (req.method === 'OPTIONS') return new Response(null, { status: 204, headers: cabecalhosCors(env, req) });

    try {
      if (caminho === '/api/formularios/enviar') {
        if (req.method !== 'POST') return erro(env, req, 405, 'Método não permitido.');
        return await receberEnvio(req, env);
      }
      const m = caminho.match(/^\/api\/formularios\/([^/]+)$/);
      if (m) {
        if (req.method !== 'GET') return erro(env, req, 405, 'Método não permitido.');
        let slug: string;
        try { slug = decodeURIComponent(m[1]); } catch { return erro(env, req, 404, 'Formulário não encontrado.'); }
        return await obterDefinicao(req, env, ctx, slug);
      }
      return erro(env, req, 404, 'Rota não encontrada.');
    } catch (e) {
      // Logs sem tokens nem dados pessoais
      console.error(JSON.stringify({ nivel: 'ERROR', rota: caminho, erro: e instanceof Error ? e.message : String(e) }));
      if (e instanceof SupabaseError) return erro(env, req, 503, 'Serviço temporariamente indisponível. Tente novamente em instantes.', { code: 'INDISPONIVEL' });
      return erro(env, req, 500, 'Erro inesperado. Tente novamente.');
    }
  },
};
