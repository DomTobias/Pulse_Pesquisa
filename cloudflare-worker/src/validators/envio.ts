// Validação do envio contra a definição publicada (seção 7).
import type { DefinicaoArmazenada, EnvioRecebido, Pergunta, RespostaEnviada } from '../types.ts';

export const MAX_TEXTO = 2000;
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const DATA_RE = /^\d{4}-\d{2}-\d{2}$/;

export const isUuid = (v: unknown): v is string => typeof v === 'string' && UUID_RE.test(v);

export interface ResultadoValidacao {
  ok: boolean;
  erros: Record<string, string>;
  /** Respostas normalizadas, prontas para a fila */
  respostas: RespostaEnviada[];
}

const vazio = (r: RespostaEnviada | undefined) =>
  !r || ((r.valor === undefined || r.valor === null || r.valor === '') && (!r.opcoes || r.opcoes.length === 0));

function validarResposta(p: Pergunta, r: RespostaEnviada): string | null {
  const v = r.valor;
  switch (p.tipo) {
    case 'CURTA':
    case 'LONGA': {
      if (typeof v !== 'string') return 'Texto inválido.';
      if (v.length > MAX_TEXTO) return `Use no máximo ${MAX_TEXTO} caracteres.`;
      return null;
    }
    case 'EMAIL':
      return typeof v === 'string' && v.length <= 254 && EMAIL_RE.test(v.trim()) ? null : 'E-mail inválido.';
    case 'TELEFONE': {
      const d = String(v ?? '').replace(/\D/g, '');
      return d.length >= 10 && d.length <= 11 ? null : 'Telefone inválido.';
    }
    case 'DATA': {
      if (typeof v !== 'string' || !DATA_RE.test(v)) return 'Data inválida.';
      const d = new Date(`${v}T12:00:00Z`);
      return Number.isNaN(d.getTime()) || d.getUTCFullYear() < 1900 || d.getUTCFullYear() > 2100 ? 'Data inválida.' : null;
    }
    case 'NUMERO': {
      const n = typeof v === 'number' ? v : Number(String(v).replace(',', '.'));
      if (!Number.isFinite(n)) return 'Número inválido.';
      if (p.minimo !== null && n < p.minimo) return `O valor mínimo é ${p.minimo}.`;
      if (p.maximo !== null && n > p.maximo) return `O valor máximo é ${p.maximo}.`;
      return null;
    }
    case 'ESCALA': {
      const mn = p.minimo ?? 0, mx = p.maximo ?? 10;
      return typeof v === 'number' && Number.isInteger(v) && v >= mn && v <= mx ? null : `A nota deve ser um inteiro de ${mn} a ${mx}.`;
    }
    case 'SIM_NAO':
      return v === 'SIM' || v === 'NAO' ? null : 'Responda sim ou não.';
    case 'UNICA':
    case 'LISTA':
    case 'MULTIPLA': {
      const ids = r.opcoes ?? [];
      if (!Array.isArray(ids) || !ids.every(isUuid)) return 'Opção inválida.';
      if (p.tipo !== 'MULTIPLA' && ids.length !== 1) return 'Escolha uma opção.';
      if (new Set(ids).size !== ids.length) return 'Opção repetida.';
      const validas = new Set(p.opcoes.map((o) => o.id));
      return ids.every((i) => validas.has(i)) ? null : 'Opção inexistente.';
    }
    case 'INFO':
      return 'Texto informativo não recebe resposta.';
    default:
      return 'Tipo de pergunta desconhecido.';
  }
}

function normalizar(p: Pergunta, r: RespostaEnviada): RespostaEnviada {
  if (['UNICA', 'LISTA', 'MULTIPLA'].includes(p.tipo)) return { pergunta_id: p.id, opcoes: r.opcoes ?? [] };
  if (p.tipo === 'NUMERO') return { pergunta_id: p.id, valor: typeof r.valor === 'number' ? r.valor : Number(String(r.valor).replace(',', '.')) };
  if (p.tipo === 'TELEFONE') return { pergunta_id: p.id, valor: String(r.valor).replace(/\D/g, '') };
  if (typeof r.valor === 'string') return { pergunta_id: p.id, valor: r.valor.trim() };
  return { pergunta_id: p.id, valor: r.valor ?? null };
}

export function validarEnvio(def: DefinicaoArmazenada, envio: EnvioRecebido): ResultadoValidacao {
  const erros: Record<string, string> = {};
  const perguntas = new Map<string, Pergunta>();
  def.topicos.forEach((t) => t.perguntas.forEach((p) => perguntas.set(p.id, p)));

  if (!Array.isArray(envio.respostas)) return { ok: false, erros: { respostas: 'Formato inválido.' }, respostas: [] };
  if (envio.respostas.length > perguntas.size) erros.respostas = 'Respostas em excesso.';

  if (def.segmentado) {
    if (!isUuid(envio.area_id) || !def.areas.some((a) => a.id === envio.area_id)) erros.area_id = 'Selecione sua área.';
    const g = def.gestores.find((x) => x.id === envio.gestor_id);
    if (!isUuid(envio.gestor_id) || !g) erros.gestor_id = 'Selecione seu gestor.';
    else if (g.area_id && envio.area_id && g.area_id !== envio.area_id) erros.gestor_id = 'Gestor não pertence à área escolhida.';
  }

  if (def.exige_identificacao) {
    const nome = envio.identificacao?.nome?.trim() ?? '';
    const email = envio.identificacao?.email?.trim() ?? '';
    if (!nome || nome.length > 150) erros.identificacao_nome = 'Informe seu nome.';
    if (!EMAIL_RE.test(email) || email.length > 254) erros.identificacao_email = 'Informe um e-mail válido.';
  }

  const recebidas = new Map<string, RespostaEnviada>();
  for (const r of envio.respostas) {
    if (!r || !isUuid(r.pergunta_id) || !perguntas.has(r.pergunta_id)) { erros[String(r?.pergunta_id ?? 'pergunta')] = 'Pergunta inexistente.'; continue; }
    if (recebidas.has(r.pergunta_id)) { erros[r.pergunta_id] = 'Resposta duplicada.'; continue; }
    recebidas.set(r.pergunta_id, r);
  }

  const normalizadas: RespostaEnviada[] = [];
  for (const p of perguntas.values()) {
    const r = recebidas.get(p.id);
    if (p.tipo === 'INFO') { if (r && !vazio(r)) erros[p.id] = 'Texto informativo não recebe resposta.'; continue; }
    if (vazio(r)) { if (p.obrigatoria) erros[p.id] = 'Esta pergunta é obrigatória.'; continue; }
    const msg = validarResposta(p, r!);
    if (msg) erros[p.id] = msg; else normalizadas.push(normalizar(p, r!));
  }

  return { ok: Object.keys(erros).length === 0, erros, respostas: normalizadas };
}
