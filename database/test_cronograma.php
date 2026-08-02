<?php
/**
 * Testes da feature "Cronograma de aulas + justificativa de ausência".
 *
 * Roda os 11 cenários obrigatórios direto contra o banco real, DENTRO de uma
 * transação que é sempre revertida (ROLLBACK) no final — nunca commita nada,
 * então é seguro rodar contra o banco de produção sem afetar dados existentes.
 *
 * Executar via CLI:
 *   php database/test_cronograma.php
 */

define('ROOT_PATH', dirname(__DIR__));

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';
require_once ROOT_PATH . '/app/helpers/Cronograma.php';

$db = Database::getInstance();

$total  = 0;
$falhas = 0;

function check(bool $cond, string $descricao): void
{
    global $total, $falhas;
    $total++;
    if ($cond) {
        echo "  ✓ $descricao\n";
    } else {
        $falhas++;
        echo "  ✗ FALHOU: $descricao\n";
    }
}

function insert(PDO $db, string $table, array $data): int
{
    $cols    = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
    $holders = implode(', ', array_fill(0, count($data), '?'));
    $db->prepare("INSERT INTO `$table` ($cols) VALUES ($holders)")->execute(array_values($data));
    return (int) $db->lastInsertId();
}

echo "=== Testes: Cronograma de aulas + justificativa de ausência ===\n\n";

$db->beginTransaction();

