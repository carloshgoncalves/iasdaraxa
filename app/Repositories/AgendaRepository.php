<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class AgendaRepository extends BaseRepository
{
    public function summary(string $month): array
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        return [
            'eventos' => (int) ($this->fetch('SELECT COUNT(*) total FROM agenda_eventos WHERE data_inicio BETWEEN :start AND :end', ['start' => $start, 'end' => $end])['total'] ?? 0),
            'publicos' => (int) ($this->fetch("SELECT COUNT(*) total FROM agenda_eventos WHERE data_inicio BETWEEN :start AND :end AND visibilidade = 'Publica'", ['start' => $start, 'end' => $end])['total'] ?? 0),
            'com_escala' => (int) ($this->fetch("SELECT COUNT(*) total FROM agenda_eventos e INNER JOIN agenda_atividades a ON a.id = e.atividade_id WHERE e.data_inicio BETWEEN :start AND :end AND a.exige_escala = 1", ['start' => $start, 'end' => $end])['total'] ?? 0),
        ];
    }

    public function activities(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM agenda_atividades';
        if ($onlyActive) {
            $sql .= " WHERE status = 'Ativa'";
        }
        $sql .= ' ORDER BY status, nome';

        return $this->fetchAll($sql);
    }

    public function createActivity(array $data, int $userId): bool
    {
        return $this->execute(
            "INSERT INTO agenda_atividades (nome, descricao, cor, duracao_padrao_minutos, exige_escala, status, created_by, created_at, updated_at)
             VALUES (:nome, :descricao, :cor, :duracao_padrao_minutos, :exige_escala, :status, :created_by, NOW(), NOW())
             ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), cor = VALUES(cor), duracao_padrao_minutos = VALUES(duracao_padrao_minutos), exige_escala = VALUES(exige_escala), status = VALUES(status), updated_at = NOW()",
            [
                'nome' => $data['nome'],
                'descricao' => $data['descricao'] ?: null,
                'cor' => $data['cor'],
                'duracao_padrao_minutos' => $data['duracao_padrao_minutos'] ?: null,
                'exige_escala' => !empty($data['exige_escala']) ? 1 : 0,
                'status' => $data['status'],
                'created_by' => $userId,
            ]
        );
    }

    public function eventsForMonth(string $month): array
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        return $this->fetchAll(
            "SELECT e.*, a.nome atividade_nome, a.cor atividade_cor, a.exige_escala
             FROM agenda_eventos e
             LEFT JOIN agenda_atividades a ON a.id = e.atividade_id
             WHERE e.data_inicio BETWEEN :start AND :end
             ORDER BY e.data_inicio, e.dia_todo DESC, e.hora_inicio IS NULL, e.hora_inicio, e.titulo",
            ['start' => $start, 'end' => $end]
        );
    }

    public function upcoming(int $limit = 8): array
    {
        return $this->fetchAll(
            "SELECT e.*, a.nome atividade_nome, a.cor atividade_cor, a.exige_escala
             FROM agenda_eventos e
             LEFT JOIN agenda_atividades a ON a.id = e.atividade_id
             WHERE e.data_inicio >= CURDATE() AND e.status <> 'Cancelada'
             ORDER BY e.data_inicio, e.hora_inicio IS NULL, e.hora_inicio, e.titulo
             LIMIT {$limit}"
        );
    }

    public function findEvent(int $id): ?array
    {
        return $this->fetch(
            "SELECT e.*, a.nome atividade_nome, a.cor atividade_cor
             FROM agenda_eventos e
             LEFT JOIN agenda_atividades a ON a.id = e.atividade_id
             WHERE e.id = :id
             LIMIT 1",
            ['id' => $id]
        );
    }

    public function createEvent(array $data, int $userId): int
    {
        $this->execute(
            "INSERT INTO agenda_eventos
                (atividade_id, titulo, descricao, data_inicio, hora_inicio, data_fim, hora_fim, dia_todo, local, responsavel, visibilidade, status, observacoes, created_by, created_at, updated_at)
             VALUES
                (:atividade_id, :titulo, :descricao, :data_inicio, :hora_inicio, :data_fim, :hora_fim, :dia_todo, :local, :responsavel, :visibilidade, :status, :observacoes, :created_by, NOW(), NOW())",
            $this->eventParams($data, $userId)
        );

        return (int) $this->db()->lastInsertId();
    }

    public function updateEvent(int $id, array $data, int $userId): bool
    {
        return $this->execute(
            "UPDATE agenda_eventos SET
                atividade_id = :atividade_id,
                titulo = :titulo,
                descricao = :descricao,
                data_inicio = :data_inicio,
                hora_inicio = :hora_inicio,
                data_fim = :data_fim,
                hora_fim = :hora_fim,
                dia_todo = :dia_todo,
                local = :local,
                responsavel = :responsavel,
                visibilidade = :visibilidade,
                status = :status,
                observacoes = :observacoes,
                created_by = COALESCE(created_by, :created_by),
                updated_at = NOW()
             WHERE id = :id",
            ['id' => $id] + $this->eventParams($data, $userId)
        );
    }

    public function deleteEvent(int $id): bool
    {
        return $this->execute('DELETE FROM agenda_eventos WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    private function eventParams(array $data, int $userId): array
    {
        $allDay = !empty($data['dia_todo']) ? 1 : 0;

        return [
            'atividade_id' => !empty($data['atividade_id']) ? (int) $data['atividade_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?: null,
            'data_inicio' => $data['data_inicio'],
            'hora_inicio' => $allDay ? null : ($data['hora_inicio'] ?: null),
            'data_fim' => $data['data_fim'] ?: null,
            'hora_fim' => $allDay ? null : ($data['hora_fim'] ?: null),
            'dia_todo' => $allDay,
            'local' => $data['local'] ?: null,
            'responsavel' => $data['responsavel'] ?: null,
            'visibilidade' => $data['visibilidade'],
            'status' => $data['status'],
            'observacoes' => $data['observacoes'] ?: null,
            'created_by' => $userId,
        ];
    }
}
