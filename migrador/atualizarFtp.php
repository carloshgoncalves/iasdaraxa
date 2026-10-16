<?php

declare(strict_types=1);

ini_set('max_execution_time', '0');
ini_set('output_buffering', '0');

const PUBLIC_REMOTE_ROOT = '/iasdaraxa.com.br';
const PRIVATE_REMOTE_ROOT = '/appiasdaraxa';

function sendMigratorLog(string $message): void
{
    echo $message . "\n";
    @ob_flush();
    flush();
}

function normalizeMigratorRemotePath(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path) ?? '';

    if ($path === '') {
        return '';
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return rtrim($path, '/');
}

function joinMigratorRemotePath(string $basePath, string $relativePath): string
{
    return normalizeMigratorRemotePath(rtrim($basePath, '/') . '/' . ltrim($relativePath, '/'));
}

function isMigratorRemotePathInsideRoot(string $remotePath, string $allowedRoot): bool
{
    $remotePath = normalizeMigratorRemotePath($remotePath);
    $allowedRoot = normalizeMigratorRemotePath($allowedRoot);

    return $remotePath === $allowedRoot || str_starts_with($remotePath, $allowedRoot . '/');
}

function assertMigratorRemotePathInsideRoot(string $remotePath, string $allowedRoot): void
{
    if (!isMigratorRemotePathInsideRoot($remotePath, $allowedRoot)) {
        throw new RuntimeException('Caminho remoto fora da pasta permitida: ' . $remotePath);
    }
}

