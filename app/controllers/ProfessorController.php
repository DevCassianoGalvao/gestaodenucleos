<?php

class ProfessorController
{
    public function dashboard(): void
    {
        Auth::requireRole('professor');
        $db        = Database::getInstance();
        $profId    = Auth::id();

        // Núcleo(s) do professor — usa o primeiro para o dashboard
        $stmt = $db->prepare("
            SELECT n.id, n.nome, n.municipio, p.nome AS projeto
            FROM nucleo_professores np
            JOIN nucleos n ON n.id = np.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            WHERE np.usuario_id = ?
              AND n.status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$profId]);
        $nucleo = $stmt->fetch();

        $stats = ['total_alunos' => 0, 'presentes_ultima' => 0, 'alunos_faltosos' => 0];
        $ultimaChamada      = null;
        $aniversariantes    = [];

        if ($nucleo) {
            $nucleoId = $nucleo['id'];

            $stats['total_alunos'] = (int) $db->prepare(
                "SELECT COUNT(*) FROM alunos WHERE nucleo_id = ? AND status = 'ativo'"
            )->execute([$nucleoId]) ? $db->query(
                "SELECT COUNT(*) FROM alunos WHERE nucleo_id = $nucleoId AND status = 'ativo'"
            )->fetchColumn() : 0;

            // Última chamada
            $stmt = $db->prepare(
                "SELECT id, data_aula FROM chamadas WHERE nucleo_id = ? ORDER BY data_aula DESC LIMIT 1"
            );
            $stmt->execute([$nucleoId]);
            $ultimaChamada = $stmt->fetch();

            if ($ultimaChamada) {
                $stats['presentes_ultima'] = (int) $db->prepare(
                    "SELECT COUNT(*) FROM chamada_presencas WHERE chamada_id = ? AND presente = 1"
                )->execute([$ultimaChamada['id']]) ? $db->query(
                    "SELECT COUNT(*) FROM chamada_presencas WHERE chamada_id = {$ultimaChamada['id']} AND presente = 1"
                )->fetchColumn() : 0;
            }

            // Alunos com mais de 3 faltas consecutivas
            $stmt = $db->prepare("
                SELECT a.nome, COUNT(cp.id) AS faltas
                FROM alunos a
                JOIN chamada_presencas cp ON cp.aluno_id = a.id
                JOIN chamadas c ON c.id = cp.chamada_id
                WHERE a.nucleo_id = ? AND cp.presente = 0
                  AND c.data_aula >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY a.id, a.nome
                HAVING faltas >= 3
                ORDER BY faltas DESC
                LIMIT 5
            ");
            $stmt->execute([$nucleoId]);
            $alunosFaltosos = $stmt->fetchAll();
            $stats['alunos_faltosos'] = count($alunosFaltosos);

            // Aniversariantes
            $stmt = $db->prepare("
                SELECT nome, data_nascimento, foto
                FROM alunos
                WHERE nucleo_id = ?
                  AND MONTH(data_nascimento) = MONTH(CURDATE())
                  AND status = 'ativo'
                ORDER BY DAY(data_nascimento)
            ");
            $stmt->execute([$nucleoId]);
            $aniversariantes = $stmt->fetchAll();
        }

        $data = compact('nucleo', 'stats', 'ultimaChamada', 'aniversariantes');
        require_once ROOT_PATH . '/app/views/professor/dashboard.php';
    }
}
