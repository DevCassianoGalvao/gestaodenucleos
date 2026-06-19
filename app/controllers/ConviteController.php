<?php

class ConviteController
{
    // ── Token validation ──────────────────────────────────────────────────────

    private function resolveToken(string $raw, string $tipo): array|false
    {
        $hash = Security::hashToken($raw);
        $db   = Database::getInstance();

        $stmt = $db->prepare("
            SELECT c.*, n.nome AS nucleo_nome, p.nome AS projeto_nome
            FROM convites c
            JOIN nucleos n ON n.id = c.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            WHERE c.token_hash = ?
              AND c.tipo = ?
              AND c.status = 'pendente'
              AND c.expira_em > NOW()
            LIMIT 1
        ");
        $stmt->execute([$hash, $tipo]);
        return $stmt->fetch() ?: false;
    }

    private function showExpired(string $motivo = ''): never
    {
        $motivo = $motivo ?: 'Este link de convite não é mais válido ou já foi utilizado.';
        require_once ROOT_PATH . '/app/views/convite/expirado.php';
        exit;
    }

    // ── Professor — show ──────────────────────────────────────────────────────

    public function showProfessor(string $token): void
    {
        // Redirect already-logged users
        if (Auth::check()) {
            header('Location: ' . Auth::dashboardUrl());
            exit;
        }

        $convite = $this->resolveToken($token, 'professor');
        if (!$convite) $this->showExpired();

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/convite/professor.php';
    }

    // ── Professor — process ───────────────────────────────────────────────────

    public function processProfessor(string $token): void
    {
        Security::verifyCsrf();

        $convite = $this->resolveToken($token, 'professor');
        if (!$convite) $this->showExpired();

        [$data, $errors] = $this->validateAccountForm();

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/convite/professor/' . rawurlencode($token));
            exit;
        }

        $foto = $this->handleFoto(token: $token, tipo: 'professor');
        if ($foto === false) exit;

        $db = Database::getInstance();

        // Check email uniqueness
        $exists = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $exists->execute([$data['email']]);
        if ($exists->fetch()) {
            $_SESSION['form_errors'] = ['email' => 'Este e-mail já está cadastrado.'];
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/convite/professor/' . rawurlencode($token));
            exit;
        }

        $hash = password_hash($data['senha'], PASSWORD_BCRYPT, ['cost' => 12]);

        $db->beginTransaction();
        try {
            $ins = $db->prepare("
                INSERT INTO usuarios (nome, email, senha_hash, perfil, foto, status, criado_em)
                VALUES (?, ?, ?, 'professor', ?, 'ativo', NOW())
            ");
            $ins->execute([$data['nome'], $data['email'], $hash, $foto ?: null]);
            $userId = (int) $db->lastInsertId();

            $db->prepare(
                "INSERT INTO nucleo_professores (nucleo_id, usuario_id, criado_em) VALUES (?, ?, NOW())"
            )->execute([$convite['nucleo_id'], $userId]);

            // Mark invite as used (single-use)
            $db->prepare(
                "UPDATE convites SET status = 'usado' WHERE id = ?"
            )->execute([$convite['id']]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[ConviteProfessor] ' . $e->getMessage());
            $_SESSION['form_errors'] = ['_geral' => 'Erro ao criar conta. Tente novamente.'];
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/convite/professor/' . rawurlencode($token));
            exit;
        }

        Security::auditLog('cadastro_convite', 'usuarios', $userId);

        // Notify super_admin
        require_once ROOT_PATH . '/app/helpers/Mailer.php';
        Mailer::notifyNewProfessor($data['nome'], $data['email'], $convite['nucleo_nome']);

        // Auto-login
        $userRow = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $userRow->execute([$userId]);
        Auth::login($userRow->fetch());

        $_SESSION['flash_success'] = 'Bem-vindo(a), ' . $data['nome'] . '! Seu cadastro foi realizado.';
        header('Location: ' . APP_URL . '/professor/dashboard');
        exit;
    }

    // ── Aluno — show ─────────────────────────────────────────────────────────

    public function showAluno(string $token): void
    {
        if (Auth::check()) {
            header('Location: ' . Auth::dashboardUrl());
            exit;
        }

        $convite = $this->resolveToken($token, 'aluno');
        if (!$convite) $this->showExpired();

        $errors  = $_SESSION['form_errors'] ?? [];
        $oldData = $_SESSION['form_data']   ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        require_once ROOT_PATH . '/app/views/convite/aluno.php';
    }

    // ── Aluno — process ───────────────────────────────────────────────────────

    public function processAluno(string $token): void
    {
        Security::verifyCsrf();

        $convite = $this->resolveToken($token, 'aluno');
        if (!$convite) $this->showExpired();

        [$data, $errors] = $this->validateAccountForm(withExtra: true);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/convite/aluno/' . rawurlencode($token));
            exit;
        }

        $foto = $this->handleFoto(token: $token, tipo: 'aluno');
        if ($foto === false) exit;

        $db = Database::getInstance();

        // Check email uniqueness
        $exists = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $exists->execute([$data['email']]);
        if ($exists->fetch()) {
            $_SESSION['form_errors'] = ['email' => 'Este e-mail já está cadastrado.'];
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/convite/aluno/' . rawurlencode($token));
            exit;
        }

        $hash = password_hash($data['senha'], PASSWORD_BCRYPT, ['cost' => 12]);

        $db->beginTransaction();
        try {
            $ins = $db->prepare("
                INSERT INTO usuarios (nome, email, senha_hash, perfil, foto, status, criado_em)
                VALUES (?, ?, ?, 'aluno', ?, 'ativo', NOW())
            ");
            $ins->execute([$data['nome'], $data['email'], $hash, $foto ?: null]);
            $userId = (int) $db->lastInsertId();

            // Create aluno record linked to usuario
            $insAluno = $db->prepare("
                INSERT INTO alunos
                  (nucleo_id, usuario_id, nome, email, telefone, whatsapp,
                   data_nascimento, foto, status, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ativo', NOW())
            ");
            $insAluno->execute([
                $convite['nucleo_id'],
                $userId,
                $data['nome'],
                $data['email'],
                $data['telefone'] ?: null,
                $data['telefone'] ?: null, // whatsapp = same as telefone from public form
                $data['nascimento'] ?: null,
                $foto ?: null,
            ]);

            // Multi-use token — do NOT mark as 'usado'

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[ConviteAluno] ' . $e->getMessage());
            $_SESSION['form_errors'] = ['_geral' => 'Erro ao criar conta. Tente novamente.'];
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/convite/aluno/' . rawurlencode($token));
            exit;
        }

        Security::auditLog('cadastro_convite', 'alunos', $userId);

        // Notify professor(es) do núcleo
        require_once ROOT_PATH . '/app/helpers/Mailer.php';
        $profStmt = $db->prepare(
            "SELECT u.nome, u.email FROM nucleo_professores np
             JOIN usuarios u ON u.id = np.usuario_id
             WHERE np.nucleo_id = ? AND u.status = 'ativo'"
        );
        $profStmt->execute([$convite['nucleo_id']]);
        $professores = $profStmt->fetchAll();
        Mailer::notifyNewAluno($data['nome'], $convite['nucleo_nome'], $professores);

        // Auto-login
        $userRow = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $userRow->execute([$userId]);
        Auth::login($userRow->fetch());

        $_SESSION['flash_success'] = 'Bem-vindo(a), ' . $data['nome'] . '! Cadastro realizado com sucesso.';
        header('Location: ' . APP_URL . '/aluno/dashboard');
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateAccountForm(bool $withExtra = false): array
    {
        $data = [
            'nome'      => Security::sanitize($_POST['nome']  ?? ''),
            'email'     => Security::sanitizeEmail($_POST['email'] ?? ''),
            'senha'     => $_POST['senha']         ?? '',
            'confirm'   => $_POST['senha_confirm'] ?? '',
            'telefone'  => Security::sanitize($_POST['telefone'] ?? ''),
            'nascimento'=> Security::sanitize($_POST['data_nascimento'] ?? ''),
        ];

        $errors = [];

        if (!$data['nome'])  $errors['nome']  = 'Nome é obrigatório.';
        if (!$data['email']) $errors['email'] = 'E-mail é obrigatório.';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }

        if (strlen($data['senha']) < 8) {
            $errors['senha'] = 'Senha deve ter pelo menos 8 caracteres.';
        }
        if ($data['senha'] !== $data['confirm']) {
            $errors['senha_confirm'] = 'As senhas não coincidem.';
        }

        return [$data, $errors];
    }

    private function handleFoto(string $token, string $tipo): string|false
    {
        if (empty($_FILES['foto']['name'])) return '';

        try {
            require_once ROOT_PATH . '/app/helpers/Upload.php';
            return Upload::image($_FILES['foto'], 'fotos', 400, 400, 80);
        } catch (RuntimeException $e) {
            $_SESSION['form_errors'] = ['foto' => $e->getMessage()];
            $_SESSION['form_data']   = $_POST;
            header('Location: ' . APP_URL . '/convite/' . $tipo . '/' . rawurlencode($token));
            return false;
        }
    }
}
