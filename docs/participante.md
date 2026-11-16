# Pulse — Parte 1: Participante

Tudo o que o participante acessa pela internet (este guia fica em `docs/` para não ser publicado junto com a página). Esta parte é independente do gerenciador: não lê arquivos dele,
não acessa o MySQL e não conhece a rede interna.

```
frontend/             página pública (Cloudflare Pages, sem build)
  index.html
  _redirects          /formulario/<slug> → index.html
  _headers            CSP e cabeçalhos de segurança
  assets/js/config.js endereço do Worker e site key do Turnstile (dados públicos)
  assets/js/app.js    aplicação (módulos ES): app, util, validacao, api
  assets/css/pulse.css
  assets/fonts, assets/vendor (Bootstrap)
../cloudflare-worker/ API pública (GET /api/formularios/:slug, POST /api/formularios/enviar)
../supabase/          migrações: fila_envios, formularios_publicados, funções, pg_cron, bucket
```

## Publicar

1. Supabase: aplique `supabase/migrations/` (veja `supabase/README.md`).
2. Worker: `cd cloudflare-worker && npm install && npm test`, defina os secrets e `npx wrangler deploy`.
3. Ajuste `assets/js/config.js` (URL do Worker e site key) e os domínios em `_headers`.
4. `npx wrangler pages deploy frontend --project-name pulse` (ou conecte o repositório no Pages com saída `frontend`).

Passo a passo completo: `docs/instalacao.md`, Parte 1.

## Relação com o gerenciador

O gerenciador mostra uma prévia desta página usando **uma cópia** destes arquivos
(`gerenciador/public/assets/participante/`). Ao alterar `assets/js/*.js` ou `assets/css/pulse.css`, atualize a
cópia no lado do gerenciador:

```powershell
cd gerenciador
php scripts/atualizar-previa.php ..\frontend
```

O teste `php tests/run.php` do gerenciador avisa se as duas versões estiverem diferentes.
