# Arquitetura do Pulse

## Visão geral

```
                      INTERNET                                   │            REDE LOCAL (nunca exposta)
                                                                 │
 Participante ──► Cloudflare Pages (frontend/)                   │
       │            HTML + CSS + JS + Bootstrap 5                │
       │                                                         │
       └──► Cloudflare Worker (cloudflare-worker/)               │
              GET  /api/formularios/:slug                        │
              POST /api/formularios/enviar                       │
              Turnstile · rate limit (KV) · ciclo · validação    │
                     │ service_role (secret do Worker)           │
                     ▼                                           │
              Supabase (banco intermediário)                     │
              fila_envios · formularios_publicados ·             │
              bucket pulse-capas                                 │
                     ▲                                           │
                     │ HTTPS, iniciado SEMPRE pelo servidor local│
                     └───────────────────────────────────────────┼── Sincronizador Python (sincronizador/)
                                                                 │        serviço do Windows (NSSM)
                                                                 │              │
                                                                 │              ▼
                                                                 │        MySQL 8 (database/) ◄── Gerenciador PHP (gerenciador/)
                                                                 │        banco definitivo          http://localhost (só rede interna)
```

Princípios:

- **O MySQL é o banco definitivo.** O Supabase guarda apenas a fila temporária (48 h após processada) e uma cópia
  somente leitura das definições publicadas.
- **Nada da internet alcança a rede local.** Todas as conexões partem do sincronizador para fora (HTTPS de saída).
- **O frontend nunca fala com o MySQL nem com o Supabase.** Ele conversa só com o Worker.
- **A chave `service_role` existe em dois lugares apenas:** secret do Worker e `.env` do sincronizador.
- **O gerenciador nunca acessa o Supabase.** O sincronizador espelha no MySQL o que o painel precisa
  (`sync_status`, `fila_erros`) e executa os comandos pedidos pelo gerenciador (`comandos_sync`).

## Fluxo A — respostas (internet → MySQL)

1. O participante abre `/formulario/<slug>`; a página chama `GET /api/formularios/<slug>`.
2. O Worker lê `formularios_publicados`, calcula o **status** (rascunho, agendado, publicado, encerrado) e o
   **ciclo atual**, e devolve somente a definição pública (sem campos se não estiver publicado). Cache de borda: 60 s.
3. As respostas ficam **apenas em memória** no navegador. No envio a página gera um `envio_id` (UUID) e o reaproveita
   em novas tentativas.
4. O Worker: confere origem (CORS), tamanho, limite por IP (hash com sal diário rotativo, TTL 1 h, nunca gravado),
   Turnstile, status do formulário e valida cada resposta contra a definição. Calcula o **ciclo** pelo horário de
   recebimento e grava com `enfileirar_envio` (idempotente pelo UUID).
5. O sincronizador chama `reservar_envios(limite, segundos)` (`FOR UPDATE SKIP LOCKED`), grava cada registro no MySQL
   numa transação (`formularios_recebidos`, `respostas`, `respostas_itens`) e só **depois do COMMIT** chama
   `confirmar_envios`. Se a confirmação falhar, a reserva expira e o UUID repetido é ignorado (`UNIQUE uuid_externo`).
6. Registro inválido → `registrar_erro`; após `max_tentativas` ele fica em `ERRO` e aparece em *Sincronização*.
7. `pg_cron` apaga diariamente os `PROCESSADO` com mais de 48 h e roda `VACUUM ANALYZE`.

## Fluxo B — publicação (MySQL → internet)

1. No construtor o gestor salva o formulário (MySQL). Ao **publicar**, **encerrar**, **despublicar** ou salvar um
   formulário que está no ar, o gerenciador monta a definição pública (`DefinicaoBuilder`) e grava em `publicacoes`
   com status `PENDENTE`.
2. O sincronizador lê as pendentes, envia imagens novas para o bucket `pulse-capas` (nome = SHA-256 do arquivo,
   cache em `imagens_publicadas`), troca as referências `local:<sha>.<ext>` pelas URLs públicas e chama
   `publicar_formulario(definicao)` — idempotente pela versão (uma versão menor nunca sobrescreve uma maior).
3. Sucesso → `ENVIADA`. Falha → nova tentativa no próximo ciclo; após `MAX_TENTATIVAS`, `ERRO` (botão *Reenviar*).

## Versionamento

- `formularios.versao` começa em 1. Se a estrutura das perguntas mudar depois de publicado (ou com respostas), a
  versão sobe. Cada resposta guarda a versão com que foi enviada.
- Pergunta, opção ou tópico removido **com respostas** fica `ativo = 0` (some do formulário, continua no relatório
  marcado como "removida"). Sem respostas, é apagado.
- Não é possível trocar o tipo de uma pergunta que já tem respostas.
- O Worker aceita o envio se ele for válido contra a definição atual; se não for e a versão enviada for diferente,
  responde `409 VERSAO_DESATUALIZADA` e a página pede para recarregar.

## Contratos JSON

### 13.1 Definição pública (`GET /api/formularios/:slug`)

