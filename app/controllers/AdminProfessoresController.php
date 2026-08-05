<?php

/**
 * Gestão de Equipe: professores (acesso operacional) e gestores — presidente,
 * coordenador geral, coordenador de projeto/núcleo, monitor, colaborador
 * (acesso administrativo restrito por permissão + escopo). O cadastro por
 * convite (formConvite/gerarConvite) continua exclusivo para professor —
 * quem entra por convite nunca recebe permissão administrativa, só o acesso
 * operacional de sempre (nucleo_professores), como manda a regra de que
 * professor não tem permissão administrativa por padrão.
 */
class AdminProfessoresController
{
    private const PER_PAGE = 20;

    public const CARGOS = [
        'presidente'          => 'Presidente',
        'coordenador_geral'   => 'Coordenador Geral',
        'coordenador_projeto' => 'Coordenador de Projeto',
        'coordenador_nucleo'  => 'Coordenador de Núcleo',
        'professor'           => 'Professor',
        'monitor'             => 'Monitor',
        'colaborador'         => 'Colaborador',
    ];

    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.visualizar');
        $db = Database::getInstance();

        $q    = Security::sanitize($_GET['q']    ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * self::PER_PAGE;

        $where  = $q ? "AND (u.nome LIKE ? OR u.email LIKE ?)" : '';
        $params = $q ? ["%$q%", "%$q%"] : [];

        $countStmt = $db->prepare(
            "SELECT COUNT(DISTINCT u.id) FROM usuarios u
             WHERE u.perfil IN ('professor','gestor') $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT
                u.id, u.nome, u.email, u.telefone, u.foto, u.status, u.perfil, u.cargo,
                GROUP_CONCAT(DISTINCT n.nome ORDER BY n.nome SEPARATOR '||') AS nucleos,
                MAX(c.data_aula) AS ultima_chamada,
                COUNT(DISTINCT CASE
                    WHEN c.data_aula >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN c.id
                END) AS chamadas_mes
            FROM usuarios u
            LEFT JOIN nucleo_professores np ON np.usuario_id = u.id
            LEFT JOIN nucleos n ON n.id = np.nucleo_id
            LEFT JOIN chamadas c ON c.professor_id = u.id AND c.nucleo_id = n.id
            WHERE u.perfil IN ('professor','gestor') $where
            GROUP BY u.id, u.nome, u.email, u.telefone, u.foto, u.status, u.perfil, u.cargo
            ORDER BY u.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $membros = $stmt->fetchAll();

        $totalPages = (int) ceil($total / self::PER_PAGE);
        $cargos = self::CARGOS;
        $data = compact('membros', 'q', 'page', 'total', 'totalPages', 'cargos');
        require_once ROOT_PATH . '/app/views/admin/professores/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.criar');
        $db = Database::getInstance();

        $prof              = null;
        $nucleos           = $this->nucleosDisponiveis($db);
        $catalogoPermissoes = Permissao::catalogo();
        $institutosDisp    = $this->listar($db, 'institutos', Escopo::institutosPermitidos(Auth::id()));
        $projetosDisp      = $this->listarProjetos($db, Escopo::projetosPermitidos(Auth::id()));
        $nucleosEscopo     = $this->listarNucleos($db, Escopo::nucleosPermitidos(Auth::id()));
        $cargosPermitidos  = $this->cargosPermitidosParaAtribuir();
        $cargos            = self::CARGOS;
        $permissoesAtuais  = [];
        $escoposAtuais     = [];
        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/professores/form.php';
    }

