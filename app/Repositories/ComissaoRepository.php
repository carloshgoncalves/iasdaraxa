<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class ComissaoRepository extends BaseRepository
{
    public function people(): array
    {
        return $this->fetchAll('SELECT * FROM com_pessoas ORDER BY nome, id');
    }

    public function findPerson(int $id): ?array
    {
        return $this->fetch('SELECT * FROM com_pessoas WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function cpfExists(string $cpf, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM com_pessoas WHERE cpf = :cpf';
        $params = ['cpf' => $cpf];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createPerson(array $data): int
    {
        $this->execute(
            'INSERT INTO com_pessoas (nome, cpf, created_at, updated_at) VALUES (:nome, :cpf, NOW(), NOW())',
            ['nome' => $data['nome'], 'cpf' => $data['cpf']]
        );

        return (int) $this->db()->lastInsertId();
    }

    public function updatePerson(int $id, array $data): bool
    {
        return $this->execute(
            'UPDATE com_pessoas SET nome = :nome, cpf = :cpf, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'nome' => $data['nome'], 'cpf' => $data['cpf']]
        );
    }

    public function deletePerson(int $id): bool
    {
        return $this->execute('DELETE FROM com_pessoas WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function commissions(): array
    {
        return $this->fetchAll('SELECT * FROM com_comissoes ORDER BY data_inicio DESC, nome, id');
    }

    public function findCommission(int $id): ?array
    {
        return $this->fetch('SELECT * FROM com_comissoes WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function createCommission(array $data): int
    {
        $this->execute(
            'INSERT INTO com_comissoes (nome, data_inicio, data_fim, situacao, created_at, updated_at)
             VALUES (:nome, :data_inicio, :data_fim, :situacao, NOW(), NOW())',
            $data
        );

        return (int) $this->db()->lastInsertId();
    }

    public function updateCommission(int $id, array $data): bool
    {
        return $this->execute(
            'UPDATE com_comissoes SET nome = :nome, data_inicio = :data_inicio, data_fim = :data_fim,
                situacao = :situacao, updated_at = NOW() WHERE id = :id',
            ['id' => $id] + $data
        );
    }

    public function deleteCommission(int $id): bool
    {
        return $this->execute('DELETE FROM com_comissoes WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function offices(): array
    {
        return $this->fetchAll("SELECT * FROM com_cargos ORDER BY status = 'ativo' DESC, nome, id");
    }

    public function findOffice(int $id): ?array
    {
        return $this->fetch('SELECT * FROM com_cargos WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function officeNameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM com_cargos WHERE nome = :nome';
        $params = ['nome' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createOffice(array $data): int
    {
        $this->execute(
            'INSERT INTO com_cargos (nome, status, created_at, updated_at) VALUES (:nome, :status, NOW(), NOW())',
            $data
        );

        return (int) $this->db()->lastInsertId();
    }

    public function updateOffice(int $id, array $data): bool
    {
        return $this->execute(
            'UPDATE com_cargos SET nome = :nome, status = :status, updated_at = NOW() WHERE id = :id',
            ['id' => $id] + $data
        );
    }

    public function deleteOffice(int $id): bool
    {
        return $this->execute('DELETE FROM com_cargos WHERE id = :id LIMIT 1', ['id' => $id]);
    }
}
