<?php

declare(strict_types=1);

use IasdCentral\Repositories\SinapiRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

$path = $argv[1] ?? '';
$competence = $argv[2] ?? '';
$sourceUrl = $argv[3] ?? '';
if (!is_file($path) || !preg_match('/^\d{4}-\d{2}$/', $competence) || $sourceUrl === '') {
    fwrite(STDERR, "Uso: php bin/import-sinapi.php <catalogo.csv> <AAAA-MM> <url-fonte>\n");
    exit(1);
}

$counts = (new SinapiRepository())->importCsv($path, $competence, $sourceUrl);
foreach ($counts as $type => $count) {
    echo $type . ': ' . $count . PHP_EOL;
}
