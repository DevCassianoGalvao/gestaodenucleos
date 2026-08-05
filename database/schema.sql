-- ============================================================
--  Gestão de Núcleos — Schema v1.0
--  Executar no MySQL 8+  |  charset utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Institutos (nível acima de Projeto) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `institutos` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nome`             VARCHAR(150)  NOT NULL,
  `nome_fantasia`    VARCHAR(150)  DEFAULT NULL,
  `descricao`        TEXT          DEFAULT NULL,
  `logotipo`         VARCHAR(255)  DEFAULT NULL COMMENT 'WebP, caminho relativo a /uploads',
  `identificacao`    VARCHAR(60)   DEFAULT NULL COMMENT 'CNPJ ou código interno',
  `responsavel_nome` VARCHAR(150)  DEFAULT NULL,
  `contato_email`    VARCHAR(180)  DEFAULT NULL,
  `contato_telefone` VARCHAR(20)   DEFAULT NULL,
  `observacoes`      TEXT          DEFAULT NULL,
  `status`           ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Usuários (super_admin, gestor, professor, aluno) ─────────────────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nome`         VARCHAR(150)     NOT NULL,
  `email`        VARCHAR(180)     NOT NULL,
  `senha_hash`   VARCHAR(255)     NOT NULL,
  `telefone`     VARCHAR(20)      DEFAULT NULL,
  `foto`         VARCHAR(255)     DEFAULT NULL COMMENT 'Caminho relativo ao /uploads (WebP)',
  `descricao`    TEXT             DEFAULT NULL,
  `redes_sociais` JSON            DEFAULT NULL COMMENT '{"instagram":"","tiktok":"","facebook":""}',
  `perfil`       ENUM('super_admin','gestor','professor','aluno') NOT NULL DEFAULT 'aluno',
  `cargo`        ENUM('presidente','coordenador_geral','coordenador_projeto','coordenador_nucleo','professor','monitor','colaborador') DEFAULT NULL COMMENT 'Rótulo organizacional — quem manda de fato é usuario_permissoes/escopos_usuario',
  `status`       ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_perfil_status` (`perfil`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Projetos ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projetos` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `instituto_id` INT UNSIGNED  NOT NULL,
  `nome`         VARCHAR(150)  NOT NULL,
  `descricao`    TEXT          DEFAULT NULL,
  `logo`         VARCHAR(255)  DEFAULT NULL COMMENT 'WebP, 300x300px',
  `status`       ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_instituto_id` (`instituto_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_projetos_instituto` FOREIGN KEY (`instituto_id`) REFERENCES `institutos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Termo de Fomento (associado ao Projeto) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `termos_fomento` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `projeto_id`  INT UNSIGNED  NOT NULL,
  `numero`      VARCHAR(100)  DEFAULT NULL,
  `descricao`   TEXT          DEFAULT NULL,
  `data_inicio` DATE          DEFAULT NULL,
  `data_fim`    DATE          DEFAULT NULL,
  `status`      ENUM('ativo','encerrado','suspenso') NOT NULL DEFAULT 'ativo',
  `observacoes` TEXT          DEFAULT NULL,
  `criado_por`  INT UNSIGNED  DEFAULT NULL,
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projeto_id` (`projeto_id`),
  CONSTRAINT `fk_tf_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`),
  CONSTRAINT `fk_tf_criador` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Anexos do Termo de Fomento ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `termo_fomento_anexos` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `termo_fomento_id` INT UNSIGNED  NOT NULL,
  `nome_arquivo`     VARCHAR(255)  NOT NULL,
  `arquivo_path`     VARCHAR(255)  NOT NULL,
  `tamanho_bytes`    INT UNSIGNED  DEFAULT NULL,
  `enviado_por`      INT UNSIGNED  NOT NULL,
  `criado_em`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_termo_fomento_id` (`termo_fomento_id`),
  CONSTRAINT `fk_tfa_termo`   FOREIGN KEY (`termo_fomento_id`) REFERENCES `termos_fomento` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tfa_usuario` FOREIGN KEY (`enviado_por`)      REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Permissões granulares (catálogo) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `permissoes` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave`     VARCHAR(80)  NOT NULL COMMENT 'ex: nucleos.editar',
  `modulo`    VARCHAR(60)  NOT NULL,
  `label`     VARCHAR(150) NOT NULL,
  `ordem`     INT UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Permissões concedidas por usuário ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuario_permissoes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `permissao_id`  INT UNSIGNED NOT NULL,
  `concedido_por` INT UNSIGNED DEFAULT NULL,
  `criado_em`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_permissao` (`usuario_id`, `permissao_id`),
  KEY `idx_permissao_id` (`permissao_id`),
  CONSTRAINT `fk_up_usuario`    FOREIGN KEY (`usuario_id`)    REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_permissao`  FOREIGN KEY (`permissao_id`)  REFERENCES `permissoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_concedente` FOREIGN KEY (`concedido_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Escopos de acesso por usuário (Instituto / Projeto / Núcleo) ─────────────
CREATE TABLE IF NOT EXISTS `escopos_usuario` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `tipo`          ENUM('instituto','projeto','nucleo') NOT NULL,
  `referencia_id` INT UNSIGNED NOT NULL,
  `concedido_por` INT UNSIGNED DEFAULT NULL,
  `criado_em`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_escopo` (`usuario_id`, `tipo`, `referencia_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_eu_usuario`    FOREIGN KEY (`usuario_id`)    REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eu_concedente` FOREIGN KEY (`concedido_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Núcleos ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nucleos` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `projeto_id`  INT UNSIGNED  NOT NULL,
  `nome`        VARCHAR(150)  NOT NULL,
  `municipio`   VARCHAR(100)  NOT NULL,
  `estado`      CHAR(2)       NOT NULL DEFAULT 'RJ',
  `latitude`    DECIMAL(10,8) DEFAULT NULL COMMENT 'Coordenadas do local de aula, usadas no check-in',
  `longitude`   DECIMAL(11,8) DEFAULT NULL,
  `status`      ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projeto_id` (`projeto_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_nucleos_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Professor ↔ Núcleo (N:N) ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nucleo_professores` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`   INT UNSIGNED  NOT NULL,
  `usuario_id`  INT UNSIGNED  NOT NULL COMMENT 'FK → usuarios (perfil=professor)',
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nucleo_professor` (`nucleo_id`, `usuario_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_np_nucleo`   FOREIGN KEY (`nucleo_id`)  REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_np_usuario`  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Alunos ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `alunos` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`         INT UNSIGNED  NOT NULL,
  `usuario_id`        INT UNSIGNED  DEFAULT NULL COMMENT 'FK → usuarios se cadastrou via convite',
  `nome`              VARCHAR(150)  NOT NULL,
  `email`             VARCHAR(180)  DEFAULT NULL,
  `telefone`          VARCHAR(20)   DEFAULT NULL,
  `whatsapp`          VARCHAR(20)   DEFAULT NULL,
  `endereco_completo` VARCHAR(255)  DEFAULT NULL,
  `cidade`            VARCHAR(100)  DEFAULT NULL,
  `cep`               VARCHAR(9)    DEFAULT NULL,
  `data_nascimento`   DATE          DEFAULT NULL,
  `foto`              VARCHAR(255)  DEFAULT NULL COMMENT 'WebP, 400x400px',
  `status`            ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id`  (`nucleo_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_status`     (`status`),
  CONSTRAINT `fk_alunos_nucleo`   FOREIGN KEY (`nucleo_id`)  REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_alunos_usuario`  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Convites por token ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `convites` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `token_hash`  CHAR(64)      NOT NULL COMMENT 'SHA-256 do token raw enviado na URL',
  `tipo`        ENUM('professor','aluno') NOT NULL,
  `nucleo_id`   INT UNSIGNED  NOT NULL,
  `criado_por`  INT UNSIGNED  NOT NULL COMMENT 'FK → usuarios',
  `status`      ENUM('pendente','usado','expirado') NOT NULL DEFAULT 'pendente',
  `expira_em`   DATETIME      NOT NULL,
  `usado_em`    DATETIME      DEFAULT NULL,
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_nucleo_id`  (`nucleo_id`),
  KEY `idx_status`     (`status`),
  KEY `idx_expira_em`  (`expira_em`),
  CONSTRAINT `fk_convites_nucleo`    FOREIGN KEY (`nucleo_id`)  REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_convites_criado`    FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Chamadas de frequência ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `chamadas` (
  `id`                          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`                   INT UNSIGNED  NOT NULL,
  `professor_id`                INT UNSIGNED  NOT NULL,
  `data_aula`                   DATE          NOT NULL,
  `registrado_retroativamente`  TINYINT(1)    NOT NULL DEFAULT 0,
  `justificativa_ausencia_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'preenchido quando o registro foi liberado por justificativa (ex: sem internet)',
  `criado_em`                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'data/hora real do lançamento no sistema',
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id`    (`nucleo_id`),
  KEY `idx_professor_id` (`professor_id`),
  KEY `idx_data_aula`    (`data_aula`),
  CONSTRAINT `fk_chamadas_nucleo`         FOREIGN KEY (`nucleo_id`)    REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_chamadas_professor`      FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Presenças por chamada ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `chamada_presencas` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `chamada_id`  INT UNSIGNED  NOT NULL,
  `aluno_id`    INT UNSIGNED  NOT NULL,
  `presente`    TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamada_aluno` (`chamada_id`, `aluno_id`),
  KEY `idx_aluno_id`   (`aluno_id`),
  CONSTRAINT `fk_cp_chamada` FOREIGN KEY (`chamada_id`) REFERENCES `chamadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_aluno`   FOREIGN KEY (`aluno_id`)   REFERENCES `alunos`   (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Histórico de correção de presença ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `chamada_presenca_historico` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `chamada_presenca_id`  INT UNSIGNED  NOT NULL,
  `presente_anterior`    TINYINT(1)    NOT NULL,
  `presente_novo`        TINYINT(1)    NOT NULL,
  `alterado_por`         INT UNSIGNED  NOT NULL,
  `criado_em`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chamada_presenca_id` (`chamada_presenca_id`),
  CONSTRAINT `fk_cph_presenca` FOREIGN KEY (`chamada_presenca_id`) REFERENCES `chamada_presencas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cph_usuario`  FOREIGN KEY (`alterado_por`)        REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Grade de horários (cronograma recorrente) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `grade_horarios` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`      INT UNSIGNED  NOT NULL,
  `professor_id`   INT UNSIGNED  DEFAULT NULL,
  `dia_semana`     TINYINT       NOT NULL COMMENT '0=Dom, 1=Seg, ..., 6=Sáb',
  `horario_inicio` TIME          NOT NULL,
  `horario_fim`    TIME          NOT NULL,
  `status`         ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id`    (`nucleo_id`),
  KEY `idx_professor_id` (`professor_id`),
  CONSTRAINT `fk_gh_nucleo`    FOREIGN KEY (`nucleo_id`)    REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_gh_professor` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Aulas previstas (ocorrência concreta do cronograma numa data) ────────────
-- Snapshot de horário/professor/núcleo no momento da geração — preserva
-- histórico mesmo que o cronograma (grade_horarios) seja alterado depois.
CREATE TABLE IF NOT EXISTS `aulas_previstas` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cronograma_id`       INT UNSIGNED  DEFAULT NULL COMMENT 'FK → grade_horarios; SET NULL se o horário for excluído',
  `professor_id`        INT UNSIGNED  NOT NULL,
  `nucleo_id`           INT UNSIGNED  NOT NULL,
  `data`                DATE          NOT NULL,
  `horario_inicio`      TIME          NOT NULL COMMENT 'Snapshot do horário previsto na época',
  `horario_fim`         TIME          NOT NULL COMMENT 'Snapshot do horário previsto na época',
  `status`              ENUM('prevista','realizada','justificativa_pendente','justificada','cancelada') NOT NULL DEFAULT 'prevista',
  `chamada_id`          INT UNSIGNED  DEFAULT NULL COMMENT 'FK → chamadas, preenchido quando identificada como realizada',
  `cancelado_por`       INT UNSIGNED  DEFAULT NULL COMMENT 'FK → usuarios (admin que cancelou)',
  `cancelado_em`        DATETIME      DEFAULT NULL,
  `motivo_cancelamento` VARCHAR(255)  DEFAULT NULL,
  `criado_em`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cronograma_data` (`cronograma_id`, `data`),
  KEY `idx_professor_data` (`professor_id`, `data`),
  KEY `idx_nucleo_data`    (`nucleo_id`, `data`),
  KEY `idx_status`         (`status`),
  CONSTRAINT `fk_ap_cronograma`  FOREIGN KEY (`cronograma_id`) REFERENCES `grade_horarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_professor`   FOREIGN KEY (`professor_id`)  REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_ap_nucleo`      FOREIGN KEY (`nucleo_id`)     REFERENCES `nucleos` (`id`),
  CONSTRAINT `fk_ap_chamada`     FOREIGN KEY (`chamada_id`)    REFERENCES `chamadas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_cancelador`  FOREIGN KEY (`cancelado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Justificativas de ausência (uma por aula prevista) ───────────────────────
CREATE TABLE IF NOT EXISTS `justificativas_ausencia` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `aula_prevista_id` INT UNSIGNED  NOT NULL,
  `professor_id`     INT UNSIGNED  NOT NULL,
  `tipo`             ENUM('sem_internet','chuva','problema_local','imprevisto','outro') NOT NULL DEFAULT 'outro',
  `motivo`           TEXT          NOT NULL,
  `enviado_em`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `criado_em`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aula_prevista` (`aula_prevista_id`),
  KEY `idx_professor_id` (`professor_id`),
  CONSTRAINT `fk_ja_aula`      FOREIGN KEY (`aula_prevista_id`) REFERENCES `aulas_previstas` (`id`),
  CONSTRAINT `fk_ja_professor` FOREIGN KEY (`professor_id`)     REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `chamadas` ADD CONSTRAINT `fk_chamadas_justificativa` FOREIGN KEY (`justificativa_ausencia_id`) REFERENCES `justificativas_ausencia` (`id`) ON DELETE SET NULL;

-- ─── Check-ins de geolocalização ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `checkins` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `professor_id`  INT UNSIGNED    NOT NULL,
  `nucleo_id`     INT UNSIGNED    NOT NULL,
  `latitude`      DECIMAL(10,8)   NOT NULL,
  `longitude`     DECIMAL(11,8)   NOT NULL,
  `precisao_m`    INT UNSIGNED    DEFAULT NULL COMMENT 'Precisão do GPS em metros (Geolocation API), quando disponível',
  `endereco`      VARCHAR(500)    DEFAULT NULL,
  `distancia_m`   INT UNSIGNED    DEFAULT NULL,
  `status`        ENUM('dentro_raio','fora_raio','sem_coordenadas') NOT NULL,
  `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_professor_id` (`professor_id`),
  KEY `idx_nucleo_id`    (`nucleo_id`),
  KEY `idx_criado_em`    (`criado_em`),
  CONSTRAINT `fk_checkins_professor` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_checkins_nucleo`    FOREIGN KEY (`nucleo_id`)    REFERENCES `nucleos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Atividades diárias (o que aconteceu na aula) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `atividades` (
  `id`                          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`                   INT UNSIGNED  NOT NULL,
  `professor_id`                INT UNSIGNED  NOT NULL,
  `aula_prevista_id`            INT UNSIGNED  DEFAULT NULL,
  `chamada_id`                  INT UNSIGNED  DEFAULT NULL,
  `data`                        DATE          NOT NULL,
  `horario`                     TIME          DEFAULT NULL,
  `descricao`                   TEXT          NOT NULL,
  `observacoes`                 TEXT          DEFAULT NULL,
  `status`                      ENUM('rascunho','concluida') NOT NULL DEFAULT 'concluida',
  `registrado_retroativamente`  TINYINT(1)    NOT NULL DEFAULT 0,
  `criado_em`                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_data`    (`nucleo_id`, `data`),
  KEY `idx_professor_data` (`professor_id`, `data`),
  CONSTRAINT `fk_ativ_nucleo`    FOREIGN KEY (`nucleo_id`)        REFERENCES `nucleos` (`id`),
  CONSTRAINT `fk_ativ_professor` FOREIGN KEY (`professor_id`)     REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_ativ_aula`      FOREIGN KEY (`aula_prevista_id`) REFERENCES `aulas_previstas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ativ_chamada`   FOREIGN KEY (`chamada_id`)       REFERENCES `chamadas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Evidências de atividade (fotos/documentos) ───────────────────────────────
CREATE TABLE IF NOT EXISTS `atividade_evidencias` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `atividade_id` INT UNSIGNED  NOT NULL,
  `tipo`         ENUM('foto','documento','video','outro') NOT NULL DEFAULT 'foto',
  `arquivo_path` VARCHAR(255)  NOT NULL,
  `latitude`     DECIMAL(10,8) DEFAULT NULL,
  `longitude`    DECIMAL(11,8) DEFAULT NULL,
  `capturado_em` DATETIME      DEFAULT NULL COMMENT 'data/hora em que a foto foi feita, se souber',
  `enviado_por`  INT UNSIGNED  NOT NULL,
  `criado_em`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'data real do upload',
  PRIMARY KEY (`id`),
  KEY `idx_atividade_id` (`atividade_id`),
  CONSTRAINT `fk_ae_atividade` FOREIGN KEY (`atividade_id`) REFERENCES `atividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ae_usuario`   FOREIGN KEY (`enviado_por`)  REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Checklist configurável por Projeto (infraestrutura) ──────────────────────
CREATE TABLE IF NOT EXISTS `projeto_requisitos` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `projeto_id`        INT UNSIGNED  NOT NULL,
  `nome`              VARCHAR(150)  NOT NULL,
  `tipo`              ENUM('foto','texto','lista_presenca','documento','video','confirmacao','outro') NOT NULL DEFAULT 'foto',
  `obrigatorio`       TINYINT(1)    NOT NULL DEFAULT 1,
  `quantidade_minima` INT UNSIGNED  NOT NULL DEFAULT 1,
  `instrucao`         TEXT          DEFAULT NULL,
  `ordem`             INT UNSIGNED  NOT NULL DEFAULT 0,
  `status`            ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projeto_id` (`projeto_id`),
  CONSTRAINT `fk_pr_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Depoimentos (mães/alunos/responsáveis) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `depoimentos` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`    INT UNSIGNED  NOT NULL,
  `aluno_id`     INT UNSIGNED  DEFAULT NULL,
  `autor_nome`   VARCHAR(150)  DEFAULT NULL,
  `conteudo`     TEXT          NOT NULL,
  `tipo`         ENUM('texto','audio','video','foto') NOT NULL DEFAULT 'texto',
  `arquivo_path` VARCHAR(255)  DEFAULT NULL,
  `criado_por`   INT UNSIGNED  NOT NULL,
  `criado_em`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id` (`nucleo_id`),
  CONSTRAINT `fk_dep_nucleo`  FOREIGN KEY (`nucleo_id`)  REFERENCES `nucleos` (`id`),
  CONSTRAINT `fk_dep_aluno`   FOREIGN KEY (`aluno_id`)   REFERENCES `alunos` (`id`),
  CONSTRAINT `fk_dep_criador` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Materiais ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `materiais` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`   INT UNSIGNED  DEFAULT NULL,
  `projeto_id`  INT UNSIGNED  DEFAULT NULL,
  `titulo`      VARCHAR(200)  NOT NULL,
  `tipo`        ENUM('pdf','imagem','link') NOT NULL,
  `arquivo_url` VARCHAR(512)  NOT NULL,
  `criado_por`  INT UNSIGNED  NOT NULL,
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id`  (`nucleo_id`),
  KEY `idx_projeto_id` (`projeto_id`),
  KEY `idx_criado_por` (`criado_por`),
  CONSTRAINT `fk_mat_nucleo`   FOREIGN KEY (`nucleo_id`)  REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_mat_projeto`  FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`),
  CONSTRAINT `fk_mat_criador`  FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Comunicados ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `comunicados` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `titulo`            VARCHAR(200)  NOT NULL,
  `mensagem`          TEXT          NOT NULL,
  `enviado_por`       INT UNSIGNED  NOT NULL,
  `destinatario_tipo` ENUM('todos','projeto','nucleo','aluno') NOT NULL,
  `destinatario_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'ID de projeto|nucleo|aluno conforme tipo',
  `criado_em`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enviado_por`       (`enviado_por`),
  KEY `idx_destinatario_tipo` (`destinatario_tipo`),
  CONSTRAINT `fk_com_enviado` FOREIGN KEY (`enviado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Fórum — tópicos ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `forum_topicos` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`   INT UNSIGNED  NOT NULL,
  `usuario_id`  INT UNSIGNED  NOT NULL,
  `titulo`      VARCHAR(200)  NOT NULL,
  `fixado`      TINYINT(1)    NOT NULL DEFAULT 0,
  `status`      ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id`  (`nucleo_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_ft_nucleo`   FOREIGN KEY (`nucleo_id`)  REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_ft_usuario`  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Fórum — posts ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `forum_posts` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `topico_id`   INT UNSIGNED  NOT NULL,
  `usuario_id`  INT UNSIGNED  NOT NULL,
  `conteudo`    TEXT          NOT NULL,
  `curtidas`    INT UNSIGNED  NOT NULL DEFAULT 0,
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_topico_id`  (`topico_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_fp_topico`   FOREIGN KEY (`topico_id`)  REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fp_usuario`  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Log de notificações ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notificacoes_log` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tipo`         VARCHAR(80)   NOT NULL COMMENT 'Ex: notif-novo-aluno, alerta-professor-inativo',
  `descricao`    VARCHAR(255)  DEFAULT NULL,
  `enviado_para` VARCHAR(180)  DEFAULT NULL COMMENT 'E-mail do destinatário',
  `status`       ENUM('enviado','erro') NOT NULL DEFAULT 'enviado',
  `criado_em`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo`      (`tipo`),
  KEY `idx_criado_em` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Audit log ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario_id`       INT UNSIGNED  DEFAULT NULL,
  `acao`             VARCHAR(80)   NOT NULL COMMENT 'Ex: login, logout, cadastro, edicao, exportacao',
  `tabela_afetada`   VARCHAR(80)   DEFAULT NULL,
  `registro_id`      INT UNSIGNED  DEFAULT NULL,
  `dados_anteriores` JSON          DEFAULT NULL,
  `dados_novos`      JSON          DEFAULT NULL,
  `ip`               VARCHAR(45)   DEFAULT NULL,
  `user_agent`       VARCHAR(512)  DEFAULT NULL,
  `criado_em`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_acao`       (`acao`),
  KEY `idx_criado_em`  (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Rate limiting de login ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(180)  NOT NULL,
  `ip`            VARCHAR(45)   NOT NULL,
  `sucesso`       TINYINT(1)    NOT NULL DEFAULT 0,
  `tentativa_em`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_tentativa` (`email`, `tentativa_em`),
  KEY `idx_ip_tentativa`    (`ip`, `tentativa_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