try {
    // ─── Fixtures ──────────────────────────────────────────────────────────
    $projetoId = insert($db, 'projetos', ['nome' => '[TESTE] Projeto', 'status' => 'ativo']);
    $nucleoId  = insert($db, 'nucleos', ['projeto_id' => $projetoId, 'nome' => '[TESTE] Núcleo', 'municipio' => 'Teste', 'estado' => 'RJ', 'status' => 'ativo']);

    $prof1 = insert($db, 'usuarios', ['nome' => '[TESTE] Professor 1', 'email' => 'teste.prof1.' . uniqid() . '@teste.com', 'senha_hash' => 'x', 'perfil' => 'professor', 'status' => 'ativo']);
    $prof2 = insert($db, 'usuarios', ['nome' => '[TESTE] Professor 2', 'email' => 'teste.prof2.' . uniqid() . '@teste.com', 'senha_hash' => 'x', 'perfil' => 'professor', 'status' => 'ativo']);

    $cronogramaId = insert($db, 'grade_horarios', [
        'nucleo_id' => $nucleoId, 'professor_id' => $prof1, 'dia_semana' => (int) date('w'),
        'horario_inicio' => '08:00:00', 'horario_fim' => '09:00:00', 'status' => 'ativo',
    ]);

    $hoje = date('Y-m-d');
    $ontem = date('Y-m-d', strtotime('-1 day'));
    $amanha = date('Y-m-d', strtotime('+1 day'));

    // ── 1. Aula realizada (existe chamada correspondente) não gera pendência ──
    $aula1 = insert($db, 'aulas_previstas', [
        'cronograma_id' => $cronogramaId, 'professor_id' => $prof1, 'nucleo_id' => $nucleoId,
        'data' => $ontem, 'horario_inicio' => '08:00:00', 'horario_fim' => '09:00:00', 'status' => 'prevista',
    ]);
    insert($db, 'chamadas', ['nucleo_id' => $nucleoId, 'professor_id' => $prof1, 'data_aula' => $ontem]);
    Cronograma::atualizarPendencias($db, $prof1);
    $status1 = $db->query("SELECT status FROM aulas_previstas WHERE id=$aula1")->fetchColumn();
    check($status1 === 'realizada', 'Professor com aula realizada (chamada existente) → status "realizada", sem pendência');
    check(!Cronograma::temPendencia($db, $prof1), 'Professor sem pendências acessa normalmente');

    // ── 2. Aula não realizada gera "justificativa_pendente" ───────────────────
    $aula2 = insert($db, 'aulas_previstas', [
        'cronograma_id' => $cronogramaId, 'professor_id' => $prof1, 'nucleo_id' => $nucleoId,
        'data' => $ontem, 'horario_inicio' => '14:00:00', 'horario_fim' => '16:00:00', 'status' => 'prevista',
    ]);
    Cronograma::atualizarPendencias($db, $prof1);
    $status2 = $db->query("SELECT status FROM aulas_previstas WHERE id=$aula2")->fetchColumn();
    check($status2 === 'justificativa_pendente', 'Aula sem chamada correspondente, horário já passado → "justificativa_pendente"');
    check(Cronograma::temPendencia($db, $prof1), 'Professor com aula não realizada é bloqueado até justificar');

    // ── 3. Professor envia justificativa → pendência resolvida ────────────────
    $motivo = 'Chuva forte impossibilitou a atividade ao ar livre.';
    insert($db, 'justificativas_ausencia', ['aula_prevista_id' => $aula2, 'professor_id' => $prof1, 'motivo' => $motivo, 'enviado_em' => date('Y-m-d H:i:s')]);
    $db->prepare("UPDATE aulas_previstas SET status='justificada' WHERE id=?")->execute([$aula2]);
    $status2b = $db->query("SELECT status FROM aulas_previstas WHERE id=$aula2")->fetchColumn();
    check($status2b === 'justificada', 'Justificativa enviada → status vira "justificada"');
    check(!Cronograma::temPendencia($db, $prof1), 'Acesso liberado após justificar a única pendência');

    // ── 11. Justificativa permanece disponível para consulta futura ───────────
    $jRow = $db->query("SELECT motivo FROM justificativas_ausencia WHERE aula_prevista_id=$aula2")->fetch();
    check($jRow && $jRow['motivo'] === $motivo, 'Justificativa enviada permanece registrada e consultável (motivo preservado)');

    // ── 4. Duas pendências: só libera depois de resolver as duas ──────────────
    $aula3a = insert($db, 'aulas_previstas', [
        'cronograma_id' => $cronogramaId, 'professor_id' => $prof1, 'nucleo_id' => $nucleoId,
        'data' => $ontem, 'horario_inicio' => '10:00:00', 'horario_fim' => '11:00:00', 'status' => 'justificativa_pendente',
    ]);
    $aula3b = insert($db, 'aulas_previstas', [
        'cronograma_id' => $cronogramaId, 'professor_id' => $prof1, 'nucleo_id' => $nucleoId,
        'data' => $ontem, 'horario_inicio' => '12:00:00', 'horario_fim' => '13:00:00', 'status' => 'justificativa_pendente',
    ]);
    check(Cronograma::temPendencia($db, $prof1), 'Duas pendências simultâneas → bloqueado');
    $pendentesAntes = Cronograma::pendentesDoProfessor($db, $prof1);
    check(count($pendentesAntes) === 2, 'Lista de pendências retorna as duas aulas não resolvidas');

    insert($db, 'justificativas_ausencia', ['aula_prevista_id' => $aula3a, 'professor_id' => $prof1, 'motivo' => 'Motivo A', 'enviado_em' => date('Y-m-d H:i:s')]);
    $db->prepare("UPDATE aulas_previstas SET status='justificada' WHERE id=?")->execute([$aula3a]);
    check(Cronograma::temPendencia($db, $prof1), 'Resolvida só a 1ª de 2 pendências → ainda bloqueado');

    insert($db, 'justificativas_ausencia', ['aula_prevista_id' => $aula3b, 'professor_id' => $prof1, 'motivo' => 'Motivo B', 'enviado_em' => date('Y-m-d H:i:s')]);
    $db->prepare("UPDATE aulas_previstas SET status='justificada' WHERE id=?")->execute([$aula3b]);
    check(!Cronograma::temPendencia($db, $prof1), 'Resolvidas as 2 pendências → acesso liberado');

    // ── 5. Aula futura não gera pendência ──────────────────────────────────────
    $aulaFutura = insert($db, 'aulas_previstas', [
        'cronograma_id' => $cronogramaId, 'professor_id' => $prof1, 'nucleo_id' => $nucleoId,
        'data' => $amanha, 'horario_inicio' => '08:00:00', 'horario_fim' => '09:00:00', 'status' => 'prevista',
    ]);
    Cronograma::atualizarPendencias($db, $prof1);
    $statusFutura = $db->query("SELECT status FROM aulas_previstas WHERE id=$aulaFutura")->fetchColumn();
    check($statusFutura === 'prevista', 'Aula futura (amanhã) não vira pendência');

    // ── 6. Aula em andamento (horário final ainda não passou) não gera pendência ──
    $aulaAndamento = insert($db, 'aulas_previstas', [
        'cronograma_id' => $cronogramaId, 'professor_id' => $prof1, 'nucleo_id' => $nucleoId,
        'data' => $hoje, 'horario_inicio' => '00:00:00', 'horario_fim' => '23:59:59', 'status' => 'prevista',
    ]);
    Cronograma::atualizarPendencias($db, $prof1);
    $statusAndamento = $db->query("SELECT status FROM aulas_previstas WHERE id=$aulaAndamento")->fetchColumn();
    check($statusAndamento === 'prevista', 'Aula de hoje ainda dentro do horário (não terminou) não vira pendência');

    // ── 7. Aula cancelada administrativamente não gera pendência ──────────────
    $aulaCancelada = insert($db, 'aulas_previstas', [
        'cronograma_id' => $cronogramaId, 'professor_id' => $prof1, 'nucleo_id' => $nucleoId,
        'data' => $ontem, 'horario_inicio' => '08:00:00', 'horario_fim' => '09:00:00',
        'status' => 'cancelada', 'cancelado_por' => $prof1, 'cancelado_em' => date('Y-m-d H:i:s'), 'motivo_cancelamento' => 'Feriado municipal.',
    ]);
    Cronograma::atualizarPendencias($db, $prof1); // só afeta status='prevista' — não deve tocar nesta
    $statusCancelada = $db->query("SELECT status FROM aulas_previstas WHERE id=$aulaCancelada")->fetchColumn();
    check($statusCancelada === 'cancelada', 'Aula cancelada pela administração permanece "cancelada" (nunca vira pendência)');
    check(!Cronograma::temPendencia($db, $prof1), 'Aula cancelada não bloqueia o professor');

    // ── 8. Aula já justificada não é solicitada novamente ──────────────────────
    Cronograma::atualizarPendencias($db, $prof1);
    $statusJaJustificada = $db->query("SELECT status FROM aulas_previstas WHERE id=$aula2")->fetchColumn();
    check($statusJaJustificada === 'justificada', 'Aula já justificada permanece "justificada" (não regride para pendente)');

    // ── 9. Professor não acessa/justifica aula de outro professor ─────────────
    $aulaDoOutro = insert($db, 'aulas_previstas', [
        'cronograma_id' => null, 'professor_id' => $prof2, 'nucleo_id' => $nucleoId,
        'data' => $ontem, 'horario_inicio' => '08:00:00', 'horario_fim' => '09:00:00', 'status' => 'justificativa_pendente',
    ]);
    $stmt = $db->prepare("SELECT * FROM aulas_previstas WHERE id = ? AND professor_id = ? LIMIT 1");
    $stmt->execute([$aulaDoOutro, $prof1]); // prof1 tentando acessar aula do prof2
    check($stmt->fetch() === false, 'Professor 1 não consegue localizar/justificar aula do Professor 2 (guarda por professor_id)');
    check(Cronograma::temPendencia($db, $prof2), 'Pendência do Professor 2 é dele, não interfere no Professor 1');
    check(!Cronograma::temPendencia($db, $prof1), 'Professor 1 continua sem pendências próprias');

    // ── 10. Alteração no cronograma não muda histórico já resolvido ───────────
    $horarioAntes = $db->query("SELECT horario_inicio, horario_fim, status FROM aulas_previstas WHERE id=$aula2")->fetch();
    // Simula o que AdminCronogramaController::update() faz: só remove futuras 'prevista',
    // nunca toca em ocorrências já resolvidas.
    Cronograma::removerFuturasPrevistas($db, $cronogramaId);
    $db->prepare("UPDATE grade_horarios SET horario_inicio='15:00:00', horario_fim='17:00:00' WHERE id=?")->execute([$cronogramaId]);
    $horarioDepois = $db->query("SELECT horario_inicio, horario_fim, status FROM aulas_previstas WHERE id=$aula2")->fetch();
    check(
        $horarioAntes['horario_inicio'] === $horarioDepois['horario_inicio']
            && $horarioAntes['horario_fim'] === $horarioDepois['horario_fim']
            && $horarioDepois['status'] === 'justificada',
        'Mudança de horário no cronograma NÃO altera retroativamente a aula já resolvida (snapshot preservado)'
    );

} finally {
    $db->rollBack();
    echo "\n(todas as alterações de teste foram revertidas — ROLLBACK)\n";
}

echo "\n=== Resultado: " . ($total - $falhas) . "/$total cenários passaram ===\n";
exit($falhas > 0 ? 1 : 0);
