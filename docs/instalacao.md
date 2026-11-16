# Instalação

O Pulse tem duas partes instaladas separadamente:

- **Parte 1 — Participante** (nuvem): Supabase, Cloudflare Worker e Cloudflare Pages. Passos 1 a 8.
- **Parte 2 — Gerenciador** (rede local): MySQL, gerenciador PHP e sincronizador. Passos 9 a 16.

A ordem recomendada é a da lista, mas cada parte pode ser instalada, atualizada ou trocada de servidor sem mexer na
outra. A única ligação entre elas é o `.env` do sincronizador (URL e chave do Supabase).

---

## Parte 1 — Participante (nuvem)

Pré-requisitos: contas Cloudflare e Supabase, Node.js 20+.

### 1. Projeto Supabase

Crie um projeto (região mais próxima). Em *Database → Extensions* habilite **pg_cron**.

### 2. Migrações do Supabase

No SQL Editor, execute em ordem os arquivos de `supabase/migrations/` (veja `supabase/README.md`).
Anote em *Project Settings → API*: a URL do projeto e a chave **service_role** (vai para o Worker e para o
sincronizador; nunca para o frontend).

### 3. Turnstile

No painel da Cloudflare, *Turnstile → Add widget*. Em **Hostnames**, inclua exatamente o domínio da página do
participante (ex.: `pulse-pesquisa.<conta>.workers.dev` ou o domínio próprio); sem isso todo envio é recusado com
"Não foi possível confirmar que você não é um robô". Anote a **site key** (pública) e a **secret key**.

### 4. KV do limite de envios

```powershell
cd cloudflare-worker
npm install
npx wrangler login
npx wrangler kv namespace create RATE_KV
```

Copie o `id` retornado para `wrangler.toml` (`[[kv_namespaces]]`).

### 5. Secrets e variáveis do Worker

```powershell
npx wrangler secret put SUPABASE_URL
npx wrangler secret put SUPABASE_SERVICE_ROLE_KEY
npx wrangler secret put TURNSTILE_SECRET_KEY
npx wrangler secret put RATE_SALT_SECRET      # texto aleatório longo
```

Em `wrangler.toml`, ajuste `ALLOWED_ORIGINS` para o domínio do Pages (ex.: `https://pesquisa.suaempresa.com.br`).

### 6. Testes e publicação do Worker

```powershell
npm test
npx wrangler deploy
```

Configure uma rota ou domínio personalizado (ex.: `api.suaempresa.com.br`).

### 7. Configuração do frontend

- `frontend/assets/js/config.js`: `apiBase` = URL do Worker; `turnstileSiteKey` = site key.
- `frontend/_headers`: troque `https://api.dominio.com.br` e `https://SEU-PROJETO.supabase.co` pelos endereços reais.

### 8. Publicação do frontend (Cloudflare Pages)

A Cloudflare hospeda as novas páginas estáticas como Worker com *static assets* (os arquivos `_headers` e
`_redirects` continuam valendo). Na raiz do projeto (pasta sem `wrangler.toml`):

```powershell
npx wrangler deploy --name pulse-pesquisa --assets frontend --compatibility-date 2026-10-01
```

O endereço publicado (`https://pulse-pesquisa.<conta>.workers.dev`) vai em `ALLOWED_ORIGINS` do Worker da API e em
*Configurações → Geral → domínio dos links* do gerenciador.

O `_redirects` faz `/formulario/<slug>` abrir a página. Enquanto nenhum formulário for publicado pelo gerenciador,
o link mostra "Formulário não encontrado".

---

## Parte 2 — Gerenciador (rede local)

Pré-requisitos: servidor Windows na rede interna, MySQL 8, PHP 8.1+ (com `pdo_mysql`, `mbstring`, `fileinfo`,
`gd`, `zip`), Composer, Python 3.11+, NSSM.

### 9. Banco definitivo (MySQL)

```powershell
mysql -u root -p < database\mysql.sql
```

Cria o banco `pulse`, as tabelas, as 11 áreas, os 13 gestores, as 4 faixas de pontuação, as preferências, o
administrador inicial e o Pulse pronto: 5 tópicos com 5 perguntas de escala cada e a pergunta aberta, quinzenal,
um tópico liberado por ciclo. Tudo editável no Construtor.

