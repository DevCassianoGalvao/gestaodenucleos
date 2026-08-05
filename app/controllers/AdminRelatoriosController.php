<?php

/**
 * Área de relatórios (Etapa 25). Arquitetura reaproveitável: os relatórios
 * de Aulas e Check-ins já existem como telas dedicadas (/admin/aulas,
 * /admin/checkins) — aqui só linkamos pra eles em vez de duplicar. O que é
 * genuinamente novo é o Relatório de Frequência (por núcleo/aluno/período)
 * e a consolidação de Inscritos por Instituto/Projeto/Núcleo (Etapa 22).
 */
class AdminRelatoriosController
{
    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('relatorios.visualizar');
        require_once ROOT_PATH . '/app/views/admin/relatorios/index.php';
    }

    public function inscritos(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('relatorios.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'n.id');

        // Consolidado por Instituto → Projeto → Núcleo
        $stmt = $db->prepare("
            SELECT i.id AS instituto_id, i.nome AS instituto_nome,
                   p.id AS projeto_id, p.nome AS projeto_nome,
                   n.id AS nucleo_id, n.nome AS nucleo_nome,
                   COUNT(a.id) AS total_inscritos
            FROM nucleos n
            JOIN projetos p ON p.id = n.projeto_id
            JOIN institutos i ON i.id = p.instituto_id
            LEFT JOIN alunos a ON a.nucleo_id = n.id AND a.status = 'ativo'
            WHERE $escopoWhere
            GROUP BY i.id, i.nome, p.id, p.nome, n.id, n.nome
            ORDER BY i.nome, p.nome, n.nome
        ");
        $stmt->execute($escopoParams);
        $linhas = $stmt->fetchAll();

        $totalGeral = array_sum(array_column($linhas, 'total_inscritos'));

        $data = compact('linhas', 'totalGeral');
        require_once ROOT_PATH . '/app/views/admin/relatorios/inscritos.php';
    }

    public function frequencia(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('relatorios.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'n.id');

        $nucleoId   = (int) ($_GET['nucleo_id'] ?? 0);
        $dataInicio = Security::sanitize($_GET['data_inicio'] ?? date('Y-m-01'));
        $dataFim    = Security::sanitize($_GET['data_fim']    ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) $dataInicio = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim))    $dataFim    = date('Y-m-d');

        $conditions = [$escopoWhere, 'c.data_aula BETWEEN ? AND ?'];
        $params     = array_merge($escopoParams, [$dataInicio, $dataFim]);
        if ($nucleoId) { $conditions[] = 'n.id = ?'; $params[] = $nucleoId; }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $db->prepare("
            SELECT a.id AS aluno_id, a.nome AS aluno_nome, n.nome AS nucleo_nome, p.nome AS projeto_nome,
                   COUNT(cp.id) AS total_chamadas,
                   SUM(cp.presente) AS total_presencas,
                   ROUND(AVG(cp.presente) * 100) AS pct_frequencia
            FROM chamada_presencas cp
            JOIN chamadas c ON c.id = cp.chamada_id
            JOIN nucleos n ON n.id = c.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            JOIN alunos a ON a.id = cp.aluno_id
            $where
            GROUP BY a.id, a.nome, n.nome, p.nome
            ORDER BY pct_frequencia ASC, a.nome ASC
        ");
        $stmt->execute($params);
        $linhas = $stmt->fetchAll();

        $nucleos = [];
        if ($permitidos) {
            [$w, $p] = Escopo::whereIn($permitidos, 'id');
            $nucleos = $db->prepare("SELECT id, nome FROM nucleos WHERE $w ORDER BY nome");
            $nucleos->execute($p);
            $nucleos = $nucleos->fetchAll();
        }

        if (($_GET['export'] ?? '') === 'csv') {
            Permissao::requer('relatorios.exportar');
            Security::auditLog('exportacao', 'relatorio_frequencia');
            $this->exportarCsv($linhas);
        }

        $data = compact('linhas', 'nucleos', 'nucleoId', 'dataInicio', 'dataFim');
        require_once ROOT_PATH . '/app/views/admin/relatorios/frequencia.php';
    }

    private function exportarCsv(array $linhas): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_frequencia_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM p/ abrir acentuado corretamente no Excel
        fputcsv($out, ['Aluno', 'Núcleo', 'Projeto', 'Total de chamadas', 'Presenças', '% Frequência']);
        foreach ($linhas as $l) {
            fputcsv($out, [
                $l['aluno_nome'], $l['nucleo_nome'], $l['projeto_nome'],
                $l['total_chamadas'], $l['total_presencas'], $l['pct_frequencia'] . '%',
            ]);
        }
        fclose($out);
        exit;
    }
}
