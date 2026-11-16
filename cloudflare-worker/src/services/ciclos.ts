// Ciclos e status (seções 5.4 e 6.3).
// O ciclo N começa em início + (N − 1) × duração e termina um instante antes do próximo.
import type { Ciclo, DefinicaoArmazenada, Recorrencia, StatusPublico, Topico } from '../types.ts';

/**
 * Tópicos liberados no ciclo. Com rodízio, o ciclo N libera só o tópico (N − 1) mod total, contando apenas
 * tópicos que têm perguntas; sem rodízio (ou fora de um ciclo), todos.
 */
export function topicosDoCiclo(def: Pick<DefinicaoArmazenada, 'topico_por_ciclo' | 'topicos'>, numero: number | null): Topico[] {
  if (!def.topico_por_ciclo || !numero) return def.topicos;
  const comPerguntas = def.topicos.filter((t) => t.perguntas.length > 0);
  if (!comPerguntas.length) return def.topicos;
  return [comPerguntas[(numero - 1) % comPerguntas.length]];
}

const DIA = 86_400_000;
export const DURACAO_DIAS: Record<Recorrencia, number> = { NENHUMA: 0, SEMANAL: 7, QUINZENAL: 14, MENSAL: 30 };

/** Data local (AAAA-MM-DD) no fuso informado. */
export function dataLocal(ms: number, fuso: string): string {
  const p = new Intl.DateTimeFormat('en-CA', { timeZone: fuso, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date(ms));
  const v = (t: string) => p.find((x) => x.type === t)?.value ?? '';
  return `${v('year')}-${v('month')}-${v('day')}`;
}

export function cicloEm(inicioIso: string | null, recorrencia: Recorrencia, agora: number, fuso = 'America/Cuiaba'): Ciclo | null {
  const dias = DURACAO_DIAS[recorrencia] ?? 0;
  if (!dias || !inicioIso) return null;
  const inicio = Date.parse(inicioIso);
  if (Number.isNaN(inicio) || agora < inicio) return null;
  const dur = dias * DIA;
  const n = Math.floor((agora - inicio) / dur) + 1;
  const ini = inicio + (n - 1) * dur;
  const fim = ini + dur - 1;
  return { numero: n, inicio: dataLocal(ini, fuso), fim: dataLocal(fim, fuso), inicio_ms: ini, fim_ms: fim };
}

/** Status exibido, calculado pelas datas e pelo estado da publicação. */
export function statusPublico(estado: string, inicioIso: string | null, fimIso: string | null, agora: number): StatusPublico {
  if (estado === 'DESPUBLICADO') return 'RASCUNHO';
  if (estado === 'ENCERRADO') return 'ENCERRADO';
  if (inicioIso && agora < Date.parse(inicioIso)) return 'AGENDADO';
  if (fimIso && agora > Date.parse(fimIso)) return 'ENCERRADO';
  return 'PUBLICADO';
}
