<?php

class AdminNucleosController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireRole('super_admin');
        $db = Database::getInstance();

        $q         = Security::sanitize($_GET['q']          ?? '');
        $projetoId = (int) ($_GET['projeto_id'] ?? 0);
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $off       = ($page - 1) * self::PER_PAGE;

        $conditions = [];
        $params     = [];

        if ($q) {
            $conditions[] = '(n.nome LIKE ? OR n.municipio LIKE ?)';
            $params[]     = "%$q%";
            $params[]     = "%$q%";
        }
        if ($projetoId) {
            $conditions[] = 'n.projeto_id = ?';
            $params[]     = $projetoId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM nucleos n $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT n.*, p.nome AS projeto,
                   COUNT(DISTINCT a.id) AS total_alunos,
                   COUNT(DISTINCT np.usuario_id) AS total_professores
            FROM nucleos n
            JOIN projetos p ON p.id = n.projeto_id
            LEFT JOIN alunos a ON a.nucleo_id = n.id AND a.status = 'ativo'
            LEFT JOIN nucleo_professores np ON np.nucleo_id = n.id
            $where
            GROUP BY n.id
            ORDER BY p.nome ASC, n.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $nucleos = $stmt->fetchAll();

        $projetos   = $db->query("SELECT id, nome FROM projetos WHERE status='ativo' ORDER BY nome")->fetchAll();
        $totalPages = (int) ceil($total / self::PER_PAGE);

        $data = compact('nucleos', 'projetos', 'q', 'projetoId', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/nucleos/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireRole('super_admin');
        $db       = Database::getInstance();
        $projetos = $db->query("SELECT id, nome FROM projetos WHERE status='ativo' ORDER BY nome")->fetchAll();
        $nucleo   = null;
        $errors   = $_SESSION['form_errors'] ?? [];
        $oldData  = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/nucleos/form.php';
    }

    public function store(): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $projetoId = (int) ($_POST['projeto_id'] ?? 0);
        $nome      = Security::sanitize($_POST['nome']      ?? '');
        $municipio = Security::sanitize($_POST['municipio'] ?? '');
        $estado    = strtoupper(Security::sanitize($_POST['estado'] ?? 'RJ'));
        $errors    = [];

        if (!$projetoId) $errors['projeto_id'] = 'Selecione um projeto.';
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
        Auth::requireRole('super_admin');
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM nucleos WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        $nucleo = $stmt->fetch();

        if (!$nucleo) {
            $_SESSION['flash_error'] = 'Núcleo não encontrado.';
            header('Location: ' . APP_URL . '/admin/nucleos');
            exit;
        }

        $projetos = $db->query("SELECT id, nome FROM projetos WHERE status='ativo' ORDER BY nome")->fetchAll();
        $errors   = $_SESSION['form_errors'] ?? [];
        $oldData  = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/nucleos/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM nucleos WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
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

        if (!$projetoId) $errors['projeto_id'] = 'Selecione um projeto.';
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
        $stmt->execute([$projetoId, $nome, $municipio, $estado, $lat, $lng, (int) $id]);

        Security::auditLog('edicao', 'nucleos', $id);
        $_SESSION['flash_success'] = "Núcleo \"$nome\" atualizado.";
        header('Location: ' . APP_URL . '/admin/nucleos');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE nucleos SET status='inativo' WHERE id=?");
        $stmt->execute([(int) $id]);

        Security::auditLog('exclusao', 'nucleos', $id);
        $_SESSION['flash_success'] = 'Núcleo inativado com sucesso.';
        header('Location: ' . APP_URL . '/admin/nucleos');
        exit;
    }
}
