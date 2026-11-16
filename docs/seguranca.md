# Segurança

## Onde ficam os segredos

| Segredo | Onde | Nunca em |
|---|---|---|
| `SUPABASE_SERVICE_ROLE_KEY` | `wrangler secret put` (Worker) e `sincronizador/.env` | frontend, gerenciador, Git |
| `TURNSTILE_SECRET_KEY` | secret do Worker | frontend, Git |
| `RATE_SALT_SECRET` | secret do Worker | qualquer outro lugar |
| Senha do MySQL (sincronizador) | `sincronizador/.env` | Git, logs |
| Senha do MySQL (gerenciador) | `gerenciador/config/config.php` | Git, logs |
| Site key do Turnstile | `frontend/assets/js/config.js` | — (é pública) |

`.env`, `config/config.php`, `storage/` e `logs/` estão no `.gitignore`. Os arquivos `*.example` não têm segredos.

## Frontend público

- Só conversa com o Worker (`connect-src`). CSP em `frontend/_headers`: scripts apenas do próprio domínio e do
  Turnstile; imagens do próprio domínio e do Storage do Supabase; `frame-ancestors 'none'`.
- Todo texto vindo da definição é escapado antes de ir para o HTML; URLs de imagem só são aceitas se forem `https://`.
- Respostas ficam só em memória (sem `localStorage`/`sessionStorage`/cookies).

## Worker

- CORS restrito a `ALLOWED_ORIGINS`.
- Corpo limitado (128 KB), JSON obrigatório, UUIDs validados.
- Turnstile verificado no servidor a cada envio, incluindo o domínio em que o token foi gerado (precisa estar
  em `ALLOWED_ORIGINS`).
- Limite por IP via KV (padrão 10/h): chave = SHA-256(sal secreto | dia | IP). O IP não é gravado em lugar nenhum.
- Toda resposta é revalidada contra a definição publicada (tipos, opções, obrigatórias, limites, tamanho de texto).
- O payload na fila não contém IP, user agent nem token.

## Supabase

- RLS habilitado nas tabelas, **sem políticas** para `anon` e `authenticated`; permissões revogadas.
- Funções com `EXECUTE` apenas para `service_role`.
- Bucket `pulse-capas` público só para leitura de imagens (limite 1,5 MB, tipos PNG/JPG/WEBP/SVG).

## Sincronizador

- Só faz conexões de saída (HTTPS para o Supabase, local para o MySQL). Nenhuma porta aberta.
- Usuário MySQL próprio com permissões mínimas (veja `instalacao.md`).
- Logs em JSON com rotação diária; senhas, tokens e chaves são mascarados antes de gravar.
- Imagens: nome validado (`<sha256>.<ext>`), hash conferido e tamanho ≤ 1,5 MB antes do upload.

## Gerenciador

- **Uso exclusivo na rede interna.** Não publique a porta do gerenciador no roteador nem em proxy reverso.
- Sessão com cookie `HttpOnly`, `SameSite=Strict`, `Secure` quando em HTTPS, ID regenerado no login,
  expiração por inatividade (30 min, configurável).
- Senhas com `password_hash` (bcrypt), troca obrigatória no primeiro acesso, mínimo de 10 caracteres com letras
  e números, bloqueio de 15 min após 5 tentativas erradas, mensagem de erro genérica.
- CSRF em todo POST (campo `_csrf` ou cabeçalho `X-CSRF-Token`).
- PDO com prepared statements reais (`ATTR_EMULATE_PREPARES = false`).
- Saída sempre escapada (`e()`); JSON embutido com `JSON_HEX_TAG`.
- CSP sem scripts inline, `X-Frame-Options: SAMEORIGIN`, `nosniff`, `Referrer-Policy`.
- Uploads: tipo verificado pelo conteúdo (`finfo`), nome pelo hash. SVG recusado se tiver script, eventos,
  entidades numéricas, DOCTYPE/ENTITY, animações, links ou CSS externos.
