<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

use IasdCentral\Core\Database;
use PDO;

abstract class BaseRepository
{
    protected function db(): PDO
    {
        return Database::pdo();
    }

    protected function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute($params);
    }
}

