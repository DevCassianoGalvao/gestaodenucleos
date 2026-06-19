<?php
/**
 * Cron: expirar convites vencidos + limpar login_attempts antigos
 *
 * Configurar no cPanel → Cron Jobs:
 *   php /home/<user>/gestao-nucleos/cron/cleanup_convites.php >> /home/<user>/gestao-nucleos/logs/cron.log 2>&1
 *
 * Frequência recomendada: a cada hora (0 * * * *)
 */

define('ROOT_PATH', dirname(__DIR__));

// Só roda via CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';

$db   = Database::getInstance();
$now  = date('Y-m-d H:i:s');
$log  = fn(string $msg) => fwrite(STDOUT, '[' . $now . '] ' . $msg . PHP_EOL);

// 1. Expirar convites vencidos
$stmt = $db->prepare(
    "UPDATE convites SET status = 'expirado'
     WHERE status = 'pendente' AND expira_em < NOW()"
);
$stmt->execute();
$log('Convites expirados: ' . $stmt->rowCount());

// 2. Limpar login_attempts com mais de 24h
$stmt2 = $db->prepare(
    "DELETE FROM login_attempts WHERE tentativa_em < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);
$stmt2->execute();
$log('Login attempts removidos: ' . $stmt2->rowCount());

$log('Cron concluido.');
