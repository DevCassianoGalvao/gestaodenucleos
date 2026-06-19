<?php

class Auth
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        ini_set('session.use_strict_mode',   '1');
        ini_set('session.gc_maxlifetime',    (string) SESSION_LIFETIME);
        ini_set('session.cookie_httponly',   '1');
        ini_set('session.cookie_samesite',   'Strict');

        session_name(SESSION_NAME);
        session_start();

        // Timeout por inatividade
        if (isset($_SESSION['last_activity'])
            && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME
        ) {
            self::destroySession();
        } else {
            $_SESSION['last_activity'] = time();
        }
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            self::flashAndRedirect('login_error', 'Faça login para continuar.', '/login');
        }
    }

    public static function requireRole(string|array $roles): void
    {
        self::requireAuth();

        $roles    = (array) $roles;
        $userRole = $_SESSION['perfil'] ?? '';

        if (!in_array($userRole, $roles, true)) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['nome']          = $user['nome'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['perfil']        = $user['perfil'];
        $_SESSION['foto']          = $user['foto'] ?? null;
        $_SESSION['last_activity'] = time();
    }

    public static function logout(): void
    {
        self::destroySession();
        header('Location: ' . APP_URL . '/login');
        exit;
    }

    public static function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id'     => $_SESSION['user_id'],
            'nome'   => $_SESSION['nome'],
            'email'  => $_SESSION['email'],
            'perfil' => $_SESSION['perfil'],
            'foto'   => $_SESSION['foto'] ?? null,
        ];
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function perfil(): string
    {
        return $_SESSION['perfil'] ?? '';
    }

    public static function dashboardUrl(): string
    {
        return match (self::perfil()) {
            'super_admin' => APP_URL . '/admin/dashboard',
            'professor'   => APP_URL . '/professor/dashboard',
            'aluno'       => APP_URL . '/aluno/dashboard',
            default       => APP_URL . '/login',
        };
    }

    private static function flashAndRedirect(string $key, string $msg, string $path): never
    {
        $_SESSION[$key] = $msg;
        header('Location: ' . APP_URL . $path);
        exit;
    }
}
