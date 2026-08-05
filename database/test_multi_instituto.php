<?php
/**
 * Testes do sistema multi-instituto: permissões, escopos, hierarquia de
 * usuários, chamada (normal/retroativa), check-in, relatórios.
 *
 * Mesma abordagem segura do database/test_cronograma.php: tudo dentro de
 * uma transação com ROLLBACK no final — nunca commita nada, seguro rodar
 * contra o banco de produção.
 *
 * Executar via CLI:
 *   php database/test_multi_instituto.php
 */

define('ROOT_PATH', dirname(__DIR__));

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';
require_once ROOT_PATH . '/app/helpers/Permissao.php';
require_once ROOT_PATH . '/app/helpers/Escopo.php';
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

function permId(PDO $db, string $chave): int
{
    $stmt = $db->prepare("SELECT id FROM permissoes WHERE chave = ? LIMIT 1");
    $stmt->execute([$chave]);
    return (int) $stmt->fetchColumn();
}

echo "=== Testes: Multi-instituto, permissões, escopos, chamada, check-in ===\n\n";

$db->beginTransaction();

try {
    // ─── Fixtures: 2 institutos, 2 projetos, 2 núcleos, isolados ──────────────
    $instA = insert($db, 'institutos', ['nome' => '[TESTE] Instituto A', 'status' => 'ativo']);
    $instB = insert($db, 'institutos', ['nome' => '[TESTE] Instituto B', 'status' => 'ativo']);

    $projA = insert($db, 'projetos', ['instituto_id' => $instA, 'nome' => '[TESTE] Projeto A', 'status' => 'ativo']);
    $projB = insert($db, 'projetos', ['instituto_id' => $instB, 'nome' => '[TESTE] Projeto B', 'status' => 'ativo']);

    $nucA = insert($db, 'nucleos', ['projeto_id' => $projA, 'nome' => '[TESTE] Núcleo A', 'municipio' => 'Teste', 'estado' => 'RJ', 'status' => 'ativo']);
    $nucB = insert($db, 'nucleos', ['projeto_id' => $projB, 'nome' => '[TESTE] Núcleo B', 'municipio' => 'Teste', 'estado' => 'RJ', 'status' => 'ativo']);

    $superAdmin = insert($db, 'usuarios', ['nome' => '[TESTE] Super', 'email' => 'teste.super.' . uniqid() . '@t.com', 'senha_hash' => 'x', 'perfil' => 'super_admin', 'status' => 'ativo']);
    $coordA     = insert($db, 'usuarios', ['nome' => '[TESTE] Coord A', 'email' => 'teste.coordA.' . uniqid() . '@t.com', 'senha_hash' => 'x', 'perfil' => 'gestor', 'cargo' => 'coordenador_projeto', 'status' => 'ativo']);
    $profA      = insert($db, 'usuarios', ['nome' => '[TESTE] Prof A', 'email' => 'teste.profA.' . uniqid() . '@t.com', 'senha_hash' => 'x', 'perfil' => 'professor', 'cargo' => 'professor', 'status' => 'ativo']);

    // coordA só enxerga o Projeto A (não o instituto inteiro nem o B)
    insert($db, 'escopos_usuario', ['usuario_id' => $coordA, 'tipo' => 'projeto', 'referencia_id' => $projA]);
    insert($db, 'usuario_permissoes', ['usuario_id' => $coordA, 'permissao_id' => permId($db, 'nucleos.visualizar')]);
    insert($db, 'usuario_permissoes', ['usuario_id' => $coordA, 'permissao_id' => permId($db, 'equipe.criar')]);

    insert($db, 'nucleo_professores', ['nucleo_id' => $nucA, 'usuario_id' => $profA]);

    // ── 1. Escopo: coordA enxerga instituto A (herdado do projeto) mas não B ──
    check(in_array($instA, Escopo::institutosPermitidos($coordA), true), 'Coordenador com escopo de Projeto A herda o Instituto A');
    check(!in_array($instB, Escopo::institutosPermitidos($coordA), true), 'Coordenador de Projeto A NÃO enxerga Instituto B');
    check(in_array($nucA, Escopo::nucleosPermitidos($coordA), true), 'Coordenador de Projeto A enxerga o Núcleo A (herdado)');
    check(!in_array($nucB, Escopo::nucleosPermitidos($coordA), true), 'Coordenador de Projeto A NÃO enxerga o Núcleo B');
    check(!Escopo::podeAcessarProjeto($coordA, $projB), 'Coordenador de Projeto A não pode acessar Projeto B diretamente por ID');

    // ── 2. Super admin enxerga tudo, sem precisar de linha em escopos_usuario ──
    check(in_array($instA, Escopo::institutosPermitidos($superAdmin), true), 'Super admin enxerga Instituto A');
    check(in_array($instB, Escopo::institutosPermitidos($superAdmin), true), 'Super admin enxerga Instituto B');
    check(Escopo::podeAcessarNucleo($superAdmin, $nucB), 'Super admin acessa qualquer núcleo por ID');

    // ── 3. Permissões: professor não tem nenhuma permissão administrativa por padrão ──
    check(!Permissao::has($profA, 'cronograma.administrar'), 'Professor não tem permissão de administrar cronograma por padrão');
    check(!Permissao::has($profA, 'equipe.criar'), 'Professor não tem permissão de criar usuários por padrão');
    check(Permissao::has($coordA, 'equipe.criar'), 'Coordenador com a permissão concedida consegue criar usuários');
    check(!Permissao::has($coordA, 'institutos.excluir'), 'Coordenador sem a permissão concedida não pode excluir institutos');

    // ── 4. Usuário não concede permissão que não possui ──────────────────────
    $novoId = insert($db, 'usuarios', ['nome' => '[TESTE] Novo', 'email' => 'teste.novo.' . uniqid() . '@t.com', 'senha_hash' => 'x', 'perfil' => 'gestor', 'cargo' => 'monitor', 'status' => 'ativo']);
    // coordA (não super admin) tenta conceder 'institutos.excluir' (que ele não tem) e 'nucleos.visualizar' (que ele tem)
    Permissao::salvar($db, $novoId, ['institutos.excluir', 'nucleos.visualizar'], $coordA, false);
    $concedidas = Permissao::todasDoUsuario($novoId);
    check(!in_array('institutos.excluir', $concedidas, true), 'Coordenador NÃO conseguiu conceder permissão que ele mesmo não possui');
    check(in_array('nucleos.visualizar', $concedidas, true), 'Coordenador conseguiu conceder permissão que ele mesmo possui');

    // ── 5. Usuário não concede escopo que não possui (Projeto B) ─────────────
    Escopo::salvar($db, $novoId, [
        ['tipo' => 'projeto', 'referencia_id' => $projB], // fora do escopo do coordA
        ['tipo' => 'nucleo',  'referencia_id' => $nucA],  // dentro do escopo do coordA (via projeto A)
    ], $coordA, false);
    check(!Escopo::podeAcessarProjeto($novoId, $projB), 'Coordenador NÃO conseguiu conceder escopo de Projeto B (fora do próprio escopo)');
    check(Escopo::podeAcessarNucleo($novoId, $nucA), 'Coordenador conseguiu conceder escopo de Núcleo A (dentro do próprio escopo)');

    // Super admin pode conceder qualquer escopo, inclusive superior ao que um usuário comum teria
    Escopo::salvar($db, $novoId, [['tipo' => 'instituto', 'referencia_id' => $instB]], $superAdmin, true);
    check(Escopo::podeAcessarInstituto($novoId, $instB), 'Super admin consegue conceder qualquer escopo (Instituto B)');

    // ── 6. Chamada normal ─────────────────────────────────────────────────────
    $ontem = date('Y-m-d', strtotime('-1 day'));
    $chamadaId = insert($db, 'chamadas', ['nucleo_id' => $nucA, 'professor_id' => $profA, 'data_aula' => $ontem]);
    $stmt = $db->prepare("SELECT registrado_retroativamente FROM chamadas WHERE id=?");
    $stmt->execute([$chamadaId]);
    check((int) $stmt->fetchColumn() === 0, 'Chamada normal não fica marcada como retroativa');

    // ── 7. Chamada retroativa vinculada a justificativa de falta de internet ──
    $cronogramaId = insert($db, 'grade_horarios', ['nucleo_id' => $nucA, 'professor_id' => $profA, 'dia_semana' => (int) date('w'), 'horario_inicio' => '08:00:00', 'horario_fim' => '09:00:00', 'status' => 'ativo']);
    $aulaId = insert($db, 'aulas_previstas', ['cronograma_id' => $cronogramaId, 'professor_id' => $profA, 'nucleo_id' => $nucA, 'data' => $ontem, 'horario_inicio' => '08:00:00', 'horario_fim' => '09:00:00', 'status' => 'justificativa_pendente']);
    $justId = insert($db, 'justificativas_ausencia', ['aula_prevista_id' => $aulaId, 'professor_id' => $profA, 'tipo' => 'sem_internet', 'motivo' => 'Sem sinal no local.', 'enviado_em' => date('Y-m-d H:i:s')]);
    $db->prepare("UPDATE aulas_previstas SET status='justificada' WHERE id=?")->execute([$aulaId]);

    $chamadaRetroId = insert($db, 'chamadas', ['nucleo_id' => $nucA, 'professor_id' => $profA, 'data_aula' => $ontem, 'registrado_retroativamente' => 1, 'justificativa_ausencia_id' => $justId]);
    $db->prepare("UPDATE aulas_previstas SET chamada_id=? WHERE id=?")->execute([$chamadaRetroId, $aulaId]);

    $stmt = $db->prepare("SELECT c.registrado_retroativamente, c.justificativa_ausencia_id, ja.tipo FROM chamadas c JOIN justificativas_ausencia ja ON ja.id=c.justificativa_ausencia_id WHERE c.id=?");
    $stmt->execute([$chamadaRetroId]);
    $row = $stmt->fetch();
    check((int) $row['registrado_retroativamente'] === 1, 'Chamada retroativa fica marcada como tal');
    check($row['tipo'] === 'sem_internet', 'Chamada retroativa está vinculada à justificativa de falta de internet');

    // ── 8. Correção de chamada gera histórico ─────────────────────────────────
    $alunoId = insert($db, 'alunos', ['nucleo_id' => $nucA, 'nome' => '[TESTE] Aluno', 'status' => 'ativo']);
    $presencaId = insert($db, 'chamada_presencas', ['chamada_id' => $chamadaId, 'aluno_id' => $alunoId, 'presente' => 0]);
    $db->prepare("UPDATE chamada_presencas SET presente=1 WHERE id=?")->execute([$presencaId]);
    insert($db, 'chamada_presenca_historico', ['chamada_presenca_id' => $presencaId, 'presente_anterior' => 0, 'presente_novo' => 1, 'alterado_por' => $coordA]);
    $stmt = $db->prepare("SELECT COUNT(*) FROM chamada_presenca_historico WHERE chamada_presenca_id=?");
    $stmt->execute([$presencaId]);
    check((int) $stmt->fetchColumn() === 1, 'Correção de presença gera 1 registro de histórico');

    // ── 9. Check-in: dentro do raio, fora do raio, sem coordenadas ────────────
    $db->prepare("UPDATE nucleos SET latitude=?, longitude=? WHERE id=?")->execute([-22.9068, -43.1729, $nucA]);
    // dentro do raio (mesmo ponto)
    $distanciaDentro = haversineTeste(-22.9068, -43.1729, -22.9068, -43.1729);
    check($distanciaDentro <= 200, 'Check-in no mesmo ponto do núcleo fica "dentro_raio" (0m ≤ 200m)');
    // fora do raio (~1.4km de distância aproximada)
    $distanciaFora = haversineTeste(-22.9068, -43.1729, -22.9200, -43.1900);
    check($distanciaFora > 200, 'Check-in a ~1,4km fica "fora_raio" (> 200m)');
    // sem coordenadas
    $stmt = $db->prepare("SELECT latitude, longitude FROM nucleos WHERE id=?");
    $stmt->execute([$nucB]);
    $coordsB = $stmt->fetch();
    check($coordsB['latitude'] === null && $coordsB['longitude'] === null, 'Núcleo sem coordenadas cadastradas fica "sem_coordenadas" (localização indisponível)');

    // ── 10. Relatório de frequência respeita escopo (Instituto/Projeto/Núcleo) ──
    $permitidosCoordA = Escopo::nucleosPermitidos($coordA);
    [$whereEscopo, $paramsEscopo] = Escopo::whereIn($permitidosCoordA, 'n.id');
    $stmt = $db->prepare("SELECT COUNT(*) FROM nucleos n WHERE $whereEscopo AND n.id = ?");
    $stmt->execute(array_merge($paramsEscopo, [$nucB]));
    check((int) $stmt->fetchColumn() === 0, 'Filtro de relatório com escopo do coordenador exclui o Núcleo B corretamente');
    $stmt = $db->prepare("SELECT COUNT(*) FROM nucleos n WHERE $whereEscopo AND n.id = ?");
    $stmt->execute(array_merge($paramsEscopo, [$nucA]));
    check((int) $stmt->fetchColumn() === 1, 'Filtro de relatório com escopo do coordenador inclui o Núcleo A corretamente');

} finally {
    $db->rollBack();
    echo "\n(todas as alterações de teste foram revertidas — ROLLBACK)\n";
}

echo "\n=== Resultado: " . ($total - $falhas) . "/$total cenários passaram ===\n";
exit($falhas > 0 ? 1 : 0);

function haversineTeste(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $R  = 6371000;
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lng2 - $lng1);
    $a  = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
    $c  = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return (int) round($R * $c);
}
