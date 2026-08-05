<?php

/**
 * Atividades diárias — o professor registra o que aconteceu na aula
 * (Etapa 12). O checklist do projeto (Etapa 13, configurado pelo admin em
 * /admin/projetos/{id}/requisitos) aparece como guia informativo — nunca
 * bloqueia o registro, já que as exigências reais ainda não foram definidas
 * pelos responsáveis de cada projeto.
 */
class ProfessorAtividadesController
{
    private const PER_PAGE = 20;

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

    public function index(): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * self::PER_PAGE;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM atividades WHERE nucleo_id = ? AND professor_id = ?");
        $countStmt->execute([$nucleoId, Auth::id()]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT a.*, COUNT(ae.id) AS total_evidencias
            FROM atividades a
            LEFT JOIN atividade_evidencias ae ON ae.atividade_id = a.id
            WHERE a.nucleo_id = ? AND a.professor_id = ?
            GROUP BY a.id
            ORDER BY a.data DESC, a.criado_em DESC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute([$nucleoId, Auth::id()]);
        $atividades = $stmt->fetchAll();

        $totalPages = (int) ceil($total / self::PER_PAGE);
        $data = compact('atividades', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/professor/atividades/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $stmt = $db->prepare("
            SELECT pr.* FROM projeto_requisitos pr
            JOIN nucleos n ON n.projeto_id = pr.projeto_id
            WHERE n.id = ? AND pr.status = 'ativo'
            ORDER BY pr.ordem ASC
        ");
        $stmt->execute([$nucleoId]);
        $requisitos = $stmt->fetchAll();

        $dataHoje = date('Y-m-d');
        $data = compact('requisitos', 'dataHoje');
        require_once ROOT_PATH . '/app/views/professor/atividades/nova.php';
    }

    public function store(): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();
        $db       = Database::getInstance();

        $dataAtiv   = Security::sanitize($_POST['data'] ?? '');
        $horario    = Security::sanitize($_POST['horario'] ?? '');
        $descricao  = Security::sanitize($_POST['descricao'] ?? '');
        $observacoes = Security::sanitize($_POST['observacoes'] ?? '');

        $errors = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAtiv)) $errors['data'] = 'Data inválida.';
        if ($dataAtiv > date('Y-m-d')) $errors['data'] = 'Não é possível registrar atividade futura.';
        if (mb_strlen($descricao) < 5) $errors['descricao'] = 'Descreva o que aconteceu (mínimo 5 caracteres).';
        if ($horario !== '' && !preg_match('/^\d{2}:\d{2}$/', $horario)) $errors['horario'] = 'Horário inválido.';

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/professor/atividades/nova');
            exit;
        }

        $arquivos = [];
        if (!empty($_FILES['evidencias']['name'][0])) {
            require_once ROOT_PATH . '/app/helpers/Upload.php';
            $count = count($_FILES['evidencias']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['evidencias']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file = [
                    'name'     => $_FILES['evidencias']['name'][$i],
                    'type'     => $_FILES['evidencias']['type'][$i],
                    'tmp_name' => $_FILES['evidencias']['tmp_name'][$i],
                    'error'    => $_FILES['evidencias']['error'][$i],
                    'size'     => $_FILES['evidencias']['size'][$i],
                ];
                try {
                    $arquivos[] = Upload::image($file, 'evidencias', 1600, 1600, 85);
                } catch (RuntimeException $e) {
                    $_SESSION['flash_error'] = 'Evidência: ' . $e->getMessage();
                    header('Location: ' . APP_URL . '/professor/atividades/nova');
                    exit;
                }
            }
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO atividades (nucleo_id, professor_id, data, horario, descricao, observacoes, status, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, 'concluida', NOW())"
            );
            $stmt->execute([
                $nucleoId, Auth::id(), $dataAtiv, $horario !== '' ? $horario . ':00' : null,
                $descricao, $observacoes ?: null,
            ]);
            $atividadeId = (int) $db->lastInsertId();

            if ($arquivos) {
                $evStmt = $db->prepare("INSERT INTO atividade_evidencias (atividade_id, tipo, arquivo_path, enviado_por, criado_em) VALUES (?, 'foto', ?, ?, NOW())");
                foreach ($arquivos as $path) {
                    $evStmt->execute([$atividadeId, $path, Auth::id()]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[ProfessorAtividades] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao salvar atividade. Tente novamente.';
            header('Location: ' . APP_URL . '/professor/atividades/nova');
            exit;
        }

        Security::auditLog('cadastro', 'atividades', $atividadeId);
        $_SESSION['flash_success'] = 'Atividade registrada.';
        header('Location: ' . APP_URL . '/professor/atividades');
        exit;
    }

    public function show(string $id): void
    {
        Auth::requireRole('professor');
        $nucleoId = $this->assertNucleo();
        $id       = (int) $id;
        $db       = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM atividades WHERE id = ? AND nucleo_id = ? AND professor_id = ? LIMIT 1");
        $stmt->execute([$id, $nucleoId, Auth::id()]);
        $atividade = $stmt->fetch();

        if (!$atividade) {
            $_SESSION['flash_error'] = 'Atividade não encontrada.';
            header('Location: ' . APP_URL . '/professor/atividades');
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM atividade_evidencias WHERE atividade_id = ? ORDER BY criado_em DESC");
        $stmt->execute([$id]);
        $evidencias = $stmt->fetchAll();

        $data = compact('atividade', 'evidencias');
        require_once ROOT_PATH . '/app/views/professor/atividades/show.php';
    }
}
