<?php

/**
 * Checklist configurável por Projeto (Etapa 13) — infraestrutura apenas.
 * As exigências reais de cada projeto (Futebol, Transformando Vidas, Vida em
 * Movimento etc.) ainda serão definidas pelos responsáveis — ver
 * docs/PENDENCIAS_REGRAS_NEGOCIO.md. Este CRUD só permite ao admin montar
 * a lista de requisitos quando ela for definida, sem regra fixa no código.
 */
class AdminProjetoRequisitosController
{
    private const TIPOS = [
        'foto'           => 'Foto',
        'texto'          => 'Texto',
        'lista_presenca' => 'Lista de presença',
        'documento'      => 'Documento',
        'video'          => 'Vídeo',
        'confirmacao'    => 'Confirmação',
        'outro'          => 'Outro',
    ];

    public function index(string $projetoId): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.visualizar');
        $projetoId = (int) $projetoId;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $projetoId)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM projetos WHERE id = ? LIMIT 1");
        $stmt->execute([$projetoId]);
        $projeto = $stmt->fetch();

        if (!$projeto) {
            $_SESSION['flash_error'] = 'Projeto não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM projeto_requisitos WHERE projeto_id = ? ORDER BY ordem ASC, id ASC");
        $stmt->execute([$projetoId]);
        $requisitos = $stmt->fetchAll();

        $tipos = self::TIPOS;
        $data = compact('projeto', 'requisitos', 'tipos');
        require_once ROOT_PATH . '/app/views/admin/requisitos/index.php';
    }

    public function store(string $projetoId): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.editar');
        Security::verifyCsrf();
        $projetoId = (int) $projetoId;

        if (!Escopo::podeAcessarProjeto(Auth::id(), $projetoId)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $nome        = Security::sanitize($_POST['nome'] ?? '');
        $tipo        = Security::sanitize($_POST['tipo'] ?? 'foto');
        $obrigatorio = !empty($_POST['obrigatorio']) ? 1 : 0;
        $qtdMinima   = max(1, (int) ($_POST['quantidade_minima'] ?? 1));
        $instrucao   = Security::sanitize($_POST['instrucao'] ?? '');

        if ($nome === '' || !array_key_exists($tipo, self::TIPOS)) {
            $_SESSION['flash_error'] = 'Preencha nome e tipo válidos.';
            header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/requisitos');
            exit;
        }

        $db = Database::getInstance();
        $ordemStmt = $db->prepare("SELECT COALESCE(MAX(ordem),0)+1 FROM projeto_requisitos WHERE projeto_id=?");
        $ordemStmt->execute([$projetoId]);
        $ordem = (int) $ordemStmt->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO projeto_requisitos (projeto_id, nome, tipo, obrigatorio, quantidade_minima, instrucao, ordem, status, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo', NOW())"
        );
        $stmt->execute([$projetoId, $nome, $tipo, $obrigatorio, $qtdMinima, $instrucao ?: null, $ordem]);

        Security::auditLog('cadastro', 'projeto_requisitos', $db->lastInsertId());
        $_SESSION['flash_success'] = 'Requisito adicionado.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/requisitos');
        exit;
    }

    public function alternarStatus(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.editar');
        Security::verifyCsrf();
        $id = (int) $id;

        [$req, $projetoId] = $this->buscarComEscopo($id);
        $novo = $req['status'] === 'ativo' ? 'inativo' : 'ativo';

        $db = Database::getInstance();
        $db->prepare("UPDATE projeto_requisitos SET status=? WHERE id=?")->execute([$novo, $id]);

        Security::auditLog('edicao', 'projeto_requisitos', $id);
        $_SESSION['flash_success'] = 'Requisito atualizado.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/requisitos');
        exit;
    }

    public function excluir(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('projetos.editar');
        Security::verifyCsrf();
        $id = (int) $id;

        [$req, $projetoId] = $this->buscarComEscopo($id);

        $db = Database::getInstance();
        $db->prepare("DELETE FROM projeto_requisitos WHERE id=?")->execute([$id]);

        Security::auditLog('exclusao', 'projeto_requisitos', $id);
        $_SESSION['flash_success'] = 'Requisito removido.';
        header('Location: ' . APP_URL . '/admin/projetos/' . $projetoId . '/requisitos');
        exit;
    }

    private function buscarComEscopo(int $id): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM projeto_requisitos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $req = $stmt->fetch();

        if (!$req) {
            $_SESSION['flash_error'] = 'Requisito não encontrado.';
            header('Location: ' . APP_URL . '/admin/projetos');
            exit;
        }
        if (!Escopo::podeAcessarProjeto(Auth::id(), (int) $req['projeto_id'])) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        return [$req, (int) $req['projeto_id']];
    }
}