function migratorRelativePath(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

function migratorWildcardMatch(string $pattern, string $path): bool
{
    $pattern = str_replace('\\', '/', trim($pattern, '/'));
    $path = str_replace('\\', '/', trim($path, '/'));

    if ($pattern === '') {
        return false;
    }

    if (fnmatch($pattern, $path, FNM_PATHNAME)) {
        return true;
    }

    return !str_contains($pattern, '/') && fnmatch($pattern, basename($path));
}

function shouldMigratorIgnorePath(string $relativePath, array $ignoredItems): bool
{
    $relativePath = str_replace('\\', '/', trim($relativePath, '/'));
    if ($relativePath === '') {
        return false;
    }

    foreach ($ignoredItems as $ignoredItem) {
        $ignoredItem = str_replace('\\', '/', trim((string) $ignoredItem, '/'));
        if ($ignoredItem === '') {
            continue;
        }

        if ($relativePath === $ignoredItem || str_starts_with($relativePath, $ignoredItem . '/')) {
            return true;
        }

        $hasWildcard = strpbrk($ignoredItem, '*?[') !== false;
        if ($hasWildcard && migratorWildcardMatch($ignoredItem, $relativePath)) {
            return true;
        }
    }

    return false;
}

function isMigratorRemoteDirectory($ftp, string $path): bool
{
    $current = ftp_pwd($ftp);
    if (@ftp_chdir($ftp, $path)) {
        @ftp_chdir($ftp, $current);
        return true;
    }

    return false;
}

function ensureMigratorRemoteDirectory($ftp, string $remoteDir, string $allowedRoot): void
{
    $remoteDir = normalizeMigratorRemotePath($remoteDir);
    $allowedRoot = normalizeMigratorRemotePath($allowedRoot);
    assertMigratorRemotePathInsideRoot($remoteDir, $allowedRoot);

    if ($remoteDir === '' || $remoteDir === '/' || $remoteDir === $allowedRoot) {
        return;
    }

    if (isMigratorRemoteDirectory($ftp, $remoteDir)) {
        return;
    }

    if (!isMigratorRemoteDirectory($ftp, $allowedRoot)) {
        throw new RuntimeException('Pasta remota raiz nao encontrada: ' . $allowedRoot);
    }

    $relativeDir = trim(substr($remoteDir, strlen($allowedRoot)), '/');
    $parts = array_values(array_filter(explode('/', $relativeDir), 'strlen'));
    $current = $allowedRoot;

    foreach ($parts as $part) {
        $current = joinMigratorRemotePath($current, $part);
        assertMigratorRemotePathInsideRoot($current, $allowedRoot);

        if (!isMigratorRemoteDirectory($ftp, $current) && !@ftp_mkdir($ftp, $current)) {
            throw new RuntimeException('Nao foi possivel criar a pasta remota: ' . $current);
        }
    }
}

function shouldMigratorUploadFile($ftp, string $localFile, string $remoteFile): bool
{
    $remoteSize = @ftp_size($ftp, $remoteFile);
    if ($remoteSize < 0) {
        return true;
    }

    $localSize = filesize($localFile);
    if ($localSize !== false && (int) $localSize !== (int) $remoteSize) {
        return true;
    }

    $localTime = filemtime($localFile);
    $remoteTime = @ftp_mdtm($ftp, $remoteFile);
    if ($localTime === false || $remoteTime < 0) {
        return false;
    }

    return abs((int) $localTime - (int) $remoteTime) > 2;
}

function updateMigratorRemoteMtime($ftp, string $remoteFile, int $mtime, string $allowedRoot): void
{
    assertMigratorRemotePathInsideRoot($remoteFile, $allowedRoot);
    @ftp_raw($ftp, 'MFMT ' . gmdate('YmdHis', $mtime) . ' ' . $remoteFile);
}

function uploadMigratorDirectory(
    $ftp,
    string $localRoot,
    string $remoteRoot,
    array $ignoredItems,
    array &$stats,
    string $logPrefix = ''
): void {
    $remoteRoot = normalizeMigratorRemotePath($remoteRoot);
    assertMigratorRemotePathInsideRoot($remoteRoot, $remoteRoot);

    $directory = new RecursiveDirectoryIterator($localRoot, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator(
        $directory,
        function (SplFileInfo $current) use ($localRoot, $ignoredItems, &$stats): bool {
            $relativePath = migratorRelativePath($localRoot, $current->getPathname());
            if (shouldMigratorIgnorePath($relativePath, $ignoredItems)) {
                $stats['ignored']++;
                return false;
            }

            return true;
        }
    );
    $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $item) {
        $localPath = $item->getPathname();
        $relativePath = migratorRelativePath($localRoot, $localPath);
        $remotePath = joinMigratorRemotePath($remoteRoot, $relativePath);
        assertMigratorRemotePathInsideRoot($remotePath, $remoteRoot);

        if ($item->isDir()) {
            ensureMigratorRemoteDirectory($ftp, $remotePath, $remoteRoot);
            $stats['dirs']++;
            continue;
        }

        if (!$item->isFile()) {
            $stats['ignored']++;
            continue;
        }

        ensureMigratorRemoteDirectory($ftp, dirname($remotePath), $remoteRoot);
        if (!shouldMigratorUploadFile($ftp, $localPath, $remotePath)) {
            $stats['unchanged']++;
            continue;
        }

        assertMigratorRemotePathInsideRoot($remotePath, $remoteRoot);
        if (@ftp_put($ftp, $remotePath, $localPath, FTP_BINARY)) {
            $stats['uploaded']++;
            updateMigratorRemoteMtime($ftp, $remotePath, (int) $item->getMTime(), $remoteRoot);
            sendMigratorLog('[SUCCESS] Enviado ' . $logPrefix . $relativePath);
            continue;
        }

        $stats['errors']++;
        sendMigratorLog('[WARNING] Falha ao enviar ' . $logPrefix . $relativePath);
    }
}

header('Content-Type: text/plain; charset=UTF-8');
header('X-Accel-Buffering: none');
while (ob_get_level() > 0) {
    @ob_end_flush();
}

try {
    if (!function_exists('ftp_connect')) {
        throw new RuntimeException('A extensao FTP do PHP nao esta habilitada.');
    }

    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Arquivo migrador/config.php nao encontrado.');
    }

    $config = require $configPath;
    $ftpConfig = $config['ftp'] ?? [];
    $ignoredItems = $config['ignored_items'] ?? [];

    foreach (['host', 'port', 'username', 'password', 'timeout'] as $required) {
        if (!isset($ftpConfig[$required]) || $ftpConfig[$required] === '') {
            throw new RuntimeException('Configuracao FTP incompleta: ' . $required);
        }
    }

    $localRoot = dirname(__DIR__);
    $publicLocalRoot = $localRoot . '/public';
    $publicRemoteRoot = normalizeMigratorRemotePath(PUBLIC_REMOTE_ROOT);
    $privateRemoteRoot = normalizeMigratorRemotePath(PRIVATE_REMOTE_ROOT);
    $privateIgnoredItems = array_values(array_unique(array_merge(
        $ignoredItems,
        [
            'public',
            'migrador',
            'env.php',
            'storage/backups',
            'storage/cache/*',
            'storage/logs/*.log',
            'storage/uploads/*',
        ]
    )));
    $publicIgnoredItems = [];
    $stats = ['dirs' => 0, 'uploaded' => 0, 'unchanged' => 0, 'ignored' => 0, 'errors' => 0];

    sendMigratorLog('[INFO] Conectando ao FTP ' . $ftpConfig['host'] . ':' . $ftpConfig['port'] . '...');
    $ftp = @ftp_connect((string) $ftpConfig['host'], (int) $ftpConfig['port'], (int) $ftpConfig['timeout']);
    if (!$ftp) {
        throw new RuntimeException('Nao foi possivel conectar ao FTP.');
    }

    if (!@ftp_login($ftp, (string) $ftpConfig['username'], (string) $ftpConfig['password'])) {
        @ftp_close($ftp);
        throw new RuntimeException('Login FTP recusado.');
    }

    if (!empty($ftpConfig['passive'])) {
        @ftp_pasv($ftp, true);
    }

    sendMigratorLog('[SUCCESS] Conectado ao FTP.');
    if (!isMigratorRemoteDirectory($ftp, $publicRemoteRoot)) {
        throw new RuntimeException('Pasta publica remota nao encontrada ou inacessivel: ' . $publicRemoteRoot);
    }
    if (!isMigratorRemoteDirectory($ftp, $privateRemoteRoot)) {
        throw new RuntimeException('Pasta privada remota nao encontrada ou inacessivel: ' . $privateRemoteRoot);
    }

    sendMigratorLog('[INFO] Enviando public/ para ' . $publicRemoteRoot . '...');
    uploadMigratorDirectory($ftp, $publicLocalRoot, $publicRemoteRoot, $publicIgnoredItems, $stats, 'public/');

    sendMigratorLog('[INFO] Enviando arquivos privados para ' . $privateRemoteRoot . '...');
    uploadMigratorDirectory($ftp, $localRoot, $privateRemoteRoot, $privateIgnoredItems, $stats);

    @ftp_close($ftp);
    sendMigratorLog('[SUCCESS] Atualizacao finalizada. Enviados: ' . $stats['uploaded'] . ', iguais: ' . $stats['unchanged'] . ', pastas: ' . $stats['dirs'] . ', ignorados: ' . $stats['ignored'] . ', falhas: ' . $stats['errors'] . '.');
} catch (Throwable $e) {
    if (isset($ftp) && $ftp) {
        @ftp_close($ftp);
    }

    sendMigratorLog('[ERROR] Erro: ' . $e->getMessage());
}