**Banco criado antes de 16/11/2026:** aplique as migrações de `database\migracoes\` em ordem
(`mysql -u root -p pulse < database\migracoes\2026-11-16-topico-por-ciclo.sql`).

### 10. Usuários do MySQL (permissões mínimas)

```sql
CREATE USER 'pulse_app'@'localhost'  IDENTIFIED BY '<senha forte 1>';
CREATE USER 'pulse_sync'@'localhost' IDENTIFIED BY '<senha forte 2>';
GRANT SELECT, INSERT, UPDATE, DELETE ON pulse.* TO 'pulse_app'@'localhost';
GRANT SELECT ON pulse.* TO 'pulse_sync'@'localhost';
GRANT INSERT ON pulse.formularios_recebidos TO 'pulse_sync'@'localhost';
GRANT INSERT ON pulse.respostas TO 'pulse_sync'@'localhost';
GRANT INSERT ON pulse.respostas_itens TO 'pulse_sync'@'localhost';
GRANT INSERT, UPDATE, DELETE ON pulse.sync_status TO 'pulse_sync'@'localhost';
GRANT INSERT, UPDATE, DELETE ON pulse.fila_erros TO 'pulse_sync'@'localhost';
GRANT INSERT, DELETE ON pulse.sincronizacao_log TO 'pulse_sync'@'localhost';
GRANT UPDATE ON pulse.publicacoes TO 'pulse_sync'@'localhost';
GRANT UPDATE ON pulse.comandos_sync TO 'pulse_sync'@'localhost';
GRANT INSERT, UPDATE ON pulse.imagens_publicadas TO 'pulse_sync'@'localhost';
```

### 11. Gerenciador: dependências

```powershell
cd gerenciador
composer install --no-dev
php tests/run.php
```

(Instala PhpSpreadsheet para Excel e Dompdf para PDF. Sem eles, CSV e impressão continuam funcionando.)

### 12. Gerenciador: configuração

Copie `config/config.example.php` para `config/config.php` e preencha o MySQL (`pulse_app`) e `base_url`
(vazio na raiz; `/pulse` se estiver numa subpasta). Mantenha `debug => false`.

O gerenciador não precisa da pasta `frontend/`: a prévia usa `public/assets/participante/`. Pode-se copiar só as
pastas `gerenciador/`, `sincronizador/` e `database/` para o servidor interno.

### 13. Gerenciador: servidor web (somente rede interna)

- **Apache/XAMPP**: aponte o `DocumentRoot` (ou um Alias) para `gerenciador/public` com `AllowOverride All`
  (o `.htaccess` já faz o roteamento).
- **IIS**: site apontando para `gerenciador/public` com regra de reescrita para `index.php`.
- Teste rápido: `php -S 0.0.0.0:8080 -t public public/index.php`.

Bloqueie no firewall o acesso externo à porta usada. Dê permissão de escrita ao PHP em `storage/`.

### 14. Primeiro acesso

Entre com `admin@dominio.com.br` / `TrocarSenha@2026`. O sistema exige trocar a senha. Depois:

- *Configurações → Geral*: domínio dos links (o endereço `https://...` do Pages da Parte 1), fuso, grupo mínimo.
- *Configurações → Áreas e gestores*: número de colaboradores de cada área e vínculos gestor x área.
- *Configurações → Usuários*: crie as contas da equipe (Administrador ou Leitor).
- Troque o e-mail do administrador inicial em *Meu perfil*.

### 15. Sincronizador (liga a Parte 2 à Parte 1)

```powershell
cd sincronizador
python -m venv .venv
.venv\Scripts\pip install -r requirements.txt
copy .env.example .env      # SUPABASE_URL e SUPABASE_SERVICE_ROLE_KEY da Parte 1; MySQL com o usuário pulse_sync
.venv\Scripts\python main.py --verificar
.venv\Scripts\python -m unittest discover -s tests -t .
```

### 16. Serviço do Windows (NSSM)

Em um PowerShell como Administrador:

```powershell
cd sincronizador
.\instalar-servico.ps1 -Nssm "C:\nssm\win64\nssm.exe"
```

O serviço `PulseSincronizador` inicia com o Windows e reinicia sozinho em caso de falha.

---

## Montando o Pulse

1. *Formulários → Pulse: Nossa Gente → Construtor*: cadastre as perguntas de escala de cada tópico (tipo
   "Escala 0 a 10"), envie o símbolo e a foto da capa (a foto de exemplo está em `database/seed-foto-cidade.jpg`)
   e defina a **abertura** (início do 1º ciclo). Salve.
2. *Publicar*. Em até ~20 s o sincronizador envia a definição para a Parte 1; o status aparece em *Sincronização*.
3. *Compartilhar*: copie o link ou baixe o QR Code.
4. Teste o link no celular e confira a resposta chegando em *Relatórios* após o próximo ciclo do sincronizador.

## Atualizando a página pública

Ao mudar `frontend/assets/js` ou `frontend/assets/css/pulse.css`, publique a Parte 1 de novo e atualize a cópia
usada na prévia do gerenciador:

```powershell
cd gerenciador
php scripts/atualizar-previa.php ..\frontend
```
