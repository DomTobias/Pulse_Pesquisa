// CORS, respostas JSON e cabeçalhos de segurança.
import type { Env } from '../types.ts';

export function origemPermitida(env: Env, req: Request): string | null {
  const origem = req.headers.get('Origin');
  if (!origem) return null;
  const lista = (env.ALLOWED_ORIGINS ?? '').split(',').map((s) => s.trim()).filter(Boolean);
  return lista.includes(origem) || lista.includes('*') ? origem : null;
}

export function cabecalhosCors(env: Env, req: Request): Record<string, string> {
  const o = origemPermitida(env, req);
  return o
    ? { 'Access-Control-Allow-Origin': o, 'Access-Control-Allow-Methods': 'GET, POST, OPTIONS', 'Access-Control-Allow-Headers': 'Content-Type', 'Access-Control-Max-Age': '86400', Vary: 'Origin' }
    : { Vary: 'Origin' };
}

export function json(env: Env, req: Request, corpo: unknown, status = 200, extra: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(corpo), {
    status,
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      'X-Content-Type-Options': 'nosniff',
      'Referrer-Policy': 'no-referrer',
      ...cabecalhosCors(env, req),
      ...extra,
    },
  });
}

export function erro(env: Env, req: Request, status: number, message: string, extra: Record<string, unknown> = {}): Response {
  return json(env, req, { success: false, message, ...extra }, status, { 'Cache-Control': 'no-store' });
}
