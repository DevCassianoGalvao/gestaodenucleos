<?php

class Security
{
    // ─── CSRF ────────────────────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . self::esc(self::csrfToken())
            . '">';
    }

    public static function verifyCsrf(): void
    {
        $submitted = $_POST['csrf_token'] ?? '';
        $stored    = $_SESSION['csrf_token'] ?? '';

        if (!$stored || !hash_equals($stored, $submitted)) {
            http_response_code(403);
            // Regenerate token so next attempt works
            unset($_SESSION['csrf_token']);
            die('Requisição inválida. Recarregue a página e tente novamente.');
        }
    }

    // ─── Output escaping ─────────────────────────────────────────────────────

    public static function esc(mixed $val): string
    {
        return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
    }

    // ─── Input sanitization ──────────────────────────────────────────────────

    public static function sanitize(string $input): string
    {
        return trim(strip_tags($input));
    }

    public static function sanitizeEmail(string $input): string
    {
        return strtolower(trim((string) filter_var($input, FILTER_SANITIZE_EMAIL)));
    }

    // ─── Rate limiting (requires DB — active after Phase 2) ─────────────────

    public static function isRateLimited(string $email): bool
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM login_attempts
                 WHERE (email = ? OR ip = ?)
                   AND sucesso = 0
                   AND tentativa_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
            );
            $stmt->execute([$email, self::clientIp(), LOGIN_LOCKOUT_MINUTES]);
            return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
        } catch (PDOException) {
            return false; // Fail open if table doesn't exist yet
        }
    }

    public static function recordLoginAttempt(string $email, bool $success): void
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO login_attempts (email, ip, sucesso, tentativa_em) VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([$email, self::clientIp(), $success ? 1 : 0]);
        } catch (PDOException) {
            // Fail silently — don't break login if table missing
        }
    }

    // ─── Audit log (requires DB — active after Phase 2) ─────────────────────

    public static function auditLog(string $acao, string $tabela = '', mixed $registroId = null): void
    {
        $userId = Auth::id();
        if (!$userId) {
            return;
        }

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO audit_log (usuario_id, acao, tabela_afetada, registro_id, ip, user_agent, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId,
                $acao,
                $tabela,
                $registroId,
                self::clientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        } catch (PDOException) {
            // Fail silently
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public static function clientIp(): string
    {
        // Trusted proxy headers — only use if behind a known proxy
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }

    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
