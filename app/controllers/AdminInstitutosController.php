<?php

class AdminInstitutosController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('institutos.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::institutosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'i.id');

        $q    = Security::sanitize($_GET['q'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * self::PER_PAGE;

        $conditions = [$escopoWhere];
        $params     = $escopoParams;
        if ($q) { $conditions[] = 'i.nome LIKE ?'; $params[] = "%$q%"; }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM institutos i $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT i.*, COUNT(DISTINCT p.id) AS total_projetos
            FROM institutos i
            LEFT JOIN projetos p ON p.instituto_id = i.id AND p.status = 'ativo'
            $where
            GROUP BY i.id
            ORDER BY i.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $institutos = $stmt->fetchAll();

        $totalPages = (int) ceil($total / self::PER_PAGE);
        $data = compact('institutos', 'q', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/institutos/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('institutos.editar');

        $instituto = null;
        $errors    = $_SESSION['form_errors'] ?? [];
        $oldData   = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/institutos/form.php';
    }

    public function store(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('institutos.editar');
        Security::verifyCsrf();

        [$dados, $errors] = $this->validar($_POST);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/institutos/novo');
            exit;
        }

        $logotipo = null;
        if (!empty($_FILES['logotipo']['name'])) {
            try {
                require_once ROOT_PATH . '/app/helpers/Upload.php';
                $logotipo = Upload::image($_FILES['logotipo'], 'institutos', 300, 300, 80);
            } catch (RuntimeException $e) {
                $_SESSION['form_errors'] = ['logotipo' => $e->getMessage()];
                $_SESSION['form_data']   = $_POST;
                header('Location: ' . APP_URL . '/admin/institutos/novo');
                exit;
            }
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO institutos (nome, nome_fantasia, descricao, logotipo, identificacao, responsavel_nome, contato_email, contato_telefone, observacoes, status, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo', NOW())"
        );
        $stmt->execute([
            $dados['nome'], $dados['nome_fantasia'] ?: null, $dados['descricao'] ?: null, $logotipo,
            $dados['identificacao'] ?: null, $dados['responsavel_nome'] ?: null,
            $dados['contato_email'] ?: null, $dados['contato_telefone'] ?: null, $dados['observacoes'] ?: null,
        ]);
        $id = $db->lastInsertId();

        Security::auditLog('cadastro', 'institutos', $id);
        $_SESSION['flash_success'] = "Instituto \"{$dados['nome']}\" criado com sucesso.";
        header('Location: ' . APP_URL . '/admin/institutos');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('institutos.editar');
        $id = (int) $id;

        if (!Escopo::podeAcessarInstituto(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM institutos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $instituto = $stmt->fetch();

        if (!$instituto) {
            $_SESSION['flash_error'] = 'Instituto não encontrado.';
            header('Location: ' . APP_URL . '/admin/institutos');
            exit;
        }

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/institutos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('institutos.editar');
        Security::verifyCsrf();
        $id = (int) $id;

        if (!Escopo::podeAcessarInstituto(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM institutos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $instituto = $stmt->fetch();
        if (!$instituto) {
            $_SESSION['flash_error'] = 'Instituto não encontrado.';
            header('Location: ' . APP_URL . '/admin/institutos');
            exit;
        }

        [$dados, $errors] = $this->validar($_POST);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/institutos/' . $id . '/editar');
            exit;
        }

        $logotipo = $instituto['logotipo'];
        if (!empty($_FILES['logotipo']['name'])) {
            try {
                require_once ROOT_PATH . '/app/helpers/Upload.php';
                $novo = Upload::image($_FILES['logotipo'], 'institutos', 300, 300, 80);
                if ($logotipo) Upload::delete($logotipo);
                $logotipo = $novo;
            } catch (RuntimeException $e) {
                $_SESSION['form_errors'] = ['logotipo' => $e->getMessage()];
                $_SESSION['form_data']   = $_POST;
                header('Location: ' . APP_URL . '/admin/institutos/' . $id . '/editar');
                exit;
            }
        }

        $stmt = $db->prepare(
            "UPDATE institutos SET nome=?, nome_fantasia=?, descricao=?, logotipo=?, identificacao=?, responsavel_nome=?, contato_email=?, contato_telefone=?, observacoes=? WHERE id=?"
        );
        $stmt->execute([
            $dados['nome'], $dados['nome_fantasia'] ?: null, $dados['descricao'] ?: null, $logotipo,
            $dados['identificacao'] ?: null, $dados['responsavel_nome'] ?: null,
            $dados['contato_email'] ?: null, $dados['contato_telefone'] ?: null, $dados['observacoes'] ?: null,
            $id,
        ]);

        Security::auditLog('edicao', 'institutos', $id);
        $_SESSION['flash_success'] = "Instituto \"{$dados['nome']}\" atualizado.";
        header('Location: ' . APP_URL . '/admin/institutos');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('institutos.excluir');
        Security::verifyCsrf();
        $id = (int) $id;

        if (!Escopo::podeAcessarInstituto(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db = Database::getInstance();
        $db->prepare("UPDATE institutos SET status = 'inativo' WHERE id = ?")->execute([$id]);

        Security::auditLog('exclusao', 'institutos', $id);
        $_SESSION['flash_success'] = 'Instituto inativado com sucesso.';
        header('Location: ' . APP_URL . '/admin/institutos');
        exit;
    }

    private function validar(array $post): array
    {
        $dados = [
            'nome'             => Security::sanitize($post['nome']             ?? ''),
            'nome_fantasia'    => Security::sanitize($post['nome_fantasia']    ?? ''),
            'descricao'        => Security::sanitize($post['descricao']        ?? ''),
            'identificacao'    => Security::sanitize($post['identificacao']    ?? ''),
            'responsavel_nome' => Security::sanitize($post['responsavel_nome'] ?? ''),
            'contato_email'    => Security::sanitizeEmail($post['contato_email'] ?? ''),
            'contato_telefone' => Security::sanitize($post['contato_telefone'] ?? ''),
            'observacoes'      => Security::sanitize($post['observacoes']      ?? ''),
        ];

        $errors = [];
        if ($dados['nome'] === '') $errors['nome'] = 'Nome é obrigatório.';
        if (strlen($dados['nome']) > 150) $errors['nome'] = 'Nome muito longo (máx. 150 caracteres).';
        if ($dados['contato_email'] && !filter_var($dados['contato_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contato_email'] = 'E-mail inválido.';
        }

        return [$dados, $errors];
    }
}
