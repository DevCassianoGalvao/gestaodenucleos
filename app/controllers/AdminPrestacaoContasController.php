<?php

/**
 * Prestação de Contas (Etapa 26) — consolida automaticamente os dados já
 * registrados na operação (aulas, chamadas, atividades, evidências,
 * inscritos) por Instituto → Projeto → Termo de Fomento → Núcleo → Período.
 * O checklist oficial de exigências ainda não foi definido pelos
 * responsáveis (ver docs/PENDENCIAS_REGRAS_NEGOCIO.md) — esta tela entrega
 * o relatório consolidado com o que já existe; quando o checklist chegar,
 * ele se soma aqui via projeto_requisitos (já configurável).
 */
class AdminPrestacaoContasController
{
    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('prestacao_contas.visualizar');
        $db = Database::getInstance();

        $projetoIds = Escopo::projetosPermitidos(Auth::id());
        $termos = [];
        if ($projetoIds) {
            [$w, $p] = Escopo::whereIn($projetoIds, 't.projeto_id');
            $stmt = $db->prepare("
                SELECT t.*, p.nome AS projeto_nome, i.nome AS instituto_nome
                FROM termos_fomento t
                JOIN projetos p ON p.id = t.projeto_id
                JOIN institutos i ON i.id = p.instituto_id
                WHERE $w
                ORDER BY t.status = 'ativo' DESC, t.criado_em DESC
            ");
            $stmt->execute($p);
            $termos = $stmt->fetchAll();
        }

        $data = compact('termos');
        require_once ROOT_PATH . '/app/views/admin/prestacao_contas/index.php';
    }

    public function consolidado(string $termoId): void
    {
        Auth::requireAdminArea();
        Permissao::requer('prestacao_contas.visualizar');
        $termoId = (int) $termoId;
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT t.*, p.nome AS projeto_nome, p.id AS projeto_id, i.nome AS instituto_nome
            FROM termos_fomento t
            JOIN projetos p ON p.id = t.projeto_id
            JOIN institutos i ON i.id = p.instituto_id
            WHERE t.id = ? LIMIT 1
        ");
        $stmt->execute([$termoId]);
        $termo = $stmt->fetch();

        if (!$termo) {
            $_SESSION['flash_error'] = 'Termo de fomento não encontrado.';
            header('Location: ' . APP_URL . '/admin/prestacao-contas');
            exit;
        }
        if (!Escopo::podeAcessarProjeto(Auth::id(), (int) $termo['projeto_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $inicio = $termo['data_inicio'] ?: '2000-01-01';
        $fim    = $termo['data_fim']    ?: date('Y-m-d');
        if (isset($_GET['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio'])) $inicio = $_GET['data_inicio'];
        if (isset($_GET['data_fim'])    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim']))    $fim    = $_GET['data_fim'];

        // Núcleos do projeto, com consolidado de tudo que já existe no sistema.
        $stmt = $db->prepare("
            SELECT
                n.id, n.nome, n.municipio,
                (SELECT COUNT(*) FROM alunos a WHERE a.nucleo_id = n.id AND a.status='ativo') AS total_inscritos,
                (SELECT COUNT(*) FROM aulas_previstas ap WHERE ap.nucleo_id = n.id AND ap.data BETWEEN ? AND ? AND ap.status='prevista') AS aulas_previstas,
                (SELECT COUNT(*) FROM aulas_previstas ap WHERE ap.nucleo_id = n.id AND ap.data BETWEEN ? AND ? AND ap.status='realizada') AS aulas_realizadas,
                (SELECT COUNT(*) FROM aulas_previstas ap WHERE ap.nucleo_id = n.id AND ap.data BETWEEN ? AND ? AND ap.status='justificada') AS aulas_justificadas,
                (SELECT COUNT(*) FROM aulas_previstas ap WHERE ap.nucleo_id = n.id AND ap.data BETWEEN ? AND ? AND ap.status='cancelada') AS aulas_canceladas,
                (SELECT COUNT(*) FROM chamadas c WHERE c.nucleo_id = n.id AND c.data_aula BETWEEN ? AND ?) AS total_chamadas,
                (SELECT COUNT(*) FROM atividades at WHERE at.nucleo_id = n.id AND at.data BETWEEN ? AND ?) AS total_atividades,
                (SELECT COUNT(*) FROM atividade_evidencias ae JOIN atividades at2 ON at2.id = ae.atividade_id WHERE at2.nucleo_id = n.id AND at2.data BETWEEN ? AND ?) AS total_evidencias,
                (SELECT COUNT(*) FROM depoimentos d WHERE d.nucleo_id = n.id AND d.criado_em BETWEEN ? AND ?) AS total_depoimentos
            FROM nucleos n
            WHERE n.projeto_id = ?
            ORDER BY n.nome
        ");
        $stmt->execute([
            $inicio, $fim, $inicio, $fim, $inicio, $fim, $inicio, $fim,
            $inicio, $fim, $inicio, $fim, $inicio, $fim,
            $inicio . ' 00:00:00', $fim . ' 23:59:59',
            (int) $termo['projeto_id'],
        ]);
        $nucleos = $stmt->fetchAll();

        $totais = [
            'inscritos'     => array_sum(array_column($nucleos, 'total_inscritos')),
            'previstas'     => array_sum(array_column($nucleos, 'aulas_previstas')),
            'realizadas'    => array_sum(array_column($nucleos, 'aulas_realizadas')),
            'justificadas'  => array_sum(array_column($nucleos, 'aulas_justificadas')),
            'canceladas'    => array_sum(array_column($nucleos, 'aulas_canceladas')),
            'chamadas'      => array_sum(array_column($nucleos, 'total_chamadas')),
            'atividades'    => array_sum(array_column($nucleos, 'total_atividades')),
            'evidencias'    => array_sum(array_column($nucleos, 'total_evidencias')),
            'depoimentos'   => array_sum(array_column($nucleos, 'total_depoimentos')),
        ];

        $anexosStmt = $db->prepare("SELECT * FROM termo_fomento_anexos WHERE termo_fomento_id = ? ORDER BY criado_em DESC");
        $anexosStmt->execute([$termoId]);
        $anexos = $anexosStmt->fetchAll();

        Security::auditLog('visualizacao', 'prestacao_contas', $termoId);

        $data = compact('termo', 'nucleos', 'totais', 'anexos', 'inicio', 'fim');
        require_once ROOT_PATH . '/app/views/admin/prestacao_contas/consolidado.php';
    }
}
