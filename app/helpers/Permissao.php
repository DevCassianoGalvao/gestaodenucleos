<?php

/**
 * Permissões granulares. Independente de escopo (ver Escopo.php) — permissão
 * diz O QUE o usuário pode fazer; escopo diz EM QUAIS institutos/projetos/
 * núcleos ele pode fazer. super_admin sempre tem todas as permissões.
 */
class Permissao
{
    private static array $cache = [];

    public static function has(int $usuarioId, string $chave): bool
    {
        if (isset(self::$cache[$usuarioId][$chave])) {
            return self::$cache[$usuarioId][$chave];
        }

        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT perfil FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$usuarioId]);
        if ($stmt->fetchColumn() === 'super_admin') {
            return self::$cache[$usuarioId][$chave] = true;
        }

        $stmt = $db->prepare("
            SELECT 1 FROM usuario_permissoes up
            JOIN permissoes p ON p.id = up.permissao_id
            WHERE up.usuario_id = ? AND p.chave = ? LIMIT 1
        ");
        $stmt->execute([$usuarioId, $chave]);
        return self::$cache[$usuarioId][$chave] = (bool) $stmt->fetch();
    }

    /** Aceita uma chave ou uma lista — libera se tiver ao menos uma. */
    public static function requer(string|array $chaves): void
    {
        $usuarioId = Auth::id();
        if (!$usuarioId) {
            http_response_code(403);
            require_once ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }

        foreach ((array) $chaves as $chave) {
            if (self::has($usuarioId, $chave)) {
                return;
            }
        }

        http_response_code(403);
        require_once ROOT_PATH . '/app/views/errors/403.php';
        exit;
    }

    public static function todasDoUsuario(int $usuarioId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT p.chave FROM usuario_permissoes up
            JOIN permissoes p ON p.id = up.permissao_id
            WHERE up.usuario_id = ?
        ");
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Catálogo completo de permissões, agrupado por módulo — usado na tela de edição de usuário. */
    public static function catalogo(): array
    {
        $db = Database::getInstance();
        $rows = $db->query("SELECT * FROM permissoes ORDER BY ordem ASC")->fetchAll();

        $agrupado = [];
        foreach ($rows as $r) {
            $agrupado[$r['modulo']][] = $r;
        }
        return $agrupado;
    }

    /**
     * Salva o conjunto exato de permissões de um usuário (substitui tudo).
     * $chavesPermitidas = lista de `chave` marcadas no formulário.
     * $chavesConcedente = permissões que quem está salvando TEM — nunca se pode
     * conceder a outra pessoa uma permissão que quem concede não possui (exceto super_admin).
     */
    public static function salvar(PDO $db, int $usuarioId, array $chavesDesejadas, int $concedidoPor, bool $concedentEhSuperAdmin): void
    {
        if (!$concedentEhSuperAdmin) {
            $chavesDoConcedente = self::todasDoUsuario($concedidoPor);
            $chavesDesejadas = array_values(array_intersect($chavesDesejadas, $chavesDoConcedente));
        }

        $db->prepare("DELETE FROM usuario_permissoes WHERE usuario_id = ?")->execute([$usuarioId]);

        if (empty($chavesDesejadas)) {
            return;
        }

        $stmt = $db->prepare("SELECT id, chave FROM permissoes WHERE chave IN (" . implode(',', array_fill(0, count($chavesDesejadas), '?')) . ")");
        $stmt->execute($chavesDesejadas);
        $ids = $stmt->fetchAll();

        $ins = $db->prepare("INSERT INTO usuario_permissoes (usuario_id, permissao_id, concedido_por) VALUES (?, ?, ?)");
        foreach ($ids as $p) {
            $ins->execute([$usuarioId, $p['id'], $concedidoPor]);
        }
    }
}
