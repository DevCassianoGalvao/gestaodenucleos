<?php
declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';
require_once ROOT_PATH . '/app/helpers/Auth.php';
require_once ROOT_PATH . '/app/helpers/Security.php';

Auth::startSession();

// ─── Parse URI ───────────────────────────────────────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$uri = rtrim($uri, '/') ?: '/';

// Strip app base path (e.g., /gestao-nucleos) when running in a subdirectory
$appBasePath = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
if ($appBasePath !== '' && str_starts_with($uri, $appBasePath)) {
    $uri = substr($uri, strlen($appBasePath)) ?: '/';
}

$method = $_SERVER['REQUEST_METHOD'];

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

        // Admin — projetos
        '/admin/projetos'                       => ['AdminProjetosController',   'index'],
        '/admin/projetos/novo'                  => ['AdminProjetosController',   'formNovo'],
        '/admin/projetos/{id}/editar'           => ['AdminProjetosController',   'formEditar'],

        // Admin — núcleos
        '/admin/nucleos'                        => ['AdminNucleosController',    'index'],
        '/admin/nucleos/novo'                   => ['AdminNucleosController',    'formNovo'],
        '/admin/nucleos/{id}/editar'            => ['AdminNucleosController',    'formEditar'],

        // Admin — professores
        '/admin/professores'                    => ['AdminProfessoresController','index'],
        '/admin/professores/convite'            => ['AdminProfessoresController','formConvite'],
        '/admin/professores/novo'               => ['AdminProfessoresController','formNovo'],
        '/admin/professores/{id}/editar'        => ['AdminProfessoresController','formEditar'],

        // Admin — monitor + exportação
        '/admin/monitor'                        => ['AdminMonitorController',    'index'],
        '/admin/exportacao'                     => ['AdminExportacaoController', 'index'],
        '/admin/exportacao/download'            => ['AdminExportacaoController', 'download'],

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

        // Professor — horários
        '/professor/horarios'                   => ['ProfessorHorariosController',   'index'],

        // Aluno
        '/aluno/dashboard'                      => ['AlunoController',               'dashboard'],
    ],
    'POST' => [
        // Auth
        '/login'                                => ['AuthController',            'processLogin'],

        // Convites públicos
        '/convite/professor/{token}'            => ['ConviteController',         'processProfessor'],
        '/convite/aluno/{token}'                => ['ConviteController',         'processAluno'],

        // Admin — projetos
        '/admin/projetos/novo'                  => ['AdminProjetosController',   'store'],
        '/admin/projetos/{id}/editar'           => ['AdminProjetosController',   'update'],
        '/admin/projetos/{id}/inativar'         => ['AdminProjetosController',   'inativar'],

        // Admin — núcleos
        '/admin/nucleos/novo'                   => ['AdminNucleosController',    'store'],
        '/admin/nucleos/{id}/editar'            => ['AdminNucleosController',    'update'],
        '/admin/nucleos/{id}/inativar'          => ['AdminNucleosController',    'inativar'],

        // Admin — professores
        '/admin/professores/convite'            => ['AdminProfessoresController',    'gerarConvite'],
        '/admin/professores/novo'               => ['AdminProfessoresController',    'store'],
        '/admin/professores/{id}/editar'        => ['AdminProfessoresController',    'update'],
        '/admin/professores/{id}/inativar'      => ['AdminProfessoresController',    'inativar'],

        // Professor — alunos
        '/professor/alunos/convite'             => ['ProfessorAlunosController',     'gerarConvite'],
        '/professor/alunos/convite/revogar'     => ['ProfessorAlunosController',     'revogarConvite'],
        '/professor/alunos/novo'                => ['ProfessorAlunosController',     'store'],
        '/professor/alunos/{id}/editar'         => ['ProfessorAlunosController',     'update'],
        '/professor/alunos/{id}/inativar'       => ['ProfessorAlunosController',     'inativar'],

        // Professor — frequência
        '/professor/frequencia/nova'            => ['ProfessorFrequenciaController', 'store'],

        // Professor — horários
        '/professor/horarios'                   => ['ProfessorHorariosController',   'save'],
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
