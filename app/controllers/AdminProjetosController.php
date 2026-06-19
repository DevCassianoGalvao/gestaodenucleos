<?php

class AdminProjetosController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireRole('super_admin');
        $db = Database::getInstance();

        $q    = Security::sanitize($_GET['q'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * self::PER_PAGE;

        $where  = $q ? "WHERE p.nome LIKE ?" : '';
        $params = $q ? ["%$q%"] : [];

        $total = (int) $db->prepare(
            "SELECT COUNT(*) FROM projetos p $where"
        )->execute($params) ? ($total_stmt = $db->prepare("SELECT COUNT(*) FROM projetos p $where") and $total_stmt->execute($params) ? (int)$total_stmt->fetchColumn() : 0) : 0;

        // Rewritten cleanly
        $countStmt = $db->prepare("SELECT COUNT(*) FROM projetos p $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT p.*,
                   COUNT(DISTINCT n.id) AS total_nucleos
            FROM projetos p
            LEFT JOIN nucleos n ON n.projeto_id = p.id AND n.status = 'ativo'
            $where
            GROUP BY p.id
            ORDER BY p.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $projetos = $stmt->fetchAll();

        $totalPages = (int) ceil($total / self::PER_PAGE);

        $data = compact('projetos', 'q', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/projetos/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireRole('super_admin');
        $projeto   = null;
        $errors    = $_SESSION['form_errors'] ?? [];
        $oldData   = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/projetos/form.php';
    }

    public function store(): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $nome      = Security::sanitize($_POST['nome']      ?? '');
        $descricao = Security::sanitize($_POST['descricao'] ?? '');
        $errors    = [];

        if ($nome === '') $errors['nome'] = 'Nome é obrigatório.';
        if (strlen($nome) > 150) $errors['nome'] = 'Nome muito longo (máx. 150 caracteres).';

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
            "INSERT INTO projetos (nome, descricao, logo, status, criado_em)
             VALUES (?, ?, ?, 'ativo', NOW())"
        );
        $stmt->execute([$nome, $descricao ?: null, $logo]);
        $id = $db->lastInsertId();

        Security::auditLog('cadastro', 'projetos', $id);
        $_SESSION['flash_success'] = "Projeto \"$nome\" criado com sucesso.";
        header('Location: ' . APP_URL . '/admin/projetos');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireRole('super_admin');
        $db      = Database::getInstance();
        $stmt    = $db->prepare("SELECT * FROM projetos WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        $projeto = $stmt->fetch();

        if (!$projeto) {
            $_SESSION['flash_error'] = 'Projeto não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/projetos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM projetos WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        $projeto = $stmt->fetch();

        if (!$projeto) {
            $_SESSION['flash_error'] = 'Projeto não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }

        $nome      = Security::sanitize($_POST['nome']      ?? '');
        $descricao = Security::sanitize($_POST['descricao'] ?? '');
        $errors    = [];

        if ($nome === '') $errors['nome'] = 'Nome é obrigatório.';
        if (strlen($nome) > 150) $errors['nome'] = 'Nome muito longo.';

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

        $stmt = $db->prepare(
            "UPDATE projetos SET nome = ?, descricao = ?, logo = ? WHERE id = ?"
        );
        $stmt->execute([$nome, $descricao ?: null, $logo, (int) $id]);

        Security::auditLog('edicao', 'projetos', $id);
        $_SESSION['flash_success'] = "Projeto \"$nome\" atualizado.";
        header('Location: ' . APP_URL . '/admin/projetos');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE projetos SET status = 'inativo' WHERE id = ?");
        $stmt->execute([(int) $id]);

        Security::auditLog('exclusao', 'projetos', $id);
        $_SESSION['flash_success'] = 'Projeto inativado com sucesso.';
        header('Location: ' . APP_URL . '/admin/projetos');
        exit;
    }
}
