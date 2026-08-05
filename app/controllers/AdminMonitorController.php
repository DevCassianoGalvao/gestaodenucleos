<?php

class AdminMonitorController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('monitoramento.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'n.id');

        $q           = Security::sanitize($_GET['q'] ?? '');
        $institutoId = (int) ($_GET['instituto_id'] ?? 0);
        $projetoId   = (int) ($_GET['projeto_id']   ?? 0);
        $nucleoId    = (int) ($_GET['nucleo_id']    ?? 0);
        $page        = max(1, (int) ($_GET['page'] ?? 1));
        $off         = ($page - 1) * self::PER_PAGE;

        $conditions = [$escopoWhere];
        $params     = $escopoParams;

        if ($q)           { $conditions[] = '(u.nome LIKE ? OR n.nome LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($institutoId) { $conditions[] = 'i.id = ?'; $params[] = $institutoId; }
        if ($projetoId)   { $conditions[] = 'p.id = ?'; $params[] = $projetoId; }
        if ($nucleoId)    { $conditions[] = 'n.id = ?'; $params[] = $nucleoId; }

        $where = 'WHERE u.perfil = \'professor\' AND ' . implode(' AND ', $conditions);

        $stmt = $db->prepare("
            SELECT
                u.id, u.nome, u.email, u.foto, u.status,
                n.nome AS nucleo, n.municipio, p.nome AS projeto, i.nome AS instituto,
                MAX(c.data_aula)    AS ultima_chamada,
                COUNT(CASE WHEN c.data_aula >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN 1 END) AS chamadas_mes,
                COUNT(c.id)         AS chamadas_total,
                DATEDIFF(CURDATE(), MAX(c.data_aula)) AS dias_sem_chamada
            FROM usuarios u
            JOIN nucleo_professores np ON np.usuario_id = u.id
            JOIN nucleos n ON n.id = np.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            JOIN institutos i ON i.id = p.instituto_id
            LEFT JOIN chamadas c ON c.professor_id = u.id AND c.nucleo_id = n.id
            $where
            GROUP BY u.id, u.nome, u.email, u.foto, u.status, n.nome, n.municipio, p.nome, i.nome
            ORDER BY
                CASE WHEN MAX(c.data_aula) IS NULL THEN 0 ELSE 1 END ASC,
                MAX(c.data_aula) ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $professores = $stmt->fetchAll();

        $resumoStmt = $db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(ultima_chamada >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS ativos_7d,
                SUM(ultima_chamada IS NULL OR ultima_chamada < DATE_SUB(CURDATE(), INTERVAL 14 DAY)) AS inativos
            FROM (
                SELECT u.id, MAX(c.data_aula) AS ultima_chamada
                FROM usuarios u
                JOIN nucleo_professores np ON np.usuario_id = u.id
                JOIN nucleos n ON n.id = np.nucleo_id
                LEFT JOIN chamadas c ON c.professor_id = u.id
                WHERE u.perfil = 'professor' AND u.status = 'ativo' AND $escopoWhere
                GROUP BY u.id
            ) atividade
        ");
        $resumoStmt->execute($escopoParams);
        $resumo = $resumoStmt->fetch();

        $totalProf   = (int) ($resumo['total'] ?? 0);
        $ativos7d    = (int) ($resumo['ativos_7d'] ?? 0);
        $inativos14d = (int) ($resumo['inativos'] ?? 0);

        $institutos = $this->listar($db, 'institutos', Escopo::institutosPermitidos(Auth::id()));
        $projetos   = $this->listarProjetos($db, Escopo::projetosPermitidos(Auth::id()));
        $nucleos    = $this->listarNucleos($db, $permitidos);

        $data = compact('professores', 'q', 'page', 'totalProf', 'ativos7d', 'inativos14d', 'institutos', 'projetos', 'nucleos', 'institutoId', 'projetoId', 'nucleoId');
        require_once ROOT_PATH . '/app/views/admin/monitor.php';
    }

    private function listar(PDO $db, string $tabela, array $ids): array
    {
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'id');
        $stmt = $db->prepare("SELECT id, nome FROM $tabela WHERE $where ORDER BY nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function listarProjetos(PDO $db, array $ids): array
    {
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'id');
        $stmt = $db->prepare("SELECT id, nome FROM projetos WHERE $where ORDER BY nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function listarNucleos(PDO $db, array $ids): array
    {
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'id');
        $stmt = $db->prepare("SELECT id, nome FROM nucleos WHERE $where ORDER BY nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
