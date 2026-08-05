<?php

/**
 * Depoimentos de alunos/mães/responsáveis — centraliza o que hoje é
 * coletado separadamente. Acesso: qualquer perfil administrativo
 * (super_admin/gestor) com permissão depoimentos.*, escopado por núcleo.
 */
class AdminDepoimentosController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('depoimentos.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'd.nucleo_id');

        $nucleoId = (int) ($_GET['nucleo_id'] ?? 0);
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $off      = ($page - 1) * self::PER_PAGE;

        $conditions = [$escopoWhere];
        $params     = $escopoParams;
        if ($nucleoId) { $conditions[] = 'd.nucleo_id = ?'; $params[] = $nucleoId; }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM depoimentos d $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT d.*, n.nome AS nucleo_nome, a.nome AS aluno_nome, u.nome AS criado_por_nome
            FROM depoimentos d
            JOIN nucleos n ON n.id = d.nucleo_id
            LEFT JOIN alunos a ON a.id = d.aluno_id
            JOIN usuarios u ON u.id = d.criado_por
            $where
            ORDER BY d.criado_em DESC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $depoimentos = $stmt->fetchAll();

        $nucleos = [];
        if ($permitidos) {
            [$w, $p] = Escopo::whereIn($permitidos, 'id');
            $nucleos = $db->prepare("SELECT id, nome FROM nucleos WHERE $w ORDER BY nome");
            $nucleos->execute($p);
            $nucleos = $nucleos->fetchAll();
        }

        $totalPages = (int) ceil($total / self::PER_PAGE);
        $data = compact('depoimentos', 'nucleos', 'nucleoId', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/depoimentos/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('depoimentos.editar');
        $db = Database::getInstance();

        $nucleos = $this->nucleosDisponiveis($db);
        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/depoimentos/form.php';
    }

    public function store(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('depoimentos.editar');
        Security::verifyCsrf();
        $db = Database::getInstance();

        [$dados, $errors] = $this->validar($_POST);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/depoimentos/novo');
            exit;
        }

        $arquivo = null;
        if (!empty($_FILES['arquivo']['name'])) {
            try {
                require_once ROOT_PATH . '/app/helpers/Upload.php';
                $arquivo = Upload::image($_FILES['arquivo'], 'depoimentos', 1200, 1200, 85);
            } catch (RuntimeException $e) {
                $_SESSION['form_errors'] = ['arquivo' => $e->getMessage()];
                $_SESSION['form_data']   = $_POST;
                header('Location: ' . APP_URL . '/admin/depoimentos/novo');
                exit;
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO depoimentos (nucleo_id, aluno_id, autor_nome, conteudo, tipo, arquivo_path, criado_por, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $dados['nucleo_id'], $dados['aluno_id'] ?: null, $dados['autor_nome'] ?: null,
            $dados['conteudo'], $arquivo ? 'foto' : 'texto', $arquivo, Auth::id(),
        ]);
        $id = $db->lastInsertId();

        Security::auditLog('cadastro', 'depoimentos', $id);
        $_SESSION['flash_success'] = 'Depoimento registrado.';
        header('Location: ' . APP_URL . '/admin/depoimentos');
        exit;
    }

    public function excluir(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('depoimentos.editar');
        Security::verifyCsrf();
        $id = (int) $id;

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM depoimentos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $dep = $stmt->fetch();

        if (!$dep || !Escopo::podeAcessarNucleo(Auth::id(), (int) $dep['nucleo_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        if ($dep['arquivo_path']) {
            require_once ROOT_PATH . '/app/helpers/Upload.php';
            Upload::delete($dep['arquivo_path']);
        }
        $db->prepare("DELETE FROM depoimentos WHERE id = ?")->execute([$id]);

        Security::auditLog('exclusao', 'depoimentos', $id);
        $_SESSION['flash_success'] = 'Depoimento removido.';
        header('Location: ' . APP_URL . '/admin/depoimentos');
        exit;
    }

    private function validar(array $post): array
    {
        $dados = [
            'nucleo_id'  => (int) ($post['nucleo_id'] ?? 0),
            'aluno_id'   => (int) ($post['aluno_id']   ?? 0),
            'autor_nome' => Security::sanitize($post['autor_nome'] ?? ''),
            'conteudo'   => Security::sanitize($post['conteudo']   ?? ''),
        ];
        $errors = [];

        if (!$dados['nucleo_id']) {
            $errors['nucleo_id'] = 'Selecione o núcleo.';
        } elseif (!Escopo::podeAcessarNucleo(Auth::id(), $dados['nucleo_id'])) {
            $errors['nucleo_id'] = 'Você não tem acesso a esse núcleo.';
        }
        if (mb_strlen($dados['conteudo']) < 5) {
            $errors['conteudo'] = 'Escreva o depoimento (mínimo 5 caracteres).';
        }

        return [$dados, $errors];
    }

    private function nucleosDisponiveis(PDO $db): array
    {
        $ids = Escopo::nucleosPermitidos(Auth::id());
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'id');
        $stmt = $db->prepare("SELECT id, nome FROM nucleos WHERE $where ORDER BY nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
