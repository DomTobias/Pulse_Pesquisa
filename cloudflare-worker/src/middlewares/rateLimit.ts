// Limite de envios por IP com contagem em KV.
// O IP é usado só aqui: guardamos apenas um hash com sal que muda a cada dia,
// com expiração de 1 hora. Nada disso vai para a fila nem para o MySQL.
import type { Env } from '../types.ts';

async function sha256(texto: string): Promise<string> {
  const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(texto));
  return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

export async function chaveIp(env: Env, ip: string, agora = Date.now()): Promise<string> {
  const dia = new Date(agora).toISOString().slice(0, 10); // sal rotativo diário
  const hora = Math.floor(agora / 3_600_000);
  return `rl:${hora}:${await sha256(`${env.RATE_SALT_SECRET ?? 'pulse'}|${dia}|${ip}`)}`;
}

/** Retorna true se o envio ainda está dentro do limite. */
export async function dentroDoLimite(env: Env, ip: string | null): Promise<boolean> {
  if (!ip || !env.RATE_KV) return true;
  const limite = Number(env.RATE_LIMIT_PER_HOUR ?? '10') || 10;
  const chave = await chaveIp(env, ip);
  const atual = Number((await env.RATE_KV.get(chave)) ?? '0');
  if (atual >= limite) return false;
  await env.RATE_KV.put(chave, String(atual + 1), { expirationTtl: 3600 });
  return true;
}
