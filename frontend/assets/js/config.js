// Configuração pública do frontend (sem segredos).
// apiBase: endereço do Cloudflare Worker. turnstileSiteKey: chave PÚBLICA do Turnstile.
window.PULSE_CONFIG = window.PULSE_CONFIG || {
  apiBase: 'https://pulse-api.pulse-cloudflare-worker.workers.dev',
  turnstileSiteKey: '0x4AAAAAAF9bX_qi-fTBKR5e'
};
