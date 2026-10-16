<?php

declare(strict_types=1);

use IasdCentral\Core\Config;
use IasdCentral\Core\Database;

ini_set('max_execution_time', '0');
ini_set('output_buffering', '0');

function sendDatabaseSyncLog(string $message): void
{
    echo $message . "\n";
    @ob_flush();
    flush();
}

function databaseSyncCommand(
    array $command,
    string $password,
    ?string $inputFile = null,
    ?string $outputFile = null
): void {
    $descriptors = [
        0 => $inputFile === null ? ['pipe', 'r'] : ['file', $inputFile, 'r'],
        1 => $outputFile === null ? ['pipe', 'w'] : ['file', $outputFile, 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, APP_BASE_PATH, ['MYSQL_PWD' => $password]);
    if (!is_resource($process)) {
        throw new RuntimeException('Nao foi possivel iniciar o cliente MySQL.');
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }
    $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
    if (isset($pipes[1]) && is_resource($pipes[1])) {
        fclose($pipes[1]);
    }
    $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
    if (isset($pipes[2]) && is_resource($pipes[2])) {
        fclose($pipes[2]);
    }

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $details = trim($stderr !== '' ? $stderr : $stdout);
        throw new RuntimeException('Falha no cliente MySQL' . ($details !== '' ? ': ' . $details : '.'));
    }
}

function databaseSyncDsn(array $config): string
{
    return sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        (int) $config['port'],
        $config['name'],
        $config['charset'] ?? 'utf8mb4'
    );
}

