<?php

class AdminCheckinsController
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        Auth::requireRole('super_admin');
        $db = Database::getInstance();

        $q          = Security::sanitize($_GET['q']         ?? '');
        $nucleoId   = (int) ($_GET['nucleo_id']              ?? 0);
        $statusFilt = Security::sanitize($_GET['status']     ?? '');
        $dataInicio = Security::sanitize($_GET['data_inicio']?? '');
        $dataFim    = Security::sanitize($_GET['data_fim']   ?? '');
        $page       = max(1, (int) ($_GET['page']            ?? 1));
        $off        = ($page - 1) * self::PER_PAGE;

        $conditions = [];
        $params     = [];

        if ($q) {
            $conditions[] = 'u.nome LIKE ?';
            $params[]     = "%$q%";
        }
        if ($nucleoId) {
            $conditions[] = 'c.nucleo_id = ?';
            $params[]     = $nucleoId;
        }
        if ($statusFilt && in_array($statusFilt, ['dentro_raio','fora_raio','sem_coordenadas'], true)) {
            $conditions[] = 'c.status = ?';
            $params[]     = $statusFilt;
        }
        if ($dataInicio) {
            $conditions[] = 'DATE(c.criado_em) >= ?';
            $params[]     = $dataInicio;
        }
        if ($dataFim) {
            $conditions[] = 'DATE(c.criado_em) <= ?';
            $params[]     = $dataFim;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $db->prepare("
            SELECT COUNT(*) FROM checkins c
            JOIN usuarios u ON u.id = c.professor_id
            $where
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT c.id, c.latitude, c.longitude, c.endereco, c.distancia_m, c.status, c.criado_em,
                   u.nome AS professor_nome,
                   n.nome AS nucleo_nome,
                   p.nome AS projeto_nome
            FROM checkins c
            JOIN usuarios u ON u.id = c.professor_id
            JOIN nucleos  n ON n.id = c.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            $where
            ORDER BY c.criado_em DESC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $checkins = $stmt->fetchAll();

        $nucleos    = $db->query("SELECT id, nome FROM nucleos WHERE status='ativo' ORDER BY nome")->fetchAll();
        $totalPages = (int) ceil($total / self::PER_PAGE);

        $data = compact('checkins','nucleos','q','nucleoId','statusFilt','dataInicio','dataFim','page','total','totalPages');
        require_once ROOT_PATH . '/app/views/admin/checkins/index.php';
    }
}
