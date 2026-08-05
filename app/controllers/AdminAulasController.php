<?php

class AdminAulasController
{
    private const PER_PAGE = 25;
    private const STATUS_VALIDOS = ['prevista', 'realizada', 'justificativa_pendente', 'justificada', 'cancelada'];

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('aulas.visualizar');
        $db = Database::getInstance();

        require_once ROOT_PATH . '/app/helpers/Cronograma.php';
        Cronograma::atualizarPendencias($db);

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'ap.nucleo_id');

        $professorId = (int) ($_GET['professor_id'] ?? 0);
        $nucleoId    = (int) ($_GET['nucleo_id']     ?? 0);
        $status      = Security::sanitize($_GET['status'] ?? '');
        $dataInicio  = Security::sanitize($_GET['data_inicio'] ?? '');
        $dataFim     = Security::sanitize($_GET['data_fim']    ?? '');
        $page        = max(1, (int) ($_GET['page'] ?? 1));
        $off         = ($page - 1) * self::PER_PAGE;

        $conditions = [$escopoWhere];
        $params     = $escopoParams;

        if ($professorId) { $conditions[] = 'ap.professor_id = ?'; $params[] = $professorId; }
        if ($nucleoId)    { $conditions[] = 'ap.nucleo_id = ?';    $params[] = $nucleoId; }
        if ($status && in_array($status, self::STATUS_VALIDOS, true)) { $conditions[] = 'ap.status = ?'; $params[] = $status; }
        if ($dataInicio) { $conditions[] = 'ap.data >= ?'; $params[] = $dataInicio; }
        if ($dataFim)    { $conditions[] = 'ap.data <= ?'; $params[] = $dataFim; }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM aulas_previstas ap $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT ap.*, u.nome AS professor_nome, n.nome AS nucleo_nome, p.nome AS projeto_nome,
                   ja.motivo AS justificativa_motivo, ja.enviado_em AS justificativa_enviado_em,
                   uc.nome AS cancelado_por_nome
            FROM aulas_previstas ap
            JOIN usuarios u ON u.id = ap.professor_id
            JOIN nucleos  n ON n.id = ap.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            LEFT JOIN justificativas_ausencia ja ON ja.aula_prevista_id = ap.id
            LEFT JOIN usuarios uc ON uc.id = ap.cancelado_por
            $where
            ORDER BY ap.data DESC, ap.horario_inicio DESC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $aulas = $stmt->fetchAll();

        $nucleosDisp = [];
        if ($permitidos) {
            [$w, $p] = Escopo::whereIn($permitidos, 'id');
            $nucleosDisp = $db->prepare("SELECT id, nome FROM nucleos WHERE $w ORDER BY nome");
            $nucleosDisp->execute($p);
            $nucleosDisp = $nucleosDisp->fetchAll();
        }
        $professores = [];
        if ($permitidos) {
            [$w, $p] = Escopo::whereIn($permitidos, 'np.nucleo_id');
            $professores = $db->prepare("SELECT DISTINCT u.id, u.nome FROM usuarios u JOIN nucleo_professores np ON np.usuario_id=u.id WHERE u.perfil='professor' AND $w ORDER BY u.nome");
            $professores->execute($p);
            $professores = $professores->fetchAll();
        }
        $nucleos     = $nucleosDisp;
        $totalPages  = (int) ceil($total / self::PER_PAGE);

        $data = compact('aulas', 'professores', 'nucleos', 'professorId', 'nucleoId', 'status', 'dataInicio', 'dataFim', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/aulas/index.php';
    }

    public function cancelar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('aulas.cancelar');
        Security::verifyCsrf();
        $db = Database::getInstance();
        $id = (int) $id;

        $motivo = Security::sanitize($_POST['motivo_cancelamento'] ?? '');
        if ($motivo === '') {
            $_SESSION['flash_error'] = 'Informe o motivo do cancelamento.';
            header('Location: ' . APP_URL . '/admin/aulas');
            exit;
        }

        $stmt = $db->prepare("SELECT id, status, nucleo_id FROM aulas_previstas WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $aula = $stmt->fetch();

        if (!$aula) {
            $_SESSION['flash_error'] = 'Aula não encontrada.';
            header('Location: ' . APP_URL . '/admin/aulas');
            exit;
        }
        if (!Escopo::podeAcessarNucleo(Auth::id(), (int) $aula['nucleo_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        if ($aula['status'] !== 'prevista') {
            $_SESSION['flash_error'] = 'Só é possível cancelar aulas ainda não resolvidas (status "Prevista").';
            header('Location: ' . APP_URL . '/admin/aulas');
            exit;
        }

        $db->prepare(
            "UPDATE aulas_previstas SET status='cancelada', cancelado_por=?, cancelado_em=NOW(), motivo_cancelamento=? WHERE id=?"
        )->execute([Auth::id(), $motivo, $id]);

        Security::auditLog('cancelamento', 'aulas_previstas', $id);
        $_SESSION['flash_success'] = 'Aula cancelada. O professor não será cobrado por justificativa.';
        header('Location: ' . APP_URL . '/admin/aulas');
        exit;
    }
}
