<?php

class JustificativaController
{
    public function pendentes(): void
    {
        Auth::requireRole('professor');
        $db          = Database::getInstance();
        $professorId = Auth::id();

        require_once ROOT_PATH . '/app/helpers/Cronograma.php';
        Cronograma::atualizarPendencias($db, $professorId);

        $pendentes = Cronograma::pendentesDoProfessor($db, $professorId);

        $stmt = $db->prepare("
            SELECT ap.data, ap.horario_inicio, ap.horario_fim, n.nome AS nucleo_nome,
                   ja.motivo, ja.enviado_em
            FROM justificativas_ausencia ja
            JOIN aulas_previstas ap ON ap.id = ja.aula_prevista_id
            JOIN nucleos n ON n.id = ap.nucleo_id
            WHERE ja.professor_id = ?
            ORDER BY ja.enviado_em DESC
            LIMIT 10
        ");
        $stmt->execute([$professorId]);
        $historico = $stmt->fetchAll();

        $errors  = $_SESSION['form_errors'] ?? [];
        $errorId = $_SESSION['form_error_id'] ?? null;
        unset($_SESSION['form_errors'], $_SESSION['form_error_id']);

        $data = compact('pendentes', 'historico', 'errors', 'errorId');
        require_once ROOT_PATH . '/app/views/professor/justificativas/index.php';
    }

    public function store(string $id): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $db          = Database::getInstance();
        $professorId = Auth::id();
        $id          = (int) $id;

        $stmt = $db->prepare(
            "SELECT * FROM aulas_previstas WHERE id = ? AND professor_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $professorId]);
        $aula = $stmt->fetch();

        if (!$aula) {
            // Não existe, ou pertence a outro professor — nunca revelar qual.
            $_SESSION['flash_error'] = 'Aula não encontrada.';
            header('Location: ' . APP_URL . '/professor/justificativas');
            exit;
        }

        if ($aula['status'] !== 'justificativa_pendente') {
            $_SESSION['flash_error'] = 'Esta aula não está com justificativa pendente.';
            header('Location: ' . APP_URL . '/professor/justificativas');
            exit;
        }

        $motivo = Security::sanitize($_POST['motivo'] ?? '');
        if (mb_strlen($motivo) < 5) {
            $_SESSION['form_errors']   = ['motivo' => 'Descreva o motivo (mínimo 5 caracteres).'];
            $_SESSION['form_error_id'] = $id;
            header('Location: ' . APP_URL . '/professor/justificativas');
            exit;
        }

        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO justificativas_ausencia (aula_prevista_id, professor_id, motivo, enviado_em)
                 VALUES (?, ?, ?, NOW())"
            )->execute([$id, $professorId, $motivo]);

            $db->prepare("UPDATE aulas_previstas SET status='justificada' WHERE id=?")->execute([$id]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[Justificativa] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao enviar justificativa. Tente novamente.';
            header('Location: ' . APP_URL . '/professor/justificativas');
            exit;
        }

        Security::auditLog('cadastro', 'justificativas_ausencia', $id);
        $_SESSION['flash_success'] = 'Justificativa enviada.';
        header('Location: ' . APP_URL . '/professor/justificativas');
        exit;
    }
}
