<?php

class AuthController
{
    public function redirectDashboard(): void
    {
        if (Auth::check()) {
            header('Location: ' . Auth::dashboardUrl());
        } else {
            header('Location: ' . APP_URL . '/login');
        }
        exit;
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . Auth::dashboardUrl());
            exit;
        }

        $error   = $_SESSION['login_error'] ?? null;
        $success = $_SESSION['login_success'] ?? null;
        unset($_SESSION['login_error'], $_SESSION['login_success']);

        require_once ROOT_PATH . '/app/views/auth/login.php';
    }

    public function processLogin(): void
    {
        Security::verifyCsrf();

        $email = Security::sanitizeEmail($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($email === '' || $senha === '') {
            $this->loginError('Preencha todos os campos.');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->loginError('E-mail ou senha incorretos.');
            return;
        }

        if (Security::isRateLimited($email)) {
            $this->loginError(
                'Muitas tentativas de login. Aguarde ' . LOGIN_LOCKOUT_MINUTES . ' minutos.'
            );
            return;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, nome, email, senha_hash, perfil, foto, status
             FROM usuarios
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($senha, $user['senha_hash'])) {
            Security::recordLoginAttempt($email, false);
            $this->loginError('E-mail ou senha incorretos.');
            return;
        }

        if ($user['status'] !== 'ativo') {
            $this->loginError('Conta inativa. Entre em contato com o administrador.');
            return;
        }

        Security::recordLoginAttempt($email, true);
        Auth::login($user);
        Security::auditLog('login', 'usuarios', $user['id']);

        header('Location: ' . Auth::dashboardUrl());
        exit;
    }

    public function logout(): void
    {
        if (Auth::check()) {
            Security::auditLog('logout', 'usuarios', Auth::id());
        }
        Auth::logout();
    }

    private function loginError(string $msg): never
    {
        $_SESSION['login_error'] = $msg;
        header('Location: ' . APP_URL . '/login');
        exit;
    }
}
