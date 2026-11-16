# Regras de negócio do Pulse

Todas as contas ficam em um único lugar: `gerenciador/app/Services/Indicadores/`
(`Indice`, `Zonas`, `Ciclos`, `Adesao`, `Anonimato`). Os testes em `gerenciador/tests/IndicadoresTest.php`
usam os exemplos abaixo (`php tests/run.php`).

## 1. Normalização das notas

Cada nota de escala vira um valor de 0 a 10:

```
nota normalizada = (nota − mínimo) ÷ (máximo − mínimo) × 10
```

| Escala | Nota | Normalizada |
|---|---|---|
| 0 a 10 | 8 | 8,0 |
| 0 a 4 | 3 | (3 − 0) ÷ 4 × 10 = 7,5 |
| 1 a 5 | 3 | (3 − 1) ÷ 4 × 10 = 5,0 |

## 2. Índice

Índice = **média de todas as notas normalizadas** do recorte, mostrada em porcentagem (× 10, arredondada).

Exemplo: notas 8, 7, 9 e 6 (escala 0–10) → média 7,5 → **75%**.
Escalas diferentes: 3 (escala 1–5 → 5,0) e 8 (escala 0–10 → 8,0) → média 6,5 → **65%**.

O índice de um tópico usa só as perguntas de escala daquele tópico; o de uma área, só as respostas daquela área.
Perguntas abertas não entram no índice.

## 3. Zonas de pontuação

| Zona | Faixa da nota | Porcentagem | Cor |
|---|---|---|---|
| Crítica | menor que 5 | 0% a 49% | `#D64545` |
| Melhoramento | de 5 a menos de 7 | 50% a 69% | `#EE8B2A` |
| Aperfeiçoamento | de 7 a menos de 8 | 70% a 79% | `#C9A414` |
| Satisfatória | de 8 a 10 | 80% a 100% | `#2E9E5B` |

**A nota exatamente no limite pertence à zona de cima**: 5,0 é melhoramento; 7,0 é aperfeiçoamento; 8,0 é satisfatória.
A classificação usa a porcentagem já arredondada, a mesma que aparece na tela: 4,96 aparece como 50% e fica em
melhoramento (nunca aparece "50%" pintado de vermelho). As faixas são editáveis em *Configurações → Zonas*.

## 4. Variação entre ciclos

Variação = **diferença entre as porcentagens já arredondadas**, em pontos percentuais (p.p.):

- ciclo anterior 7,06 → 71%; ciclo atual 7,43 → 74%; variação **+3 p.p.**
- anterior 7,00 → 70%; atual 6,84 → 68%; variação **−2 p.p.**

Assim a conta sempre bate com os números exibidos.

## 5. Ciclos

Durações: **SEMANAL = 7 dias**, **QUINZENAL = 14**, **MENSAL = 30** (dias corridos, não mês do calendário).

```
o ciclo N começa em  início + (N − 1) × duração
e termina um instante antes do início do ciclo N + 1
```

Exemplo quinzenal com início em 03/11/2026 00:00 (America/Cuiaba):

| Ciclo | Início | Fim |
|---|---|---|
| 1 | 03/11/2026 | 16/11/2026 23:59:59 |
| 2 | 17/11/2026 | 30/11/2026 23:59:59 |
| 3 | 01/12/2026 | 14/12/2026 23:59:59 |

O **Worker** calcula o ciclo no momento em que recebe a resposta e o grava junto com ela; o gerenciador usa a mesma
regra para listar os ciclos. O fuso padrão é `America/Cuiaba` (configurável); no MySQL as datas ficam em UTC.

### Rodízio de tópicos (um tópico por ciclo)

Com a opção *Liberar um tópico por ciclo* (Construtor → Período e recorrência), cada ciclo libera só um tópico, na
ordem da lista, contando apenas tópicos com perguntas:

```
tópico liberado no ciclo N = tópicos[(N − 1) mod total]
```

No Pulse (5 tópicos, quinzenal): ciclo 1 Liderança, 2 Estrutura organizacional, 3 Benefícios, 4 Orgulho,
5 Reconhecimento, 6 Liderança de novo, e assim por diante (cada tópico volta a cada 10 semanas).

- O **Worker** entrega e aceita só o tópico do ciclo; as obrigatórias dos outros tópicos não são exigidas.
- Se o ciclo virar enquanto a pessoa responde, o envio recebe `409 VERSAO_DESATUALIZADA` e a página pede para
  recarregar (o tópico novo aparece).
- Na *Visão geral*, a variação de cada tópico compara com a última rodada em que ele foi respondido.
- A prévia do gerenciador mostra todos os tópicos.

## 6. Adesão

```
adesão = respostas ÷ (colaboradores × ciclos) × 100
```

- Colaboradores = soma das áreas ativas (ou só a área filtrada), cadastrados em *Configurações → Áreas e gestores*.
- Ciclos = quantidade de ciclos do período escolhido.
- Exemplo: 96 respostas, 60 colaboradores, 2 ciclos → 96 ÷ 120 = **80%**.
- **Não é calculada com filtro de gestor** (não existe número de colaboradores por gestor).
- Só existe para pesquisas recorrentes.

## 7. Status do formulário

| Status | Regra |
|---|---|
| Rascunho | estado RASCUNHO (ou despublicado) |
| Agendado | publicado e data de abertura no futuro |
| Publicado | publicado, já aberto e não encerrado |
| Encerrado | encerrado manualmente ou data de encerramento passou |

A página pública mostra um aviso sem campos para agendado ("ainda não está aberto", com a data de abertura),
encerrado e rascunho.

## 8. Anonimato

- Formulário anônimo **nunca** grava nome, e-mail, IP, user agent ou identificador de dispositivo.
- O IP é usado apenas no Worker para o limite de envios: vira um hash SHA-256 com sal que muda todo dia, guardado no
  KV por 1 hora.
- **Grupo mínimo** (padrão 3): recortes por área ou gestor com menos respostas não mostram índice, comentários nem
  base ("Respostas insuficientes para preservar o anonimato"). Na base e nas perguntas abertas, o nome de uma área ou
  gestor com menos respostas que o mínimo aparece como "Grupo pequeno".
- Exemplo: grupo mínimo 3; a área Logística teve 2 respostas no ciclo → a barra mostra "grupo pequeno" e o filtro
  por Logística esconde os resultados.

## 9. Gestor x área

O vínculo é opcional. Quando o gestor tem área, a página pública só o mostra depois que o participante escolhe essa
área; gestores sem área aparecem sempre. O Worker rejeita combinação de gestor e área incompatível.
