# Pulse — Parte 2: Gerenciador

Administração local do Pulse (PHP 8, MVC). Roda **somente na rede interna** e não depende dos arquivos da página
pública: a prévia "ver como participante" usa a cópia em `public/assets/participante/`.

```
gerenciador/
  public/                 raiz web (index.php, .htaccess, assets locais: Bootstrap, ícones, Chart.js, QR Code, fontes)
    assets/participante/  cópia do renderizador do participante, usada só na prévia
  app/Core                roteador, sessão, CSRF, autenticação, PDO, auditoria
  app/Controllers         telas
  app/Models              acesso ao MySQL
  app/Services            construtor, publicação, relatórios, exportação, uploads
  app/Services/Indicadores índice, zonas, ciclos, adesão, anonimato (única fonte das contas)
  app/Views               telas
  config/config.example.php
  routes/web.php
  scripts/atualizar-previa.php
  storage/                uploads e logs (sem acesso web)
  tests/                  php tests/run.php
../sincronizador/         serviço Python que liga esta parte ao Supabase
../database/mysql.sql     banco definitivo
```

## Instalar

```powershell
mysql -u root -p < ..\database\mysql.sql
composer install --no-dev                 # Excel (PhpSpreadsheet) e PDF (Dompdf)
copy config\config.example.php config\config.php   # preencha o MySQL
php tests/run.php
```

Sirva a pasta `public/` (Apache/XAMPP, IIS ou `php -S 0.0.0.0:8080 -t public public/index.php` para teste) e
bloqueie a porta no firewall para acesso externo. Depois instale o sincronizador (`../sincronizador`).
Passo a passo completo: `../docs/instalacao.md`, Parte 2.

Primeiro acesso: `admin@dominio.com.br` / `TrocarSenha@2026` (troca obrigatória).

## Prévia do participante

A prévia é gerada inteiramente aqui: a definição vem do MySQL e os arquivos de `public/assets/participante/`.
Nada é enviado. Quando a página pública mudar, atualize a cópia:

```powershell
php scripts/atualizar-previa.php C:\caminho\para\frontend
```
