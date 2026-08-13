<?php

declare(strict_types=1);

return [
    'ftp' => [
        'host' => 'ftp.seudominio.com.br',
        'port' => 21,
        'username' => 'usuario_ftp',
        'password' => 'senha_ftp',
        'timeout' => 90,
        'passive' => true,
    ],
    'ignored_items' => [
        '.git',
        '.github',
        '.gitignore',
        'migrador/config.php',
        'env.php',
        'storage/backups',
        'storage/cache/*',
        'storage/logs/*.log',
        'storage/uploads/*',
        'vendor',
        'node_modules',
        '*.log',
    ],
];