    public function store(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.criar');
        Security::verifyCsrf();
        $db = Database::getInstance();

        [$dados, $errors] = $this->validar($_POST, $db, null);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/professores/novo');
            exit;
        }

        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            try {
                require_once ROOT_PATH . '/app/helpers/Upload.php';
                $foto = Upload::image($_FILES['foto'], 'fotos', 400, 400, 80);
            } catch (RuntimeException $e) {
                $_SESSION['form_errors'] = ['foto' => $e->getMessage()];
                $_SESSION['form_data']   = $_POST;
                header('Location: ' . APP_URL . '/admin/professores/novo');
                exit;
            }
        }

        $perfil = $dados['cargo'] === 'professor' ? 'professor' : 'gestor';
        $redes  = json_encode(['instagram' => $dados['instagram'], 'facebook' => $dados['facebook'], 'tiktok' => $dados['tiktok']]);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO usuarios (nome, email, senha_hash, telefone, foto, descricao, redes_sociais, cargo, perfil, status, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo', NOW())"
            );
            $stmt->execute([
                $dados['nome'], $dados['email'],
                password_hash($dados['senha'], PASSWORD_BCRYPT, ['cost' => 12]),
                $dados['telefone'] ?: null, $foto, $dados['descricao'] ?: null, $redes, $dados['cargo'], $perfil,
            ]);
            $novoId = (int) $db->lastInsertId();

            $this->salvarPermissoesEscopo($db, $novoId, $dados);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminProfessores] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao cadastrar. Tente novamente.';
            header('Location: ' . APP_URL . '/admin/professores/novo');
            exit;
        }

        Security::auditLog('cadastro', 'usuarios', $novoId);
        $_SESSION['flash_success'] = "\"{$dados['nome']}\" cadastrado(a) com sucesso.";
        header('Location: ' . APP_URL . '/admin/professores');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.editar');
        $id = (int) $id;
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND perfil IN ('professor','gestor') LIMIT 1");
        $stmt->execute([$id]);
        $prof = $stmt->fetch();

        if (!$prof) {
            $_SESSION['flash_error'] = 'Usuário não encontrado.';
            header('Location: ' . APP_URL . '/admin/professores');
            exit;
        }
        if (!$this->podeGerenciar($prof)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $nucleos = $this->nucleosDisponiveis($db);

        $nucStmt = $db->prepare("SELECT nucleo_id FROM nucleo_professores WHERE usuario_id=?");
        $nucStmt->execute([$id]);
        $prof['nucleo_id'] = $nucStmt->fetchColumn();

        $catalogoPermissoes = Permissao::catalogo();
        $institutosDisp   = $this->listar($db, 'institutos', Escopo::institutosPermitidos(Auth::id()));
        $projetosDisp     = $this->listarProjetos($db, Escopo::projetosPermitidos(Auth::id()));
        $nucleosEscopo    = $this->listarNucleos($db, Escopo::nucleosPermitidos(Auth::id()));
        $cargosPermitidos = $this->cargosPermitidosParaAtribuir();
        $cargos           = self::CARGOS;
        $permissoesAtuais = Permissao::todasDoUsuario($id);
        $escoposAtuais    = Escopo::doUsuario($id);

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/professores/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.editar');
        Security::verifyCsrf();
        $id = (int) $id;
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND perfil IN ('professor','gestor') LIMIT 1");
        $stmt->execute([$id]);
        $prof = $stmt->fetch();

        if (!$prof) {
            $_SESSION['flash_error'] = 'Usuário não encontrado.';
            header('Location: ' . APP_URL . '/admin/professores');
            exit;
        }
        if (!$this->podeGerenciar($prof)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        [$dados, $errors] = $this->validar($_POST, $db, $id);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/admin/professores/' . $id . '/editar');
            exit;
        }

        $foto = $prof['foto'];
        if (!empty($_FILES['foto']['name'])) {
            try {
                require_once ROOT_PATH . '/app/helpers/Upload.php';
                $novo = Upload::image($_FILES['foto'], 'fotos', 400, 400, 80);
                if ($foto) Upload::delete($foto);
                $foto = $novo;
            } catch (RuntimeException $e) {
                $_SESSION['form_errors'] = ['foto' => $e->getMessage()];
                $_SESSION['form_data']   = $_POST;
                header('Location: ' . APP_URL . '/admin/professores/' . $id . '/editar');
                exit;
            }
        }

        $perfil = $dados['cargo'] === 'professor' ? 'professor' : 'gestor';
        $redes  = json_encode(['instagram' => $dados['instagram'], 'facebook' => $dados['facebook'], 'tiktok' => $dados['tiktok']]);

        $db->beginTransaction();
        try {
            $fields = "nome=?, email=?, telefone=?, foto=?, descricao=?, redes_sociais=?, cargo=?, perfil=?";
            $vals   = [$dados['nome'], $dados['email'], $dados['telefone'] ?: null, $foto, $dados['descricao'] ?: null, $redes, $dados['cargo'], $perfil];

            if ($dados['senha']) {
                $fields .= ', senha_hash=?';
                $vals[]  = password_hash($dados['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
            }
            $vals[] = $id;

            $db->prepare("UPDATE usuarios SET $fields WHERE id=?")->execute($vals);

            $this->salvarPermissoesEscopo($db, $id, $dados);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminProfessores] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao atualizar. Tente novamente.';
            header('Location: ' . APP_URL . '/admin/professores/' . $id . '/editar');
            exit;
        }

        Security::auditLog('edicao', 'usuarios', $id);
        $_SESSION['flash_success'] = "\"{$dados['nome']}\" atualizado(a).";
        header('Location: ' . APP_URL . '/admin/professores');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.excluir');
        Security::verifyCsrf();
        $id = (int) $id;

        if ($id === Auth::id()) {
            $_SESSION['flash_error'] = 'Você não pode inativar seu próprio usuário.';
            header('Location: ' . APP_URL . '/admin/professores');
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND perfil IN ('professor','gestor') LIMIT 1");
        $stmt->execute([$id]);
        $prof = $stmt->fetch();
        if (!$prof) {
            $_SESSION['flash_error'] = 'Usuário não encontrado.';
            header('Location: ' . APP_URL . '/admin/professores');
            exit;
        }
        if (!$this->podeGerenciar($prof)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        $db->prepare("UPDATE usuarios SET status='inativo' WHERE id=?")->execute([$id]);

        Security::auditLog('exclusao', 'usuarios', $id);
        $_SESSION['flash_success'] = 'Usuário inativado.';
        header('Location: ' . APP_URL . '/admin/professores');
        exit;
    }

    // ── Convite por token (só professor, sem permissão administrativa) ────────

    public function formConvite(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.criar');
        $db      = Database::getInstance();
        $nucleos = $this->nucleosDisponiveis($db);

        $inviteUrl = $_SESSION['invite_url'] ?? null;
        unset($_SESSION['invite_url']);

        require_once ROOT_PATH . '/app/views/admin/professores/convite.php';
    }

    public function gerarConvite(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('equipe.criar');
        Security::verifyCsrf();

        $nucleoId = (int) ($_POST['nucleo_id'] ?? 0);
        if (!$nucleoId || !Escopo::podeAcessarNucleo(Auth::id(), $nucleoId)) {
            $_SESSION['flash_error'] = 'Selecione um núcleo válido dentro do seu escopo.';
            header('Location: ' . APP_URL . '/admin/professores/convite');
            exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM nucleos WHERE id=? AND status='ativo' LIMIT 1");
        $stmt->execute([$nucleoId]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = 'Núcleo inválido.';
            header('Location: ' . APP_URL . '/admin/professores/convite');
            exit;
        }

        $db->prepare(
            "UPDATE convites SET status='expirado' WHERE nucleo_id=? AND tipo='professor' AND status='pendente'"
        )->execute([$nucleoId]);

        $raw   = Security::generateToken(32);
        $hash  = Security::hashToken($raw);
        $expira = date('Y-m-d H:i:s', strtotime('+7 days'));

        $db->prepare(
            "INSERT INTO convites (token_hash, tipo, nucleo_id, criado_por, status, expira_em, criado_em)
             VALUES (?, 'professor', ?, ?, 'pendente', ?, NOW())"
        )->execute([$hash, $nucleoId, Auth::id(), $expira]);

        Security::auditLog('geracao_token', 'convites', $db->lastInsertId());

        $inviteUrl = APP_URL . '/convite/professor/' . $raw;
        $_SESSION['invite_url'] = $inviteUrl;

        $emailDest = Security::sanitizeEmail($_POST['email_destinatario'] ?? '');
        $nomeDest  = Security::sanitize($_POST['nome_destinatario'] ?? '');
        if ($emailDest && filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
            $nucleoStmt = $db->prepare("SELECT n.nome, p.nome AS projeto FROM nucleos n JOIN projetos p ON p.id=n.projeto_id WHERE n.id=? LIMIT 1");
            $nucleoStmt->execute([$nucleoId]);
            $nucleo = $nucleoStmt->fetch();
            $nomeNucleo = $nucleo ? $nucleo['projeto'] . ' — ' . $nucleo['nome'] : '';
            require_once ROOT_PATH . '/app/helpers/Mailer.php';
            Mailer::inviteProfessor($emailDest, $nomeDest ?: 'Professor(a)', $inviteUrl, $nomeNucleo);
            $_SESSION['flash_success'] = 'Convite gerado e enviado por e-mail para ' . $emailDest . '.';
        }

        header('Location: ' . APP_URL . '/admin/professores/convite');
        exit;
    }

    // ── Internos ────────────────────────────────────────────────────────────

    private function validar(array $post, PDO $db, ?int $idEmEdicao): array
    {
        $dados = [
            'nome'      => Security::sanitize($post['nome']      ?? ''),
            'email'     => Security::sanitizeEmail($post['email'] ?? ''),
            'senha'     => $post['senha'] ?? '',
            'telefone'  => Security::sanitize($post['telefone']  ?? ''),
            'cargo'     => Security::sanitize($post['cargo']     ?? ''),
            'descricao' => Security::sanitize($post['descricao'] ?? ''),
            'instagram' => Security::sanitize($post['instagram'] ?? ''),
            'facebook'  => Security::sanitize($post['facebook']  ?? ''),
            'tiktok'    => Security::sanitize($post['tiktok']    ?? ''),
            'permissoes' => array_map('strval', (array) ($post['permissoes'] ?? [])),
            'escopo_instituto' => array_map('intval', (array) ($post['escopo_instituto'] ?? [])),
            'escopo_projeto'   => array_map('intval', (array) ($post['escopo_projeto']   ?? [])),
            'escopo_nucleo'    => array_map('intval', (array) ($post['escopo_nucleo']    ?? [])),
        ];

        $errors = [];
        if (!$dados['nome'])  $errors['nome']  = 'Nome é obrigatório.';
        if (!$dados['email']) $errors['email'] = 'E-mail é obrigatório.';
        elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail inválido.';

        if (!$idEmEdicao && !$dados['senha']) $errors['senha'] = 'Senha é obrigatória.';
        if ($dados['senha'] && strlen($dados['senha']) < 8) $errors['senha'] = 'Senha deve ter ao menos 8 caracteres.';

        if (!array_key_exists($dados['cargo'], self::CARGOS)) {
            $errors['cargo'] = 'Selecione um cargo válido.';
        } elseif (!in_array($dados['cargo'], $this->cargosPermitidosParaAtribuir(), true)) {
            $errors['cargo'] = 'Você não tem permissão para atribuir esse cargo.';
        }

        if (!isset($errors['email'])) {
            $dupSql = "SELECT id FROM usuarios WHERE email=?" . ($idEmEdicao ? " AND id!=?" : "");
            $dupParams = $idEmEdicao ? [$dados['email'], $idEmEdicao] : [$dados['email']];
            $dup = $db->prepare($dupSql);
            $dup->execute($dupParams);
            if ($dup->fetch()) $errors['email'] = 'Este e-mail já está em uso.';
        }

        return [$dados, $errors];
    }

    /**
     * Impede que um gestor edite/inative alguém de tier igual ou superior ao
     * dele (ex: coordenador de projeto não mexe em presidente). super_admin
     * sempre pode. Usuários sem cargo definido (professores antigos vindos de
     * convite) podem ser gerenciados por qualquer um com a permissão de equipe.
     */
    private function podeGerenciar(array $alvo): bool
    {
        if (Auth::perfil() === 'super_admin') return true;
        if (!$alvo['cargo']) return true;
        return in_array($alvo['cargo'], $this->cargosPermitidosParaAtribuir(), true);
    }

    /** Cargos que o usuário logado tem permissão de atribuir a outra pessoa (Etapa 10). */
    private function cargosPermitidosParaAtribuir(): array
    {
        if (Auth::perfil() === 'super_admin') {
            return array_keys(self::CARGOS);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT cargo FROM usuarios WHERE id = ?");
        $stmt->execute([Auth::id()]);
        $meuCargo = $stmt->fetchColumn();

        return match ($meuCargo) {
            'presidente', 'coordenador_geral' =>
                ['coordenador_geral', 'coordenador_projeto', 'coordenador_nucleo', 'professor', 'monitor', 'colaborador'],
            'coordenador_projeto', 'coordenador_nucleo' =>
                ['professor', 'monitor', 'colaborador'],
            default => [],
        };
    }

    /**
     * Salva permissões + escopo do usuário-alvo, e sincroniza nucleo_professores
     * (compatibilidade com o fluxo tradicional de frequência/agenda) quando o
     * cargo é "professor" e há escopo de núcleo selecionado.
     */
    private function salvarPermissoesEscopo(PDO $db, int $usuarioId, array $dados): void
    {
        $souSuperAdmin = Auth::perfil() === 'super_admin';

        Permissao::salvar($db, $usuarioId, $dados['permissoes'], Auth::id(), $souSuperAdmin);

        $desejados = [];
        foreach ($dados['escopo_instituto'] as $i) $desejados[] = ['tipo' => 'instituto', 'referencia_id' => $i];
        foreach ($dados['escopo_projeto']   as $i) $desejados[] = ['tipo' => 'projeto',   'referencia_id' => $i];
        foreach ($dados['escopo_nucleo']    as $i) $desejados[] = ['tipo' => 'nucleo',    'referencia_id' => $i];
        Escopo::salvar($db, $usuarioId, $desejados, Auth::id(), $souSuperAdmin);

        if ($dados['cargo'] === 'professor') {
            $db->prepare("DELETE FROM nucleo_professores WHERE usuario_id=?")->execute([$usuarioId]);
            if ($dados['escopo_nucleo']) {
                $ins = $db->prepare("INSERT IGNORE INTO nucleo_professores (nucleo_id, usuario_id) VALUES (?, ?)");
                foreach ($dados['escopo_nucleo'] as $nucleoId) {
                    $ins->execute([$nucleoId, $usuarioId]);
                }
            }
        }
    }

    private function nucleosDisponiveis(PDO $db): array
    {
        return $db->query("SELECT n.id, n.nome, p.nome AS projeto FROM nucleos n JOIN projetos p ON p.id=n.projeto_id WHERE n.status='ativo' ORDER BY p.nome,n.nome")->fetchAll();
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
        [$where, $params] = Escopo::whereIn($ids, 'p.id');
        $stmt = $db->prepare("SELECT p.id, p.nome, i.nome AS instituto FROM projetos p JOIN institutos i ON i.id=p.instituto_id WHERE $where ORDER BY i.nome, p.nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function listarNucleos(PDO $db, array $ids): array
    {
        if (!$ids) return [];
        [$where, $params] = Escopo::whereIn($ids, 'n.id');
        $stmt = $db->prepare("SELECT n.id, n.nome, p.nome AS projeto FROM nucleos n JOIN projetos p ON p.id=n.projeto_id WHERE $where ORDER BY p.nome, n.nome");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
