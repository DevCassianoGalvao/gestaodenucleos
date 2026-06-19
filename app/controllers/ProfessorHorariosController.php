<?php

class ProfessorHorariosController
{
    private const DIAS = ['Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'];

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

        $stmt = $db->prepare(
            "SELECT * FROM grade_horarios WHERE nucleo_id = ? ORDER BY dia_semana ASC, horario_inicio ASC"
        );
        $stmt->execute([$nucleoId]);
        $slots = $stmt->fetchAll();

        // Group by day
        $grade = [];
        foreach ($slots as $s) {
            $grade[$s['dia_semana']][] = $s;
        }

        $dias = self::DIAS;
        $data = compact('grade', 'dias', 'nucleoId');
        require_once ROOT_PATH . '/app/views/professor/horarios/index.php';
    }

    public function save(): void
    {
        Auth::requireRole('professor');
        Security::verifyCsrf();
        $nucleoId = $this->assertNucleo();

        $dias    = $_POST['dia']    ?? [];
        $incios  = $_POST['inicio'] ?? [];
        $fins    = $_POST['fim']    ?? [];

        $slots = [];
        foreach ($dias as $i => $dia) {
            $d = (int) $dia;
            $s = Security::sanitize($incios[$i] ?? '');
            $f = Security::sanitize($fins[$i]   ?? '');

            if (!isset(self::DIAS[$d])) continue;
            if (!preg_match('/^\d{2}:\d{2}$/', $s)) continue;
            if (!preg_match('/^\d{2}:\d{2}$/', $f)) continue;
            if ($f <= $s) continue;

            $slots[] = [$d, $s . ':00', $f . ':00'];
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM grade_horarios WHERE nucleo_id = ?")->execute([$nucleoId]);

            $ins = $db->prepare(
                "INSERT INTO grade_horarios (nucleo_id, dia_semana, horario_inicio, horario_fim) VALUES (?, ?, ?, ?)"
            );
            foreach ($slots as [$dia, $inicio, $fim]) {
                $ins->execute([$nucleoId, $dia, $inicio, $fim]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[Horarios] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao salvar horários.';
            header('Location: ' . APP_URL . '/professor/horarios');
            exit;
        }

        Security::auditLog('edicao', 'grade_horarios', $nucleoId);
        $_SESSION['flash_success'] = 'Grade de horários atualizada.';
        header('Location: ' . APP_URL . '/professor/horarios');
        exit;
    }
}
