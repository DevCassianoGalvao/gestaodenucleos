<?php

/**
 * Lógica central do cronograma de aulas: geração de ocorrências (aulas_previstas)
 * a partir da grade recorrente (grade_horarios), transição de status conforme o
 * tempo passa, e checagem de pendências de justificativa.
 */
class Cronograma
{
    public const DIAS = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];

    /**
     * Gera as ocorrências (aulas_previstas) de um intervalo de datas a partir
     * dos horários ativos em grade_horarios. Idempotente — nunca duplica uma
     * ocorrência já existente para o mesmo (cronograma_id, data).
     *
     * @param int|null $cronogramaId Restringe a um único horário (usado ao criar/editar no admin).
     */
    public static function gerarOcorrenciasIntervalo(PDO $db, string $dataInicio, string $dataFim, ?int $cronogramaId = null): int
    {
        $where  = "gh.status = 'ativo' AND gh.professor_id IS NOT NULL";
        $params = [];
        if ($cronogramaId !== null) {
            $where   .= ' AND gh.id = ?';
            $params[] = $cronogramaId;
        }

        $stmt = $db->prepare("SELECT * FROM grade_horarios gh WHERE $where");
        $stmt->execute($params);
        $horarios = $stmt->fetchAll();
        if (!$horarios) {
            return 0;
        }

        $existeStmt = $db->prepare(
            "SELECT id FROM aulas_previstas WHERE cronograma_id = ? AND data = ? LIMIT 1"
        );
        $insertStmt = $db->prepare(
            "INSERT INTO aulas_previstas
                (cronograma_id, professor_id, nucleo_id, data, horario_inicio, horario_fim, status)
             VALUES (?, ?, ?, ?, ?, ?, 'prevista')"
        );

        $inseridas = 0;
        $inicio = new DateTime($dataInicio);
        $fim    = new DateTime($dataFim);

        for ($d = clone $inicio; $d <= $fim; $d->modify('+1 day')) {
            $diaSemana = (int) $d->format('w'); // 0=Dom..6=Sáb, igual à coluna dia_semana
            $dataStr   = $d->format('Y-m-d');

            foreach ($horarios as $h) {
                if ((int) $h['dia_semana'] !== $diaSemana) {
                    continue;
                }

                $existeStmt->execute([$h['id'], $dataStr]);
                if ($existeStmt->fetch()) {
                    continue;
                }

                $insertStmt->execute([
                    $h['id'], $h['professor_id'], $h['nucleo_id'], $dataStr,
                    $h['horario_inicio'], $h['horario_fim'],
                ]);
                $inseridas++;
            }
        }

        return $inseridas;
    }

    /** Atalho para gerar ocorrências de uma única data (usado no acesso do professor). */
    public static function gerarOcorrenciasParaData(PDO $db, string $data, ?int $professorId = null): int
    {
        $where  = "gh.status = 'ativo' AND gh.professor_id IS NOT NULL AND gh.dia_semana = ?";
        $params = [(int) (new DateTime($data))->format('w')];
        if ($professorId !== null) {
            $where   .= ' AND gh.professor_id = ?';
            $params[] = $professorId;
        }

        $stmt = $db->prepare("SELECT * FROM grade_horarios gh WHERE $where");
        $stmt->execute($params);
        $horarios = $stmt->fetchAll();
        if (!$horarios) {
            return 0;
        }

        $existeStmt = $db->prepare("SELECT id FROM aulas_previstas WHERE cronograma_id = ? AND data = ? LIMIT 1");
        $insertStmt = $db->prepare(
            "INSERT INTO aulas_previstas
                (cronograma_id, professor_id, nucleo_id, data, horario_inicio, horario_fim, status)
             VALUES (?, ?, ?, ?, ?, ?, 'prevista')"
        );

        $inseridas = 0;
        foreach ($horarios as $h) {
            $existeStmt->execute([$h['id'], $data]);
            if ($existeStmt->fetch()) {
                continue;
            }
            $insertStmt->execute([$h['id'], $h['professor_id'], $h['nucleo_id'], $data, $h['horario_inicio'], $h['horario_fim']]);
            $inseridas++;
        }

        return $inseridas;
    }

    /**
     * Avança o status das ocorrências cujo horário final já passou:
     * - se existir uma `chamada` correspondente (mesmo professor/núcleo/data) → 'realizada'
     * - senão → 'justificativa_pendente'
     * Nunca mexe em ocorrências futuras ou em andamento (regra: aula só vira
     * pendência depois que o horário final passou).
     */
    public static function atualizarPendencias(PDO $db, ?int $professorId = null): int
    {
        $where  = '';
        $params = [];
        if ($professorId !== null) {
            $where   = 'AND ap.professor_id = ?';
            $params[] = $professorId;
        }

        $stmt = $db->prepare("
            UPDATE aulas_previstas ap
            LEFT JOIN chamadas c
                ON c.nucleo_id = ap.nucleo_id
               AND c.professor_id = ap.professor_id
               AND c.data_aula = ap.data
            SET ap.status = IF(c.id IS NOT NULL, 'realizada', 'justificativa_pendente'),
                ap.chamada_id = c.id
            WHERE ap.status = 'prevista'
              AND TIMESTAMP(ap.data, ap.horario_fim) < NOW()
              $where
        ");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function temPendencia(PDO $db, int $professorId): bool
    {
        $stmt = $db->prepare(
            "SELECT 1 FROM aulas_previstas WHERE professor_id = ? AND status = 'justificativa_pendente' LIMIT 1"
        );
        $stmt->execute([$professorId]);
        return (bool) $stmt->fetch();
    }

    public static function pendentesDoProfessor(PDO $db, int $professorId): array
    {
        $stmt = $db->prepare("
            SELECT ap.*, n.nome AS nucleo_nome
            FROM aulas_previstas ap
            JOIN nucleos n ON n.id = ap.nucleo_id
            WHERE ap.professor_id = ? AND ap.status = 'justificativa_pendente'
            ORDER BY ap.data ASC, ap.horario_inicio ASC
        ");
        $stmt->execute([$professorId]);
        return $stmt->fetchAll();
    }

    /**
     * Cancela (status='cancelada') as ocorrências futuras ainda 'prevista' de um
     * cronograma — usado quando o admin desativa ou exclui um horário, para que
     * o cronograma não gere pendência para aulas que não vão mais acontecer.
     * Nunca toca ocorrências já resolvidas (realizada/justificada/pendente/cancelada).
     */
    public static function cancelarFuturasPrevistas(PDO $db, int $cronogramaId, int $canceladoPor, string $motivo): int
    {
        $stmt = $db->prepare("
            UPDATE aulas_previstas
            SET status = 'cancelada', cancelado_por = ?, cancelado_em = NOW(), motivo_cancelamento = ?
            WHERE cronograma_id = ? AND status = 'prevista' AND data >= CURDATE()
        ");
        $stmt->execute([$canceladoPor, $motivo, $cronogramaId]);
        return $stmt->rowCount();
    }

    /** Remove ocorrências futuras ainda não resolvidas de um cronograma (usado antes de regenerar após edição). */
    public static function removerFuturasPrevistas(PDO $db, int $cronogramaId): int
    {
        $stmt = $db->prepare(
            "DELETE FROM aulas_previstas WHERE cronograma_id = ? AND status = 'prevista' AND data >= CURDATE()"
        );
        $stmt->execute([$cronogramaId]);
        return $stmt->rowCount();
    }
}
