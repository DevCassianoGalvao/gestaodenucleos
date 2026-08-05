<?php

/**
 * Visualização e correção de chamadas já registradas (Etapa 18-19).
 * Correção é restrita por permissão dedicada ('chamadas.corrigir') e por
 * escopo — nunca só escondida no frontend. Toda correção fica registrada
 * em chamada_presenca_historico (valor anterior/novo/quem/quando).
 */
class AdminChamadasController
{
    public function show(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('chamadas.visualizar');
        $id = (int) $id;

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT c.*, u.nome AS professor_nome, n.nome AS nucleo_nome, p.nome AS projeto_nome
            FROM chamadas c
            JOIN usuarios u ON u.id = c.professor_id
            JOIN nucleos n ON n.id = c.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            WHERE c.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $chamada = $stmt->fetch();

        if (!$chamada) {
            $_SESSION['flash_error'] = 'Chamada não encontrada.';
            header('Location: ' . APP_URL . '/admin/aulas');
            exit;
        }
        if (!Escopo::podeAcessarNucleo(Auth::id(), (int) $chamada['nucleo_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $stmt = $db->prepare("
            SELECT cp.*, a.nome AS aluno_nome, a.foto AS aluno_foto
            FROM chamada_presencas cp
            JOIN alunos a ON a.id = cp.aluno_id
            WHERE cp.chamada_id = ?
            ORDER BY a.nome ASC
        ");
        $stmt->execute([$id]);
        $presencas = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT h.*, a.nome AS aluno_nome, u.nome AS alterado_por_nome
            FROM chamada_presenca_historico h
            JOIN chamada_presencas cp ON cp.id = h.chamada_presenca_id
            JOIN alunos a ON a.id = cp.aluno_id
            JOIN usuarios u ON u.id = h.alterado_por
            WHERE cp.chamada_id = ?
            ORDER BY h.criado_em DESC
        ");
        $stmt->execute([$id]);
        $historico = $stmt->fetchAll();

        $podeCorrigir = Permissao::has(Auth::id(), 'chamadas.corrigir');

        $data = compact('chamada', 'presencas', 'historico', 'podeCorrigir');
        require_once ROOT_PATH . '/app/views/admin/chamadas/show.php';
    }

    public function corrigir(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('chamadas.corrigir');
        Security::verifyCsrf();
        $id = (int) $id;

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM chamadas WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $chamada = $stmt->fetch();

        if (!$chamada) {
            $_SESSION['flash_error'] = 'Chamada não encontrada.';
            header('Location: ' . APP_URL . '/admin/aulas');
            exit;
        }
        if (!Escopo::podeAcessarNucleo(Auth::id(), (int) $chamada['nucleo_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $novo    = isset($_POST['presente']) ? (int) $_POST['presente'] : null;

        if (!$alunoId || !in_array($novo, [0, 1], true)) {
            $_SESSION['flash_error'] = 'Dados inválidos.';
            header('Location: ' . APP_URL . '/admin/chamadas/' . $id);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM chamada_presencas WHERE chamada_id = ? AND aluno_id = ? LIMIT 1");
        $stmt->execute([$id, $alunoId]);
        $presenca = $stmt->fetch();

        if (!$presenca) {
            $_SESSION['flash_error'] = 'Registro de presença não encontrado.';
            header('Location: ' . APP_URL . '/admin/chamadas/' . $id);
            exit;
        }

        if ((int) $presenca['presente'] === $novo) {
            $_SESSION['flash_success'] = 'Nada para alterar — já estava assim.';
            header('Location: ' . APP_URL . '/admin/chamadas/' . $id);
            exit;
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE chamada_presencas SET presente = ? WHERE id = ?")->execute([$novo, $presenca['id']]);
            $db->prepare(
                "INSERT INTO chamada_presenca_historico (chamada_presenca_id, presente_anterior, presente_novo, alterado_por, criado_em)
                 VALUES (?, ?, ?, ?, NOW())"
            )->execute([$presenca['id'], $presenca['presente'], $novo, Auth::id()]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminChamadas] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao corrigir. Tente novamente.';
            header('Location: ' . APP_URL . '/admin/chamadas/' . $id);
            exit;
        }

        Security::auditLog(
            'correcao', 'chamada_presencas', $presenca['id'],
            ['presente' => (int) $presenca['presente']], ['presente' => $novo]
        );
        $_SESSION['flash_success'] = 'Presença corrigida.';
        header('Location: ' . APP_URL . '/admin/chamadas/' . $id);
        exit;
    }
}