- `Cache-Control: no-store` nas páginas, HSTS quando em HTTPS, sem `X-Powered-By`.
- Perfis: **ADMIN** (edita, publica, configura) e **LEITOR** (só consulta). Não é possível remover o último
  administrador.
- Auditoria de login, publicação, edição, exportação, visualização de resposta e configurações (sem senhas).
- Bootstrap, ícones, Chart.js, QR Code e fontes são servidos localmente (sem CDN).

## Servidor interno e ferramentas de segurança (EDR, scanner, inventário)

O que o servidor executa e o que a equipe de segurança deve esperar ver:

| Item | Comportamento esperado | Ação recomendada |
|---|---|---|
| `python.exe` (serviço `PulseSincronizador`, via NSSM) | Conexões HTTPS de saída a cada 20–30 s para `<projeto>.supabase.co` (porta 443) e TCP local para o MySQL | Liberar o domínio no proxy/firewall e registrar a periodicidade no EDR (padrão de "beaconing" legítimo) |
| `nssm.exe` | Gerenciador de serviço (binário sem assinatura digital, às vezes usado por malware) | Baixar só de nssm.cc, registrar o hash SHA-256 na lista de permissões do EDR |
| Conta do serviço | `NT AUTHORITY\LocalService` (padrão do `instalar-servico.ps1`), nunca LocalSystem | Ou uma conta de serviço dedicada, sem login interativo |
| Apache/IIS + PHP | Atende só a rede interna | Servir em HTTPS com certificado interno; `php -S` só para teste |
| MySQL | Só conexões locais | `bind-address = 127.0.0.1` no `my.ini`; versão com suporte (8.4 LTS) |

- **Pastas:** instale em `C:\Pulse\...`, fora do perfil de usuário. Executáveis rodando de `Desktop` ou `AppData`
  são tratados como anomalia por EDR.
- **Python:** instalador oficial "para todos os usuários" (Program Files), assim aparece corretamente no inventário.
- **Segredos em disco:** `sincronizador\.env` e `gerenciador\config\config.php` ficam com permissão só para
  Administradores, SYSTEM e a conta que os lê (o script do serviço aplica isso ao `.env`; aplique ao `config.php`
  para a conta do Apache/IIS).
- **Proxy corporativo:** o sincronizador respeita `HTTPS_PROXY` (variável de ambiente do serviço via
  `nssm set PulseSincronizador AppEnvironmentExtra HTTPS_PROXY=http://proxy:porta`).
- **Scanner de vulnerabilidades:** sem HTTPS no gerenciador, o login trafega em texto puro e o scanner aponta
  "cookie sem Secure" e "sem HSTS". Com HTTPS, o cookie vira `Secure` e o HSTS é enviado automaticamente.
- **Só estas pastas vão para o servidor:** `gerenciador\` (com `vendor\` do Composer), `sincronizador\` e
  `database\`. Não copie `prototipos\`, `frontend\`, `cloudflare-worker\` nem `node_modules\`.

## Checklist antes de colocar no ar

- [ ] Trocar a senha do administrador inicial (o sistema obriga no primeiro acesso).
- [ ] `config.php` com `debug => false`.
- [ ] Secrets do Worker definidos; `ALLOWED_ORIGINS` com o domínio do Pages.
- [ ] `_headers` com o domínio real do Worker e do Supabase no CSP.
- [ ] Usuários MySQL separados para gerenciador e sincronizador, sem acesso remoto.
- [ ] Firewall do Windows bloqueando acesso externo à porta do gerenciador.
- [ ] Backup diário do MySQL (`mysqldump`) fora do servidor.
- [ ] Gerar chaves novas (Supabase `sb_secret`, Turnstile secret, senha do banco do Supabase) se as atuais já
      circularam fora dos arquivos de configuração (chat, e-mail, print).
- [ ] Domínio da página do participante em *Hostnames* do widget Turnstile.
- [ ] Hash do `nssm.exe` e domínio do Supabase liberados com a equipe de segurança (EDR/proxy).
