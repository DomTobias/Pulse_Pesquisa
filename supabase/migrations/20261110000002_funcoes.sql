-- =====================================================================
-- 8.4 Funções RPC (chamadas via PostgREST: POST /rest/v1/rpc/<nome>)
-- Executáveis apenas pela service_role.
-- =====================================================================

-- Inserção idempotente pelo Worker: reenvio com o mesmo UUID não duplica.
create or replace function public.enfileirar_envio(p_id uuid, p_formulario_id uuid, p_payload jsonb)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_inserido boolean;
begin
  insert into fila_envios (id, formulario_id, payload)
  values (p_id, p_formulario_id, p_payload)
  on conflict (id) do nothing;
  get diagnostics v_inserido = row_count;
  return jsonb_build_object('id', p_id, 'novo', v_inserido);
end;
$$;

-- Reserva atômica de um lote: PENDENTE, ou PROCESSANDO com reserva vencida.
-- FOR UPDATE SKIP LOCKED garante que dois sincronizadores nunca peguem o mesmo registro.
create or replace function public.reservar_envios(limite integer default 50, segundos_reserva integer default 300)
returns setof public.fila_envios
language plpgsql
set search_path = public
as $$
begin
  return query
  with alvo as (
    select f.id
      from fila_envios f
     where f.status = 'PENDENTE'
        or (f.status = 'PROCESSANDO' and f.reservado_ate < now())
     order by f.criado_em
     limit greatest(1, least(limite, 500))
     for update skip locked
  )
  update fila_envios f
     set status = 'PROCESSANDO',
         reservado_ate = now() + make_interval(secs => greatest(30, segundos_reserva)),
         atualizado_em = now()
    from alvo
   where f.id = alvo.id
  returning f.*;
end;
$$;

-- Confirma o processamento (só depois do COMMIT no MySQL).
create or replace function public.confirmar_envios(ids uuid[])
returns integer
language plpgsql
set search_path = public
as $$
declare
  v_qtd integer;
begin
  update fila_envios
     set status = 'PROCESSADO',
         processado_em = now(),
         reservado_ate = null,
         mensagem_erro = null,
         atualizado_em = now()
   where id = any(ids)
     and status in ('PROCESSANDO','PENDENTE');
  get diagnostics v_qtd = row_count;
  return v_qtd;
end;
$$;

-- Registra falha: incrementa tentativas e devolve à fila; acima do máximo vira ERRO.
create or replace function public.registrar_erro(id uuid, mensagem text)
returns text
language plpgsql
set search_path = public
as $$
declare
  v_max integer := coalesce((select valor::integer from parametros where chave = 'max_tentativas'), 5);
  v_status text;
begin
  update fila_envios f
     set tentativas = f.tentativas + 1,
         mensagem_erro = left(mensagem, 2000),
         status = case when f.tentativas + 1 >= v_max then 'ERRO' else 'PENDENTE' end,
         reservado_ate = null,
         atualizado_em = now()
   where f.id = registrar_erro.id
  returning f.status into v_status;
  return v_status;
end;
$$;

-- Devolve um registro em ERRO para a fila (botão "reprocessar" do gerenciador).
create or replace function public.reprocessar_envio(id uuid)
returns boolean
language plpgsql
set search_path = public
as $$
begin
  update fila_envios f
     set status = 'PENDENTE', tentativas = 0, mensagem_erro = null,
         reservado_ate = null, atualizado_em = now()
   where f.id = reprocessar_envio.id and f.status = 'ERRO';
  return found;
end;
$$;

-- Publicação idempotente pela versão (Fluxo B).
-- Só substitui a definição se a versão for igual ou maior que a gravada.
create or replace function public.publicar_formulario(definicao jsonb)
returns jsonb
language plpgsql
set search_path = public
as $$
#variable_conflict use_variable
declare
  v_id uuid := (definicao->>'id')::uuid;
  v_versao integer := (definicao->>'versao')::integer;
  v_estado text := coalesce(definicao->>'estado', 'ATIVO');
  v_atual integer;
