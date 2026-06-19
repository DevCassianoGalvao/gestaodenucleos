<?php

class AdminMonitorController
{
    public function index(): void
    {
        Auth::requireRole('super_admin');
        $db = Database::getInstance();

        $q    = Security::sanitize($_GET['q'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * 20;

        $where  = $q ? "AND (u.nome LIKE ? OR n.nome LIKE ?)" : '';
        $params = $q ? ["%$q%", "%$q%"] : [];

        $stmt = $db->prepare("
            SELECT
                u.id, u.nome, u.email, u.foto, u.status,
                n.nome   AS nucleo,
                n.municipio,
                MAX(c.data_aula)    AS ultima_chamada,
                COUNT(CASE WHEN c.data_aula >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN 1 END) AS chamadas_mes,
                COUNT(c.id)         AS chamadas_total,
                DATEDIFF(CURDATE(), MAX(c.data_aula)) AS dias_sem_chamada
            FROM usuarios u
            JOIN nucleo_professores np ON np.usuario_id = u.id
            JOIN nucleos n ON n.id = np.nucleo_id
            LEFT JOIN chamadas c ON c.professor_id = u.id
            WHERE u.perfil = 'professor' $where
            GROUP BY u.id, u.nome, u.email, u.foto, u.status, n.nome, n.municipio
            ORDER BY ultima_chamada ASC NULLS FIRST
            LIMIT 20 OFFSET $off
        ");
        $stmt->execute($params);
        $professores = $stmt->fetchAll();

        // Totals for summary
        $resumo = $db->query("
            SELECT
                COUNT(DISTINCT u.id) AS total,
                SUM(CASE WHEN MAX(c.data_aula) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS ativos_7d,
                SUM(CASE WHEN MAX(c.data_aula) IS NULL OR MAX(c.data_aula) < DATE_SUB(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS inativos
            FROM usuarios u
            JOIN nucleo_professores np ON np.usuario_id = u.id
            LEFT JOIN chamadas c ON c.professor_id = u.id
            WHERE u.perfil = 'professor' AND u.status = 'ativo'
            GROUP BY u.id
        ")->fetchAll();

        $totalProf   = count($resumo);
        $ativos7d    = count(array_filter($resumo, fn($r) => $r['ativos_7d'] > 0));
        $inativos14d = count(array_filter($resumo, fn($r) => $r['inativos'] > 0));

        $data = compact('professores', 'q', 'page', 'totalProf', 'ativos7d', 'inativos14d');
        require_once ROOT_PATH . '/app/views/admin/monitor.php';
    }
}
