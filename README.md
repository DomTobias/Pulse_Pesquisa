# Pulse

Plataforma de pesquisas internas (clima organizacional recorrente e formulários em geral), dividida em **duas partes
independentes**. Cada uma é instalada, publicada e atualizada separadamente; elas só se comunicam pelo Supabase,
através do sincronizador.

## Parte 1 — Participante (nuvem, acesso pela internet)

| Pasta | O que é | Onde roda |
|---|---|---|
| `frontend/` | Página do participante (HTML, CSS, JS, Bootstrap 5) | Cloudflare Pages |
| `cloudflare-worker/` | API pública: definição, envio, Turnstile, limite por IP, ciclo | Cloudflare Workers |
| `supabase/` | Fila de envios e cópia das definições publicadas | Supabase |

Não contém nada do gerenciador, do MySQL ou da rede interna. Veja [docs/participante.md](docs/participante.md).

## Parte 2 — Gerenciador (rede local, nunca exposto)

| Pasta | O que é | Onde roda |
|---|---|---|
| `gerenciador/` | Administração PHP 8 MVC: construtor, publicação, relatórios, configurações | Servidor interno |
| `sincronizador/` | Serviço Python: Supabase ⇄ MySQL | Servidor interno (serviço do Windows) |
| `database/` | MySQL 8: banco definitivo | Servidor interno |

Não depende dos arquivos da Parte 1: a prévia "ver como participante" usa uma cópia própria do renderizador em
`gerenciador/public/assets/participante/`. Veja [gerenciador/README.md](gerenciador/README.md).

## Como as partes conversam

```
Parte 1 (nuvem)                                   Parte 2 (rede local)
frontend ⇄ Worker ⇄ Supabase  ◄──── HTTPS de saída ────  sincronizador ⇄ MySQL ⇄ gerenciador
```

O único elo é o sincronizador, que **parte da rede local** para o Supabase. Nada da internet acessa a rede interna.

## Documentação

- Arquitetura e contratos JSON: [docs/arquitetura.md](docs/arquitetura.md)
- Instalação (Parte 1 e Parte 2): [docs/instalacao.md](docs/instalacao.md)
- Sincronizador: [docs/sincronizacao.md](docs/sincronizacao.md)
- Segurança: [docs/seguranca.md](docs/seguranca.md)
- Regras (índice, zonas, ciclos, adesão, anonimato): [docs/regras-pulse.md](docs/regras-pulse.md)

## Testes

```powershell
cd cloudflare-worker; npm test                                   # Parte 1: Worker
cd sincronizador; python -m unittest discover -s tests -t .      # Parte 2: sincronizador
cd gerenciador; php tests/run.php                                # Parte 2: indicadores e prévia
```
