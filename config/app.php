<?php

declare(strict_types=1);

return [
    'app_name' => 'IASD Central',
    'app_env' => 'production',
    'app_url' => 'http://localhost/iasdaraxa',
    'asset_path' => '/assets',
    'app_debug' => false,
    'timezone' => 'America/Bahia',
    'session' => [
        'name' => 'iasdaraxa_session',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'tunacc14_iasdaraxa',
        'user' => 'tunacc14_iasdaraxa',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'news' => [
        'feed_url' => 'https://noticias.adventistas.org/pt/feed/',
        'max_age_days' => 14,
    ],
];
