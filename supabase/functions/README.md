# Funções

O Pulse não usa Edge Functions: a API pública roda no Cloudflare Worker (`cloudflare-worker/`)
e toda a lógica do banco está nas funções SQL de `../migrations/20261110000002_funcoes.sql`.
Esta pasta existe apenas para manter a estrutura prevista na especificação.