```json
{
  "id": "uuid do formulário", "slug": "pulse-nossa-gente", "versao": 3,
  "titulo": "Pulse: Nossa Gente", "descricao": "",
  "status": "PUBLICADO",
  "inicio_em": "2026-11-03T04:00:00Z", "fim_em": null,
  "recorrencia": "QUINZENAL", "topico_por_ciclo": true, "fuso_horario": "America/Cuiaba",
  "ciclo_atual": { "numero": 2, "inicio": "2026-11-17", "fim": "2026-11-30" },
  "cor": "#032B4C",
  "capa": { "tipo": "INSTITUCIONAL", "titulo": "PULSE", "subtitulo": "Nossa Gente", "chamada": "...",
            "destaque_titulo": "...", "destaque_texto": "...", "convite": "...", "encerramento": "...",
            "simbolo": "https://<projeto>.supabase.co/storage/v1/object/public/pulse-capas/<sha>.png", "foto": null },
  "segmentado": true, "exige_identificacao": false, "permite_multiplas": false, "formato": "ETAPAS",
  "mensagem_apresentacao": "...", "mensagem_conclusao": "...",
  "areas":    [{ "id": "uuid", "nome": "Produção" }],
  "gestores": [{ "id": "uuid", "nome": "...", "area_id": "uuid | null" }],
  "topicos": [{
    "id": "uuid", "titulo": "Liderança", "descricao": "",
    "perguntas": [{
      "id": "uuid", "tipo": "ESCALA", "titulo": "...", "orientacao": "", "obrigatoria": true,
      "minimo": 0, "maximo": 10, "rotulo_minimo": "Nada", "rotulo_maximo": "Totalmente",
      "aparencia": "ZONAS", "opcoes": []
    }]
  }]
}
```

Com status `RASCUNHO`, `AGENDADO` ou `ENCERRADO` a resposta traz só os campos de identificação, datas, cor e capa —
nunca tópicos, áreas ou gestores. Com `topico_por_ciclo`, `topicos` traz só o tópico do ciclo atual (veja
`regras-pulse.md`, rodízio de tópicos).

Tipos de pergunta: `CURTA`, `LONGA`, `NUMERO`, `EMAIL`, `TELEFONE`, `DATA`, `UNICA`, `MULTIPLA`, `LISTA`, `ESCALA`,
`SIM_NAO`, `INFO`. Formato: `AUTO` e `ETAPAS` (uma pergunta por tela) ou `PAGINA_UNICA` (um tópico por tela).

### 13.2 Envio (`POST /api/formularios/enviar`)

```json
{
  "envio_id": "uuid gerado no navegador (reaproveitado em novas tentativas)",
  "formulario_id": "uuid", "versao": 3,
  "area_id": "uuid | null", "gestor_id": "uuid | null",
  "identificacao": { "nome": "...", "email": "..." },
  "respostas": [
    { "pergunta_id": "uuid", "valor": 8 },
    { "pergunta_id": "uuid", "valor": "texto" },
    { "pergunta_id": "uuid", "valor": "SIM" },
    { "pergunta_id": "uuid", "valor": "2026-11-10" },
    { "pergunta_id": "uuid", "opcoes": ["uuid da opção"] }
  ],
  "turnstile_token": "..."
}
```

`identificacao` só é aceita quando o formulário exige; em formulário anônimo é descartada. Resposta de sucesso:

```json
{ "success": true, "message": "Formulário recebido com sucesso", "id": "envio_id" }
```

Erros: `400` JSON/UUID inválido, `403 TURNSTILE`, `404`, `409 AGENDADO|ENCERRADO|RASCUNHO|VERSAO_DESATUALIZADA`,
`413`, `415`, `422` (lista de erros por pergunta), `429` limite por IP, `503` Supabase indisponível.

### 13.3 Payload gravado na fila (`fila_envios.payload`)

```json
{ "envio_id": "...", "formulario_id": "...", "versao": 3, "area_id": "...", "gestor_id": "...",
  "identificacao": null, "respostas": [ ... normalizadas ... ],
  "recebido_em": "2026-11-10T14:03:22.123Z", "ciclo": 2, "status": "COMPLETA" }
```

Nunca contém IP, user agent, token do Turnstile ou identificador de dispositivo.

### 13.4 Definição armazenada (`publicacoes.definicao` → `formularios_publicados.definicao`)

Igual à 13.1, sem `status` e `ciclo_atual` (calculados pelo Worker), com `estado` = `ATIVO`, `ENCERRADO` ou
`DESPUBLICADO` (gravado na coluna `estado`, removido do JSON). Imagens chegam do gerenciador como
`local:<sha256>.<ext>` e o sincronizador as substitui pelas URLs públicas antes de enviar.

## Componentes

O sistema é dividido em duas partes independentes. Nenhuma lê arquivos da outra; a comunicação é só de dados, pelo
Supabase, sempre iniciada pelo sincronizador.

| Parte | Pasta | Tecnologia | Papel |
|---|---|---|---|
| 1 — Participante | `frontend/` | HTML, CSS, JS (módulos), Bootstrap 5 | Página do participante no Cloudflare Pages |
| 1 — Participante | `cloudflare-worker/` | TypeScript | API pública, validação, Turnstile, rate limit, ciclo |
| 1 — Participante | `supabase/` | PostgreSQL, pg_cron, Storage | Fila e cópia das definições |
| 2 — Gerenciador | `gerenciador/` | PHP 8 MVC, Bootstrap, Chart.js (locais) | Administração, construtor, relatórios |
| 2 — Gerenciador | `sincronizador/` | Python 3.11+, PyMySQL, requests | Fluxos A e B, serviço do Windows |
| 2 — Gerenciador | `database/` | MySQL 8 | Banco definitivo e cargas iniciais |

### Prévia do participante no gerenciador

A prévia ("ver como participante" e a coluna direita do construtor) é gerada inteiramente na Parte 2: a definição
vem do MySQL (`DefinicaoBuilder::paraPrevia`) e o renderizador é uma **cópia** dos arquivos da página pública em
`gerenciador/public/assets/participante/` (`pulse.css` e `js/`). Em modo prévia (`window.PULSE_PREVIEW`) nada é
enviado ao Worker. Ao alterar a página pública, rode `php scripts/atualizar-previa.php` no gerenciador; o teste
`tests/ParticipanteTest.php` aponta se as cópias divergirem.
