<?php

class ProfessorAlunosController
{
    private const PER_PAGE = 20;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function nucleoId(): int
    {
        static $id = null;
        if ($id !== null) return $id;

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT nucleo_id FROM nucleo_professores WHERE usuario_id = ? LIMIT 1"
        );
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

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $q      = Security::sanitize($_GET['q']      ?? '');
        $status = Security::sanitize($_GET['status'] ?? 'ativo');
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $off    = ($page - 1) * self::PER_PAGE;

        $conditions = ['a.nucleo_id = ?'];
        $params     = [$nucleoId];

        if ($q) {
            $conditions[] = '(a.nome LIKE ? OR a.email LIKE ? OR a.telefone LIKE ?)';
            $params[]     = "%$q%";
            $params[]     = "%$q%";
            $params[]     = "%$q%";
        }
        if (in_array($status, ['ativo', 'inativo'], true)) {
            $conditions[] = 'a.status = ?';
            $params[]     = $status;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM alunos a $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT a.*,
                   COUNT(cp.id) AS total_chamadas,
                   SUM(cp.presente) AS total_presencas
            FROM alunos a
            LEFT JOIN chamada_presencas cp ON cp.aluno_id = a.id
            LEFT JOIN chamadas c ON c.id = cp.chamada_id AND c.nucleo_id = a.nucleo_id
            $where
            GROUP BY a.id
            ORDER BY a.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $alunos = $stmt->fetchAll();

        $totalPages = (int) ceil($total / self::PER_PAGE);

        // Convite ativo
        $conviteStmt = $db->prepare("
            SELECT token_hash, expira_em FROM convites
            WHERE nucleo_id = ? AND tipo = 'aluno' AND status = 'pendente'
              AND expira_em > NOW()
            ORDER BY criado_em DESC LIMIT 1
        ");
        $conviteStmt->execute([$nucleoId]);
        $conviteAtivo = $conviteStmt->fetch();

        $data = compact('alunos', 'q', 'status', 'page', 'total', 'totalPages', 'conviteAtivo');
        require_once ROOT_PATH . '/app/views/professor/alunos/index.php';
    }

    // ── Novo / Store ─────────────────────────────────────────────────────────

    public function formNovo(): void
    {
        Auth::requireRole('professor');
        $this->assertNucleo();
        $aluno   = null;
        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/professor/alunos/form.php';
    }

    public function store(): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();

        [$data, $errors] = $this->validateForm();

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/professor/alunos/novo');
            exit;
        }

        $foto = $this->handleFotoUpload();
        if ($foto === false) {
            header('Location: ' . APP_URL . '/professor/alunos/novo');
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO alunos
              (nucleo_id, nome, email, telefone, whatsapp, endereco_completo,
               cidade, cep, data_nascimento, foto, status, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo', NOW())
        ");
        $stmt->execute([
            $nucleoId,
            $data['nome'],
            $data['email']     ?: null,
            $data['telefone']  ?: null,
            $data['whatsapp']  ?: null,
            $data['endereco']  ?: null,
            $data['cidade']    ?: null,
            $data['cep']       ?: null,
            $data['nascimento']?: null,
            $foto,
        ]);
        $id = $db->lastInsertId();

        Security::auditLog('cadastro', 'alunos', $id);
        $_SESSION['flash_success'] = "Aluno \"{$data['nome']}\" cadastrado.";
        header('Location: ' . APP_URL . '/professor/alunos');
        exit;
    }

    // ── Editar / Update ───────────────────────────────────────────────────────

    public function formEditar(string $id): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT * FROM alunos WHERE id = ? AND nucleo_id = ? LIMIT 1"
        );
        $stmt->execute([(int) $id, $nucleoId]);
        $aluno = $stmt->fetch();

