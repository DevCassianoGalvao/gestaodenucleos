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
        '/'                          => ['AuthController',     'redirectDashboard'],
        '/login'                     => ['AuthController',     'showLogin'],
        '/logout'                    => ['AuthController',     'logout'],
        '/convite/professor/{token}' => ['ConviteController',  'showProfessor'],
        '/convite/aluno/{token}'     => ['ConviteController',  'showAluno'],
        '/admin/dashboard'           => ['AdminController',    'dashboard'],
        '/professor/dashboard'       => ['ProfessorController','dashboard'],
        '/aluno/dashboard'           => ['AlunoController',    'dashboard'],
    ],
    'POST' => [
        '/login'                         => ['AuthController',    'processLogin'],
        '/convite/professor/{token}'     => ['ConviteController', 'processProfessor'],
        '/convite/aluno/{token}'         => ['ConviteController', 'processAluno'],
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
