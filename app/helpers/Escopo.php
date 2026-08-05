<?php

/**
 * Escopo de acesso: EM QUAIS institutos/projetos/núcleos o usuário pode atuar.
 * Diferente de Permissão (O QUE ele pode fazer — ver Permissao.php).
 *
 * super_admin sempre enxerga tudo. Demais usuários herdam de cima para baixo:
 * escopo de instituto → todos os projetos e núcleos dele; escopo de projeto →
 * todos os núcleos dele; escopo de núcleo → só aquele núcleo. Professores
 * cadastrados via nucleo_professores (fluxo tradicional) continuam
 * funcionando sem precisar de linha em escopos_usuario.
 */
class Escopo
{
    private static array $cacheSuper = [];

    public static function isSuperAdmin(int $usuarioId): bool
    {
        if (isset(self::$cacheSuper[$usuarioId])) {
            return self::$cacheSuper[$usuarioId];
        }
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT perfil FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$usuarioId]);
        return self::$cacheSuper[$usuarioId] = ($stmt->fetchColumn() === 'super_admin');
    }

    public static function institutosPermitidos(int $usuarioId): array
    {
        $db = Database::getInstance();
        if (self::isSuperAdmin($usuarioId)) {
            return array_map('intval', $db->query("SELECT id FROM institutos")->fetchAll(PDO::FETCH_COLUMN));
        }

        $direto = self::idsPorTipo($db, $usuarioId, 'instituto');

        $viaProjeto = $db->prepare("
            SELECT DISTINCT p.instituto_id FROM escopos_usuario eu
            JOIN projetos p ON p.id = eu.referencia_id
            WHERE eu.usuario_id = ? AND eu.tipo = 'projeto'
        ");
        $viaProjeto->execute([$usuarioId]);

        $viaNucleo = $db->prepare("
            SELECT DISTINCT p.instituto_id FROM escopos_usuario eu
            JOIN nucleos n ON n.id = eu.referencia_id
            JOIN projetos p ON p.id = n.projeto_id
            WHERE eu.usuario_id = ? AND eu.tipo = 'nucleo'
        ");
        $viaNucleo->execute([$usuarioId]);

        return self::unicos(array_merge(
            $direto,
            $viaProjeto->fetchAll(PDO::FETCH_COLUMN),
            $viaNucleo->fetchAll(PDO::FETCH_COLUMN)
        ));
    }

    public static function projetosPermitidos(int $usuarioId): array
    {
        $db = Database::getInstance();
        if (self::isSuperAdmin($usuarioId)) {
            return array_map('intval', $db->query("SELECT id FROM projetos")->fetchAll(PDO::FETCH_COLUMN));
        }

        $direto = self::idsPorTipo($db, $usuarioId, 'projeto');

        $institutoIds = self::idsPorTipo($db, $usuarioId, 'instituto');
        $viaInstituto = [];
        if ($institutoIds) {
            [$where, $params] = self::whereIn($institutoIds, 'instituto_id');
            $stmt = $db->prepare("SELECT id FROM projetos WHERE $where");
            $stmt->execute($params);
            $viaInstituto = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $viaNucleo = $db->prepare("
            SELECT DISTINCT n.projeto_id FROM escopos_usuario eu
            JOIN nucleos n ON n.id = eu.referencia_id
            WHERE eu.usuario_id = ? AND eu.tipo = 'nucleo'
        ");
        $viaNucleo->execute([$usuarioId]);

        return self::unicos(array_merge($direto, $viaInstituto, $viaNucleo->fetchAll(PDO::FETCH_COLUMN)));
    }

    public static function nucleosPermitidos(int $usuarioId): array
    {
        $db = Database::getInstance();
        if (self::isSuperAdmin($usuarioId)) {
            return array_map('intval', $db->query("SELECT id FROM nucleos")->fetchAll(PDO::FETCH_COLUMN));
        }

        $direto = self::idsPorTipo($db, $usuarioId, 'nucleo');

        $projetoIds = self::projetosPermitidos($usuarioId); // já herda instituto
        $viaProjeto = [];
        if ($projetoIds) {
            [$where, $params] = self::whereIn($projetoIds, 'projeto_id');
            $stmt = $db->prepare("SELECT id FROM nucleos WHERE $where");
            $stmt->execute($params);
            $viaProjeto = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Compatibilidade com o fluxo tradicional de professor (nucleo_professores).
        $viaProfessor = $db->prepare("SELECT nucleo_id FROM nucleo_professores WHERE usuario_id = ?");
        $viaProfessor->execute([$usuarioId]);

        return self::unicos(array_merge($direto, $viaProjeto, $viaProfessor->fetchAll(PDO::FETCH_COLUMN)));
    }

    public static function podeAcessarInstituto(int $usuarioId, int $institutoId): bool
    {
        return in_array($institutoId, self::institutosPermitidos($usuarioId), true);
    }

    public static function podeAcessarProjeto(int $usuarioId, int $projetoId): bool
    {
        return in_array($projetoId, self::projetosPermitidos($usuarioId), true);
    }

    public static function podeAcessarNucleo(int $usuarioId, int $nucleoId): bool
    {
        return in_array($nucleoId, self::nucleosPermitidos($usuarioId), true);
    }

    /**
     * Monta um trecho `coluna IN (...)` seguro para usar em queries já
     * existentes. Lista vazia = nenhum acesso (nunca casa com ID real, não
     * remove o filtro por engano).
     */
    public static function whereIn(array $ids, string $coluna): array
    {
        if (empty($ids)) {
            return ["$coluna = -1", []];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return ["$coluna IN ($placeholders)", array_values($ids)];
    }

    /** Escopos concedidos a um usuário, com nome legível — usado na tela de edição. */
    public static function doUsuario(int $usuarioId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM escopos_usuario WHERE usuario_id = ? ORDER BY tipo, referencia_id");
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll();
    }

    /**
     * Salva o conjunto exato de escopos de um usuário (substitui tudo).
     * Nunca permite conceder escopo que o concedente não possua (exceto super_admin).
     * $desejados = array de ['tipo' => 'instituto'|'projeto'|'nucleo', 'referencia_id' => int]
     */
    public static function salvar(PDO $db, int $usuarioId, array $desejados, int $concedidoPor, bool $concedentEhSuperAdmin): void
    {
        if (!$concedentEhSuperAdmin) {
            $institutosDoConcedente = self::institutosPermitidos($concedidoPor);
            $projetosDoConcedente   = self::projetosPermitidos($concedidoPor);
            $nucleosDoConcedente    = self::nucleosPermitidos($concedidoPor);

            $desejados = array_values(array_filter($desejados, function ($d) use ($institutosDoConcedente, $projetosDoConcedente, $nucleosDoConcedente) {
                return match ($d['tipo']) {
                    'instituto' => in_array($d['referencia_id'], $institutosDoConcedente, true),
                    'projeto'   => in_array($d['referencia_id'], $projetosDoConcedente, true),
                    'nucleo'    => in_array($d['referencia_id'], $nucleosDoConcedente, true),
                    default     => false,
                };
            }));
        }

        $db->prepare("DELETE FROM escopos_usuario WHERE usuario_id = ?")->execute([$usuarioId]);

        $ins = $db->prepare("INSERT INTO escopos_usuario (usuario_id, tipo, referencia_id, concedido_por) VALUES (?, ?, ?, ?)");
        foreach ($desejados as $d) {
            $ins->execute([$usuarioId, $d['tipo'], (int) $d['referencia_id'], $concedidoPor]);
        }
    }

    private static function idsPorTipo(PDO $db, int $usuarioId, string $tipo): array
    {
        $stmt = $db->prepare("SELECT referencia_id FROM escopos_usuario WHERE usuario_id = ? AND tipo = ?");
        $stmt->execute([$usuarioId, $tipo]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function unicos(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
