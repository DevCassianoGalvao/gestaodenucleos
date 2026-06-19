<?php
/**
 * Seed de dados de teste — Gestão de Núcleos
 *
 * Executar via CLI dentro da pasta do projeto:
 *   php database/seed.php
 *
 * Ou via browser (temporariamente) apontando para este arquivo.
 * REMOVER em produção.
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';

$db = Database::getInstance();

echo "=== Seed — Gestão de Núcleos ===\n\n";

// ─── Helpers ─────────────────────────────────────────────────────────────────

function insert(PDO $db, string $table, array $data): int
{
    $cols    = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
    $holders = implode(', ', array_fill(0, count($data), '?'));
    $stmt    = $db->prepare("INSERT INTO `$table` ($cols) VALUES ($holders)");
    $stmt->execute(array_values($data));
    return (int) $db->lastInsertId();
}

function h(string $plain): string
{
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

function ok(string $msg): void { echo "  ✓ $msg\n"; }

// ─── Limpeza (ordem inversa das FKs) ─────────────────────────────────────────

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'audit_log','login_attempts','notificacoes_log',
    'forum_posts','forum_topicos','comunicados','materiais',
    'grade_horarios','chamada_presencas','chamadas',
    'convites','alunos','nucleo_professores','nucleos','projetos','usuarios',
] as $t) {
    $db->exec("TRUNCATE TABLE `$t`");
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
ok("Tabelas limpas");

// ─── Super Admin ──────────────────────────────────────────────────────────────

$adminId = insert($db, 'usuarios', [
    'nome'       => 'Cassiano Galvão',
    'email'      => 'cassianogalvao2020@gmail.com',
    'senha_hash' => h('Admin@2024'),
    'perfil'     => 'super_admin',
    'status'     => 'ativo',
]);
ok("Super Admin criado  →  cassianogalvao2020@gmail.com / Admin@2024  (id=$adminId)");

// ─── Projetos ─────────────────────────────────────────────────────────────────

$proj1 = insert($db, 'projetos', [
    'nome'      => 'Friburgo em Movimento',
    'descricao' => 'Projeto esportivo multidisciplinar voltado à população de Nova Friburgo e região.',
    'status'    => 'ativo',
]);
ok("Projeto: Friburgo em Movimento (id=$proj1)");

$proj2 = insert($db, 'projetos', [
    'nome'      => 'Judô Infantil',
    'descricao' => 'Formação de atletas de judô na faixa etária de 6 a 14 anos no estado do RJ.',
    'status'    => 'ativo',
]);
ok("Projeto: Judô Infantil (id=$proj2)");

// ─── Núcleos ──────────────────────────────────────────────────────────────────

$nuc1 = insert($db, 'nucleos', [
    'projeto_id' => $proj1,
    'nome'       => 'Friburgo em Movimento — Nova Friburgo',
    'municipio'  => 'Nova Friburgo',
    'estado'     => 'RJ',
    'status'     => 'ativo',
]);
ok("Núcleo: Nova Friburgo (id=$nuc1)");

$nuc2 = insert($db, 'nucleos', [
    'projeto_id' => $proj2,
    'nome'       => 'Judô Infantil — Cardoso Moreira',
    'municipio'  => 'Cardoso Moreira',
    'estado'     => 'RJ',
    'status'     => 'ativo',
]);
ok("Núcleo: Cardoso Moreira (id=$nuc2)");

// ─── Professores ──────────────────────────────────────────────────────────────

$prof1 = insert($db, 'usuarios', [
    'nome'       => 'Ana Paula Ferreira',
    'email'      => 'ana.ferreira@teste.com',
    'senha_hash' => h('Prof@2024'),
    'telefone'   => '(22) 99001-0001',
    'perfil'     => 'professor',
    'status'     => 'ativo',
]);
insert($db, 'nucleo_professores', ['nucleo_id' => $nuc1, 'usuario_id' => $prof1]);
ok("Professor: Ana Paula Ferreira → Núcleo $nuc1  (id=$prof1, senha: Prof@2024)");

$prof2 = insert($db, 'usuarios', [
    'nome'       => 'Ricardo Santos',
    'email'      => 'ricardo.santos@teste.com',
    'senha_hash' => h('Prof@2024'),
    'telefone'   => '(22) 99001-0002',
    'perfil'     => 'professor',
    'status'     => 'ativo',
]);
insert($db, 'nucleo_professores', ['nucleo_id' => $nuc2, 'usuario_id' => $prof2]);
ok("Professor: Ricardo Santos → Núcleo $nuc2  (id=$prof2, senha: Prof@2024)");

// ─── Alunos ───────────────────────────────────────────────────────────────────

$alunosNuc1 = [
    ['nome' => 'Lucas Oliveira',    'email' => 'lucas.oliveira@teste.com',    'data_nascimento' => '2010-03-15', 'telefone' => '(22) 99101-0001'],
    ['nome' => 'Mariana Costa',     'email' => 'mariana.costa@teste.com',     'data_nascimento' => '2009-07-22', 'telefone' => '(22) 99101-0002'],
    ['nome' => 'Pedro Henrique',    'email' => 'pedro.henrique@teste.com',    'data_nascimento' => '2011-11-05', 'telefone' => '(22) 99101-0003'],
];

$alunosNuc2 = [
    ['nome' => 'Carla Mendes',      'email' => 'carla.mendes@teste.com',      'data_nascimento' => '2013-01-30', 'telefone' => '(22) 99201-0001'],
    ['nome' => 'Felipe Rodrigues',  'email' => 'felipe.rodrigues@teste.com',  'data_nascimento' => '2012-06-18', 'telefone' => '(22) 99201-0002'],
];

foreach ($alunosNuc1 as $a) {
    $uid = insert($db, 'usuarios', [
        'nome'       => $a['nome'],
        'email'      => $a['email'],
        'senha_hash' => h('Aluno@2024'),
        'telefone'   => $a['telefone'],
        'perfil'     => 'aluno',
        'status'     => 'ativo',
    ]);
    insert($db, 'alunos', [
        'nucleo_id'        => $nuc1,
        'usuario_id'       => $uid,
        'nome'             => $a['nome'],
        'email'            => $a['email'],
        'telefone'         => $a['telefone'],
        'data_nascimento'  => $a['data_nascimento'],
        'cidade'           => 'Nova Friburgo',
        'status'           => 'ativo',
    ]);
    ok("Aluno: {$a['nome']} → Núcleo $nuc1  (senha: Aluno@2024)");
}

foreach ($alunosNuc2 as $a) {
    $uid = insert($db, 'usuarios', [
        'nome'       => $a['nome'],
        'email'      => $a['email'],
        'senha_hash' => h('Aluno@2024'),
        'telefone'   => $a['telefone'],
        'perfil'     => 'aluno',
        'status'     => 'ativo',
    ]);
    insert($db, 'alunos', [
        'nucleo_id'        => $nuc2,
        'usuario_id'       => $uid,
        'nome'             => $a['nome'],
        'email'            => $a['email'],
        'telefone'         => $a['telefone'],
        'data_nascimento'  => $a['data_nascimento'],
        'cidade'           => 'Cardoso Moreira',
        'status'           => 'ativo',
    ]);
    ok("Aluno: {$a['nome']} → Núcleo $nuc2  (senha: Aluno@2024)");
}

// ─── Grade de horários de exemplo ────────────────────────────────────────────

$horarios = [
    ['dia_semana' => 1, 'horario_inicio' => '08:00', 'horario_fim' => '09:30'], // Seg
    ['dia_semana' => 3, 'horario_inicio' => '08:00', 'horario_fim' => '09:30'], // Qua
    ['dia_semana' => 5, 'horario_inicio' => '08:00', 'horario_fim' => '09:30'], // Sex
];
foreach ($horarios as $h) {
    insert($db, 'grade_horarios', array_merge(['nucleo_id' => $nuc1], $h));
    insert($db, 'grade_horarios', array_merge(['nucleo_id' => $nuc2], $h));
}
ok("Grade de horários: Seg/Qua/Sex 08h–09h30 criada para ambos os núcleos");

// ─── Chamadas de exemplo ──────────────────────────────────────────────────────

$chamada1 = insert($db, 'chamadas', [
    'nucleo_id'    => $nuc1,
    'professor_id' => $prof1,
    'data_aula'    => date('Y-m-d', strtotime('-7 days')),
]);

// Busca alunos do nucleo 1 para adicionar nas presenças
$stmt = $db->prepare("SELECT id FROM alunos WHERE nucleo_id = ?");
$stmt->execute([$nuc1]);
$alunosNuc1Ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($alunosNuc1Ids as $i => $aId) {
    insert($db, 'chamada_presencas', [
        'chamada_id' => $chamada1,
        'aluno_id'   => $aId,
        'presente'   => $i < 2 ? 1 : 0, // 2 presentes, 1 ausente
    ]);
}
ok("Chamada de exemplo: Núcleo $nuc1 — " . date('d/m/Y', strtotime('-7 days')));

echo "\n=== Seed concluído com sucesso! ===\n";
echo "\nAcessos de teste:\n";
echo "  Super Admin : cassianogalvao2020\@gmail.com  /  Admin\@2024\n";
echo "  Professor 1 : ana.ferreira\@teste.com         /  Prof\@2024\n";
echo "  Professor 2 : ricardo.santos\@teste.com       /  Prof\@2024\n";
echo "  Alunos      : lucas.oliveira\@teste.com etc.  /  Aluno\@2024\n";
echo "\nATENÇÃO: Remova este script e troque as senhas antes de ir para produção.\n";
