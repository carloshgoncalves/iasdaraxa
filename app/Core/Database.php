<?php

declare(strict_types=1);

namespace IasdCentral\Core;

use PDO;

final class Database
{
    private static array $config = [];
    private static ?PDO $pdo = null;

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host'] ?? '127.0.0.1',
            (int) (self::$config['port'] ?? 3306),
            self::$config['name'] ?? '',
            self::$config['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, (string) (self::$config['user'] ?? ''), (string) (self::$config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}

