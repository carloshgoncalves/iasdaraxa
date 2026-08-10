<?php

declare(strict_types=1);

use IasdCentral\Support\SinapiUpdateService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
$result = (new SinapiUpdateService())->run(in_array('--scheduled', $argv, true));
echo $result['message'] . PHP_EOL;
exit($result['status'] === 'error' ? 1 : 0);
