<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class DirectoryRepository extends BaseRepository
{
    public function clients(): array
    {
        return $this->fetchAll('SELECT * FROM clientes ORDER BY nome');
    }

    public function suppliers(): array
    {
        return $this->fetchAll('SELECT * FROM obra_fornecedores ORDER BY nome');
    }

    public function findClient(int $id): ?array
    {
        return $id > 0 ? $this->fetch('SELECT * FROM clientes WHERE id = :id', ['id' => $id]) : null;
    }

    public function findSupplier(int $id): ?array
    {
        return $id > 0 ? $this->fetch('SELECT * FROM obra_fornecedores WHERE id = :id', ['id' => $id]) : null;
    }

    public function saveClient(?int $id, array $data): void
    {
        if ($id) {
            $this->execute('UPDATE clientes SET nome=:nome, documento=:documento, contato=:contato, telefone=:telefone, email=:email, observacoes=:observacoes, status=:status, updated_at=NOW() WHERE id=:id', $data + ['id' => $id]);
            return;
        }
        $this->execute('INSERT INTO clientes (nome, documento, contato, telefone, email, observacoes, status, created_at, updated_at) VALUES (:nome, :documento, :contato, :telefone, :email, :observacoes, :status, NOW(), NOW())', $data);
    }

    public function saveSupplier(?int $id, array $data): void
    {
        if ($id) {
            $this->execute('UPDATE obra_fornecedores SET nome=:nome, documento=:documento, contato=:contato, telefone=:telefone, observacoes=:observacoes, status=:status, updated_at=NOW() WHERE id=:id', $data + ['id' => $id]);
            return;
        }
        $this->execute('INSERT INTO obra_fornecedores (nome, documento, contato, telefone, observacoes, status, created_at, updated_at) VALUES (:nome, :documento, :contato, :telefone, :observacoes, :status, NOW(), NOW())', $data);
    }
}
