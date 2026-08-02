-- ============================================================
--  Migration 008 — Cronograma de aulas + justificativa de ausência
--  Executar manualmente: mysql -u USER -p DBNAME < migration_008_cronograma_justificativas.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── grade_horarios: vincular a um professor específico e permitir desativar ──
ALTER TABLE `grade_horarios`
  ADD COLUMN `professor_id` INT UNSIGNED NULL AFTER `nucleo_id`,
  ADD COLUMN `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo' AFTER `horario_fim`,
  ADD KEY `idx_professor_id` (`professor_id`),
  ADD CONSTRAINT `fk_gh_professor` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`);

-- Backfill: assume o único professor já vinculado ao núcleo (padrão atual do sistema)
UPDATE `grade_horarios` gh
SET gh.professor_id = (
  SELECT np.usuario_id FROM `nucleo_professores` np
  WHERE np.nucleo_id = gh.nucleo_id
  ORDER BY np.id ASC LIMIT 1
)
WHERE gh.professor_id IS NULL;

-- ─── aulas_previstas: instância concreta de uma aula esperada numa data ───────
-- Snapshot de horario/professor/nucleo no momento da geração — preserva o
-- histórico mesmo que o cronograma (grade_horarios) seja alterado depois.
CREATE TABLE IF NOT EXISTS `aulas_previstas` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cronograma_id`       INT UNSIGNED  DEFAULT NULL COMMENT 'FK → grade_horarios; SET NULL se o horário for excluído (histórico preservado via snapshot)',
  `professor_id`        INT UNSIGNED  NOT NULL,
  `nucleo_id`           INT UNSIGNED  NOT NULL,
  `data`                DATE          NOT NULL,
  `horario_inicio`      TIME          NOT NULL COMMENT 'Snapshot do horário previsto na época',
  `horario_fim`         TIME          NOT NULL COMMENT 'Snapshot do horário previsto na época',
  `status`              ENUM('prevista','realizada','justificativa_pendente','justificada','cancelada') NOT NULL DEFAULT 'prevista',
  `chamada_id`          INT UNSIGNED  DEFAULT NULL COMMENT 'FK → chamadas, preenchido quando a aula é identificada como realizada',
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

-- ─── justificativas_ausencia: motivo enviado pelo professor por aula ──────────
CREATE TABLE IF NOT EXISTS `justificativas_ausencia` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `aula_prevista_id` INT UNSIGNED  NOT NULL,
  `professor_id`     INT UNSIGNED  NOT NULL,
  `motivo`           TEXT          NOT NULL,
  `enviado_em`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `criado_em`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aula_prevista` (`aula_prevista_id`),
  KEY `idx_professor_id` (`professor_id`),
  CONSTRAINT `fk_ja_aula`      FOREIGN KEY (`aula_prevista_id`) REFERENCES `aulas_previstas` (`id`),
  CONSTRAINT `fk_ja_professor` FOREIGN KEY (`professor_id`)     REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
