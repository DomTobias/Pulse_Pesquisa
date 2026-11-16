// Comunicação com o Cloudflare Worker. O frontend nunca fala com o MySQL nem com o Supabase.
const base = () => String((window.PULSE_CONFIG || {}).apiBase || '').replace(/\/$/, '');

export class ErroApi extends Error {
  constructor(message, status, dados = {}) { super(message); this.status = status; this.dados = dados; }
}

async function pedir(caminho, init = {}, timeout = 15000) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), timeout);
  let r;
  try {
    r = await fetch(`${base()}${caminho}`, { ...init, signal: ctrl.signal, credentials: 'omit' });
  } catch {
    throw new ErroApi('Não foi possível conectar. Verifique sua internet e tente novamente.', 0);
  } finally { clearTimeout(t); }
  let dados = {};
  try { dados = await r.json(); } catch { /* corpo vazio */ }
  if (!r.ok) throw new ErroApi(dados.message || 'Erro ao comunicar com o servidor.', r.status, dados);
  return dados;
}

export const buscarDefinicao = (slug) => pedir(`/api/formularios/${encodeURIComponent(slug)}`);

export const enviarRespostas = (corpo) => pedir('/api/formularios/enviar', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(corpo),
});
