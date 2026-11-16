// Utilitários da página pública.

/** Escapa texto vindo da definição antes de montar HTML (proteção contra XSS). */
export function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** Aceita apenas URLs https (ou caminhos/dados locais da prévia) para imagens. */
export function urlSegura(u) {
  const s = String(u ?? '');
  return /^(https:\/\/|\/|data:image\/(png|jpe?g|webp|svg\+xml);base64,)/i.test(s) ? s : '';
}

export function uuid() {
  if (crypto.randomUUID) return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

export function maskPhone(v) {
  const d = String(v || '').replace(/\D/g, '').slice(0, 11);
  if (d.length <= 2) return d.length ? `(${d}` : '';
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

/** "2026-11-18" → "18/11/2026" */
export function fmtData(iso) {
  if (!iso) return '';
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
  if (m && iso.length === 10) return `${m[3]}/${m[2]}/${m[1]}`;
  const d = new Date(iso);
  return isNaN(d) ? '' : d.toLocaleDateString('pt-BR');
}
export function fmtDataHora(iso, fuso) {
  const d = new Date(iso);
  if (isNaN(d)) return '';
  try {
    return d.toLocaleString('pt-BR', { timeZone: fuso || undefined, day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).replace(',', ' às');
  } catch { return d.toLocaleString('pt-BR'); }
}

// Gradiente oficial da escala 0 a 10 (vermelho #C8102E até verde #34A853, 11 tons)
export const SCALE_COLORS = ['#C8102E', '#E01F26', '#F04E23', '#F57A1F', '#F9A11B', '#F7C21A', '#E2D51B', '#C3D923', '#9DCF2E', '#6FBE3F', '#34A853'];

const ICONES = {
  check: '<path d="M20 6 9 17l-5-5"/>',
  left: '<path d="m15 18-6-6 6-6"/>',
  right: '<path d="m9 18 6-6-6-6"/>',
  clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
  lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  alert: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
  edit: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
  send: '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  rotate: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
  info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
  eye: '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
  up: '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>',
  down: '<path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/>',
};
export function icon(nome, tam = 18, traco = 2) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${tam}" height="${tam}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${traco}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONES[nome] || ''}</svg>`;
}

// Elementos gráficos da capa (ondas, coração e traço amarelo)
export const SVG = {
  waves: (cls) => `<svg class="${cls}" viewBox="0 0 400 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H400C330 10 250 30 170 62C110 86 50 108 0 118Z" fill="var(--navy)"/><path d="M0 118C50 108 110 86 170 62C250 30 330 10 400 0H372C300 18 230 40 160 70C100 95 48 112 0 124Z" fill="var(--yellow)"/></svg>`,
  heart: (cls) => `<svg class="${cls}" viewBox="0 0 160 40" aria-hidden="true" fill="none" stroke="var(--yellow)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 34C30 34 52 32 68 26"/><path d="M80 34C64 24 58 15 64 9C69 4 76 7 80 13C84 7 91 4 96 9C102 15 96 24 80 34Z"/><path d="M92 26C108 32 130 34 156 34"/></svg>`,
  swash: (cls) => `<svg class="${cls}" viewBox="0 0 280 12" preserveAspectRatio="none" aria-hidden="true"><path d="M4 8C70 2 190 2 276 6" fill="none" stroke="var(--yellow)" stroke-width="5" stroke-linecap="round"/></svg>`,
  waveBr: (cls) => `<svg class="${cls}" viewBox="0 0 300 160" preserveAspectRatio="none" aria-hidden="true"><path d="M300 18C210 40 120 110 0 160H300Z" fill="var(--navy)"/><path d="M300 10C206 34 116 104 0 156V160C120 112 210 44 300 22Z" fill="var(--yellow)"/></svg>`,
};
