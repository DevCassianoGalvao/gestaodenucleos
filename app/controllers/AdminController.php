<?php

class AdminController
{
    public function dashboard(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('dashboard.visualizar');
        $db = Database::getInstance();

        $nucleoIds  = Escopo::nucleosPermitidos(Auth::id());
        $projetoIds = Escopo::projetosPermitidos(Auth::id());

        // Apenas opções para os filtros — dados vêm via JS/API (que também aplica escopo)
        $projetos = [];
        if ($projetoIds) {
            [$w, $p] = Escopo::whereIn($projetoIds, 'id');
            $projetos = $db->prepare("SELECT id, nome FROM projetos WHERE status='ativo' AND $w ORDER BY nome ASC");
            $projetos->execute($p);
            $projetos = $projetos->fetchAll();
        }

        $municipios = [];
        if ($nucleoIds) {
            [$w, $p] = Escopo::whereIn($nucleoIds, 'n.id');
            $municipios = $db->prepare("SELECT DISTINCT n.municipio, n.projeto_id FROM nucleos n WHERE n.status='ativo' AND $w ORDER BY n.municipio ASC");
            $municipios->execute($p);
            $municipios = $municipios->fetchAll();
        }

        $data = compact('projetos', 'municipios');
        require_once ROOT_PATH . '/app/views/admin/dashboard.php';
    }

    // ── Legacy dashboard kept for reference — remove after Phase 8 stable ─────
    private function _legacyDashboard(): void
    {
        $db = Database::getInstance();

        // ── Stat cards ────────────────────────────────────────────────────────
        $stats = [
            'total_alunos' => (int) $db->query(
                "SELECT COUNT(*) FROM alunos WHERE status = 'ativo'"
            )->fetchColumn(),

            'total_nucleos' => (int) $db->query(
                "SELECT COUNT(*) FROM nucleos WHERE status = 'ativo'"
            )->fetchColumn(),

            'chamadas_mes' => (int) $db->query(
                "SELECT COUNT(*) FROM chamadas
                 WHERE data_aula >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            )->fetchColumn(),

            'professores_inativos' => (int) $db->query(
                "SELECT COUNT(DISTINCT np.usuario_id)
                 FROM nucleo_professores np
                 WHERE NOT EXISTS (
                     SELECT 1 FROM chamadas c
                     WHERE c.professor_id = np.usuario_id
                       AND c.data_aula > DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                 )"
            )->fetchColumn(),
        ];

        // ── Núcleos com saúde de frequência ──────────────────────────────────
        $stmt = $db->prepare("
            SELECT
                n.id,
                n.nome,
                n.municipio,
                p.nome   AS projeto,
                COUNT(DISTINCT a.id) AS total_alunos,

                (SELECT ROUND(AVG(cp.presente) * 100)
                 FROM chamadas c
                 JOIN chamada_presencas cp ON cp.chamada_id = c.id
                 WHERE c.nucleo_id = n.id
                   AND c.data_aula >= DATE_FORMAT(NOW(), '%Y-%m-01')
                ) AS freq_mes,

                (SELECT ROUND(AVG(cp.presente) * 100)
                 FROM chamadas c
                 JOIN chamada_presencas cp ON cp.chamada_id = c.id
                 WHERE c.nucleo_id = n.id
                   AND c.data_aula >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01')
                   AND c.data_aula <  DATE_FORMAT(NOW(), '%Y-%m-01')
                ) AS freq_mes_anterior,

                (SELECT MAX(c.data_aula)
                 FROM chamadas c
                 WHERE c.nucleo_id = n.id
                ) AS ultima_chamada

            FROM nucleos n
            JOIN projetos p ON p.id = n.projeto_id
            LEFT JOIN alunos a ON a.nucleo_id = n.id AND a.status = 'ativo'
            WHERE n.status = 'ativo'
            GROUP BY n.id, n.nome, n.municipio, p.nome
            ORDER BY
                CASE WHEN freq_mes IS NULL THEN 1 ELSE 0 END,
                freq_mes DESC
        ");
        $stmt->execute();
        $nucleos = $stmt->fetchAll();

        // ── Aniversariantes do mês ────────────────────────────────────────────
        $stmt = $db->prepare("
            SELECT
                a.nome,
                a.data_nascimento,
                a.foto,
                n.nome AS nucleo
            FROM alunos a
            JOIN nucleos n ON n.id = a.nucleo_id
            WHERE MONTH(a.data_nascimento) = MONTH(CURDATE())
              AND a.status = 'ativo'
            ORDER BY DAY(a.data_nascimento)
            LIMIT 8
        ");
        $stmt->execute();
        $aniversariantes = $stmt->fetchAll();

        // ── Professores inativos (detalhe) ────────────────────────────────────
        $stmt = $db->prepare("
            SELECT
                u.id, u.nome, u.email, u.foto,
                n.nome AS nucleo,
                MAX(c.data_aula) AS ultima_chamada
            FROM usuarios u
            JOIN nucleo_professores np ON np.usuario_id = u.id
            JOIN nucleos n ON n.id = np.nucleo_id
            LEFT JOIN chamadas c ON c.professor_id = u.id
            WHERE u.perfil = 'professor' AND u.status = 'ativo'
            GROUP BY u.id, u.nome, u.email, u.foto, n.nome
            HAVING ultima_chamada IS NULL
                OR ultima_chamada < DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            ORDER BY ultima_chamada ASC
            LIMIT 5
        ");
        $stmt->execute();
        $professoresInativos = $stmt->fetchAll();

        $data = compact('stats', 'nucleos', 'aniversariantes', 'professoresInativos');
    }
}
