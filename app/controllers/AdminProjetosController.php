<?php

class AdminProjetosController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::projetosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'p.id');

        $q          = Security::sanitize($_GET['q'] ?? '');
        $institutoId = (int) ($_GET['instituto_id'] ?? 0);
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $off        = ($page - 1) * self::PER_PAGE;

        $conditions = [$escopoWhere];
        $params     = $escopoParams;
        if ($q) { $conditions[] = 'p.nome LIKE ?'; $params[] = "%$q%"; }
        if ($institutoId) { $conditions[] = 'p.instituto_id = ?'; $params[] = $institutoId; }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM projetos p $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT p.*, i.nome AS instituto_nome,
                   COUNT(DISTINCT n.id) AS total_nucleos
            FROM projetos p
            JOIN institutos i ON i.id = p.instituto_id
            LEFT JOIN nucleos n ON n.projeto_id = p.id AND n.status = 'ativo'
            $where
            GROUP BY p.id
            ORDER BY i.nome ASC, p.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $projetos = $stmt->fetchAll();

        $institutosPermitidosIds = $permitidos;
        $institutos = [];
        if ($institutosPermitidosIds) {
            [$w, $p2] = Escopo::whereIn($institutosPermitidosIds, 'id');
            $institutos = $db->prepare("SELECT id, nome FROM institutos WHERE $w ORDER BY nome");
            $institutos->execute($p2);
            $institutos = $institutos->fetchAll();
        }

        $totalPages = (int) ceil($total / self::PER_PAGE);

        $data = compact('projetos', 'q', 'institutoId', 'institutos', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/projetos/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.editar');
        $db = Database::getInstance();

        $projeto    = null;
        $institutos = $this->institutosDisponiveis($db);
        $errors     = $_SESSION['form_errors'] ?? [];
        $oldData    = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/projetos/form.php';
    }

    public function store(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.editar');
        Security::verifyCsrf();

        $nome        = Security::sanitize($_POST['nome']      ?? '');
        $descricao   = Security::sanitize($_POST['descricao'] ?? '');
        $institutoId = (int) ($_POST['instituto_id'] ?? 0);
        $errors      = [];

        if ($nome === '') $errors['nome'] = 'Nome é obrigatório.';
        if (strlen($nome) > 150) $errors['nome'] = 'Nome muito longo (máx. 150 caracteres).';
        if (!$institutoId) {
            $errors['instituto_id'] = 'Selecione o instituto.';
        } elseif (!Escopo::podeAcessarInstituto(Auth::id(), $institutoId)) {
            $errors['instituto_id'] = 'Você não tem acesso a esse instituto.';
        }

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/projetos/novo');
            exit;
        }

        $logo = null;
        if (!empty($_FILES['logo']['name'])) {
            try {
                require_once ROOT_PATH . '/app/helpers/Upload.php';
                $logo = Upload::image($_FILES['logo'], 'logos', 300, 300, 80);
            } catch (RuntimeException $e) {
                $_SESSION['form_errors'] = ['logo' => $e->getMessage()];
                $_SESSION['form_data']   = $_POST;
                header('Location: ' . APP_URL . '/admin/projetos/novo');
                exit;
            }
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO projetos (instituto_id, nome, descricao, logo, status, criado_em)
             VALUES (?, ?, ?, ?, 'ativo', NOW())"
        );
        $stmt->execute([$institutoId, $nome, $descricao ?: null, $logo]);
        $id = $db->lastInsertId();

        Security::auditLog('cadastro', 'projetos', $id);
        $_SESSION['flash_success'] = "Projeto \"$nome\" criado com sucesso.";
        header('Location: ' . APP_URL . '/admin/projetos');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.editar');
        $id = (int) $id;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db      = Database::getInstance();
        $stmt    = $db->prepare("SELECT * FROM projetos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $projeto = $stmt->fetch();

        if (!$projeto) {
            $_SESSION['flash_error'] = 'Projeto não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }

        $institutos = $this->institutosDisponiveis($db);
        $errors     = $_SESSION['form_errors'] ?? [];
        $oldData    = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/projetos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.editar');
        Security::verifyCsrf();
        $id = (int) $id;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM projetos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $projeto = $stmt->fetch();

        if (!$projeto) {
            $_SESSION['flash_error'] = 'Projeto não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }

        $nome        = Security::sanitize($_POST['nome']      ?? '');
        $descricao   = Security::sanitize($_POST['descricao'] ?? '');
        $institutoId = (int) ($_POST['instituto_id'] ?? 0);
        $errors      = [];

        if ($nome === '') $errors['nome'] = 'Nome é obrigatório.';
        if (strlen($nome) > 150) $errors['nome'] = 'Nome muito longo.';
        if (!$institutoId) {
            $errors['instituto_id'] = 'Selecione o instituto.';
        } elseif (!Escopo::podeAcessarInstituto(Auth::id(), $institutoId)) {
            $errors['instituto_id'] = 'Você não tem acesso a esse instituto.';
        }

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/projetos/' . $id . '/editar');
            exit;
        }

        $logo = $projeto['logo'];
        if (!empty($_FILES['logo']['name'])) {
            try {
                require_once ROOT_PATH . '/app/helpers/Upload.php';
                $newLogo = Upload::image($_FILES['logo'], 'logos', 300, 300, 80);
                if ($logo) Upload::delete($logo);
                $logo = $newLogo;
            } catch (RuntimeException $e) {
                $_SESSION['form_errors'] = ['logo' => $e->getMessage()];
                $_SESSION['form_data']   = $_POST;
                header('Location: ' . APP_URL . '/admin/projetos/' . $id . '/editar');
                exit;
            }
        }

        // Se o instituto mudou, os núcleos dependentes seguem o projeto — a
        // hierarquia é resolvida por join (nucleos.projeto_id → projetos.instituto_id),
        // então não há coluna redundante para sincronizar aqui.
        $stmt = $db->prepare(
            "UPDATE projetos SET instituto_id = ?, nome = ?, descricao = ?, logo = ? WHERE id = ?"
        );
        $stmt->execute([$institutoId, $nome, $descricao ?: null, $logo, $id]);

        Security::auditLog('edicao', 'projetos', $id);
        $_SESSION['flash_success'] = "Projeto \"$nome\" atualizado.";
        header('Location: ' . APP_URL . '/admin/projetos');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.excluir');
        Security::verifyCsrf();
        $id = (int) $id;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE projetos SET status = 'inativo' WHERE id = ?");
        $stmt->execute([$id]);

        Security::auditLog('exclusao', 'projetos', $id);
        $_SESSION['flash_success'] = 'Projeto inativado com sucesso.';
        header('Location: ' . APP_URL . '/admin/projetos');
        exit;
    }

    private function institutosDisponiveis(PDO $db): array
    {
        $ids = Escopo::institutosPermitidos(Auth::id());
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'id');
        $stmt = $db->prepare("SELECT id, nome FROM institutos WHERE status = 'ativo' AND $where ORDER BY nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
