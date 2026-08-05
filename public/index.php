<?php
declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';
require_once ROOT_PATH . '/app/helpers/Auth.php';
require_once ROOT_PATH . '/app/helpers/Security.php';
require_once ROOT_PATH . '/app/helpers/Permissao.php';
require_once ROOT_PATH . '/app/helpers/Escopo.php';

Auth::startSession();

// ─── Parse URI ───────────────────────────────────────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$uri = rtrim($uri, '/') ?: '/';

// Strip app base path (e.g., /gestao-nucleos) when running in a subdirectory
$appBasePath = rtrim(BASE_PATH, '/');
if ($appBasePath !== '' && str_starts_with($uri, $appBasePath)) {
    $uri = substr($uri, strlen($appBasePath)) ?: '/';
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── Bloqueio obrigatório: justificativa de aula não realizada ───────────────
// Professor com aula "justificativa_pendente" não pode usar o sistema normalmente
// até resolver todas as pendências. Ele continua autenticado — apenas é
// redirecionado de volta para a tela de justificativa em qualquer outra rota.
if (Auth::check() && ($_SESSION['perfil'] ?? '') === 'professor') {
    $rotaLivre = $uri === '/professor/justificativas'
        || preg_match('#^/professor/justificativas/\d+$#', $uri)
        || $uri === '/logout';

    if (!$rotaLivre) {
        require_once ROOT_PATH . '/app/helpers/Cronograma.php';
        $db = Database::getInstance();
        Cronograma::gerarOcorrenciasParaData($db, date('Y-m-d'), (int) Auth::id());
        Cronograma::atualizarPendencias($db, (int) Auth::id());

        if (Cronograma::temPendencia($db, (int) Auth::id())) {
            if (str_starts_with($uri, '/api/')) {
                http_response_code(423); // Locked
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Existem justificativas de aula pendentes.', 'redirect' => APP_URL . '/professor/justificativas']);
                exit;
            }
            header('Location: ' . APP_URL . '/professor/justificativas');
            exit;
        }
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function loadController(string $name): void
{
    $file = ROOT_PATH . '/app/controllers/' . $name . '.php';
    if (!file_exists($file)) {
        http_response_code(500);
        error_log("[Router] Controller file not found: $file");
        require_once ROOT_PATH . '/app/views/errors/500.php';
        exit;
    }
    require_once $file;
}

function matchRoute(string $pattern, string $uri): array|false
{
    $regex = preg_replace('/\{[a-z_]+\}/', '([^/]+)', $pattern);
    if (!preg_match('#^' . $regex . '$#', $uri, $matches)) {
        return false;
    }
    array_shift($matches);
    return $matches;
}

// ─── Route table ─────────────────────────────────────────────────────────────
$routes = [
    'GET' => [
        // Auth
        '/'                                     => ['AuthController',            'redirectDashboard'],
        '/login'                                => ['AuthController',            'showLogin'],
        '/logout'                               => ['AuthController',            'logout'],

        // Convites públicos
        '/convite/professor/{token}'            => ['ConviteController',         'showProfessor'],
        '/convite/aluno/{token}'                => ['ConviteController',         'showAluno'],

        // Admin — dashboard
        '/admin/dashboard'                      => ['AdminController',           'dashboard'],

        // Admin — API JSON (dashboard analítico)
        '/api/dashboard/resumo'                 => ['DashboardController',       'resumo'],
        '/api/dashboard/destaques'              => ['DashboardController',       'destaques'],
        '/api/dashboard/ranking'                => ['DashboardController',       'ranking'],

        // Admin — institutos
        '/admin/institutos'                     => ['AdminInstitutosController', 'index'],
        '/admin/institutos/novo'                => ['AdminInstitutosController', 'formNovo'],
        '/admin/institutos/{id}/editar'         => ['AdminInstitutosController', 'formEditar'],

        // Admin — projetos
        '/admin/projetos'                       => ['AdminProjetosController',   'index'],
        '/admin/projetos/novo'                  => ['AdminProjetosController',   'formNovo'],
        '/admin/projetos/{id}/editar'           => ['AdminProjetosController',   'formEditar'],

        // Admin — termos de fomento
        '/admin/projetos/{projetoId}/termos'      => ['AdminTermosFomentoController', 'index'],
        '/admin/projetos/{projetoId}/termos/novo' => ['AdminTermosFomentoController', 'formNovo'],
        '/admin/termos/{id}/editar'               => ['AdminTermosFomentoController', 'formEditar'],

        // Admin — checklist configurável do projeto
        '/admin/projetos/{projetoId}/requisitos'  => ['AdminProjetoRequisitosController', 'index'],

        // Admin — núcleos
        '/admin/nucleos'                        => ['AdminNucleosController',    'index'],
        '/admin/nucleos/novo'                   => ['AdminNucleosController',    'formNovo'],
        '/admin/nucleos/{id}/editar'            => ['AdminNucleosController',    'formEditar'],

        // Admin — professores
        '/admin/professores'                    => ['AdminProfessoresController','index'],
        '/admin/professores/convite'            => ['AdminProfessoresController','formConvite'],
        '/admin/professores/novo'               => ['AdminProfessoresController','formNovo'],
        '/admin/professores/{id}/editar'        => ['AdminProfessoresController','formEditar'],

        // Admin — monitor + check-ins + exportação
        '/admin/monitor'                        => ['AdminMonitorController',    'index'],
        '/admin/checkins'                       => ['AdminCheckinsController',   'index'],
        '/admin/exportacao'                     => ['AdminExportacaoController', 'index'],
        '/admin/exportacao/download'            => ['AdminExportacaoController', 'download'],

        // Admin — cronograma de aulas
        '/admin/cronograma'                     => ['AdminCronogramaController', 'index'],
        '/admin/cronograma/novo'                => ['AdminCronogramaController', 'formNovo'],
        '/admin/cronograma/{id}/editar'         => ['AdminCronogramaController', 'formEditar'],

        // Admin — acompanhamento de aulas (previstas/realizadas/justificadas/canceladas)
        '/admin/aulas'                          => ['AdminAulasController',      'index'],

        // Admin — chamadas (detalhe + correção)
        '/admin/chamadas/{id}'                  => ['AdminChamadasController',   'show'],

        // Admin — evidências
        '/admin/evidencias'                     => ['AdminEvidenciasController', 'index'],

        // Admin — depoimentos
        '/admin/depoimentos'                    => ['AdminDepoimentosController', 'index'],
        '/admin/depoimentos/novo'               => ['AdminDepoimentosController', 'formNovo'],

        // Admin — relatórios
        '/admin/relatorios'                     => ['AdminRelatoriosController', 'index'],
        '/admin/relatorios/inscritos'           => ['AdminRelatoriosController', 'inscritos'],
        '/admin/relatorios/frequencia'          => ['AdminRelatoriosController', 'frequencia'],

        // Admin — prestação de contas
        '/admin/prestacao-contas'               => ['AdminPrestacaoContasController', 'index'],
        '/admin/prestacao-contas/{termoId}'     => ['AdminPrestacaoContasController', 'consolidado'],

        // Professor — dashboard
        '/professor/dashboard'                  => ['ProfessorController',           'dashboard'],

        // Professor — alunos
        '/professor/alunos'                     => ['ProfessorAlunosController',     'index'],
        '/professor/alunos/convite'             => ['ProfessorAlunosController',     'formConvite'],
        '/professor/alunos/novo'                => ['ProfessorAlunosController',     'formNovo'],
        '/professor/alunos/{id}/editar'         => ['ProfessorAlunosController',     'formEditar'],

        // Professor — frequência
        '/professor/frequencia'                 => ['ProfessorFrequenciaController', 'index'],
        '/professor/frequencia/nova'            => ['ProfessorFrequenciaController', 'formNova'],
        '/professor/frequencia/{id}'            => ['ProfessorFrequenciaController', 'show'],

        // Professor — atividades diárias
        '/professor/atividades'                 => ['ProfessorAtividadesController', 'index'],
        '/professor/atividades/nova'            => ['ProfessorAtividadesController', 'formNovo'],
        '/professor/atividades/{id}'            => ['ProfessorAtividadesController', 'show'],

        // Professor — minha agenda
        '/professor/agenda'                     => ['ProfessorAgendaController',     'index'],

        // Professor — justificativa de aula não realizada
        '/professor/justificativas'             => ['JustificativaController',       'pendentes'],

        // Aluno
        '/aluno/dashboard'                      => ['AlunoController',               'dashboard'],
    ],
    'POST' => [
        // Auth
        '/login'                                => ['AuthController',            'processLogin'],

        // Convites públicos
        '/convite/professor/{token}'            => ['ConviteController',         'processProfessor'],
        '/convite/aluno/{token}'                => ['ConviteController',         'processAluno'],

        // Admin — institutos
        '/admin/institutos/novo'                => ['AdminInstitutosController', 'store'],
        '/admin/institutos/{id}/editar'         => ['AdminInstitutosController', 'update'],
        '/admin/institutos/{id}/inativar'       => ['AdminInstitutosController', 'inativar'],

        // Admin — projetos
        '/admin/projetos/novo'                  => ['AdminProjetosController',   'store'],
        '/admin/projetos/{id}/editar'           => ['AdminProjetosController',   'update'],
        '/admin/projetos/{id}/inativar'         => ['AdminProjetosController',   'inativar'],

        // Admin — termos de fomento
        '/admin/projetos/{projetoId}/termos/novo' => ['AdminTermosFomentoController', 'store'],
        '/admin/termos/{id}/editar'               => ['AdminTermosFomentoController', 'update'],
        '/admin/termos/{id}/status'                => ['AdminTermosFomentoController', 'mudarStatus'],
        '/admin/termos/{id}/anexos'                => ['AdminTermosFomentoController', 'storeAnexo'],
        '/admin/termos/anexos/{anexoId}/excluir'   => ['AdminTermosFomentoController', 'excluirAnexo'],

        // Admin — núcleos
        '/admin/nucleos/novo'                   => ['AdminNucleosController',    'store'],
        '/admin/nucleos/{id}/editar'            => ['AdminNucleosController',    'update'],
        '/admin/nucleos/{id}/inativar'          => ['AdminNucleosController',    'inativar'],

        // Admin — professores
        '/admin/professores/convite'            => ['AdminProfessoresController',    'gerarConvite'],
        '/admin/professores/novo'               => ['AdminProfessoresController',    'store'],
        '/admin/professores/{id}/editar'        => ['AdminProfessoresController',    'update'],
        '/admin/professores/{id}/inativar'      => ['AdminProfessoresController',    'inativar'],

        // Admin — cronograma de aulas
        '/admin/cronograma/novo'                => ['AdminCronogramaController',     'store'],
        '/admin/cronograma/{id}/editar'         => ['AdminCronogramaController',     'update'],
        '/admin/cronograma/{id}/inativar'       => ['AdminCronogramaController',     'inativar'],
        '/admin/cronograma/{id}/reativar'       => ['AdminCronogramaController',     'reativar'],
        '/admin/cronograma/{id}/excluir'        => ['AdminCronogramaController',     'excluir'],

        // Admin — cancelamento administrativo de aula prevista
        '/admin/aulas/{id}/cancelar'            => ['AdminAulasController',          'cancelar'],

        // Admin — correção de chamada
        '/admin/chamadas/{id}/corrigir'         => ['AdminChamadasController',       'corrigir'],

        // Admin — depoimentos
        '/admin/depoimentos/novo'               => ['AdminDepoimentosController',    'store'],
        '/admin/depoimentos/{id}/excluir'       => ['AdminDepoimentosController',    'excluir'],

        // Admin — checklist configurável do projeto
        '/admin/projetos/{projetoId}/requisitos'   => ['AdminProjetoRequisitosController', 'store'],
        '/admin/requisitos/{id}/status'            => ['AdminProjetoRequisitosController', 'alternarStatus'],
        '/admin/requisitos/{id}/excluir'           => ['AdminProjetoRequisitosController', 'excluir'],

        // Professor — alunos
        '/professor/alunos/convite'             => ['ProfessorAlunosController',     'gerarConvite'],
        '/professor/alunos/convite/revogar'     => ['ProfessorAlunosController',     'revogarConvite'],
        '/professor/alunos/novo'                => ['ProfessorAlunosController',     'store'],
        '/professor/alunos/{id}/editar'         => ['ProfessorAlunosController',     'update'],
        '/professor/alunos/{id}/inativar'       => ['ProfessorAlunosController',     'inativar'],

        // Professor — frequência
        '/professor/frequencia/nova'            => ['ProfessorFrequenciaController', 'store'],

        // Professor — atividades diárias
        '/professor/atividades/nova'            => ['ProfessorAtividadesController', 'store'],

        // Professor — justificativa de aula não realizada
        '/professor/justificativas/{id}'        => ['JustificativaController',       'store'],

        // Check-in de geolocalização
        '/api/checkin'                          => ['CheckinController',             'store'],
    ],
];

// ─── Dispatch ────────────────────────────────────────────────────────────────
$dispatched = false;

foreach ($routes[$method] ?? [] as $pattern => [$controllerName, $action]) {
    $params = matchRoute($pattern, $uri);

    if ($params === false) {
        continue;
    }

    $dispatched = true;
    loadController($controllerName);

    if (!class_exists($controllerName)) {
        http_response_code(500);
        error_log("[Router] Class not found: $controllerName");
        require_once ROOT_PATH . '/app/views/errors/500.php';
        exit;
    }

    $controller = new $controllerName();

    if (!method_exists($controller, $action)) {
        http_response_code(500);
        error_log("[Router] Method not found: $controllerName::$action");
        require_once ROOT_PATH . '/app/views/errors/500.php';
        exit;
    }

    $controller->$action(...$params);
    break;
}

if (!$dispatched) {
    http_response_code(404);
    require_once ROOT_PATH . '/app/views/errors/404.php';
}
