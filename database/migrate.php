<?php
/**
 * Migração completa — executa schema.sql e seed.php
 *
 * CLI:  php database/migrate.php [--seed]
 * Flags:
 *   --seed   Também popula dados de teste após criar as tabelas
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/models/Database.php';

$runSeed = in_array('--seed', $argv ?? [], true);

echo "=== Migração — Gestão de Núcleos ===\n\n";

$db  = Database::getInstance();
$sql = file_get_contents(__DIR__ . '/schema.sql');

if ($sql === false) {
    echo "ERRO: schema.sql não encontrado.\n";
    exit(1);
}

// Executa cada statement do schema
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (array_filter(explode(';', $sql)) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
        continue;
    }
    try {
        $db->exec($stmt);
    } catch (PDOException $e) {
        echo "ERRO no statement:\n$stmt\n\nMensagem: " . $e->getMessage() . "\n";
        exit(1);
    }
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "  ✓ Schema aplicado com sucesso.\n";

if ($runSeed) {
    echo "\nExecutando seed...\n";
    require __DIR__ . '/seed.php';
} else {
    echo "\nDica: execute  php database/migrate.php --seed  para popular dados de teste.\n";
}

echo "\nMigração concluída.\n";
