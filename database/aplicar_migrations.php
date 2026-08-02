<?php
/**
 * Aplica as alterações de schema pendentes automaticamente. Roda a cada
 * deploy (chamado pelo .cpanel.yml) — idempotente: cada mudança verifica
 * se já existe antes de aplicar, então rodar de novo nunca dá erro nem
 * duplica nada. Não precisa de terminal nem phpMyAdmin para atualizar o banco.
 *
 * Executar manualmente se quiser: php database/aplicar_migrations.php
 */

define('ROOT_PATH', dirname(__DIR__));

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';

$log = fn(string $msg) => fwrite(STDOUT, '[migrations] ' . $msg . PHP_EOL);

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $db, string $table, string $indexName): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$table, $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

function constraintExists(PDO $db, string $constraintName): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?"
    );
    $stmt->execute([$constraintName]);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    $log('ERRO ao conectar no banco: ' . $e->getMessage());
    exit(1);
}

try {
    // ─── Migration 008: cronograma de aulas + justificativa de ausência ──────

    if (!columnExists($db, 'grade_horarios', 'professor_id')) {
        $db->exec("ALTER TABLE grade_horarios ADD COLUMN professor_id INT UNSIGNED NULL AFTER nucleo_id");
        $log('grade_horarios.professor_id — coluna criada.');
    } else {
        $log('grade_horarios.professor_id — já existe, ok.');
    }

    if (!indexExists($db, 'grade_horarios', 'idx_professor_id')) {
        $db->exec("ALTER TABLE grade_horarios ADD KEY idx_professor_id (professor_id)");
        $log('grade_horarios.idx_professor_id — índice criado.');
    }

    if (!constraintExists($db, 'fk_gh_professor')) {
        $db->exec("ALTER TABLE grade_horarios ADD CONSTRAINT fk_gh_professor FOREIGN KEY (professor_id) REFERENCES usuarios (id)");
        $log('grade_horarios — FK fk_gh_professor criada.');
    }

    if (!columnExists($db, 'grade_horarios', 'status')) {
        $db->exec("ALTER TABLE grade_horarios ADD COLUMN status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo' AFTER horario_fim");
        $log('grade_horarios.status — coluna criada.');
    } else {
        $log('grade_horarios.status — já existe, ok.');
    }

    // Backfill: assume o único professor já vinculado ao núcleo (padrão do sistema).
    // Sempre seguro rodar de novo — só afeta linhas com professor_id ainda vazio.
    $afetadas = $db->exec("
        UPDATE grade_horarios gh
        SET gh.professor_id = (
          SELECT np.usuario_id FROM nucleo_professores np
          WHERE np.nucleo_id = gh.nucleo_id
          ORDER BY np.id ASC LIMIT 1
        )
        WHERE gh.professor_id IS NULL
    ");
    if ($afetadas > 0) {
        $log("grade_horarios — professor_id preenchido em $afetadas linha(s) antiga(s).");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS aulas_previstas (
          id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          cronograma_id       INT UNSIGNED  DEFAULT NULL,
          professor_id        INT UNSIGNED  NOT NULL,
          nucleo_id           INT UNSIGNED  NOT NULL,
          data                DATE          NOT NULL,
          horario_inicio      TIME          NOT NULL,
          horario_fim         TIME          NOT NULL,
          status              ENUM('prevista','realizada','justificativa_pendente','justificada','cancelada') NOT NULL DEFAULT 'prevista',
          chamada_id          INT UNSIGNED  DEFAULT NULL,
          cancelado_por       INT UNSIGNED  DEFAULT NULL,
          cancelado_em        DATETIME      DEFAULT NULL,
          motivo_cancelamento VARCHAR(255)  DEFAULT NULL,
          criado_em           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          atualizado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_cronograma_data (cronograma_id, data),
          KEY idx_professor_data (professor_id, data),
          KEY idx_nucleo_data (nucleo_id, data),
          KEY idx_status (status),
          CONSTRAINT fk_ap_cronograma FOREIGN KEY (cronograma_id) REFERENCES grade_horarios (id) ON DELETE SET NULL,
          CONSTRAINT fk_ap_professor  FOREIGN KEY (professor_id)  REFERENCES usuarios (id),
          CONSTRAINT fk_ap_nucleo     FOREIGN KEY (nucleo_id)     REFERENCES nucleos (id),
          CONSTRAINT fk_ap_chamada    FOREIGN KEY (chamada_id)    REFERENCES chamadas (id) ON DELETE SET NULL,
          CONSTRAINT fk_ap_cancelador FOREIGN KEY (cancelado_por) REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('aulas_previstas — ok.');

    $db->exec("
        CREATE TABLE IF NOT EXISTS justificativas_ausencia (
          id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          aula_prevista_id INT UNSIGNED  NOT NULL,
          professor_id     INT UNSIGNED  NOT NULL,
          motivo           TEXT          NOT NULL,
          enviado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_aula_prevista (aula_prevista_id),
          KEY idx_professor_id (professor_id),
          CONSTRAINT fk_ja_aula      FOREIGN KEY (aula_prevista_id) REFERENCES aulas_previstas (id),
          CONSTRAINT fk_ja_professor FOREIGN KEY (professor_id)     REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('justificativas_ausencia — ok.');

    $log('Concluído — nenhuma alteração de schema pendente.');
} catch (Throwable $e) {
    $log('ERRO: ' . $e->getMessage());
    exit(1);
}
