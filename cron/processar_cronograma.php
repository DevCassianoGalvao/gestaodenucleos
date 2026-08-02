<?php
/**
 * Cron: gera as aulas previstas dos próximos dias a partir do cronograma
 * (grade_horarios) e avança o status das aulas cujo horário final já passou
 * (prevista → realizada ou justificativa_pendente).
 *
 * Idempotente — pode rodar quantas vezes quiser, nunca duplica ocorrências.
 *
 * Configurar no cPanel → Cron Jobs:
 *   php /home/<user>/gestao-nucleos/cron/processar_cronograma.php >> /home/<user>/gestao-nucleos/logs/cron.log 2>&1
 *
 * Frequência recomendada: a cada hora (0 * * * *)
 */

define('ROOT_PATH', dirname(__DIR__));

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';
require_once ROOT_PATH . '/app/helpers/Cronograma.php';

const JANELA_DIAS = 30;

$now = date('Y-m-d H:i:s');
$log = fn(string $msg) => fwrite(STDOUT, '[' . $now . '] ' . $msg . PHP_EOL);

$db = Database::getInstance();

$geradas = Cronograma::gerarOcorrenciasIntervalo(
    $db, date('Y-m-d'), date('Y-m-d', strtotime('+' . JANELA_DIAS . ' days'))
);
$log("Aulas previstas geradas: $geradas");

$atualizadas = Cronograma::atualizarPendencias($db);
$log("Ocorrências com status atualizado (realizada/justificativa_pendente): $atualizadas");

$log('Processamento de cronograma concluído.');
