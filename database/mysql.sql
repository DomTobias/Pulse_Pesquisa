-- =====================================================================
-- Pulse: banco definitivo (MySQL 8)
-- Fonte oficial de tudo: definições, cadastros, respostas e logs.
-- Datas gravadas em UTC (DATETIME). A conversão para o fuso configurado
-- (padrão America/Cuiaba) é feita pelo gerenciador e pelo sincronizador.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS pulse CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE pulse;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
-- Acesso administrativo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios_admin (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  perfil        ENUM('ADMIN','LEITOR') NOT NULL DEFAULT 'LEITOR',
  cargo         VARCHAR(120) NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  trocar_senha  TINYINT(1) NOT NULL DEFAULT 0,
  tentativas_login INT NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME NULL,
  ultimo_acesso DATETIME NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auditoria (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,
  acao        VARCHAR(60) NOT NULL,
  entidade    VARCHAR(60) NOT NULL,
  entidade_id VARCHAR(64) NULL,
  detalhes    JSON NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auditoria_data (criado_em),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Preferências da plataforma (chave/valor)
CREATE TABLE IF NOT EXISTS configuracoes (
  chave         VARCHAR(80) PRIMARY KEY,
  valor         TEXT NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Cadastros do Pulse
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS areas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid          CHAR(36) NOT NULL,
  nome          VARCHAR(160) NOT NULL,
  colaboradores INT UNSIGNED NOT NULL DEFAULT 0,
  ordem         INT NOT NULL DEFAULT 0,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_areas_uuid (uuid)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gestores (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid     CHAR(36) NOT NULL,
  nome     VARCHAR(120) NOT NULL,
  area_id  INT UNSIGNED NULL,          -- NULL até existir o vínculo gestor x área (seção 5.6)
  ordem    INT NOT NULL DEFAULT 0,
  ativo    TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_gestores_uuid (uuid),
  CONSTRAINT fk_gestores_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS faixas_pontuacao (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ordem            INT NOT NULL,
  nome             VARCHAR(80) NOT NULL,
  limite_superior  DECIMAL(4,2) NOT NULL,
  cor              CHAR(7) NOT NULL,
  UNIQUE KEY uq_faixas_ordem (ordem)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Formulários (definição original)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS formularios (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                   CHAR(36) NOT NULL,
  slug                   VARCHAR(80) NOT NULL,
  titulo                 VARCHAR(200) NOT NULL,
  descricao              TEXT NULL,
  mensagem_apresentacao  TEXT NULL,
  mensagem_conclusao     TEXT NULL,
  estado                 ENUM('RASCUNHO','ATIVO','ENCERRADO') NOT NULL DEFAULT 'RASCUNHO',
  inicio_em              DATETIME NULL,
  fim_em                 DATETIME NULL,
  recorrencia            ENUM('NENHUMA','SEMANAL','QUINZENAL','MENSAL') NOT NULL DEFAULT 'NENHUMA',
  -- Rodízio: em cada ciclo só um tópico fica liberado (ciclo 1 = 1º tópico, ciclo 2 = 2º, ... e recomeça)
  topico_por_ciclo       TINYINT(1) NOT NULL DEFAULT 0,
  segmentado            TINYINT(1) NOT NULL DEFAULT 0,
  exige_identificacao    TINYINT(1) NOT NULL DEFAULT 0,
  permite_multiplas      TINYINT(1) NOT NULL DEFAULT 0,
  formato                ENUM('AUTO','ETAPAS','PAGINA_UNICA') NOT NULL DEFAULT 'AUTO',
  cor                    CHAR(7) NOT NULL DEFAULT '#032B4C',
  capa                   JSON NULL,
  versao                 INT UNSIGNED NOT NULL DEFAULT 1,
  criado_em              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  excluido_em            DATETIME NULL,
  UNIQUE KEY uq_formularios_uuid (uuid),
  UNIQUE KEY uq_formularios_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS topicos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid           CHAR(36) NOT NULL,
  formulario_id  INT UNSIGNED NOT NULL,
  titulo         VARCHAR(200) NOT NULL,
  descricao      TEXT NULL,
  ordem          INT NOT NULL DEFAULT 0,
  ativo          TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_topicos_uuid (uuid),
  KEY idx_topicos_form (formulario_id, ordem),
  CONSTRAINT fk_topicos_form FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS perguntas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid            CHAR(36) NOT NULL,
  topico_id       INT UNSIGNED NOT NULL,
  tipo            ENUM('CURTA','LONGA','NUMERO','EMAIL','TELEFONE','DATA','UNICA','MULTIPLA','LISTA','ESCALA','SIM_NAO','INFO') NOT NULL,
  titulo          VARCHAR(500) NOT NULL,
  orientacao      TEXT NULL,
  obrigatoria     TINYINT(1) NOT NULL DEFAULT 0,
  ordem           INT NOT NULL DEFAULT 0,
  minimo          DECIMAL(10,2) NULL,
  maximo          DECIMAL(10,2) NULL,
  rotulo_minimo   VARCHAR(80) NULL,
  rotulo_maximo   VARCHAR(80) NULL,
  aparencia       ENUM('NEUTRA','ZONAS') NOT NULL DEFAULT 'NEUTRA',
  ativo           TINYINT(1) NOT NULL DEFAULT 1,   -- perguntas removidas com respostas ficam inativas (histórico)
  versao_inclusao INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_perguntas_uuid (uuid),
  KEY idx_perguntas_topico (topico_id, ordem),
  CONSTRAINT fk_perguntas_topico FOREIGN KEY (topico_id) REFERENCES topicos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS opcoes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36) NOT NULL,
  pergunta_id  INT UNSIGNED NOT NULL,
  rotulo       VARCHAR(300) NOT NULL,
  ordem        INT NOT NULL DEFAULT 0,
  ativo        TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_opcoes_uuid (uuid),
  CONSTRAINT fk_opcoes_pergunta FOREIGN KEY (pergunta_id) REFERENCES perguntas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Recebimento (Fluxo A)
-- ---------------------------------------------------------------------
-- Bruto, mantido do escopo original
CREATE TABLE IF NOT EXISTS formularios_recebidos (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid_externo     CHAR(36) NOT NULL,
  formulario_id    CHAR(36) NOT NULL,
  dados            JSON NOT NULL,
  recebido_em      DATETIME NOT NULL,
  sincronizado_em  DATETIME NOT NULL,
  UNIQUE KEY uq_recebidos_uuid (uuid_externo),
  KEY idx_recebidos_form (formulario_id, recebido_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS respostas (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid_externo     CHAR(36) NOT NULL,
  formulario_id    INT UNSIGNED NOT NULL,
  versao           INT UNSIGNED NOT NULL DEFAULT 1,
  ciclo            INT NULL,
  area_id          INT UNSIGNED NULL,
  gestor_id        INT UNSIGNED NULL,
  status           ENUM('COMPLETA','PARCIAL') NOT NULL DEFAULT 'COMPLETA',
  identificacao    JSON NULL,   -- só para formulários com "Exigir identificação"; nunca no Pulse
  recebido_em      DATETIME NOT NULL,
  sincronizado_em  DATETIME NOT NULL,
  UNIQUE KEY uq_respostas_uuid (uuid_externo),
  KEY idx_respostas_form_ciclo (formulario_id, ciclo),
  KEY idx_respostas_form_data (formulario_id, recebido_em),
  KEY idx_respostas_area (area_id),
  KEY idx_respostas_gestor (gestor_id),
  CONSTRAINT fk_respostas_form   FOREIGN KEY (formulario_id) REFERENCES formularios(id),
  CONSTRAINT fk_respostas_area   FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
  CONSTRAINT fk_respostas_gestor FOREIGN KEY (gestor_id) REFERENCES gestores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS respostas_itens (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resposta_id     BIGINT UNSIGNED NOT NULL,
  pergunta_id     INT UNSIGNED NOT NULL,
  valor_numerico  DECIMAL(10,2) NULL,
  valor_texto     TEXT NULL,
  opcao_id        INT UNSIGNED NULL,
  UNIQUE KEY uq_itens (resposta_id, pergunta_id, opcao_id),
  KEY idx_itens_pergunta (pergunta_id),
  CONSTRAINT fk_itens_resposta FOREIGN KEY (resposta_id) REFERENCES respostas(id) ON DELETE CASCADE,
  CONSTRAINT fk_itens_pergunta FOREIGN KEY (pergunta_id) REFERENCES perguntas(id),
  CONSTRAINT fk_itens_opcao    FOREIGN KEY (opcao_id) REFERENCES opcoes(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Publicação (Fluxo B) e sincronização
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS publicacoes (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formulario_id  INT UNSIGNED NOT NULL,
  versao         INT UNSIGNED NOT NULL,
  status         ENUM('PENDENTE','ENVIADA','ERRO') NOT NULL DEFAULT 'PENDENTE',
  motivo         VARCHAR(60) NOT NULL DEFAULT 'PUBLICAR',   -- PUBLICAR | ATUALIZAR | ENCERRAR | DESPUBLICAR
  definicao      JSON NOT NULL,   -- definição pública montada pelo gerenciador (contrato 13.1), com estado
  tentativas     INT NOT NULL DEFAULT 0,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_em     DATETIME NULL,
  mensagem_erro  TEXT NULL,
  KEY idx_publicacoes_status (status, criado_em),
  CONSTRAINT fk_publicacoes_form FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Imagens já enviadas ao Supabase Storage (evita reenvio)
CREATE TABLE IF NOT EXISTS imagens_publicadas (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sha256     CHAR(64) NOT NULL,
  url        VARCHAR(500) NOT NULL,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_imagens_hash (sha256)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sincronizacao_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nivel      ENUM('DEBUG','INFO','WARNING','ERROR','CRITICAL') NOT NULL,
  evento     VARCHAR(60) NOT NULL,
  uuid_envio CHAR(36) NULL,
  mensagem   TEXT NOT NULL,              -- nunca contém senhas nem tokens
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_synclog_data (criado_em),
  KEY idx_synclog_nivel (nivel, criado_em)
) ENGINE=InnoDB;

-- Estado informado pelo sincronizador (o gerenciador nunca consulta o Supabase)
CREATE TABLE IF NOT EXISTS sync_status (
  chave         VARCHAR(60) PRIMARY KEY,
  valor         VARCHAR(255) NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Espelho dos registros em ERRO na fila do Supabase
CREATE TABLE IF NOT EXISTS fila_erros (
  uuid_envio     CHAR(36) PRIMARY KEY,
  formulario_id  CHAR(36) NULL,
  tentativas     INT NOT NULL DEFAULT 0,
  mensagem_erro  TEXT NULL,
  criado_em      DATETIME NULL,
  atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Comandos do gerenciador para o sincronizador (ex.: reprocessar)
CREATE TABLE IF NOT EXISTS comandos_sync (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  comando       ENUM('REPROCESSAR') NOT NULL,
  alvo          CHAR(36) NOT NULL,
  status        ENUM('PENDENTE','EXECUTADO','ERRO') NOT NULL DEFAULT 'PENDENTE',
  usuario_id    INT UNSIGNED NULL,
  mensagem      TEXT NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  executado_em  DATETIME NULL,
  KEY idx_comandos_status (status)
) ENGINE=InnoDB;

-- =====================================================================
-- Cargas iniciais
-- =====================================================================

-- 11 áreas (número de colaboradores a preencher em Configurações)
INSERT INTO areas (uuid, nome, colaboradores, ordem) VALUES
 (UUID(), 'Administrativo + Segurança do Trabalho', 0, 1),
 (UUID(), 'Recursos Humanos + TI', 0, 2),
 (UUID(), 'Produção', 0, 3),
 (UUID(), 'Enxoval', 0, 4),
 (UUID(), 'Manutenção', 0, 5),
 (UUID(), 'Logística', 0, 6),
 (UUID(), 'Cáceres', 0, 7),
 (UUID(), 'Sinop', 0, 8),
 (UUID(), 'Rondonópolis', 0, 9),
 (UUID(), 'Cuiabá In Locos', 0, 10),
 (UUID(), 'Liderança (supervisor, analista de enxoval, gestor de clientes)', 0, 11);

-- 13 gestores
INSERT INTO gestores (uuid, nome, ordem) VALUES
 (UUID(), 'Tatielle', 1), (UUID(), 'Tássia', 2), (UUID(), 'Gustavo', 3), (UUID(), 'Luana', 4),
 (UUID(), 'Higor', 5), (UUID(), 'Juliane', 6), (UUID(), 'Quaresma', 7), (UUID(), 'Quesia', 8),
 (UUID(), 'Elizandro', 9), (UUID(), 'Alexandre', 10), (UUID(), 'Bruno', 11), (UUID(), 'Evellyn', 12),
 (UUID(), 'Marizete', 13);

-- 4 faixas da planilha (a nota no limite pertence à zona de cima)
INSERT INTO faixas_pontuacao (ordem, nome, limite_superior, cor) VALUES
 (1, 'Zona crítica',            5.00, '#D64545'),
 (2, 'Zona de melhoramento',    7.00, '#EE8B2A'),
 (3, 'Zona de aperfeiçoamento', 8.00, '#C9A414'),
 (4, 'Zona satisfatória',      10.00, '#2E9E5B');

-- Preferências
INSERT INTO configuracoes (chave, valor) VALUES
 ('plataforma_nome', 'Pulse'),
 ('dominio_links', 'https://dominio.com.br'),
 ('email_contato', 'contato@dominio.com.br'),
 ('fuso_horario', 'America/Cuiaba'),
 ('cor_primaria', '#032B4C'),
 ('grupo_minimo', '3'),
 ('padrao_formato', 'AUTO'),
 ('padrao_exige_identificacao', '0'),
 ('padrao_permite_multiplas', '0'),
 ('padrao_mensagem_conclusao', 'Obrigado! Sua resposta foi registrada com sucesso.'),
 ('tema', 'auto'),
 ('densidade', 'confortavel');

-- Administrador inicial. Senha: TrocarSenha@2026 (troca obrigatória no primeiro acesso)
INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, cargo, trocar_senha) VALUES
 ('Administrador', 'admin@dominio.com.br',
  '$2y$12$ZqYaSHg8U936ssNNJbggYudDzXCZqy8JqKDNeKpnTrrknCFLEr7aq', 'ADMIN', 'Recursos Humanos', 1);

-- Estrutura do Pulse (planilha "POR PERGUNTAS"): 5 tópicos, cada um com 5 perguntas de escala 0 a 10
-- (obrigatórias, com zonas) e a pergunta aberta opcional no fim. Tudo editável no Construtor do gerenciador.
SET @pulse_uuid = UUID();
INSERT INTO formularios (uuid, slug, titulo, descricao, mensagem_apresentacao, mensagem_conclusao, estado,
                         recorrencia, topico_por_ciclo, segmentado, exige_identificacao, permite_multiplas, formato, cor, capa)
VALUES (@pulse_uuid, 'pulse-nossa-gente', 'Pulse: Nossa Gente',
        'Pesquisa de clima organizacional enviada a cada 15 dias.',
        'Responda pensando nas últimas duas semanas. Nenhuma resposta é associada ao seu nome.',
        'Obrigado por participar! Sua voz ajuda a construir um lugar ainda melhor para todos.',
        'RASCUNHO', 'QUINZENAL', 1, 1, 0, 0, 'ETAPAS', '#032B4C',
        JSON_OBJECT('tipo','INSTITUCIONAL','titulo','PULSE','subtitulo','Nossa Gente',
                    'chamada','Sua voz transforma nosso futuro!',
                    'destaque_titulo','Esta é a nossa pesquisa de clima organizacional.',
                    'destaque_texto','Aqui, você pode compartilhar sua opinião de forma simples, rápida e anônima.',
                    'convite','Contamos com você!',
                    'encerramento','Juntos, construímos um lugar ainda melhor para todos!',
                    'simbolo', NULL, 'foto', NULL));
SET @pulse_id = LAST_INSERT_ID();

INSERT INTO topicos (uuid, formulario_id, titulo, descricao, ordem) VALUES
 (UUID(), @pulse_id, 'Liderança', '', 1),
 (UUID(), @pulse_id, 'Estrutura organizacional', '', 2),
 (UUID(), @pulse_id, 'Benefícios', '', 3),
 (UUID(), @pulse_id, 'Orgulho', '', 4),
 (UUID(), @pulse_id, 'Reconhecimento', '', 5);

INSERT INTO perguntas (uuid, topico_id, tipo, titulo, orientacao, obrigatoria, ordem,
                       minimo, maximo, rotulo_minimo, rotulo_maximo, aparencia)
SELECT UUID(), t.id, 'ESCALA', q.titulo, '', 1, q.ordem, 0, 10, q.rot_min, q.rot_max, 'ZONAS'
FROM topicos t
JOIN (
  SELECT 1 AS topico, 1 AS ordem, 'Quanto você confia na capacidade de liderança do seu gestor?' AS titulo, 'Nada' AS rot_min, 'Totalmente' AS rot_max
  UNION ALL SELECT 1, 2, 'Quanto seu gestor oferece direcionamento claro sobre suas atividades e responsabilidades?', 'Nada', 'Totalmente'
  UNION ALL SELECT 1, 3, 'Quanto seu gestor está disponível para ouvir e apoiar as necessidades da equipe?', 'Nada', 'Totalmente'
  UNION ALL SELECT 1, 4, 'Quanto seu gestor fornece feedbacks que contribuem para seu desenvolvimento profissional?', 'Nada', 'Totalmente'
  UNION ALL SELECT 1, 5, 'Quanto seu gestor conduz a equipe com transparência, respeito e imparcialidade?', 'Nada', 'Totalmente'
  UNION ALL SELECT 2, 1, 'Quanto você considera claras as responsabilidades e atribuições do seu cargo?', 'Nada', 'Totalmente'
  UNION ALL SELECT 2, 2, 'Quanto os processos e procedimentos da empresa facilitam a execução do seu trabalho?', 'Nada', 'Totalmente'
  UNION ALL SELECT 2, 3, 'Quanto a comunicação entre as áreas contribui para o bom andamento das atividades?', 'Nada', 'Totalmente'
  UNION ALL SELECT 2, 4, 'Quanto você considera adequada a quantidade de profissionais para atender às demandas da área?', 'Nada', 'Totalmente'
  UNION ALL SELECT 2, 5, 'Quanto a estrutura e os recursos disponibilizados pela empresa permitem que você realize seu trabalho com eficiência?', 'Nada', 'Totalmente'
  UNION ALL SELECT 3, 1, 'Quanto os benefícios oferecidos pela empresa atendem às suas necessidades?', 'Nada', 'Totalmente'
  UNION ALL SELECT 3, 2, 'Quanto você considera competitivo o pacote de benefícios em relação ao mercado?', 'Nada', 'Totalmente'
  UNION ALL SELECT 3, 3, 'Quanto os benefícios oferecidos contribuem para sua qualidade de vida?', 'Nada', 'Totalmente'
  UNION ALL SELECT 3, 4, 'Quanto você está satisfeito(a) com as condições e regras de utilização dos benefícios?', 'Nada', 'Totalmente'
  UNION ALL SELECT 3, 5, 'Quanto você considera que a empresa demonstra preocupação com o bem-estar dos colaboradores por meio dos benefícios oferecidos?', 'Nada', 'Totalmente'
  UNION ALL SELECT 4, 1, 'Quanto você sente orgulho em fazer parte desta empresa?', 'Nada', 'Totalmente'
  UNION ALL SELECT 4, 2, 'Quanto você recomendaria a empresa como um bom lugar para trabalhar?', 'Nada', 'Totalmente'
  UNION ALL SELECT 4, 3, 'Você pretende ficar na empresa pelos próximos 12 meses?', 'Com certeza não', 'Com certeza sim'
  UNION ALL SELECT 4, 4, 'Quanto você se identifica com os valores e a cultura organizacional?', 'Nada', 'Totalmente'
  UNION ALL SELECT 4, 5, 'Quanto você acredita que a empresa pratica aquilo que comunica como seus valores?', 'Nada', 'Totalmente'
  UNION ALL SELECT 5, 1, 'Quanto você se sente reconhecido(a) pelo trabalho que realiza?', 'Nada', 'Totalmente'
  UNION ALL SELECT 5, 2, 'Quanto seus resultados e entregas são valorizados pelo seu gestor?', 'Nada', 'Totalmente'
  UNION ALL SELECT 5, 3, 'Quanto a empresa reconhece os profissionais que se destacam em suas atividades?', 'Nada', 'Totalmente'
  UNION ALL SELECT 5, 4, 'Quanto você considera justos os critérios utilizados para reconhecer bons resultados?', 'Nada', 'Totalmente'
  UNION ALL SELECT 5, 5, 'Quanto você recebe reconhecimento quando demonstra iniciativa e contribui com melhorias para a empresa?', 'Nada', 'Totalmente'
) q ON q.topico = t.ordem
WHERE t.formulario_id = @pulse_id;

INSERT INTO perguntas (uuid, topico_id, tipo, titulo, orientacao, obrigatoria, ordem)
SELECT UUID(), t.id, 'LONGA', 'Você tem alguma sugestão ou melhoria para reportar?',
       'Opcional. Sua resposta continua anônima.', 0, 99
FROM topicos t WHERE t.formulario_id = @pulse_id;