        if (!$aluno) {
            $_SESSION['flash_error'] = 'Aluno não encontrado.';
            header('Location: ' . APP_URL . '/professor/alunos');
            exit;
        }

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/professor/alunos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM alunos WHERE id = ? AND nucleo_id = ? LIMIT 1"
        );
        $stmt->execute([(int) $id, $nucleoId]);
        $aluno = $stmt->fetch();

        if (!$aluno) {
            $_SESSION['flash_error'] = 'Aluno não encontrado.';
            header('Location: ' . APP_URL . '/professor/alunos');
            exit;
        }

        [$data, $errors] = $this->validateForm(isEdit: true);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/professor/alunos/' . $id . '/editar');
            exit;
        }

        $foto = $aluno['foto'];
        if (!empty($_FILES['foto']['name'])) {
            $novo = $this->handleFotoUpload(redirectBack: APP_URL . '/professor/alunos/' . $id . '/editar');
            if ($novo === false) exit;
            require_once ROOT_PATH . '/app/helpers/Upload.php';
            if ($foto) Upload::delete($foto);
            $foto = $novo;
        }

        $stmt = $db->prepare("
            UPDATE alunos SET
              nome = ?, email = ?, telefone = ?, whatsapp = ?,
              endereco_completo = ?, cidade = ?, cep = ?, data_nascimento = ?, foto = ?
            WHERE id = ? AND nucleo_id = ?
        ");
        $stmt->execute([
            $data['nome'], $data['email'] ?: null, $data['telefone'] ?: null,
            $data['whatsapp'] ?: null, $data['endereco'] ?: null,
            $data['cidade'] ?: null, $data['cep'] ?: null,
            $data['nascimento'] ?: null, $foto,
            (int) $id, $nucleoId,
        ]);

        Security::auditLog('edicao', 'alunos', $id);
        $_SESSION['flash_success'] = "Aluno \"{$data['nome']}\" atualizado.";
        header('Location: ' . APP_URL . '/professor/alunos');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE alunos SET status = 'inativo' WHERE id = ? AND nucleo_id = ?"
        );
        $stmt->execute([(int) $id, $nucleoId]);

        Security::auditLog('exclusao', 'alunos', $id);
        $_SESSION['flash_success'] = 'Aluno inativado. Histórico preservado.';
        header('Location: ' . APP_URL . '/professor/alunos');
        exit;
    }

    // ── Convite por token (reutilizável) ──────────────────────────────────────

    public function formConvite(): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $stmt = $db->prepare("
            SELECT c.*, n.nome AS nucleo
            FROM convites c
            JOIN nucleos n ON n.id = c.nucleo_id
            WHERE c.nucleo_id = ? AND c.tipo = 'aluno' AND c.status = 'pendente'
              AND c.expira_em > NOW()
            ORDER BY c.criado_em DESC LIMIT 1
        ");
        $stmt->execute([$nucleoId]);
        $conviteAtivo = $stmt->fetch();

        $inviteUrl  = $_SESSION['invite_url_aluno'] ?? null;
        unset($_SESSION['invite_url_aluno']);

        require_once ROOT_PATH . '/app/views/professor/alunos/convite.php';
    }

    public function gerarConvite(): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();

        $db = Database::getInstance();

        // Expirar convites pendentes do mesmo núcleo
        $db->prepare(
            "UPDATE convites SET status='expirado'
             WHERE nucleo_id = ? AND tipo = 'aluno' AND status = 'pendente'"
        )->execute([$nucleoId]);

        $raw    = Security::generateToken(32);
        $hash   = Security::hashToken($raw);
        $expira = date('Y-m-d H:i:s', strtotime('+7 days'));

        $db->prepare(
            "INSERT INTO convites (token_hash, tipo, nucleo_id, criado_por, status, expira_em, criado_em)
             VALUES (?, 'aluno', ?, ?, 'pendente', ?, NOW())"
        )->execute([$hash, $nucleoId, Auth::id(), $expira]);

        Security::auditLog('geracao_token', 'convites', $db->lastInsertId());

        $_SESSION['invite_url_aluno'] = APP_URL . '/convite/aluno/' . $raw;
        header('Location: ' . APP_URL . '/professor/alunos/convite');
        exit;
    }

    public function revogarConvite(): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();

        $db = Database::getInstance();
        $db->prepare(
            "UPDATE convites SET status='expirado'
             WHERE nucleo_id = ? AND tipo = 'aluno' AND status = 'pendente'"
        )->execute([$nucleoId]);

        Security::auditLog('revogacao_token', 'convites');
        $_SESSION['flash_success'] = 'Link de convite revogado.';
        header('Location: ' . APP_URL . '/professor/alunos/convite');
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateForm(bool $isEdit = false): array
    {
        $data = [
            'nome'      => Security::sanitize($_POST['nome']               ?? ''),
            'email'     => Security::sanitizeEmail($_POST['email']         ?? ''),
            'telefone'  => Security::sanitize($_POST['telefone']           ?? ''),
            'whatsapp'  => Security::sanitize($_POST['whatsapp']           ?? ''),
            'endereco'  => Security::sanitize($_POST['endereco_completo']  ?? ''),
            'cidade'    => Security::sanitize($_POST['cidade']             ?? ''),
            'cep'       => Security::sanitize($_POST['cep']               ?? ''),
            'nascimento'=> Security::sanitize($_POST['data_nascimento']    ?? ''),
        ];

        $errors = [];
        if (!$data['nome']) $errors['nome'] = 'Nome é obrigatório.';
        if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }
        if ($data['nascimento'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['nascimento'])) {
            $errors['data_nascimento'] = 'Data inválida.';
        }

        return [$data, $errors];
    }

    private function handleFotoUpload(string $redirectBack = ''): string|false
    {
        if (empty($_FILES['foto']['name'])) return '';

        try {
            require_once ROOT_PATH . '/app/helpers/Upload.php';
            return Upload::image($_FILES['foto'], 'fotos', 400, 400, 80);
        } catch (RuntimeException $e) {
            $_SESSION['form_errors'] = ['foto' => $e->getMessage()];
            $_SESSION['form_data']   = $_POST;
            $url = $redirectBack ?: APP_URL . '/professor/alunos/novo';
            header('Location: ' . $url);
            return false;
        }
    }
}
