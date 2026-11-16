-- =====================================================================
-- Pulse: banco intermediário (Supabase PostgreSQL)
-- O Supabase guarda APENAS a fila de envios e uma cópia somente leitura
-- das definições publicadas. O original de tudo fica no MySQL local.
-- =====================================================================

create extension if not exists pgcrypto;

-- ---------------------------------------------------------------------
-- 8.1 Fila de envios
-- ---------------------------------------------------------------------
create table if not exists public.fila_envios (
  id             uuid primary key,
  formulario_id  uuid not null,
  payload        jsonb not null,
  status         varchar(12) not null default 'PENDENTE'
                 check (status in ('PENDENTE','PROCESSANDO','PROCESSADO','ERRO')),
  tentativas     integer not null default 0,
  criado_em      timestamptz not null default now(),
  atualizado_em  timestamptz not null default now(),
  reservado_ate  timestamptz,
  processado_em  timestamptz,
  mensagem_erro  text
);

create index if not exists idx_fila_status    on public.fila_envios (status);
create index if not exists idx_fila_criado_em on public.fila_envios (criado_em);
create index if not exists idx_fila_status_criado on public.fila_envios (status, criado_em);

-- ---------------------------------------------------------------------
-- 8.2 Definições publicadas (cópia somente leitura)
-- ---------------------------------------------------------------------
create table if not exists public.formularios_publicados (
  id             uuid primary key,              -- mesmo uuid do formulário no MySQL
  slug           varchar(80) not null unique,
  versao         integer not null,
  definicao      jsonb not null,
  estado         varchar(14) not null check (estado in ('ATIVO','ENCERRADO','DESPUBLICADO')),
  inicio_em      timestamptz,
  fim_em         timestamptz,
  recorrencia    varchar(12) not null default 'NENHUMA',
  atualizado_em  timestamptz not null default now(),
  despublicado_em timestamptz
);

create index if not exists idx_publicados_estado on public.formularios_publicados (estado);

-- ---------------------------------------------------------------------
-- Parâmetros (máximo de tentativas, capacidade do plano)
-- ---------------------------------------------------------------------
create table if not exists public.parametros (
  chave  varchar(60) primary key,
  valor  text not null
);

insert into public.parametros (chave, valor) values
  ('max_tentativas', '5'),
  ('capacidade_bytes', '524288000')   -- 500 MB (plano gratuito)
on conflict (chave) do nothing;

-- ---------------------------------------------------------------------
-- 8.3 RLS: ativo em todas as tabelas, sem políticas para anon/authenticated.
-- Somente a chave service_role (Worker e sincronizador) acessa os dados.
-- ---------------------------------------------------------------------
alter table public.fila_envios            enable row level security;
alter table public.formularios_publicados enable row level security;
alter table public.parametros             enable row level security;

revoke all on public.fila_envios, public.formularios_publicados, public.parametros from anon, authenticated;
grant select, insert, update, delete on public.fila_envios, public.formularios_publicados, public.parametros to service_role;
