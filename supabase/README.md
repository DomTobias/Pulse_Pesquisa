# Supabase — banco intermediário

O Supabase guarda **somente**:

- `fila_envios`: respostas recebidas pelo Worker, aguardando o sincronizador (retenção de 48 h após `PROCESSADO`);
- `formularios_publicados`: cópia somente leitura das definições publicadas pelo gerenciador;
- bucket `pulse-capas`: imagens de capa (até 1,5 MB cada).

O banco definitivo é o MySQL local. Nenhuma tela pública ou administrativa acessa o Supabase diretamente:
o Worker e o sincronizador usam a chave `service_role`, guardada apenas como *secret* do Worker e no `.env` do sincronizador.

## Aplicar as migrações

No SQL Editor do projeto (ou com a CLI `supabase db push`), execute em ordem:

1. `migrations/20261110000001_estrutura.sql` — tabelas, índices e RLS (sem políticas para `anon`/`authenticated`).
2. `migrations/20261110000002_funcoes.sql` — RPCs `enfileirar_envio`, `reservar_envios`, `confirmar_envios`, `registrar_erro`, `reprocessar_envio`, `publicar_formulario`, `estatisticas_fila`.
3. `migrations/20261110000003_retencao_storage.sql` — `pg_cron` (limpeza 03:15 e `VACUUM ANALYZE` 03:45 UTC) e bucket `pulse-capas`.

Antes do passo 3, habilite a extensão **pg_cron** em *Database → Extensions*.

## Conferência

```sql
select * from cron.job;                       -- dois jobs pulse_*
select public.estatisticas_fila();            -- deve responder como service_role
select count(*) from public.fila_envios;      -- como anon deve falhar (sem permissão)
```
