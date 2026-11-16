# Sincronizador

Serviço Python que roda no servidor local e faz os dois fluxos:

- **Fluxo A** (a cada `INTERVALO_SINCRONIZACAO`, padrão 30 s): respostas do Supabase → MySQL.
- **Fluxo B** (a cada `INTERVALO_PUBLICACAO`, padrão 20 s): definições do MySQL → Supabase.

## Estrutura

```
sincronizador/
  main.py                         laço principal, --uma-vez, --verificar
  app/config/settings.py          leitura do .env e validação
  app/database/mysql.py           PyMySQL com timeout e reconexão
  app/services/supabase.py        RPCs e Storage via HTTPS (timeout e novas tentativas)
  app/repositories/               acesso ao MySQL (respostas, publicações, estado)
  app/synchronization/payload.py  conversão pura payload → linhas (testada)
  app/synchronization/respostas.py Fluxo A
  app/publicacao/publicador.py    Fluxo B (inclui upload de imagens)
  app/utils/logger.py             logs JSON com rotação diária e máscara de segredos
  tests/                          python -m unittest
  instalar-servico.ps1            instalação com NSSM
```

## Configuração (`.env`)

| Variável | Padrão | Descrição |
|---|---|---|
| `SUPABASE_URL` | — | `https://<projeto>.supabase.co` |
| `SUPABASE_SERVICE_ROLE_KEY` | — | chave service_role (secreta) |
| `SUPABASE_BUCKET` | `pulse-capas` | bucket das imagens |
| `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` | — | MySQL local |
| `INTERVALO_SINCRONIZACAO` | 30 | segundos entre buscas de respostas |
| `INTERVALO_PUBLICACAO` | 20 | segundos entre envios de publicações |
| `TEMPO_RESERVA` | 300 | segundos que um lote fica reservado |
| `LIMITE_REGISTROS_POR_LOTE` | 50 | registros por lote |
| `MAX_TENTATIVAS` | 5 | tentativas de publicação antes de ERRO |
| `UPLOADS_DIR` | `../gerenciador/storage/uploads` | imagens enviadas no construtor |
| `LOG_DIR`, `LOG_RETENCAO_DIAS` | `logs`, 30 | arquivos de log |

## Fluxo A em detalhe

```
reservar_envios(limite, TEMPO_RESERVA)        → status PROCESSANDO, reservado_ate = agora + reserva
para cada registro:
    BEGIN
      já existe respostas.uuid_externo?  → conta como duplicado (idempotente)
      senão: valida e converte o payload  → formularios_recebidos + respostas + respostas_itens
    COMMIT
    erro de dados   → ROLLBACK + registrar_erro(id, motivo)   (ERRO após max_tentativas)
    MySQL fora      → ROLLBACK e para o lote (a reserva expira e o registro volta sozinho)
confirmar_envios(ids gravados)                → PROCESSADO
estatisticas_fila()                           → sync_status + fila_erros no MySQL
```

Garantias:

- **Idempotência**: `respostas.uuid_externo` e `formularios_recebidos.uuid_externo` são `UNIQUE`. Reprocessar o mesmo
  envio nunca duplica.
- **Nada se perde**: a confirmação só acontece depois do COMMIT. Se o serviço cair no meio, a reserva expira
  (`TEMPO_RESERVA`) e o registro volta para `PENDENTE`.
- **Anonimato**: a identificação só é gravada se o formulário exigir.
- **Comandos do gerenciador**: o botão *Reprocessar* grava em `comandos_sync`; o sincronizador chama
  `reprocessar_envio`, que devolve o registro em ERRO para a fila.

## Fluxo B em detalhe

```
publicações PENDENTE (as antigas do mesmo formulário são marcadas como substituídas)
para cada uma:
    troca "local:<sha>.<ext>" → envia ao Storage (se ainda não estiver em imagens_publicadas) → URL pública
    publicar_formulario(definicao)          (idempotente pela versão)
    sucesso → ENVIADA | falha → tentativas + 1 → ERRO ao atingir MAX_TENTATIVAS
```

## Operação

```powershell
python main.py --verificar   # testa MySQL e Supabase
python main.py --uma-vez     # roda um ciclo de cada fluxo e sai
python main.py               # modo contínuo (o que o serviço executa)
python -m unittest discover -s tests -t .
```

Painel: **Gerenciador → Sincronização** mostra se o serviço está ativo (sinal a cada ciclo), a última
sincronização, fila, erros (com *Reprocessar*), publicações (com *Reenviar*), uso do Supabase e o log.

Logs em `sincronizador/logs/sincronizador.log` (um arquivo por dia, JSON por linha) e na tabela
`sincronizacao_log` (eventos principais, 90 dias).

## Problemas comuns

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| "Sem sinal" no painel | serviço parado | `nssm restart PulseSincronizador`; veja `logs/servico-stderr.log` |
| Publicação em ERRO "imagem não encontrada" | arquivo apagado de `storage/uploads` | envie a imagem de novo no construtor e salve |
| Registro em ERRO "pergunta desconhecida" | resposta de uma pergunta apagada sem respostas anteriores | reprocesse depois de restaurar a pergunta, ou descarte |
| Fila crescendo | MySQL fora do ar | verifique o serviço MySQL; o sincronizador retoma sozinho |
| `401/403` do Supabase | chave service_role errada | corrija o `.env` e reinicie o serviço |
