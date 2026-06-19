<?php

class AdminProfessoresController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        Auth::requireRole('super_admin');
        $db = Database::getInstance();

        $q    = Security::sanitize($_GET['q']    ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $off  = ($page - 1) * self::PER_PAGE;

        $where  = $q ? "AND (u.nome LIKE ? OR u.email LIKE ?)" : '';
        $params = $q ? ["%$q%", "%$q%"] : [];

        $countStmt = $db->prepare(
            "SELECT COUNT(DISTINCT u.id) FROM usuarios u
             WHERE u.perfil = 'professor' $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT
                u.id, u.nome, u.email, u.telefone, u.foto, u.status,
                GROUP_CONCAT(DISTINCT n.nome ORDER BY n.nome SEPARATOR '||') AS nucleos,
                MAX(c.data_aula) AS ultima_chamada,
                COUNT(DISTINCT CASE
                    WHEN c.data_aula >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN c.id
                END) AS chamadas_mes
            FROM usuarios u
            LEFT JOIN nucleo_professores np ON np.usuario_id = u.id
            LEFT JOIN nucleos n ON n.id = np.nucleo_id
            LEFT JOIN chamadas c ON c.professor_id = u.id AND c.nucleo_id = n.id
            WHERE u.perfil = 'professor' $where
            GROUP BY u.id, u.nome, u.email, u.telefone, u.foto, u.status
            ORDER BY u.nome ASC
            LIMIT " . self::PER_PAGE . " OFFSET $off
        ");
        $stmt->execute($params);
        $professores = $stmt->fetchAll();

        $totalPages = (int) ceil($total / self::PER_PAGE);
        $data = compact('professores', 'q', 'page', 'total', 'totalPages');
        require_once ROOT_PATH . '/app/views/admin/professores/index.php';
    }

    public function formNovo(): void
    {
        Auth::requireRole('super_admin');
        $db      = Database::getInstance();
        $nucleos = $db->query("SELECT n.id, n.nome, p.nome AS projeto FROM nucleos n JOIN projetos p ON p.id=n.projeto_id WHERE n.status='ativo' ORDER BY p.nome,n.nome")->fetchAll();
        $prof    = null;
        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/professores/form.php';
    }

    public function store(): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $nome     = Security::sanitize($_POST['nome']     ?? '');
        $email    = Security::sanitizeEmail($_POST['email'] ?? '');
        $senha    = $_POST['senha'] ?? '';
        $telefone = Security::sanitize($_POST['telefone'] ?? '');
        $descricao= Security::sanitize($_POST['descricao']?? '');
        $nucleoId = (int) ($_POST['nucleo_id'] ?? 0);
        $errors   = [];

        if (!$nome)  $errors['nome']  = 'Nome é obrigatório.';
        if (!$email) $errors['email'] = 'E-mail é obrigatório.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail inválido.';

        if (!$senha) $errors['senha'] = 'Senha é obrigatória.';
        elseif (strlen($senha) < 8) $errors['senha'] = 'Senha deve ter ao menos 8 caracteres.';

        $db = Database::getInstance();
        if (!isset($errors['email']) && $email) {
            $dup = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            $dup->execute([$email]);
            if ($dup->fetch()) $errors['email'] = 'Este e-mail já está cadastrado.';
        }

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

        $redes = json_encode([
            'instagram' => Security::sanitize($_POST['instagram'] ?? ''),
            'facebook'  => Security::sanitize($_POST['facebook']  ?? ''),
            'tiktok'    => Security::sanitize($_POST['tiktok']    ?? ''),
        ]);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO usuarios (nome, email, senha_hash, telefone, foto, descricao, redes_sociais, perfil, status, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'professor', 'ativo', NOW())"
            );
            $stmt->execute([
                $nome, $email,
                password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
                $telefone ?: null,
                $foto,
                $descricao ?: null,
                $redes,
            ]);
            $profId = (int) $db->lastInsertId();

            if ($nucleoId) {
                $db->prepare(
                    "INSERT IGNORE INTO nucleo_professores (nucleo_id, usuario_id) VALUES (?, ?)"
                )->execute([$nucleoId, $profId]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminProfessores] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao cadastrar professor. Tente novamente.';
            header('Location: ' . APP_URL . '/admin/professores/novo');
            exit;
        }

        Security::auditLog('cadastro', 'usuarios', $profId);
        $_SESSION['flash_success'] = "Professor \"$nome\" cadastrado com sucesso.";
        header('Location: ' . APP_URL . '/admin/professores');
        exit;
    }

    public function formEditar(string $id): void
    {
        Auth::requireRole('super_admin');
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND perfil='professor' LIMIT 1");
        $stmt->execute([(int) $id]);
        $prof = $stmt->fetch();

        if (!$prof) {
            $_SESSION['flash_error'] = 'Professor não encontrado.';
            header('Location: ' . APP_URL . '/admin/professores');
            exit;
        }

        $nucleos = $db->query("SELECT n.id, n.nome, p.nome AS projeto FROM nucleos n JOIN projetos p ON p.id=n.projeto_id WHERE n.status='ativo' ORDER BY p.nome,n.nome")->fetchAll();

        $nucStmt = $db->prepare("SELECT nucleo_id FROM nucleo_professores WHERE usuario_id=?");
        $nucStmt->execute([(int) $id]);
        $prof['nucleo_id'] = $nucStmt->fetchColumn();

        $redes = json_decode($prof['redes_sociais'] ?? '{}', true) ?? [];

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/admin/professores/form.php';
    }

    public function update(string $id): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND perfil='professor' LIMIT 1");
        $stmt->execute([(int) $id]);
        $prof = $stmt->fetch();

        if (!$prof) {
            $_SESSION['flash_error'] = 'Professor não encontrado.';
            header('Location: ' . APP_URL . '/admin/professores');
            exit;
        }

        $nome      = Security::sanitize($_POST['nome']      ?? '');
        $email     = Security::sanitizeEmail($_POST['email'] ?? '');
        $senha     = $_POST['senha'] ?? '';
        $telefone  = Security::sanitize($_POST['telefone']  ?? '');
        $descricao = Security::sanitize($_POST['descricao'] ?? '');
        $nucleoId  = (int) ($_POST['nucleo_id'] ?? 0);
        $errors    = [];

        if (!$nome)  $errors['nome']  = 'Nome é obrigatório.';
        if (!$email) $errors['email'] = 'E-mail é obrigatório.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail inválido.';

        if ($senha && strlen($senha) < 8) $errors['senha'] = 'Senha deve ter ao menos 8 caracteres.';

        if (!isset($errors['email'])) {
            $dup = $db->prepare("SELECT id FROM usuarios WHERE email=? AND id!=? LIMIT 1");
            $dup->execute([$email, (int) $id]);
            if ($dup->fetch()) $errors['email'] = 'E-mail já em uso.';
        }

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

        $redes = json_encode([
            'instagram' => Security::sanitize($_POST['instagram'] ?? ''),
            'facebook'  => Security::sanitize($_POST['facebook']  ?? ''),
            'tiktok'    => Security::sanitize($_POST['tiktok']    ?? ''),
        ]);

        $db->beginTransaction();
        try {
            $fields = "nome=?, email=?, telefone=?, foto=?, descricao=?, redes_sociais=?";
            $vals   = [$nome, $email, $telefone ?: null, $foto, $descricao ?: null, $redes];

            if ($senha) {
                $fields .= ', senha_hash=?';
                $vals[]  = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            }
            $vals[] = (int) $id;

            $db->prepare("UPDATE usuarios SET $fields WHERE id=?")->execute($vals);

            // Update nucleo link
            $db->prepare("DELETE FROM nucleo_professores WHERE usuario_id=?")->execute([(int)$id]);
            if ($nucleoId) {
                $db->prepare("INSERT INTO nucleo_professores (nucleo_id, usuario_id) VALUES (?,?)")->execute([$nucleoId, (int)$id]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[AdminProfessores] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao atualizar. Tente novamente.';
            header('Location: ' . APP_URL . '/admin/professores/' . $id . '/editar');
            exit;
        }

        Security::auditLog('edicao', 'usuarios', $id);
        $_SESSION['flash_success'] = "Professor \"$nome\" atualizado.";
        header('Location: ' . APP_URL . '/admin/professores');
        exit;
    }

    public function inativar(string $id): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE usuarios SET status='inativo' WHERE id=? AND perfil='professor'");
        $stmt->execute([(int) $id]);

        Security::auditLog('exclusao', 'usuarios', $id);
        $_SESSION['flash_success'] = 'Professor inativado.';
        header('Location: ' . APP_URL . '/admin/professores');
        exit;
    }

    // ── Convite por token ────────────────────────────────────────────────────

    public function formConvite(): void
    {
        Auth::requireRole('super_admin');
        $db      = Database::getInstance();
        $nucleos = $db->query("SELECT n.id, n.nome, p.nome AS projeto FROM nucleos n JOIN projetos p ON p.id=n.projeto_id WHERE n.status='ativo' ORDER BY p.nome,n.nome")->fetchAll();

        $inviteUrl = $_SESSION['invite_url'] ?? null;
        unset($_SESSION['invite_url']);

        require_once ROOT_PATH . '/app/views/admin/professores/convite.php';
    }

    public function gerarConvite(): void
    {
        Auth::requireRole('super_admin');
        Security::verifyCsrf();

        $nucleoId = (int) ($_POST['nucleo_id'] ?? 0);
        if (!$nucleoId) {
            $_SESSION['flash_error'] = 'Selecione um núcleo.';
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

        // Invalidar convites pendentes anteriores para o mesmo núcleo
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

        // Enviar convite por e-mail se informado
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
}
