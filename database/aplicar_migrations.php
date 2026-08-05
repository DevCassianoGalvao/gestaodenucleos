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

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function enumHasValue(PDO $db, string $table, string $column, string $value): bool
{
    $stmt = $db->prepare(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    $type = (string) $stmt->fetchColumn();
    return str_contains($type, "'$value'");
}

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    $log('ERRO ao conectar no banco: ' . $e->getMessage());
    exit(1);
}

try {
    // ─── Migration 007 (retroativo): garante latitude/longitude em núcleos ───
    // para ambientes que nunca rodaram a antiga migration_007_checkins.sql manual.
    if (!columnExists($db, 'nucleos', 'latitude')) {
        $db->exec("ALTER TABLE nucleos ADD COLUMN latitude DECIMAL(10,8) NULL AFTER estado");
        $log('nucleos.latitude — coluna criada.');
    }
    if (!columnExists($db, 'nucleos', 'longitude')) {
        $db->exec("ALTER TABLE nucleos ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude");
        $log('nucleos.longitude — coluna criada.');
    }

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

    // ─── Migration 009: multi-instituto, permissões granulares, escopos ──────
    // ─── evidências, atividades diárias, checklist configurável, depoimentos ──

    // 1) Institutos — novo nível acima de Projeto.
    $db->exec("
        CREATE TABLE IF NOT EXISTS institutos (
          id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          nome             VARCHAR(150)  NOT NULL,
          nome_fantasia    VARCHAR(150)  DEFAULT NULL,
          descricao        TEXT          DEFAULT NULL,
          logotipo         VARCHAR(255)  DEFAULT NULL COMMENT 'WebP, caminho relativo a /uploads',
          identificacao    VARCHAR(60)   DEFAULT NULL COMMENT 'CNPJ ou código interno',
          responsavel_nome VARCHAR(150)  DEFAULT NULL,
          contato_email    VARCHAR(180)  DEFAULT NULL,
          contato_telefone VARCHAR(20)   DEFAULT NULL,
          observacoes      TEXT          DEFAULT NULL,
          status           ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
          criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('institutos — ok.');

    // Backfill: projetos existentes precisam de um instituto. Cria um instituto
    // "guarda-chuva" e associa todo projeto órfão a ele — o Super Admin deve
    // revisar depois e mover cada projeto para o instituto correto de verdade
    // (documentado em docs/PENDENCIAS_REGRAS_NEGOCIO.md).
    $stmt = $db->query("SELECT id FROM institutos WHERE nome = '[Migração] Revisar institutos' LIMIT 1");
    $institutoPadraoId = $stmt->fetchColumn();
    if (!$institutoPadraoId) {
        $db->exec("
            INSERT INTO institutos (nome, descricao, status)
            VALUES (
              '[Migração] Revisar institutos',
              'Criado automaticamente para receber projetos cadastrados antes do módulo de Institutos existir. Revise e mova cada projeto para o instituto correto.',
              'ativo'
            )
        ");
        $institutoPadraoId = (int) $db->lastInsertId();
        $log("institutos — instituto padrão de migração criado (id=$institutoPadraoId).");
    }

    if (!columnExists($db, 'projetos', 'instituto_id')) {
        $db->exec("ALTER TABLE projetos ADD COLUMN instituto_id INT UNSIGNED NULL AFTER id");
        $log('projetos.instituto_id — coluna criada.');
    }

    $afetados = $db->exec("UPDATE projetos SET instituto_id = $institutoPadraoId WHERE instituto_id IS NULL");
    if ($afetados > 0) {
        $log("projetos — instituto_id preenchido em $afetados projeto(s) existente(s) com o instituto de migração (revisar manualmente).");
    }

    // Só torna a coluna obrigatória depois de garantir que não há mais NULLs.
    $stmt = $db->query("SELECT COUNT(*) FROM projetos WHERE instituto_id IS NULL");
    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE projetos MODIFY COLUMN instituto_id INT UNSIGNED NOT NULL");
    }

    if (!indexExists($db, 'projetos', 'idx_instituto_id')) {
        $db->exec("ALTER TABLE projetos ADD KEY idx_instituto_id (instituto_id)");
    }
    if (!constraintExists($db, 'fk_projetos_instituto')) {
        $db->exec("ALTER TABLE projetos ADD CONSTRAINT fk_projetos_instituto FOREIGN KEY (instituto_id) REFERENCES institutos (id)");
        $log('projetos — FK fk_projetos_instituto criada.');
    }

    // 2) Termo de Fomento — associado ao Projeto, estrutura extensível.
    $db->exec("
        CREATE TABLE IF NOT EXISTS termos_fomento (
          id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          projeto_id   INT UNSIGNED  NOT NULL,
          numero       VARCHAR(100)  DEFAULT NULL,
          descricao    TEXT          DEFAULT NULL,
          data_inicio  DATE          DEFAULT NULL,
          data_fim     DATE          DEFAULT NULL,
          status       ENUM('ativo','encerrado','suspenso') NOT NULL DEFAULT 'ativo',
          observacoes  TEXT          DEFAULT NULL,
          criado_por   INT UNSIGNED  DEFAULT NULL,
          criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_projeto_id (projeto_id),
          CONSTRAINT fk_tf_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id),
          CONSTRAINT fk_tf_criador FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('termos_fomento — ok.');

    $db->exec("
        CREATE TABLE IF NOT EXISTS termo_fomento_anexos (
          id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          termo_fomento_id INT UNSIGNED  NOT NULL,
          nome_arquivo     VARCHAR(255)  NOT NULL,
          arquivo_path     VARCHAR(255)  NOT NULL,
          tamanho_bytes    INT UNSIGNED  DEFAULT NULL,
          enviado_por      INT UNSIGNED  NOT NULL,
          criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_termo_fomento_id (termo_fomento_id),
          CONSTRAINT fk_tfa_termo    FOREIGN KEY (termo_fomento_id) REFERENCES termos_fomento (id) ON DELETE CASCADE,
          CONSTRAINT fk_tfa_usuario  FOREIGN KEY (enviado_por)      REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('termo_fomento_anexos — ok.');

    // 3) Permissões granulares + escopos de acesso.
    $db->exec("
        CREATE TABLE IF NOT EXISTS permissoes (
          id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
          chave     VARCHAR(80)  NOT NULL COMMENT 'ex: nucleos.editar',
          modulo    VARCHAR(60)  NOT NULL COMMENT 'ex: Núcleos',
          label     VARCHAR(150) NOT NULL,
          ordem     INT UNSIGNED NOT NULL DEFAULT 0,
          criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_chave (chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('permissoes — ok.');

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuario_permissoes (
          id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
          usuario_id    INT UNSIGNED NOT NULL,
          permissao_id  INT UNSIGNED NOT NULL,
          concedido_por INT UNSIGNED DEFAULT NULL,
          criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_usuario_permissao (usuario_id, permissao_id),
          KEY idx_permissao_id (permissao_id),
          CONSTRAINT fk_up_usuario    FOREIGN KEY (usuario_id)    REFERENCES usuarios (id) ON DELETE CASCADE,
          CONSTRAINT fk_up_permissao  FOREIGN KEY (permissao_id)  REFERENCES permissoes (id) ON DELETE CASCADE,
          CONSTRAINT fk_up_concedente FOREIGN KEY (concedido_por) REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('usuario_permissoes — ok.');

    $db->exec("
        CREATE TABLE IF NOT EXISTS escopos_usuario (
          id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
          usuario_id    INT UNSIGNED NOT NULL,
          tipo          ENUM('instituto','projeto','nucleo') NOT NULL,
          referencia_id INT UNSIGNED NOT NULL,
          concedido_por INT UNSIGNED DEFAULT NULL,
          criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_usuario_escopo (usuario_id, tipo, referencia_id),
          KEY idx_usuario_id (usuario_id),
          CONSTRAINT fk_eu_usuario    FOREIGN KEY (usuario_id)    REFERENCES usuarios (id) ON DELETE CASCADE,
          CONSTRAINT fk_eu_concedente FOREIGN KEY (concedido_por) REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('escopos_usuario — ok.');

    // Catálogo de permissões — organizado pelos módulos reais do sistema.
    // Idempotente: insere só as chaves que ainda não existem.
    $catalogo = [
        // módulo, chave, label
        ['Dashboard',           'dashboard.visualizar',        'Visualizar dashboard'],
        ['Institutos',          'institutos.visualizar',       'Visualizar institutos'],
        ['Institutos',          'institutos.editar',           'Criar/editar institutos'],
        ['Institutos',          'institutos.excluir',          'Inativar institutos'],
        ['Projetos',            'projetos.visualizar',         'Visualizar projetos'],
        ['Projetos',            'projetos.editar',             'Criar/editar projetos'],
        ['Projetos',            'projetos.excluir',            'Inativar projetos'],
        ['Termos de Fomento',   'termos_fomento.visualizar',   'Visualizar termos de fomento'],
        ['Termos de Fomento',   'termos_fomento.editar',       'Criar/editar termos de fomento'],
        ['Núcleos',             'nucleos.visualizar',          'Visualizar núcleos'],
        ['Núcleos',             'nucleos.editar',               'Criar/editar núcleos'],
        ['Núcleos',             'nucleos.excluir',              'Inativar núcleos'],
        ['Equipe',              'equipe.visualizar',            'Visualizar equipe/usuários'],
        ['Equipe',              'equipe.criar',                 'Cadastrar novos usuários'],
        ['Equipe',              'equipe.editar',                'Editar usuários'],
        ['Equipe',              'equipe.excluir',               'Inativar usuários'],
        ['Equipe',              'equipe.permissoes',            'Gerenciar permissões e escopos de outros usuários'],
        ['Cronograma',          'cronograma.visualizar',        'Visualizar cronograma'],
        ['Cronograma',          'cronograma.administrar',       'Criar/editar/desativar horários do cronograma'],
        ['Aulas',               'aulas.visualizar',             'Acompanhar aulas previstas/realizadas'],
        ['Aulas',               'aulas.cancelar',               'Cancelar aula administrativamente'],
        ['Alunos',              'alunos.visualizar',            'Visualizar alunos'],
        ['Alunos',              'alunos.editar',                'Cadastrar/editar alunos'],
        ['Alunos',              'alunos.excluir',                'Inativar alunos'],
        ['Chamadas',            'chamadas.visualizar',          'Visualizar chamadas'],
        ['Chamadas',            'chamadas.registrar',           'Registrar chamada'],
        ['Chamadas',            'chamadas.corrigir',            'Corrigir chamada já registrada'],
        ['Atividades',          'atividades.visualizar',        'Visualizar atividades diárias'],
        ['Atividades',          'atividades.registrar',         'Registrar atividade diária'],
        ['Evidências',          'evidencias.visualizar',        'Visualizar fotos e evidências'],
        ['Evidências',          'evidencias.enviar',            'Enviar fotos e evidências'],
        ['Depoimentos',         'depoimentos.visualizar',       'Visualizar depoimentos'],
        ['Depoimentos',         'depoimentos.editar',           'Cadastrar/editar depoimentos'],
        ['Monitoramento',       'monitoramento.visualizar',     'Visualizar monitoramento de professores'],
        ['Check-ins',           'checkins.visualizar',          'Visualizar check-ins de geolocalização'],
        ['Relatórios',          'relatorios.visualizar',        'Visualizar relatórios'],
        ['Relatórios',          'relatorios.exportar',          'Exportar relatórios (PDF/CSV)'],
        ['Exportação',          'exportacao.executar',          'Exportar planilhas de dados'],
        ['Prestação de Contas', 'prestacao_contas.visualizar',  'Visualizar prestação de contas'],
        ['Prestação de Contas', 'prestacao_contas.editar',      'Editar prestação de contas'],
    ];
    $checkPerm = $db->prepare('SELECT id FROM permissoes WHERE chave = ? LIMIT 1');
    $insPerm   = $db->prepare('INSERT INTO permissoes (chave, modulo, label, ordem) VALUES (?, ?, ?, ?)');
    $novasPerm = 0;
    foreach ($catalogo as $i => [$modulo, $chave, $label]) {
        $checkPerm->execute([$chave]);
        if (!$checkPerm->fetchColumn()) {
            $insPerm->execute([$chave, $modulo, $label, $i]);
            $novasPerm++;
        }
    }
    if ($novasPerm > 0) {
        $log("permissoes — $novasPerm nova(s) chave(s) inserida(s) no catálogo.");
    }

    // 4) Usuários — cargo organizacional + novo perfil "gestor" (staff não-super-admin
    // com acesso administrativo restrito por permissão + escopo: presidente,
    // coordenador geral, coordenador de projeto/núcleo, monitor, colaborador).
    if (!columnExists($db, 'usuarios', 'cargo')) {
        $db->exec("
            ALTER TABLE usuarios ADD COLUMN cargo
            ENUM('presidente','coordenador_geral','coordenador_projeto','coordenador_nucleo','professor','monitor','colaborador')
            NULL AFTER perfil
        ");
        $log('usuarios.cargo — coluna criada.');
    }
    if (!enumHasValue($db, 'usuarios', 'perfil', 'gestor')) {
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN perfil ENUM('super_admin','gestor','professor','aluno') NOT NULL DEFAULT 'aluno'");
        $log("usuarios.perfil — valor 'gestor' adicionado ao enum.");
    }

    // 5) Atividades diárias (registro do que aconteceu na aula/atividade).
    $db->exec("
        CREATE TABLE IF NOT EXISTS atividades (
          id                        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          nucleo_id                 INT UNSIGNED  NOT NULL,
          professor_id              INT UNSIGNED  NOT NULL,
          aula_prevista_id          INT UNSIGNED  DEFAULT NULL,
          chamada_id                INT UNSIGNED  DEFAULT NULL,
          data                      DATE          NOT NULL,
          horario                   TIME          DEFAULT NULL,
          descricao                 TEXT          NOT NULL,
          observacoes               TEXT          DEFAULT NULL,
          status                    ENUM('rascunho','concluida') NOT NULL DEFAULT 'concluida',
          registrado_retroativamente TINYINT(1)   NOT NULL DEFAULT 0,
          criado_em                 DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          atualizado_em             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_nucleo_data (nucleo_id, data),
          KEY idx_professor_data (professor_id, data),
          CONSTRAINT fk_ativ_nucleo    FOREIGN KEY (nucleo_id)        REFERENCES nucleos (id),
          CONSTRAINT fk_ativ_professor FOREIGN KEY (professor_id)     REFERENCES usuarios (id),
          CONSTRAINT fk_ativ_aula      FOREIGN KEY (aula_prevista_id) REFERENCES aulas_previstas (id) ON DELETE SET NULL,
          CONSTRAINT fk_ativ_chamada   FOREIGN KEY (chamada_id)       REFERENCES chamadas (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('atividades — ok.');

    // 6) Evidências — fotos/documentos vinculadas a uma atividade (contexto completo
    // vem por join: atividade → núcleo → projeto → instituto).
    $db->exec("
        CREATE TABLE IF NOT EXISTS atividade_evidencias (
          id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          atividade_id  INT UNSIGNED  NOT NULL,
          tipo          ENUM('foto','documento','video','outro') NOT NULL DEFAULT 'foto',
          arquivo_path  VARCHAR(255)  NOT NULL,
          latitude      DECIMAL(10,8) DEFAULT NULL,
          longitude     DECIMAL(11,8) DEFAULT NULL,
          capturado_em  DATETIME      DEFAULT NULL COMMENT 'data/hora em que a foto/evidência foi feita, se souber',
          enviado_por   INT UNSIGNED  NOT NULL,
          criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'data real do upload no sistema',
          PRIMARY KEY (id),
          KEY idx_atividade_id (atividade_id),
          CONSTRAINT fk_ae_atividade FOREIGN KEY (atividade_id) REFERENCES atividades (id) ON DELETE CASCADE,
          CONSTRAINT fk_ae_usuario   FOREIGN KEY (enviado_por)  REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('atividade_evidencias — ok.');

    // 7) Checklist configurável por Projeto (infraestrutura — exigências reais
    // ainda serão definidas pelos responsáveis, ver docs/PENDENCIAS_REGRAS_NEGOCIO.md).
    $db->exec("
        CREATE TABLE IF NOT EXISTS projeto_requisitos (
          id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          projeto_id        INT UNSIGNED  NOT NULL,
          nome              VARCHAR(150)  NOT NULL,
          tipo              ENUM('foto','texto','lista_presenca','documento','video','confirmacao','outro') NOT NULL DEFAULT 'foto',
          obrigatorio       TINYINT(1)    NOT NULL DEFAULT 1,
          quantidade_minima INT UNSIGNED  NOT NULL DEFAULT 1,
          instrucao         TEXT          DEFAULT NULL,
          ordem             INT UNSIGNED  NOT NULL DEFAULT 0,
          status            ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
          criado_em         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_projeto_id (projeto_id),
          CONSTRAINT fk_pr_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('projeto_requisitos — ok.');

    // 8) Depoimentos (mães/alunos/responsáveis) — vinculados à hierarquia.
    $db->exec("
        CREATE TABLE IF NOT EXISTS depoimentos (
          id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          nucleo_id    INT UNSIGNED  NOT NULL,
          aluno_id     INT UNSIGNED  DEFAULT NULL,
          autor_nome   VARCHAR(150)  DEFAULT NULL COMMENT 'quando não é um aluno cadastrado, ex: mãe/responsável',
          conteudo     TEXT          NOT NULL,
          tipo         ENUM('texto','audio','video','foto') NOT NULL DEFAULT 'texto',
          arquivo_path VARCHAR(255)  DEFAULT NULL,
          criado_por   INT UNSIGNED  NOT NULL,
          criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_nucleo_id (nucleo_id),
          CONSTRAINT fk_dep_nucleo  FOREIGN KEY (nucleo_id)  REFERENCES nucleos (id),
          CONSTRAINT fk_dep_aluno   FOREIGN KEY (aluno_id)   REFERENCES alunos (id),
          CONSTRAINT fk_dep_criador FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('depoimentos — ok.');

    // 9) Justificativa de ausência — categoriza o motivo (motivo em texto livre
    // continua obrigatório); "sem_internet" libera lançamento retroativo.
    if (!columnExists($db, 'justificativas_ausencia', 'tipo')) {
        $db->exec("
            ALTER TABLE justificativas_ausencia ADD COLUMN tipo
            ENUM('sem_internet','chuva','problema_local','imprevisto','outro')
            NOT NULL DEFAULT 'outro' AFTER professor_id
        ");
        $log('justificativas_ausencia.tipo — coluna criada.');
    }

    // 10) Chamada — rastrear lançamento retroativo (ex: sem internet no dia da aula)
    // ligado à justificativa que liberou o lançamento posterior.
    if (!columnExists($db, 'chamadas', 'registrado_retroativamente')) {
        $db->exec("ALTER TABLE chamadas ADD COLUMN registrado_retroativamente TINYINT(1) NOT NULL DEFAULT 0 AFTER data_aula");
        $log('chamadas.registrado_retroativamente — coluna criada.');
    }
    if (!columnExists($db, 'chamadas', 'justificativa_ausencia_id')) {
        $db->exec("ALTER TABLE chamadas ADD COLUMN justificativa_ausencia_id INT UNSIGNED NULL AFTER registrado_retroativamente");
        $log('chamadas.justificativa_ausencia_id — coluna criada.');
    }
    if (!constraintExists($db, 'fk_chamadas_justificativa')) {
        $db->exec("ALTER TABLE chamadas ADD CONSTRAINT fk_chamadas_justificativa FOREIGN KEY (justificativa_ausencia_id) REFERENCES justificativas_ausencia (id) ON DELETE SET NULL");
    }

    // 11) Histórico de correção de presença (auditoria de quem mudou o quê).
    $db->exec("
        CREATE TABLE IF NOT EXISTS chamada_presenca_historico (
          id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
          chamada_presenca_id INT UNSIGNED  NOT NULL,
          presente_anterior   TINYINT(1)    NOT NULL,
          presente_novo       TINYINT(1)    NOT NULL,
          alterado_por        INT UNSIGNED  NOT NULL,
          criado_em           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_chamada_presenca_id (chamada_presenca_id),
          CONSTRAINT fk_cph_presenca FOREIGN KEY (chamada_presenca_id) REFERENCES chamada_presencas (id) ON DELETE CASCADE,
          CONSTRAINT fk_cph_usuario  FOREIGN KEY (alterado_por)        REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log('chamada_presenca_historico — ok.');

    // 12) Check-in — garante a tabela (caso o ambiente nunca tenha aplicado a
    // antiga migration_007_checkins.sql manual) e guarda precisão do GPS.
    $db->exec("
        CREATE TABLE IF NOT EXISTS checkins (
          id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
          professor_id INT UNSIGNED    NOT NULL,
          nucleo_id    INT UNSIGNED    NOT NULL,
          latitude     DECIMAL(10,8)   NOT NULL,
          longitude    DECIMAL(11,8)   NOT NULL,
          endereco     VARCHAR(500)    DEFAULT NULL,
          distancia_m  INT UNSIGNED    DEFAULT NULL,
          status       ENUM('dentro_raio','fora_raio','sem_coordenadas') NOT NULL,
          criado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_professor_id (professor_id),
          KEY idx_nucleo_id (nucleo_id),
          KEY idx_criado_em (criado_em),
          CONSTRAINT fk_checkins_professor FOREIGN KEY (professor_id) REFERENCES usuarios (id),
          CONSTRAINT fk_checkins_nucleo    FOREIGN KEY (nucleo_id)    REFERENCES nucleos (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if (!columnExists($db, 'checkins', 'precisao_m')) {
        $db->exec("ALTER TABLE checkins ADD COLUMN precisao_m INT UNSIGNED NULL AFTER longitude");
        $log('checkins.precisao_m — coluna criada.');
    }

    // 13) Audit log — antes/depois para alterações sensíveis (permissão, escopo, correção de chamada).
    if (!columnExists($db, 'audit_log', 'dados_anteriores')) {
        $db->exec("ALTER TABLE audit_log ADD COLUMN dados_anteriores JSON NULL AFTER registro_id");
        $log('audit_log.dados_anteriores — coluna criada.');
    }
    if (!columnExists($db, 'audit_log', 'dados_novos')) {
        $db->exec("ALTER TABLE audit_log ADD COLUMN dados_novos JSON NULL AFTER dados_anteriores");
        $log('audit_log.dados_novos — coluna criada.');
    }

    $log('Concluído — nenhuma alteração de schema pendente.');
} catch (Throwable $e) {
    $log('ERRO: ' . $e->getMessage());
    exit(1);
}
