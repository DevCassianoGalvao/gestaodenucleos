<?php

class AdminNucleosController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('nucleos.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'n.id');

        $q         = Security::sanitize($_GET['q']          ?? '');
        $projetoId = (int) ($_GET['projeto_id'] ?? 0);
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $off       = ($page - 1) * self::PER_PAGE;

        $conditions = [$escopoWhere];
        $params     = $escopoParams;

        if ($q) {
            $conditions[] = '(n.nome LIKE ? OR n.municipio LIKE ?)';
            $params[]     = "%$q%";
            $params[]     = "%$q%";
        }
        if ($projetoId) {
            $conditions[] = 'n.projeto_id = ?';
            $params[]     = $projetoId;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM nucleos n $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT n.*, p.nome AS projeto, i.nome AS instituto,
                   COUNT(DISTINCT a.id) AS total_alunos,
                   COUNT(DISTINCT np.usuario_id) AS total_professores
            FROM nucleos n
            JOIN projetos p ON p.id = n.projeto_id
            JOIN institutos i ON i.id = p.instituto_id
            LEFT JOIN alunos a ON a.nucleo_id = n.id AND a.status = 'ativo'
            LEFT JOIN nucleo_professores np ON np.nucleo_id = n.id
            $where
            GROUP BY n.id
            ORDER BY p.nome ASC, n.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $nucleos = $stmt->fetchAll();

        $projetos   = $this->projetosDisponiveis($db);
        $totalPages = (int) ceil($total / self::PER_PAGE);

        $data = compact('nucleos', 'projetos', 'q', 'projetoId', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/nucleos/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('nucleos.editar');
        $db       = Database::getInstance();
        $projetos = $this->projetosDisponiveis($db);
        $nucleo   = null;
        $errors   = $_SESSION['form_errors'] ?? [];
        $oldData  = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/nucleos/form.php';
    }

    public function store(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('nucleos.editar');
        Security::verifyCsrf();

        $projetoId = (int) ($_POST['projeto_id'] ?? 0);
        $nome      = Security::sanitize($_POST['nome']      ?? '');
        $municipio = Security::sanitize($_POST['municipio'] ?? '');
        $estado    = strtoupper(Security::sanitize($_POST['estado'] ?? 'RJ'));
        $errors    = [];

        if (!$projetoId) {
            $errors['projeto_id'] = 'Selecione um projeto.';
        } elseif (!Escopo::podeAcessarProjeto(Auth::id(), $projetoId)) {
            $errors['projeto_id'] = 'Você não tem acesso a esse projeto.';
        }
        if (!$nome)      $errors['nome']       = 'Nome é obrigatório.';
        if (!$municipio) $errors['municipio']  = 'Município é obrigatório.';
        if (strlen($estado) !== 2) $errors['estado'] = 'UF inválida.';

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/nucleos/novo');
            exit;
        }

        $lat = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float) $_POST['latitude']  : null;
        $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null;

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO nucleos (projeto_id, nome, municipio, estado, latitude, longitude, status, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, 'ativo', NOW())"
        );
        $stmt->execute([$projetoId, $nome, $municipio, $estado, $lat, $lng]);
        $id = $db->lastInsertId();

        Security::auditLog('cadastro', 'nucleos', $id);
        $_SESSION['flash_success'] = "Núcleo \"$nome\" criado com sucesso.";
        header('Location: ' . APP_URL . '/admin/nucleos');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('nucleos.editar');
        $id = (int) $id;

        if (!Escopo::podeAcessarNucleo(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM nucleos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $nucleo = $stmt->fetch();

        if (!$nucleo) {
            $_SESSION['flash_error'] = 'Núcleo não encontrado.';
            header('Location: ' . APP_URL . '/admin/nucleos');
            exit;
        }

        $projetos = $this->projetosDisponiveis($db);
        $errors   = $_SESSION['form_errors'] ?? [];
        $oldData  = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/nucleos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('nucleos.editar');
        Security::verifyCsrf();
        $id = (int) $id;

        if (!Escopo::podeAcessarNucleo(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM nucleos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = 'Núcleo não encontrado.';
            header('Location: ' . APP_URL . '/admin/nucleos');
            exit;
        }

        $projetoId = (int) ($_POST['projeto_id'] ?? 0);
        $nome      = Security::sanitize($_POST['nome']      ?? '');
        $municipio = Security::sanitize($_POST['municipio'] ?? '');
        $estado    = strtoupper(Security::sanitize($_POST['estado'] ?? 'RJ'));
        $errors    = [];

        if (!$projetoId) {
            $errors['projeto_id'] = 'Selecione um projeto.';
        } elseif (!Escopo::podeAcessarProjeto(Auth::id(), $projetoId)) {
            $errors['projeto_id'] = 'Você não tem acesso a esse projeto.';
        }
        if (!$nome)      $errors['nome']       = 'Nome é obrigatório.';
        if (!$municipio) $errors['municipio']  = 'Município é obrigatório.';

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/nucleos/' . $id . '/editar');
            exit;
        }

        $lat = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float) $_POST['latitude']  : null;
        $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null;

        $stmt = $db->prepare(
            "UPDATE nucleos SET projeto_id=?, nome=?, municipio=?, estado=?, latitude=?, longitude=? WHERE id=?"
        );
        $stmt->execute([$projetoId, $nome, $municipio, $estado, $lat, $lng, $id]);

        Security::auditLog('edicao', 'nucleos', $id);
        $_SESSION['flash_success'] = "Núcleo \"$nome\" atualizado.";
        header('Location: ' . APP_URL . '/admin/nucleos');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('nucleos.excluir');
        Security::verifyCsrf();
        $id = (int) $id;

        if (!Escopo::podeAcessarNucleo(Auth::id(), $id)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE nucleos SET status='inativo' WHERE id=?");
        $stmt->execute([$id]);

        Security::auditLog('exclusao', 'nucleos', $id);
        $_SESSION['flash_success'] = 'Núcleo inativado com sucesso.';
        header('Location: ' . APP_URL . '/admin/nucleos');
        exit;
    }

    private function projetosDisponiveis(PDO $db): array
    {
        $ids = Escopo::projetosPermitidos(Auth::id());
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'p.id');
        $stmt = $db->prepare("SELECT p.id, p.nome, i.nome AS instituto FROM projetos p JOIN institutos i ON i.id=p.instituto_id WHERE p.status='ativo' AND $where ORDER BY i.nome, p.nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
