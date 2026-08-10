<?php

declare(strict_types=1);

use IasdCentral\Repositories\ObraRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

$obraId = (int) ($argv[1] ?? 0);
$pdfPath = (string) ($argv[2] ?? '');

if ($obraId <= 0 || !is_file($pdfPath)) {
    fwrite(STDERR, "Uso: php bin/import-eap.php <obra_id> <arquivo.pdf>\n");
    exit(1);
}

$command = 'pdftotext -raw ' . escapeshellarg($pdfPath) . ' - 2>/dev/null';
$output = shell_exec($command);
if (!is_string($output) || trim($output) === '') {
    fwrite(STDERR, "Nao foi possivel extrair o conteudo do PDF.\n");
    exit(1);
}

$entries = [];
$current = null;
foreach (preg_split('/\R/u', str_replace("\f", '', $output)) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line === 'ID Etapa') {
        continue;
    }

    if (preg_match('/^(\d+(?:\.\d+){0,2})(?:\s+(.*))?$/u', $line, $matches)) {
        if ($current !== null) {
            $entries[] = $current;
        }
        $current = ['code' => $matches[1], 'name' => trim($matches[2] ?? '')];
        continue;
    }

    if ($current !== null) {
        $current['name'] = trim($current['name'] . ' ' . $line);
    }
}
if ($current !== null) {
    $entries[] = $current;
}

$items = [];
foreach ($entries as $entry) {
    $parts = array_map('intval', explode('.', $entry['code']));
    $name = count($parts) === 1
        ? (preg_replace('/^Subtotal\s+\d+\s*-\s*/ui', '', $entry['name']) ?: $entry['name'])
        : $entry['name'];
    $lastSeparator = strrpos($entry['code'], '.');
    $items[] = [
        'code' => $entry['code'],
        'parent_code' => $lastSeparator === false ? null : substr($entry['code'], 0, $lastSeparator),
        'name' => $name,
        'description' => count($parts) < 3 ? 'Grupo da Estrutura Analitica de Projeto (EAP).' : null,
        'order' => (int) end($parts) * 100,
    ];
}

$repository = new ObraRepository();
if (!$repository->find($obraId)) {
    fwrite(STDERR, "Obra {$obraId} nao encontrada.\n");
    exit(1);
}

$result = $repository->importAnalyticalStructure($obraId, $items);
echo "Itens criados: {$result['items']}\n";
