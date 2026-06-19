<?php

class AlunoController
{
    public function dashboard(): void
    {
        Auth::requireRole('aluno');
        $db     = Database::getInstance();
        $userId = Auth::id();

        // Dados do aluno
        $stmt = $db->prepare("
            SELECT a.*, n.nome AS nucleo, p.nome AS projeto
            FROM alunos a
            JOIN nucleos n ON n.id = a.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            WHERE a.usuario_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $aluno = $stmt->fetch();

        $presencas      = [];
        $comunicados    = [];
        $proximasAulas  = [];

        if ($aluno) {
            $nucleoId = $aluno['nucleo_id'];

            // Últimas 10 presenças
            $stmt = $db->prepare("
                SELECT c.data_aula, cp.presente
                FROM chamada_presencas cp
                JOIN chamadas c ON c.id = cp.chamada_id
                WHERE cp.aluno_id = ?
                ORDER BY c.data_aula DESC
                LIMIT 10
            ");
            $stmt->execute([$aluno['id']]);
            $presencas = $stmt->fetchAll();

            // Próximas aulas (grade de horários)
            $stmt = $db->prepare("
                SELECT dia_semana, horario_inicio, horario_fim
                FROM grade_horarios
                WHERE nucleo_id = ?
                ORDER BY dia_semana, horario_inicio
            ");
            $stmt->execute([$nucleoId]);
            $proximasAulas = $stmt->fetchAll();
        }

        $data = compact('aluno', 'presencas', 'comunicados', 'proximasAulas');
        require_once ROOT_PATH . '/app/views/aluno/dashboard.php';
    }
}
