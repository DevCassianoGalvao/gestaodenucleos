<?php

class ProfessorFrequenciaController
{
    private function nucleoId(): int
    {
        static $id = null;
        if ($id !== null) return $id;
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT nucleo_id FROM nucleo_professores WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([Auth::id()]);
        $id = (int) $stmt->fetchColumn();
        return $id;
    }

    private function assertNucleo(): int
    {
        $id = $this->nucleoId();
        if (!$id) {
            $_SESSION['flash_error'] = 'Você não está vinculado a nenhum núcleo.';
            header('Location: ' . APP_URL . '/professor/dashboard');
            exit;
        }
        return $id;
    }

    // ── Histórico de chamadas ────────────────────────────────────────────────

    public function index(): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * 20;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM chamadas WHERE nucleo_id = ? AND professor_id = ?");
        $countStmt->execute([$nucleoId, Auth::id()]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT c.*,
                   COUNT(cp.id)           AS total_alunos,
                   SUM(cp.presente)       AS total_presentes,
                   ROUND(AVG(cp.presente)*100) AS pct_presenca
            FROM chamadas c
            LEFT JOIN chamada_presencas cp ON cp.chamada_id = c.id
            WHERE c.nucleo_id = ? AND c.professor_id = ?
            GROUP BY c.id
            ORDER BY c.data_aula DESC
            LIMIT 20 OFFSET $off
        ");
        $stmt->execute([$nucleoId, Auth::id()]);
        $chamadas = $stmt->fetchAll();

        $totalPages = (int) ceil($total / 20);

        $data = compact('chamadas', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/professor/frequencia/index.php';
    }

    // ── Nova chamada ──────────────────────────────────────────────────────────

    public function formNova(): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        // Alunos ativos do núcleo
        $stmt = $db->prepare(
            "SELECT id, nome, foto FROM alunos
             WHERE nucleo_id = ? AND status = 'ativo'
             ORDER BY nome ASC"
        );
        $stmt->execute([$nucleoId]);
        $alunos = $stmt->fetchAll();

        if (empty($alunos)) {
            $_SESSION['flash_error'] = 'Nenhum aluno ativo no núcleo para registrar chamada.';
            header('Location: ' . APP_URL . '/professor/frequencia');
            exit;
        }

        // Sugerir próxima data: hoje por padrão
        $dataHoje = date('Y-m-d');

        // Verificar se já há chamada hoje
        $jaExiste = $db->prepare(
            "SELECT id FROM chamadas WHERE nucleo_id = ? AND data_aula = ? LIMIT 1"
        );
        $jaExiste->execute([$nucleoId, $dataHoje]);
        $chamadaExistente = $jaExiste->fetch();

        $data = compact('alunos', 'dataHoje', 'chamadaExistente');
        require_once ROOT_PATH . '/app/views/professor/frequencia/nova.php';
    }

    public function store(): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();

        $dataAula = Security::sanitize($_POST['data_aula'] ?? '');
        $presentes = $_POST['presentes'] ?? []; // array of aluno IDs marked present

        if (!$dataAula || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAula)) {
            $_SESSION['flash_error'] = 'Data inválida.';
            header('Location: ' . APP_URL . '/professor/frequencia/nova');
            exit;
        }

        // Validate date is not in the future
        if ($dataAula > date('Y-m-d')) {
            $_SESSION['flash_error'] = 'Não é possível registrar chamada para uma data futura.';
            header('Location: ' . APP_URL . '/professor/frequencia/nova');
            exit;
        }

        $db = Database::getInstance();

        // Check if chamada already exists for this date/nucleo
        $exists = $db->prepare(
            "SELECT id FROM chamadas WHERE nucleo_id = ? AND data_aula = ? LIMIT 1"
        );
        $exists->execute([$nucleoId, $dataAula]);
        if ($exists->fetch()) {
            $_SESSION['flash_error'] = 'Já existe uma chamada registrada para esta data.';
            header('Location: ' . APP_URL . '/professor/frequencia/nova');
            exit;
        }

        // Load all active students for the nucleo
        $allStmt = $db->prepare(
            "SELECT id FROM alunos WHERE nucleo_id = ? AND status = 'ativo'"
        );
        $allStmt->execute([$nucleoId]);
        $todosIds = $allStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($todosIds)) {
            $_SESSION['flash_error'] = 'Nenhum aluno ativo encontrado.';
            header('Location: ' . APP_URL . '/professor/frequencia/nova');
            exit;
        }

        $presentesSet = array_flip(array_filter((array) $presentes, fn($v) => is_numeric($v)));

        $db->beginTransaction();
        try {
            $chamadaStmt = $db->prepare(
                "INSERT INTO chamadas (nucleo_id, professor_id, data_aula, criado_em) VALUES (?, ?, ?, NOW())"
            );
            $chamadaStmt->execute([$nucleoId, Auth::id(), $dataAula]);
            $chamadaId = (int) $db->lastInsertId();

            $presStmt = $db->prepare(
                "INSERT INTO chamada_presencas (chamada_id, aluno_id, presente) VALUES (?, ?, ?)"
            );
            foreach ($todosIds as $alunoId) {
                $presente = isset($presentesSet[$alunoId]) ? 1 : 0;
                $presStmt->execute([$chamadaId, $alunoId, $presente]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[Frequencia] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao salvar chamada. Tente novamente.';
            header('Location: ' . APP_URL . '/professor/frequencia/nova');
            exit;
        }

        $totalPresentes = count(array_filter($todosIds, fn($id) => isset($presentesSet[$id])));
        Security::auditLog('cadastro', 'chamadas', $chamadaId);

        $_SESSION['flash_success'] = sprintf(
            'Chamada registrada para %s — %d/%d presente%s.',
            date('d/m/Y', strtotime($dataAula)),
            $totalPresentes,
            count($todosIds),
            $totalPresentes !== 1 ? 's' : ''
        );
        header('Location: ' . APP_URL . '/professor/frequencia');
        exit;
    }

    // ── Detalhe de chamada ───────────────────────────────────────────────────

    public function show(string $id): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT * FROM chamadas WHERE id = ? AND nucleo_id = ? AND professor_id = ? LIMIT 1"
        );
        $stmt->execute([(int) $id, $nucleoId, Auth::id()]);
        $chamada = $stmt->fetch();

        if (!$chamada) {
            $_SESSION['flash_error'] = 'Chamada não encontrada.';
            header('Location: ' . APP_URL . '/professor/frequencia');
            exit;
        }

        $stmt = $db->prepare("
            SELECT a.id, a.nome, a.foto, cp.presente
            FROM chamada_presencas cp
            JOIN alunos a ON a.id = cp.aluno_id
            WHERE cp.chamada_id = ?
            ORDER BY a.nome ASC
        ");
        $stmt->execute([(int) $id]);
        $presencas = $stmt->fetchAll();

        $data = compact('chamada', 'presencas');
        require_once ROOT_PATH . '/app/views/professor/frequencia/show.php';
    }
}
