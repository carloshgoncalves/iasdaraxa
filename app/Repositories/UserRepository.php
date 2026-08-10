<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class UserRepository extends BaseRepository
{
    public function find(int $id): ?array
    {
        return $this->fetch('SELECT user.*, (SELECT role_id FROM user_roles WHERE user_id = user.id LIMIT 1) role_id FROM users user WHERE user.id = :id LIMIT 1', ['id' => $id]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->fetch('SELECT * FROM users WHERE username = :username LIMIT 1', ['username' => $username]);
    }

    public function allWithRoles(): array
    {
        return $this->fetchAll(
            "SELECT user.id, user.name, user.username, user.status, user.created_at,
                    GROUP_CONCAT(role.name ORDER BY role.name SEPARATOR ', ') roles
             FROM users user
             LEFT JOIN user_roles relation ON relation.user_id = user.id
             LEFT JOIN roles role ON role.id = relation.role_id
             GROUP BY user.id
             ORDER BY user.name"
        );
    }

    public function roles(): array
    {
        return $this->fetchAll("SELECT id, name, slug FROM roles WHERE status = 'Ativo' ORDER BY name");
    }

    public function isAdmin(int $userId): bool
    {
        return $this->fetch(
            "SELECT 1 FROM user_roles relation INNER JOIN roles role ON role.id = relation.role_id WHERE relation.user_id = :user_id AND role.slug = 'admin' AND role.status = 'Ativo' LIMIT 1",
            ['user_id' => $userId]
        ) !== null;
    }

    public function insert(array $data): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO users (name, username, password, status, created_at, updated_at) VALUES (:name, :username, :password, :status, NOW(), NOW())',
                ['name' => $data['name'], 'username' => $data['username'], 'password' => password_hash($data['password'], PASSWORD_DEFAULT), 'status' => $data['status']]
            );
            $id = (int) $pdo->lastInsertId();
            $this->syncRole($id, (int) $data['role_id']);
            $pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data): bool
    {
        $params = ['id' => $id, 'name' => $data['name'], 'username' => $data['username'], 'status' => $data['status']];
        $passwordSql = '';
        if ($data['password'] !== '') {
            $passwordSql = ', password = :password';
            $params['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $updated = $this->execute("UPDATE users SET name=:name, username=:username, status=:status{$passwordSql}, updated_at=NOW() WHERE id=:id", $params);
            $this->syncRole($id, (int) $data['role_id']);
            $pdo->commit();
            return $updated;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function syncRole(int $userId, int $roleId): void
    {
        $this->execute('DELETE FROM user_roles WHERE user_id = :user_id', ['user_id' => $userId]);
        if ($roleId > 0) {
            $this->execute('INSERT INTO user_roles (user_id, role_id, created_at) SELECT :user_id, id, NOW() FROM roles WHERE id = :role_id AND status = \'Ativo\'', ['user_id' => $userId, 'role_id' => $roleId]);
        }
    }
}
