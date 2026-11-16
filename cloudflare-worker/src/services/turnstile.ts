// Validação do token do Cloudflare Turnstile (obrigatória em todo POST).
import type { Env } from '../types.ts';

/** Hostnames das origens permitidas (ALLOWED_ORIGINS). Vazio quando a lista aceita qualquer origem. */
function hostnamesPermitidos(env: Env): string[] {
  const lista = (env.ALLOWED_ORIGINS ?? '').split(',').map((s) => s.trim()).filter(Boolean);
  if (lista.includes('*')) return [];
  return lista.flatMap((o) => {
    try { return [new URL(o).hostname]; } catch { return []; }
  });
}

export async function validarTurnstile(env: Env, token: string, ip: string | null): Promise<boolean> {
  if (!token || typeof token !== 'string' || token.length > 2048) return false;
  const corpo = new FormData();
  corpo.append('secret', env.TURNSTILE_SECRET_KEY);
  corpo.append('response', token);
  if (ip) corpo.append('remoteip', ip);
  try {
    const r = await fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', { method: 'POST', body: corpo });
    if (!r.ok) return false;
    const d = (await r.json()) as { success?: boolean; 'error-codes'?: string[]; hostname?: string };
    // O token precisa ter sido gerado na própria página do participante (não em outro site com a mesma site key).
    const hosts = hostnamesPermitidos(env);
    const hostOk = hosts.length === 0 || (typeof d.hostname === 'string' && hosts.includes(d.hostname));
    if (d.success !== true || !hostOk) {
      // Só os códigos de erro e o hostname; nunca o token ou o IP.
      console.warn(JSON.stringify({ nivel: 'WARN', evento: 'turnstile', codigos: d['error-codes'] ?? [], hostname: d.hostname ?? null, host_ok: hostOk }));
      return false;
    }
    return true;
  } catch {
    return false;
  }
}
