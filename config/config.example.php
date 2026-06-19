<?php
// Copie este arquivo para config.php e preencha os valores reais.
// NUNCA commite config.php no repositório.

if (!defined('ROOT_PATH')) { http_response_code(403); exit; }

define('APP_ENV',  'development'); // 'production' em produção
define('APP_NAME', 'Gestão de Núcleos');
define('APP_URL',  'http://localhost/gestao-nucleos'); // sem barra final

define('DB_HOST',    'localhost');
define('DB_NAME',    'gestao_nucleos');
define('DB_USER',    'SEU_USUARIO');
define('DB_PASS',    'SUA_SENHA');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_NAME',      'gn_sess');
define('SESSION_LIFETIME',  1800);

define('CSRF_TOKEN_LENGTH',    32);
define('LOGIN_MAX_ATTEMPTS',   5);
define('LOGIN_LOCKOUT_MINUTES', 15);

define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('UPLOAD_PATH',     ROOT_PATH . '/uploads');
define('LOG_PATH',        ROOT_PATH . '/logs');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_PATH . '/erros.log');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

define('BREVO_API_KEY',    'SUA_CHAVE_BREVO');
define('BREVO_FROM_EMAIL', 'noreply@seudominio.com.br');
define('BREVO_FROM_NAME',  APP_NAME);
define('ADMIN_EMAIL',      'admin@seudominio.com.br');