begin
  if v_id is null or v_versao is null or (definicao->>'slug') is null then
    raise exception 'definição incompleta: id, slug e versao são obrigatórios';
  end if;
  if v_estado not in ('ATIVO','ENCERRADO','DESPUBLICADO') then
    raise exception 'estado inválido: %', v_estado;
  end if;

  select versao into v_atual from formularios_publicados where id = v_id;

  if v_atual is not null and v_atual > v_versao then
    return jsonb_build_object('id', v_id, 'aplicado', false, 'versao_atual', v_atual);
  end if;

  -- Libera o slug se outro formulário despublicado o usava
  delete from formularios_publicados
   where slug = definicao->>'slug' and id <> v_id and estado = 'DESPUBLICADO';

  insert into formularios_publicados (id, slug, versao, definicao, estado, inicio_em, fim_em, recorrencia, atualizado_em, despublicado_em)
  values (v_id, definicao->>'slug', v_versao, definicao - 'estado', v_estado,
          nullif(definicao->>'inicio_em','')::timestamptz, nullif(definicao->>'fim_em','')::timestamptz,
          coalesce(definicao->>'recorrencia','NENHUMA'), now(),
          case when v_estado = 'DESPUBLICADO' then now() end)
  on conflict (id) do update
     set slug = excluded.slug, versao = excluded.versao, definicao = excluded.definicao,
         estado = excluded.estado, inicio_em = excluded.inicio_em, fim_em = excluded.fim_em,
         recorrencia = excluded.recorrencia, atualizado_em = now(),
         despublicado_em = case when excluded.estado = 'DESPUBLICADO'
                                then coalesce(formularios_publicados.despublicado_em, now()) end;

  return jsonb_build_object('id', v_id, 'aplicado', true, 'versao', v_versao);
end;
$$;

-- Indicadores para o painel de sincronização (lidos pelo sincronizador e gravados no MySQL).
create or replace function public.estatisticas_fila()
returns jsonb
language sql
stable
set search_path = public
as $$
  select jsonb_build_object(
    'pendentes',   (select count(*) from fila_envios where status in ('PENDENTE','PROCESSANDO')),
    'processados', (select count(*) from fila_envios where status = 'PROCESSADO'),
    'erros',       (select coalesce(jsonb_agg(jsonb_build_object(
                        'id', id, 'formulario_id', formulario_id, 'tentativas', tentativas,
                        'mensagem_erro', mensagem_erro, 'criado_em', criado_em) order by criado_em), '[]'::jsonb)
                      from fila_envios where status = 'ERRO'),
    'uso_bytes',   pg_database_size(current_database()),
    'capacidade_bytes', coalesce((select valor::bigint from parametros where chave = 'capacidade_bytes'), 524288000)
  );
$$;

-- Permissões: somente service_role
revoke all on function public.enfileirar_envio(uuid, uuid, jsonb) from public, anon, authenticated;
revoke all on function public.reservar_envios(integer, integer)   from public, anon, authenticated;
revoke all on function public.confirmar_envios(uuid[])            from public, anon, authenticated;
revoke all on function public.registrar_erro(uuid, text)          from public, anon, authenticated;
revoke all on function public.reprocessar_envio(uuid)             from public, anon, authenticated;
revoke all on function public.publicar_formulario(jsonb)          from public, anon, authenticated;
revoke all on function public.estatisticas_fila()                 from public, anon, authenticated;

grant execute on function public.enfileirar_envio(uuid, uuid, jsonb) to service_role;
grant execute on function public.reservar_envios(integer, integer)   to service_role;
grant execute on function public.confirmar_envios(uuid[])            to service_role;
grant execute on function public.registrar_erro(uuid, text)          to service_role;
grant execute on function public.reprocessar_envio(uuid)             to service_role;
grant execute on function public.publicar_formulario(jsonb)          to service_role;
grant execute on function public.estatisticas_fila()                 to service_role;
