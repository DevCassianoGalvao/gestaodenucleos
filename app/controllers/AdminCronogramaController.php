<?php

require_once ROOT_PATH . '/app/helpers/Cronograma.php';

class AdminCronogramaController
{
    private const PER_PAGE = 50;
    private const JANELA_DIAS = 30; // horizonte de geração de aulas_previstas ao criar/editar/reativar

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.visualizar');
        $db = Database::getInstance();

        $professorId = (int) ($_GET['professor_id'] ?? 0);
        $nucleoId    = (int) ($_GET['nucleo_id']     ?? 0);
        $diaSemana   = $_GET['dia_semana'] ?? '';
        $status      = Security::sanitize($_GET['status'] ?? '');
        $page        = max(1, (int) ($_GET['page'] ?? 1));
        $off         = ($page - 1) * self::PER_PAGE;

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'gh.nucleo_id');

        $conditions = [$escopoWhere];
        $params     = $escopoParams;

        if ($professorId) { $conditions[] = 'gh.professor_id = ?'; $params[] = $professorId; }
        if ($nucleoId)    { $conditions[] = 'gh.nucleo_id = ?';    $params[] = $nucleoId; }
        if ($diaSemana !== '' && ctype_digit((string) $diaSemana)) { $conditions[] = 'gh.dia_semana = ?'; $params[] = (int) $diaSemana; }
        if ($status && in_array($status, ['ativo', 'inativo'], true)) { $conditions[] = 'gh.status = ?'; $params[] = $status; }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM grade_horarios gh $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT gh.*, u.nome AS professor_nome, n.nome AS nucleo_nome, p.nome AS projeto_nome
            FROM grade_horarios gh
            LEFT JOIN usuarios u ON u.id = gh.professor_id
            JOIN nucleos n  ON n.id = gh.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            $where
            ORDER BY u.nome ASC, gh.dia_semana ASC, gh.horario_inicio ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $horarios = $stmt->fetchAll();

        $professores = $this->professoresDisponiveis($db);
        $nucleos     = $this->nucleosDisponiveis($db);
        $dias        = Cronograma::DIAS;
        $totalPages  = (int) ceil($total / self::PER_PAGE);

        $data = compact('horarios', 'professores', 'nucleos', 'dias', 'professorId', 'nucleoId', 'diaSemana', 'status', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/cronograma/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.administrar');
        $db = Database::getInstance();

        $horario     = null;
        $professores = $this->professoresDisponiveis($db);
        $nucleos     = $this->nucleosDisponiveis($db);
        $dias        = Cronograma::DIAS;
        $errors      = $_SESSION['form_errors'] ?? [];
        $oldData     = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/cronograma/form.php';
    }

    private function validar(array $post, PDO $db): array
    {
        $professorId = (int) ($post['professor_id'] ?? 0);
        $nucleoId    = (int) ($post['nucleo_id']     ?? 0);
        $diaSemana   = (int) ($post['dia_semana']    ?? -1);
        $inicio      = Security::sanitize($post['horario_inicio'] ?? '');
        $fim         = Security::sanitize($post['horario_fim']    ?? '');
        $errors      = [];

        if (!$professorId) {
            $errors['professor_id'] = 'Selecione um professor.';
        } else {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE id=? AND perfil='professor' AND status='ativo' LIMIT 1");
            $stmt->execute([$professorId]);
            if (!$stmt->fetch()) $errors['professor_id'] = 'Professor inválido.';
        }

        if (!$nucleoId) {
            $errors['nucleo_id'] = 'Selecione a turma/projeto (núcleo).';
        } elseif (!Escopo::podeAcessarNucleo(Auth::id(), $nucleoId)) {
            $errors['nucleo_id'] = 'Você não tem acesso a esse núcleo.';
        } else {
            $stmt = $db->prepare("SELECT id FROM nucleos WHERE id=? AND status='ativo' LIMIT 1");
            $stmt->execute([$nucleoId]);
            if (!$stmt->fetch()) $errors['nucleo_id'] = 'Núcleo inválido.';
        }

        if ($diaSemana < 0 || $diaSemana > 6) $errors['dia_semana'] = 'Selecione o dia da semana.';
        if (!preg_match('/^\d{2}:\d{2}$/', $inicio)) $errors['horario_inicio'] = 'Horário inicial inválido.';
        if (!preg_match('/^\d{2}:\d{2}$/', $fim))    $errors['horario_fim']    = 'Horário final inválido.';
        if (!isset($errors['horario_inicio']) && !isset($errors['horario_fim']) && $fim <= $inicio) {
            $errors['horario_fim'] = 'Horário final deve ser depois do inicial.';
        }

        return [$errors, $professorId, $nucleoId, $diaSemana, $inicio, $fim];
    }

    public function store(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.administrar');
        Security::verifyCsrf();
        $db = Database::getInstance();

        [$errors, $professorId, $nucleoId, $diaSemana, $inicio, $fim] = $this->validar($_POST, $db);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/cronograma/novo');
            exit;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO grade_horarios (nucleo_id, professor_id, dia_semana, horario_inicio, horario_fim, status)
                 VALUES (?, ?, ?, ?, ?, 'ativo')"
            );
            $stmt->execute([$nucleoId, $professorId, $diaSemana, $inicio . ':00', $fim . ':00']);
            $id = (int) $db->lastInsertId();

            require_once ROOT_PATH . '/app/helpers/Cronograma.php';
            Cronograma::gerarOcorrenciasIntervalo(
                $db, date('Y-m-d'), date('Y-m-d', strtotime('+' . self::JANELA_DIAS . ' days')), $id
            );

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminCronograma] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao salvar horário.';
            header('Location: ' . APP_URL . '/admin/cronograma/novo');
            exit;
        }

        Security::auditLog('cadastro', 'grade_horarios', $id);
        $_SESSION['flash_success'] = 'Horário cadastrado no cronograma.';
        header('Location: ' . APP_URL . '/admin/cronograma');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.administrar');
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM grade_horarios WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        $horario = $stmt->fetch();

        if (!$horario) {
            $_SESSION['flash_error'] = 'Horário não encontrado.';
            header('Location: ' . APP_URL . '/admin/cronograma');
            exit;
        }
        if (!Escopo::podeAcessarNucleo(Auth::id(), (int) $horario['nucleo_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $horario['horario_inicio'] = substr($horario['horario_inicio'], 0, 5);
        $horario['horario_fim']    = substr($horario['horario_fim'], 0, 5);

        $professores = $this->professoresDisponiveis($db);
        $nucleos     = $this->nucleosDisponiveis($db);
        $dias        = Cronograma::DIAS;
        $errors      = $_SESSION['form_errors'] ?? [];
        $oldData     = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/cronograma/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.administrar');
        Security::verifyCsrf();
        $db = Database::getInstance();
        $id = (int) $id;

        $stmt = $db->prepare("SELECT * FROM grade_horarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $horario = $stmt->fetch();
        if (!$horario) {
            $_SESSION['flash_error'] = 'Horário não encontrado.';
            header('Location: ' . APP_URL . '/admin/cronograma');
            exit;
        }
        if (!Escopo::podeAcessarNucleo(Auth::id(), (int) $horario['nucleo_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        [$errors, $professorId, $nucleoId, $diaSemana, $inicio, $fim] = $this->validar($_POST, $db);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/cronograma/' . $id . '/editar');
            exit;
        }

        require_once ROOT_PATH . '/app/helpers/Cronograma.php';

        $db->beginTransaction();
        try {
            $db->prepare(
                "UPDATE grade_horarios SET nucleo_id=?, professor_id=?, dia_semana=?, horario_inicio=?, horario_fim=? WHERE id=?"
            )->execute([$nucleoId, $professorId, $diaSemana, $inicio . ':00', $fim . ':00', $id]);

            // Ocorrências futuras ainda não resolvidas refletem a definição nova;
            // ocorrências já resolvidas (realizada/justificada/pendente/cancelada)
            // preservam o snapshot antigo — histórico nunca é alterado retroativamente.
            Cronograma::removerFuturasPrevistas($db, $id);
            if ($horario['status'] === 'ativo') {
                Cronograma::gerarOcorrenciasIntervalo(
                    $db, date('Y-m-d'), date('Y-m-d', strtotime('+' . self::JANELA_DIAS . ' days')), $id
                );
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminCronograma] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao atualizar horário.';
            header('Location: ' . APP_URL . '/admin/cronograma/' . $id . '/editar');
            exit;
        }

        Security::auditLog('edicao', 'grade_horarios', $id);
        $_SESSION['flash_success'] = 'Horário atualizado.';
        header('Location: ' . APP_URL . '/admin/cronograma');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.administrar');
        Security::verifyCsrf();
        $db = Database::getInstance();
        $id = (int) $id;

        if (!$this->horarioNoEscopo($db, $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        require_once ROOT_PATH . '/app/helpers/Cronograma.php';

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE grade_horarios SET status='inativo' WHERE id=?")->execute([$id]);
            Cronograma::cancelarFuturasPrevistas($db, $id, Auth::id(), 'Horário desativado pelo administrador.');
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminCronograma] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao desativar horário.';
            header('Location: ' . APP_URL . '/admin/cronograma');
            exit;
        }

        Security::auditLog('exclusao', 'grade_horarios', $id);
        $_SESSION['flash_success'] = 'Horário desativado. Aulas futuras já previstas foram canceladas.';
        header('Location: ' . APP_URL . '/admin/cronograma');
        exit;
    }

    public function reativar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.administrar');
        Security::verifyCsrf();
        $db = Database::getInstance();
        $id = (int) $id;

        if (!$this->horarioNoEscopo($db, $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        require_once ROOT_PATH . '/app/helpers/Cronograma.php';

        $db->prepare("UPDATE grade_horarios SET status='ativo' WHERE id=?")->execute([$id]);
        Cronograma::gerarOcorrenciasIntervalo(
            $db, date('Y-m-d'), date('Y-m-d', strtotime('+' . self::JANELA_DIAS . ' days')), $id
        );

        Security::auditLog('edicao', 'grade_horarios', $id);
        $_SESSION['flash_success'] = 'Horário reativado.';
        header('Location: ' . APP_URL . '/admin/cronograma');
        exit;
    }

    public function excluir(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('cronograma.administrar');
        Security::verifyCsrf();
        $db = Database::getInstance();
        $id = (int) $id;

        if (!$this->horarioNoEscopo($db, $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        require_once ROOT_PATH . '/app/helpers/Cronograma.php';

        $db->beginTransaction();
        try {
            Cronograma::cancelarFuturasPrevistas($db, $id, Auth::id(), 'Horário excluído pelo administrador.');
            // Ocorrências já resolvidas mantêm o histórico via snapshot; o vínculo
            // com o cronograma some (ON DELETE SET NULL), mas os dados permanecem.
            $db->prepare("DELETE FROM grade_horarios WHERE id=?")->execute([$id]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminCronograma] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao excluir horário.';
            header('Location: ' . APP_URL . '/admin/cronograma');
            exit;
        }

        Security::auditLog('exclusao', 'grade_horarios', $id);
        $_SESSION['flash_success'] = 'Horário excluído.';
        header('Location: ' . APP_URL . '/admin/cronograma');
        exit;
    }

    private function horarioNoEscopo(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("SELECT nucleo_id FROM grade_horarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $nucleoId = $stmt->fetchColumn();
        return $nucleoId && Escopo::podeAcessarNucleo(Auth::id(), (int) $nucleoId);
    }

    private function nucleosDisponiveis(PDO $db): array
    {
        $ids = Escopo::nucleosPermitidos(Auth::id());
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'n.id');
        $stmt = $db->prepare("SELECT n.id, n.nome, p.nome AS projeto FROM nucleos n JOIN projetos p ON p.id=n.projeto_id WHERE n.status='ativo' AND $where ORDER BY p.nome, n.nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function professoresDisponiveis(PDO $db): array
    {
        // Professores vinculados a algum núcleo dentro do escopo do usuário logado.
        $ids = Escopo::nucleosPermitidos(Auth::id());
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'np.nucleo_id');
        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.nome FROM usuarios u
            JOIN nucleo_professores np ON np.usuario_id = u.id
            WHERE u.perfil='professor' AND u.status='ativo' AND $where
            ORDER BY u.nome
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
