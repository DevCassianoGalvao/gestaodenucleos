-- ============================================================
--  Gestão de Núcleos — Schema v1.0
--  Executar no MySQL 8+  |  charset utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Usuários (super_admin, professor, aluno) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nome`         VARCHAR(150)     NOT NULL,
  `email`        VARCHAR(180)     NOT NULL,
  `senha_hash`   VARCHAR(255)     NOT NULL,
  `telefone`     VARCHAR(20)      DEFAULT NULL,
  `foto`         VARCHAR(255)     DEFAULT NULL COMMENT 'Caminho relativo ao /uploads (WebP)',
  `descricao`    TEXT             DEFAULT NULL,
  `redes_sociais` JSON            DEFAULT NULL COMMENT '{"instagram":"","tiktok":"","facebook":""}',
  `perfil`       ENUM('super_admin','professor','aluno') NOT NULL DEFAULT 'aluno',
  `status`       ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_perfil_status` (`perfil`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Projetos ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projetos` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(150)  NOT NULL,
  `descricao`   TEXT          DEFAULT NULL,
  `logo`        VARCHAR(255)  DEFAULT NULL COMMENT 'WebP, 300x300px',
  `status`      ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Núcleos ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nucleos` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `projeto_id`  INT UNSIGNED  NOT NULL,
  `nome`        VARCHAR(150)  NOT NULL,
  `municipio`   VARCHAR(100)  NOT NULL,
  `estado`      CHAR(2)       NOT NULL DEFAULT 'RJ',
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
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`    INT UNSIGNED  NOT NULL,
  `professor_id` INT UNSIGNED  NOT NULL,
  `data_aula`    DATE          NOT NULL,
  `criado_em`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id`    (`nucleo_id`),
  KEY `idx_professor_id` (`professor_id`),
  KEY `idx_data_aula`    (`data_aula`),
  CONSTRAINT `fk_chamadas_nucleo`     FOREIGN KEY (`nucleo_id`)    REFERENCES `nucleos`  (`id`),
  CONSTRAINT `fk_chamadas_professor`  FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`)
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

-- ─── Grade de horários ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `grade_horarios` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nucleo_id`      INT UNSIGNED  NOT NULL,
  `dia_semana`     TINYINT       NOT NULL COMMENT '0=Dom, 1=Seg, ..., 6=Sáb',
  `horario_inicio` TIME          NOT NULL,
  `horario_fim`    TIME          NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nucleo_id` (`nucleo_id`),
  CONSTRAINT `fk_gh_nucleo` FOREIGN KEY (`nucleo_id`) REFERENCES `nucleos` (`id`)
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
