<?php

/**
 * Termo de Fomento — associado ao Projeto. Estrutura intencionalmente
 * simples e extensível: os campos administrativos definitivos (exigidos
 * pelos responsáveis do programa) ainda não foram definidos — ver
 * docs/PENDENCIAS_REGRAS_NEGOCIO.md. Por ora: identificação, período,
 * status, observações e anexos (documentos PDF).
 */
class AdminTermosFomentoController
{
    public function index(string $projetoId): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.visualizar');
        $projetoId = (int) $projetoId;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $projetoId)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT p.*, i.nome AS instituto_nome FROM projetos p JOIN institutos i ON i.id=p.instituto_id WHERE p.id=? LIMIT 1");
        $stmt->execute([$projetoId]);
        $projeto = $stmt->fetch();

        if (!$projeto) {
            $_SESSION['flash_error'] = 'Projeto não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM termos_fomento WHERE projeto_id = ? ORDER BY criado_em DESC");
        $stmt->execute([$projetoId]);
        $termos = $stmt->fetchAll();

        foreach ($termos as &$t) {
            $anexosStmt = $db->prepare("SELECT * FROM termo_fomento_anexos WHERE termo_fomento_id = ? ORDER BY criado_em DESC");
            $anexosStmt->execute([$t['id']]);
            $t['anexos'] = $anexosStmt->fetchAll();
        }
        unset($t);

        $data = compact('projeto', 'termos');
        require_once ROOT_PATH . '/app/views/admin/termos/index.php';
    }

    public function formNovo(string $projetoId): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.editar');
        $projetoId = (int) $projetoId;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $projetoId)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM projetos WHERE id=? LIMIT 1");
        $stmt->execute([$projetoId]);
        $projeto = $stmt->fetch();
        if (!$projeto) {
            $_SESSION['flash_error'] = 'Projeto não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }

        $termo   = null;
        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/termos/form.php';
    }

    public function store(string $projetoId): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.editar');
        Security::verifyCsrf();
        $projetoId = (int) $projetoId;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $projetoId)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        [$dados, $errors] = $this->validar($_POST);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos/novo');
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO termos_fomento (projeto_id, numero, descricao, data_inicio, data_fim, observacoes, criado_por, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $projetoId, $dados['numero'] ?: null, $dados['descricao'] ?: null,
            $dados['data_inicio'] ?: null, $dados['data_fim'] ?: null, $dados['observacoes'] ?: null,
            Auth::id(),
        ]);
        $id = $db->lastInsertId();

        Security::auditLog('cadastro', 'termos_fomento', $id);
        $_SESSION['flash_success'] = 'Termo de fomento cadastrado.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.editar');
        [$termo, $projetoId] = $this->buscarComEscopo((int) $id);

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM projetos WHERE id=? LIMIT 1");
        $stmt->execute([$projetoId]);
        $projeto = $stmt->fetch();

        require_once ROOT_PATH . '/app/views/admin/termos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.editar');
        Security::verifyCsrf();
        [$termo, $projetoId] = $this->buscarComEscopo((int) $id);

        [$dados, $errors] = $this->validar($_POST);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/termos/' . $id . '/editar');
            exit;
        }

        $db = Database::getInstance();
        $db->prepare(
            "UPDATE termos_fomento SET numero=?, descricao=?, data_inicio=?, data_fim=?, observacoes=? WHERE id=?"
        )->execute([
            $dados['numero'] ?: null, $dados['descricao'] ?: null,
            $dados['data_inicio'] ?: null, $dados['data_fim'] ?: null, $dados['observacoes'] ?: null,
            $termo['id'],
        ]);

        Security::auditLog('edicao', 'termos_fomento', $termo['id']);
        $_SESSION['flash_success'] = 'Termo de fomento atualizado.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos');
        exit;
    }

    public function mudarStatus(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.editar');
        Security::verifyCsrf();
        [$termo, $projetoId] = $this->buscarComEscopo((int) $id);

        $novoStatus = Security::sanitize($_POST['status'] ?? '');
        if (!in_array($novoStatus, ['ativo', 'encerrado', 'suspenso'], true)) {
            $_SESSION['flash_error'] = 'Status inválido.';
            header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos');
            exit;
        }

        $db = Database::getInstance();
        $db->prepare("UPDATE termos_fomento SET status=? WHERE id=?")->execute([$novoStatus, $termo['id']]);

        Security::auditLog('edicao', 'termos_fomento', $termo['id']);
        $_SESSION['flash_success'] = 'Status do termo atualizado.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos');
        exit;
    }

    public function storeAnexo(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.editar');
        Security::verifyCsrf();
        [$termo, $projetoId] = $this->buscarComEscopo((int) $id);

        if (empty($_FILES['arquivo']['name'])) {
            $_SESSION['flash_error'] = 'Selecione um arquivo.';
            header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos');
            exit;
        }

        try {
            require_once ROOT_PATH . '/app/helpers/Upload.php';
            $path = Upload::pdf($_FILES['arquivo'], 'termos_fomento');
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos');
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO termo_fomento_anexos (termo_fomento_id, nome_arquivo, arquivo_path, tamanho_bytes, enviado_por, criado_em)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $termo['id'], Security::sanitize($_FILES['arquivo']['name']), $path,
            $_FILES['arquivo']['size'] ?? null, Auth::id(),
        ]);

        Security::auditLog('cadastro', 'termo_fomento_anexos', $db->lastInsertId());
        $_SESSION['flash_success'] = 'Anexo enviado.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/termos');
        exit;
    }

    public function excluirAnexo(string $anexoId): void
    {
        Auth::requireAdminArea();
        Permissao::requer('termos_fomento.editar');
        Security::verifyCsrf();
        $anexoId = (int) $anexoId;

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT a.*, t.projeto_id FROM termo_fomento_anexos a
            JOIN termos_fomento t ON t.id = a.termo_fomento_id
            WHERE a.id = ? LIMIT 1
        ");
        $stmt->execute([$anexoId]);
        $anexo = $stmt->fetch();

        if (!$anexo || !Escopo::podeAcessarProjeto(Auth::id(), (int) $anexo['projeto_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        require_once ROOT_PATH . '/app/helpers/Upload.php';
        Upload::delete($anexo['arquivo_path']);
        $db->prepare("DELETE FROM termo_fomento_anexos WHERE id=?")->execute([$anexoId]);

        Security::auditLog('exclusao', 'termo_fomento_anexos', $anexoId);
        $_SESSION['flash_success'] = 'Anexo removido.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $anexo['projeto_id'] . '/termos');
        exit;
    }

    // ── Internos ────────────────────────────────────────────────────────────

    private function validar(array $post): array
    {
        $dados = [
            'numero'       => Security::sanitize($post['numero']       ?? ''),
            'descricao'    => Security::sanitize($post['descricao']    ?? ''),
            'data_inicio'  => Security::sanitize($post['data_inicio']  ?? ''),
            'data_fim'     => Security::sanitize($post['data_fim']     ?? ''),
            'observacoes'  => Security::sanitize($post['observacoes']  ?? ''),
        ];
        $errors = [];
        if ($dados['data_inicio'] && $dados['data_fim'] && $dados['data_fim'] < $dados['data_inicio']) {
            $errors['data_fim'] = 'Data final não pode ser antes da data inicial.';
        }
        return [$dados, $errors];
    }

    /** Busca o termo garantindo que o usuário logado tem acesso ao projeto dono. Encerra com 403/404 se não. */
    private function buscarComEscopo(int $id): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM termos_fomento WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $termo = $stmt->fetch();

        if (!$termo) {
            $_SESSION['flash_error'] = 'Termo de fomento não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }
        if (!Escopo::podeAcessarProjeto(Auth::id(), (int) $termo['projeto_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        return [$termo, (int) $termo['projeto_id']];
    }
}
