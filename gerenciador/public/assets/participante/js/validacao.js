// Validação no navegador (repetida no Worker).
export const MAX_TEXTO = 2000;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export const vazio = (v) => v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);

export function validarPergunta(p, v) {
  if (p.tipo === 'INFO') return null;
  if (vazio(v)) return p.obrigatoria ? 'Esta pergunta é obrigatória.' : null;
  switch (p.tipo) {
    case 'CURTA': case 'LONGA':
      return String(v).length > MAX_TEXTO ? `Use no máximo ${MAX_TEXTO} caracteres.` : null;
    case 'EMAIL': return EMAIL_RE.test(String(v).trim()) ? null : 'Informe um e-mail válido, como nome@empresa.com.br.';
    case 'TELEFONE': { const d = String(v).replace(/\D/g, ''); return d.length >= 10 && d.length <= 11 ? null : 'Informe o telefone com DDD, como (65) 91234-5678.'; }
    case 'NUMERO': {
      const n = Number(String(v).replace(',', '.'));
      if (!Number.isFinite(n)) return 'Informe apenas números.';
      if (p.minimo !== null && p.minimo !== undefined && n < p.minimo) return `O valor mínimo é ${p.minimo}.`;
      if (p.maximo !== null && p.maximo !== undefined && n > p.maximo) return `O valor máximo é ${p.maximo}.`;
      return null;
    }
    case 'DATA': { const d = new Date(`${v}T12:00`); return isNaN(d) || d.getFullYear() < 1900 || d.getFullYear() > 2100 ? 'Informe uma data válida.' : null; }
    default: return null;
  }
}

/** Converte o estado da página no array "respostas" do contrato 13.2. */
export function montarRespostas(def, respostas) {
  const lista = [];
  def.topicos.forEach((t) => t.perguntas.forEach((p) => {
    const v = respostas[p.id];
    if (p.tipo === 'INFO' || vazio(v)) return;
    if (['UNICA', 'LISTA'].includes(p.tipo)) lista.push({ pergunta_id: p.id, opcoes: [v] });
    else if (p.tipo === 'MULTIPLA') lista.push({ pergunta_id: p.id, opcoes: v });
    else if (p.tipo === 'NUMERO') lista.push({ pergunta_id: p.id, valor: Number(String(v).replace(',', '.')) });
    else lista.push({ pergunta_id: p.id, valor: typeof v === 'string' ? v.trim() : v });
  }));
  return lista;
}
