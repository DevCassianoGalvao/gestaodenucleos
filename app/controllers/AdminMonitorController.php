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
            LEFT JOIN chamadas c ON c.professor_id = u.id AND c.nucleo_id = n.id
            WHERE u.perfil = 'professor' $where
            GROUP BY u.id, u.nome, u.email, u.foto, u.status, n.nome, n.municipio
            ORDER BY ultima_chamada IS NOT NULL, ultima_chamada ASC
            LIMIT 20 OFFSET $off
        ");
        $stmt->execute($params);
        $professores = $stmt->fetchAll();

        // Totals for summary
        $resumo = $db->query("
            SELECT
                COUNT(*) AS total,
                SUM(ultima_chamada >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS ativos_7d,
                SUM(ultima_chamada IS NULL OR ultima_chamada < DATE_SUB(CURDATE(), INTERVAL 14 DAY)) AS inativos
            FROM (
                SELECT u.id, MAX(c.data_aula) AS ultima_chamada
                FROM usuarios u
                JOIN nucleo_professores np ON np.usuario_id = u.id
                LEFT JOIN chamadas c ON c.professor_id = u.id
                WHERE u.perfil = 'professor' AND u.status = 'ativo'
                GROUP BY u.id
            ) atividade
        ")->fetch();

        $totalProf   = (int) ($resumo['total'] ?? 0);
        $ativos7d    = (int) ($resumo['ativos_7d'] ?? 0);
        $inativos14d = (int) ($resumo['inativos'] ?? 0);

        $data = compact('professores', 'q', 'page', 'totalProf', 'ativos7d', 'inativos14d');
        require_once ROOT_PATH . '/app/views/admin/monitor.php';
    }
}
