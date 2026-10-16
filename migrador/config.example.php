<?php

declare(strict_types=1);

return [
    'production_database' => [
        'host' => 'ip-do-banco-producao',
        'port' => 3306,
        'name' => 'nome_do_banco',
        'user' => 'usuario_do_banco',
        'password' => 'senha_do_banco',
        'charset' => 'utf8mb4',
    ],
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
        'migrador',
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