function databaseSyncPdo(array $config): PDO
{
    return new PDO(databaseSyncDsn($config), (string) $config['user'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function databaseSyncDropTables(PDO $pdo): void
{
    $tables = $pdo->query(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $table) . '`');
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

function databaseSyncCounts(PDO $pdo): array
{
    $tables = $pdo->query(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name"
    )->fetchAll(PDO::FETCH_COLUMN);
    $counts = [];
    foreach ($tables as $table) {
        $identifier = '`' . str_replace('`', '``', (string) $table) . '`';
        $counts[(string) $table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $identifier)->fetchColumn();
    }

    return $counts;
}

function databaseSyncDumpCommand(array $config): array
{
    return [
        '/usr/bin/mysqldump',
        '--host=' . $config['host'],
        '--port=' . (int) $config['port'],
        '--user=' . $config['user'],
        '--single-transaction',
        '--skip-lock-tables',
        '--hex-blob',
        '--set-gtid-purged=OFF',
        '--column-statistics=0',
        '--no-tablespaces',
        '--default-character-set=' . ($config['charset'] ?? 'utf8mb4'),
    ];
}

function databaseSyncImportCommand(array $config): array
{
    return [
        '/usr/bin/mysql',
        '--host=' . $config['host'],
        '--port=' . (int) $config['port'],
        '--user=' . $config['user'],
        '--default-character-set=' . ($config['charset'] ?? 'utf8mb4'),
        $config['name'],
    ];
}

header('Content-Type: text/plain; charset=UTF-8');
header('X-Accel-Buffering: none');
while (ob_get_level() > 0) {
    @ob_end_flush();
}

$backupFile = null;
$remoteChanged = false;

try {
    if (($_POST['confirmation'] ?? '') !== 'COPIAR PARA PRODUCAO') {
        throw new RuntimeException('Confirmacao de seguranca invalida.');
    }
    if (!function_exists('proc_open')) {
        throw new RuntimeException('A funcao proc_open nao esta disponivel.');
    }
    if (!is_executable('/usr/bin/mysql') || !is_executable('/usr/bin/mysqldump')) {
        throw new RuntimeException('Os clientes mysql e mysqldump nao estao instalados.');
    }

    $migratorConfig = require __DIR__ . '/config.php';
    $local = (array) Config::get('database', []);
    $remote = (array) ($migratorConfig['production_database'] ?? []);
    foreach (['host', 'port', 'name', 'user', 'password'] as $required) {
        if (($local[$required] ?? '') === '' || ($remote[$required] ?? '') === '') {
            throw new RuntimeException('Configuracao de banco incompleta: ' . $required);
        }
    }
    if ($local['host'] === $remote['host'] && $local['port'] === $remote['port'] && $local['name'] === $remote['name']) {
        throw new RuntimeException('Banco local e banco de producao apontam para o mesmo destino.');
    }

    $backupDirectory = APP_BASE_PATH . '/storage/backups';
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0775, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de backups.');
    }
    $timestamp = date('Ymd-His');
    $backupFile = $backupDirectory . '/producao-before-local-sync-' . $timestamp . '.sql';
    $schemaFile = sys_get_temp_dir() . '/iasdaraxa-schema-' . bin2hex(random_bytes(8)) . '.sql';
    $dataFile = sys_get_temp_dir() . '/iasdaraxa-data-' . bin2hex(random_bytes(8)) . '.sql';

    sendDatabaseSyncLog('[INFO] Validando conexoes local e remota...');
    $localPdo = Database::pdo();
    $remotePdo = databaseSyncPdo($remote);
    $localPdo->query('SELECT 1');
    $remotePdo->query('SELECT 1');

    sendDatabaseSyncLog('[INFO] Gerando backup completo da producao...');
    $backupCommand = array_merge(databaseSyncDumpCommand($remote), ['--routines', '--triggers', '--events', $remote['name']]);
    databaseSyncCommand($backupCommand, (string) $remote['password'], null, $backupFile);
    if (!is_file($backupFile) || filesize($backupFile) < 100) {
        throw new RuntimeException('O backup de producao nao foi gerado corretamente.');
    }
    sendDatabaseSyncLog('[SUCCESS] Backup salvo em ' . $backupFile);

    sendDatabaseSyncLog('[INFO] Preparando estrutura e dados locais...');
    databaseSyncCommand(
        array_merge(databaseSyncDumpCommand($local), ['--no-data', '--add-drop-table', $local['name']]),
        (string) $local['password'],
        null,
        $schemaFile
    );
    databaseSyncCommand(
        array_merge(databaseSyncDumpCommand($local), ['--no-create-info', '--skip-triggers', '--complete-insert', $local['name']]),
        (string) $local['password'],
        null,
        $dataFile
    );

    sendDatabaseSyncLog('[INFO] Recriando as tabelas de producao...');
    databaseSyncDropTables($remotePdo);
    $remoteChanged = true;
    databaseSyncCommand(databaseSyncImportCommand($remote), (string) $remote['password'], $schemaFile);
    sendDatabaseSyncLog('[SUCCESS] Estrutura de producao atualizada.');

    sendDatabaseSyncLog('[INFO] Copiando os dados locais para producao...');
    databaseSyncCommand(databaseSyncImportCommand($remote), (string) $remote['password'], $dataFile);

    $localCounts = databaseSyncCounts($localPdo);
    $remoteCounts = databaseSyncCounts($remotePdo);
    if ($localCounts !== $remoteCounts) {
        throw new RuntimeException('A conferencia de tabelas e registros nao corresponde ao banco local.');
    }

    @unlink($schemaFile);
    @unlink($dataFile);
    sendDatabaseSyncLog('[SUCCESS] Banco de producao sincronizado: ' . count($remoteCounts) . ' tabelas conferidas.');
} catch (Throwable $exception) {
    sendDatabaseSyncLog('[ERROR] ' . $exception->getMessage());
    if ($remoteChanged && $backupFile !== null && is_file($backupFile) && isset($remotePdo, $remote)) {
        sendDatabaseSyncLog('[WARNING] Tentando restaurar automaticamente o backup de producao...');
        try {
            databaseSyncDropTables($remotePdo);
            databaseSyncCommand(databaseSyncImportCommand($remote), (string) $remote['password'], $backupFile);
            sendDatabaseSyncLog('[SUCCESS] Backup de producao restaurado.');
        } catch (Throwable $restoreException) {
            sendDatabaseSyncLog('[ERROR] Restauracao automatica falhou: ' . $restoreException->getMessage());
        }
    }
}
