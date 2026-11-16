-- =====================================================================
-- 10. Política de retenção + Storage das imagens da capa
-- =====================================================================

-- Limpeza: só exclui PROCESSADO com 48 h ou mais desde processado_em.
-- PENDENTE e PROCESSANDO nunca; ERRO nunca automaticamente.
create or replace function public.limpar_fila()
returns integer
language plpgsql
set search_path = public
as $$
declare
  v_qtd integer;
begin
  delete from fila_envios
   where status = 'PROCESSADO'
     and processado_em is not null
     and processado_em < now() - interval '48 hours';
  get diagnostics v_qtd = row_count;

  -- Definições despublicadas há mais de 90 dias (o original está no MySQL)
  delete from formularios_publicados
   where estado = 'DESPUBLICADO'
     and despublicado_em < now() - interval '90 days';

  return v_qtd;
end;
$$;

revoke all on function public.limpar_fila() from public, anon, authenticated;
grant execute on function public.limpar_fila() to service_role;

-- Agendamento com pg_cron (ative a extensão em Database > Extensions se necessário).
-- VACUUM não pode rodar dentro de uma função/transação, por isso é um job separado.
create extension if not exists pg_cron;

select cron.unschedule(jobid) from cron.job where jobname in ('pulse_limpar_fila', 'pulse_vacuum_fila');

select cron.schedule('pulse_limpar_fila', '15 3 * * *', $$select public.limpar_fila();$$);
select cron.schedule('pulse_vacuum_fila', '45 3 * * *', $$vacuum (analyze) public.fila_envios;$$);

-- ---------------------------------------------------------------------
-- Storage: bucket público somente leitura, imagens de até 1,5 MB.
-- Upload apenas pela service_role (sincronizador).
-- ---------------------------------------------------------------------
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('pulse-capas', 'pulse-capas', true, 1572864,
        array['image/png','image/jpeg','image/webp','image/svg+xml'])
on conflict (id) do update
   set public = true, file_size_limit = excluded.file_size_limit,
       allowed_mime_types = excluded.allowed_mime_types;

-- Leitura pública dos objetos do bucket (sem escrita para anon/authenticated).
drop policy if exists "pulse_capas_leitura_publica" on storage.objects;
create policy "pulse_capas_leitura_publica" on storage.objects
  for select to anon, authenticated
  using (bucket_id = 'pulse-capas');
