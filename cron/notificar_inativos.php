<?php
/**
 * Cron: alertar super_admin sobre professores inativos (sem chamada há 14+ dias)
 *
 * Configurar no cPanel → Cron Jobs:
 *   php /home/<user>/gestao-nucleos/cron/notificar_inativos.php >> /home/<user>/gestao-nucleos/logs/cron.log 2>&1
 *
 * Frequência recomendada: uma vez por dia às 8h (0 8 * * *)
 */

define('ROOT_PATH', dirname(__DIR__));

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';
require_once ROOT_PATH . '/app/helpers/Brevo.php';
require_once ROOT_PATH . '/app/helpers/Mailer.php';

$now = date('Y-m-d H:i:s');
$log = fn(string $msg) => fwrite(STDOUT, '[' . $now . '] ' . $msg . PHP_EOL);

$db = Database::getInstance();

// Evitar reenvio no mesmo dia
$jaNotificouHoje = $db->prepare(
    "SELECT id FROM notificacoes_log
     WHERE tipo = 'alerta-inativos'
       AND DATE(criado_em) = CURDATE()
     LIMIT 1"
);
$jaNotificouHoje->execute();
if ($jaNotificouHoje->fetch()) {
    $log('Alerta de inativos ja enviado hoje. Pulando.');
    exit(0);
}

// Professores sem chamada há 14+ dias (ou nunca)
$stmt = $db->query("
    SELECT u.nome, u.email,
           n.nome    AS nucleo,
           n.municipio,
           MAX(c.data_aula) AS ultima_chamada,
           DATEDIFF(CURDATE(), MAX(c.data_aula)) AS dias_sem_chamada
    FROM usuarios u
    JOIN nucleo_professores np ON np.usuario_id = u.id
    JOIN nucleos n ON n.id = np.nucleo_id
    LEFT JOIN chamadas c ON c.professor_id = u.id AND c.nucleo_id = n.id
    WHERE u.perfil = 'professor' AND u.status = 'ativo' AND n.status = 'ativo'
    GROUP BY u.id, n.id
    HAVING ultima_chamada IS NULL OR dias_sem_chamada >= 14
    ORDER BY dias_sem_chamada DESC
");
$professores = $stmt->fetchAll();

if (empty($professores)) {
    $log('Nenhum professor inativo. Notificacao nao necessaria.');
    exit(0);
}

$log('Professores inativos encontrados: ' . count($professores));

$ok = Mailer::alertaInativos($professores);
$log('E-mail enviado: ' . ($ok ? 'sim' : 'falhou (ver logs)'));

exit(0);
