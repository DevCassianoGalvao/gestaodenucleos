<?php

/**
 * Galeria consolidada de evidências (Etapa 14) — todas as fotos/documentos
 * enviados via Atividades (incluindo lançamentos retroativos), com o
 * contexto completo: núcleo, projeto, instituto, professor, data, quem
 * enviou. Escopado como tudo mais.
 */
class AdminEvidenciasController
{
    private const PER_PAGE = 24;

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('evidencias.visualizar');
        $db = Database::getInstance();

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'at.nucleo_id');

        $nucleoId = (int) ($_GET['nucleo_id'] ?? 0);
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $off      = ($page - 1) * self::PER_PAGE;

        $conditions = [$escopoWhere];
        $params     = $escopoParams;
        if ($nucleoId) { $conditions[] = 'at.nucleo_id = ?'; $params[] = $nucleoId; }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM atividade_evidencias ae JOIN atividades at ON at.id = ae.atividade_id $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT ae.*, at.data AS data_atividade, at.descricao AS atividade_descricao,
                   at.registrado_retroativamente, n.nome AS nucleo_nome, p.nome AS projeto_nome,
                   u.nome AS professor_nome
            FROM atividade_evidencias ae
            JOIN atividades at ON at.id = ae.atividade_id
            JOIN nucleos n ON n.id = at.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            JOIN usuarios u ON u.id = at.professor_id
            $where
            ORDER BY ae.criado_em DESC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $evidencias = $stmt->fetchAll();

        $nucleos = [];
        if ($permitidos) {
            [$w, $p] = Escopo::whereIn($permitidos, 'id');
            $nucleos = $db->prepare("SELECT id, nome FROM nucleos WHERE $w ORDER BY nome");
            $nucleos->execute($p);
            $nucleos = $nucleos->fetchAll();
        }

        $totalPages = (int) ceil($total / self::PER_PAGE);
        $data = compact('evidencias', 'nucleos', 'nucleoId', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/evidencias/index.php';
    }
}
