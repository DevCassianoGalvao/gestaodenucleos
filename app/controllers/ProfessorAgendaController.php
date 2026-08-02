<?php

class ProfessorAgendaController
{
    public function index(): void
    {
        Auth::requireRole('professor');
        $db          = Database::getInstance();
        $professorId = Auth::id();

        require_once ROOT_PATH . '/app/helpers/Cronograma.php';
        $hoje = date('Y-m-d');
        Cronograma::gerarOcorrenciasParaData($db, $hoje, $professorId);
        Cronograma::atualizarPendencias($db, $professorId);

        // Hoje: ocorrências reais (com status) do dia
        $stmt = $db->prepare("
            SELECT ap.*, n.nome AS nucleo_nome
            FROM aulas_previstas ap
            JOIN nucleos n ON n.id = ap.nucleo_id
            WHERE ap.professor_id = ? AND ap.data = ?
            ORDER BY ap.horario_inicio ASC
        ");
        $stmt->execute([$professorId, $hoje]);
        $hojeAulas = $stmt->fetchAll();

        // Próximos 7 dias: recorrência da grade ativa (visão de planejamento da semana)
        $stmt = $db->prepare("
            SELECT gh.*, n.nome AS nucleo_nome
            FROM grade_horarios gh
            JOIN nucleos n ON n.id = gh.nucleo_id
            WHERE gh.professor_id = ? AND gh.status = 'ativo'
            ORDER BY gh.dia_semana ASC, gh.horario_inicio ASC
        ");
        $stmt->execute([$professorId]);
        $slots = $stmt->fetchAll();

        $semana = [];
        foreach ($slots as $s) {
            $semana[(int) $s['dia_semana']][] = $s;
        }

        $dias = Cronograma::DIAS;
        $diaSemanaHoje = (int) date('w');

        $data = compact('hojeAulas', 'semana', 'dias', 'diaSemanaHoje');
        require_once ROOT_PATH . '/app/views/professor/agenda/index.php';
    }
}
