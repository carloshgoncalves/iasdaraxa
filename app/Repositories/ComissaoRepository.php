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
            ['nome' => $data['nome'], 'cpf' => $data['cpf'] !== '' ? $data['cpf'] : null]
        );

        return (int) $this->db()->lastInsertId();
    }

    public function updatePerson(int $id, array $data): bool
    {
        return $this->execute(
            'UPDATE com_pessoas SET nome = :nome, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'nome' => $data['nome']]
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

    public function commissionMembers(int $commissionId): array
    {
        return $this->fetchAll(
            "SELECT cm.id, cm.pessoa_id, cm.papel, p.nome
             FROM com_comissao_membros cm
             INNER JOIN com_pessoas p ON p.id = cm.pessoa_id
             WHERE cm.comissao_id = :comissao_id
             ORDER BY FIELD(cm.papel, 'presidente', 'secretario', 'membro'), p.nome, cm.id",
            ['comissao_id' => $commissionId]
        );
    }

    public function findCommissionMemberAccess(int $commissionId, int $memberId): ?array
    {
        return $this->fetch(
            'SELECT cm.id, cm.comissao_id, cm.pessoa_id, cm.papel, p.nome,
                    c.nome comissao_nome, c.data_inicio, c.data_fim, c.situacao
             FROM com_comissao_membros cm
             INNER JOIN com_pessoas p ON p.id = cm.pessoa_id
             INNER JOIN com_comissoes c ON c.id = cm.comissao_id
             WHERE cm.id = :member_id AND cm.comissao_id = :comissao_id
             LIMIT 1',
            ['member_id' => $memberId, 'comissao_id' => $commissionId]
        );
    }

    public function commissionMemberStages(int $commissionId): array
    {
        return $this->fetchAll(
            "SELECT cc.id comissao_cargo_id, cc.ordem, cc.votacao_status,
                    c.nome cargo_nome, v.id voto_id, v.status etapa_status,
                    v.limite_indicacoes, v.limite_votos
             FROM com_comissao_cargos cc
             INNER JOIN com_cargos c ON c.id = cc.cargo_id
             LEFT JOIN com_voto v ON v.comissao_cargo_id = cc.id
             WHERE cc.comissao_id = :comissao_id
             ORDER BY
                 CASE WHEN cc.votacao_status = 'encerrada' THEN 1 ELSE 0 END,
                 CASE
                     WHEN cc.votacao_status = 'aberta' AND v.status = 'indicacao' THEN 0
                     WHEN cc.votacao_status = 'aberta' AND v.status = 'votando' THEN 1
                     WHEN cc.votacao_status = 'aberta' AND v.status = 'encerrado' THEN 2
                     WHEN v.id IS NULL THEN 3
                     ELSE 4
                 END,
                 cc.ordem, cc.id",
            ['comissao_id' => $commissionId]
        );
    }

    public function findCommissionMemberStage(int $commissionId, int $voteId): ?array
    {
        return $this->fetch(
            'SELECT cc.id comissao_cargo_id, cc.ordem, cc.votacao_status,
                    c.nome cargo_nome, v.id voto_id, v.status etapa_status,
                    v.limite_indicacoes, v.limite_votos
             FROM com_voto v
             INNER JOIN com_comissao_cargos cc ON cc.id = v.comissao_cargo_id
             INNER JOIN com_cargos c ON c.id = cc.cargo_id
             WHERE v.id = :voto_id AND cc.comissao_id = :comissao_id
             LIMIT 1',
            ['voto_id' => $voteId, 'comissao_id' => $commissionId]
        );
    }

    public function availablePeopleForCommission(int $commissionId): array
    {
        return $this->fetchAll(
            'SELECT p.id, p.nome
             FROM com_pessoas p
             WHERE NOT EXISTS (
                 SELECT 1 FROM com_comissao_membros cm
                 WHERE cm.comissao_id = :comissao_id AND cm.pessoa_id = p.id
             )
             ORDER BY p.nome, p.id',
            ['comissao_id' => $commissionId]
        );
    }

    public function attachCommissionMember(int $commissionId, int $personId, string $role): bool
    {
        return $this->execute(
            'INSERT INTO com_comissao_membros
                (comissao_id, pessoa_id, papel, created_at, updated_at)
             SELECT :comissao_id, id, :insert_role, NOW(), NOW()
             FROM com_pessoas WHERE id = :pessoa_id
             ON DUPLICATE KEY UPDATE papel = :update_role, updated_at = NOW()',
            [
                'comissao_id' => $commissionId,
                'pessoa_id' => $personId,
                'insert_role' => $role,
                'update_role' => $role,
            ]
        );
    }

    public function createAndAttachCommissionMember(int $commissionId, string $name, string $role): int
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $person = $this->fetch('SELECT id FROM com_pessoas WHERE nome = :nome LIMIT 1', ['nome' => $name]);
            $personId = $person === null
                ? $this->createPerson(['nome' => $name, 'cpf' => ''])
                : (int) $person['id'];

            $this->attachCommissionMember($commissionId, $personId, $role);
            $database->commit();
            return $personId;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function updateCommissionMember(int $commissionId, int $memberId, string $role): bool
    {
        return $this->execute(
            'UPDATE com_comissao_membros
             SET papel = :papel, updated_at = NOW()
             WHERE id = :id AND comissao_id = :comissao_id',
            ['id' => $memberId, 'comissao_id' => $commissionId, 'papel' => $role]
        );
    }

    public function removeCommissionMember(int $commissionId, int $memberId): bool
    {
        return $this->execute(
            'DELETE FROM com_comissao_membros
             WHERE id = :id AND comissao_id = :comissao_id
             LIMIT 1',
            ['id' => $memberId, 'comissao_id' => $commissionId]
        );
    }

    public function startCommission(int $id): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $commission = $this->fetch(
                'SELECT id, situacao FROM com_comissoes WHERE id = :id LIMIT 1 FOR UPDATE',
                ['id' => $id]
            );
            if ($commission === null || $commission['situacao'] === 'finalizada') {
                $database->rollBack();
                return false;
            }

            if ($commission['situacao'] === 'aguardando') {
                $this->execute(
                    "INSERT IGNORE INTO com_comissao_cargos (comissao_id, cargo_id, ordem, created_at)
                     SELECT :comissao_id, id, id, NOW() FROM com_cargos WHERE status = 'ativo'",
                    ['comissao_id' => $id]
                );
                $this->execute(
                    "UPDATE com_comissoes SET situacao = 'iniciada', updated_at = NOW() WHERE id = :id",
                    ['id' => $id]
                );
            }

            $database->commit();
            return true;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function commissionOffices(int $commissionId): array
    {
        return $this->fetchAll(
            "SELECT c.id, c.nome, c.status, cc.id comissao_cargo_id, cc.ordem,
                    cc.votacao_status, cc.votacao_encerrada_at, cc.created_at vinculado_em
             FROM com_comissao_cargos cc
             INNER JOIN com_cargos c ON c.id = cc.cargo_id
             WHERE cc.comissao_id = :comissao_id
             ORDER BY cc.ordem, cc.id",
            ['comissao_id' => $commissionId]
        );
    }

    public function findCommissionOffice(int $commissionId, int $commissionOfficeId): ?array
    {
        return $this->fetch(
            "SELECT cc.id comissao_cargo_id, cc.comissao_id, cc.cargo_id, cc.ordem,
                    cc.votacao_status, cc.votacao_encerrada_at, c.nome, c.status
             FROM com_comissao_cargos cc
             INNER JOIN com_cargos c ON c.id = cc.cargo_id
             WHERE cc.id = :id AND cc.comissao_id = :comissao_id
             LIMIT 1",
            ['id' => $commissionOfficeId, 'comissao_id' => $commissionId]
        );
    }

    public function findCommissionOfficeByCargo(int $commissionId, int $officeId): ?array
    {
        return $this->fetch(
            'SELECT id comissao_cargo_id, comissao_id, cargo_id, votacao_status
             FROM com_comissao_cargos
             WHERE comissao_id = :comissao_id AND cargo_id = :cargo_id
             LIMIT 1',
            ['comissao_id' => $commissionId, 'cargo_id' => $officeId]
        );
    }

    public function voteParticipants(int $commissionOfficeId): array
    {
        return $this->fetchAll(
            "SELECT vp.id, vp.pessoa_id, vp.votos, vp.papel, p.nome
             FROM com_votacao_pessoas vp
             INNER JOIN com_pessoas p ON p.id = vp.pessoa_id
             WHERE vp.comissao_cargo_id = :comissao_cargo_id
             ORDER BY FIELD(vp.papel, 'votado', 'secretario', 'associado', 'candidato'),
                      vp.votos DESC, p.nome, vp.id",
            ['comissao_cargo_id' => $commissionOfficeId]
        );
    }

    public function findVoteFlow(int $commissionOfficeId): ?array
    {
        return $this->fetch(
            'SELECT id, comissao_cargo_id, status, limite_indicacoes, limite_votos,
                    indicacoes_abertas_at, created_at, updated_at
             FROM com_voto
             WHERE comissao_cargo_id = :comissao_cargo_id
             LIMIT 1',
            ['comissao_cargo_id' => $commissionOfficeId]
        );
    }

    public function memberIndications(int $voteId, int $commissionMemberId): array
    {
        return $this->fetchAll(
            'SELECT i.id, i.pessoa_id, p.nome, i.created_at
             FROM com_indicacoes i
             INNER JOIN com_pessoas p ON p.id = i.pessoa_id
             WHERE i.voto_id = :voto_id AND i.comissao_membro_id = :comissao_membro_id
             ORDER BY p.nome, i.id',
            ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
        );
    }

    public function availablePeopleForIndication(int $voteId, int $commissionMemberId): array
    {
        return $this->fetchAll(
            'SELECT p.id, p.nome
             FROM com_pessoas p
             WHERE NOT EXISTS (
                 SELECT 1 FROM com_indicacoes i
                 WHERE i.voto_id = :voto_id
                   AND i.comissao_membro_id = :comissao_membro_id
                   AND i.pessoa_id = p.id
             )
             ORDER BY p.nome, p.id',
            ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
        );
    }

    public function hasMemberFinishedIndications(int $voteId, int $commissionMemberId): bool
    {
        return $this->fetch(
            'SELECT 1 FROM com_indicacao_conclusoes
             WHERE voto_id = :voto_id AND comissao_membro_id = :comissao_membro_id
             LIMIT 1',
            ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
        ) !== null;
    }

    public function addMemberIndication(int $voteId, int $commissionMemberId, int $personId): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $context = $this->lockedIndicationContext($voteId, $commissionMemberId);
            if ($this->hasMemberFinishedIndications($voteId, $commissionMemberId)) {
                throw new \InvalidArgumentException('Sua indicação já foi encerrada.');
            }
            if ($this->findPerson($personId) === null) {
                throw new \InvalidArgumentException('Selecione uma pessoa cadastrada.');
            }
            if ($this->fetch(
                'SELECT 1 FROM com_indicacoes
                 WHERE voto_id = :voto_id AND comissao_membro_id = :comissao_membro_id AND pessoa_id = :pessoa_id
                 LIMIT 1',
                ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId, 'pessoa_id' => $personId]
            ) !== null) {
                $database->rollBack();
                return false;
            }

            $total = (int) ($this->fetch(
                'SELECT COUNT(*) total FROM com_indicacoes
                 WHERE voto_id = :voto_id AND comissao_membro_id = :comissao_membro_id',
                ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
            )['total'] ?? 0);
            if ($total >= (int) $context['limite_indicacoes']) {
                throw new \InvalidArgumentException('Você já atingiu o limite de indicações deste cargo.');
            }

            $inserted = $this->execute(
                'INSERT INTO com_indicacoes (voto_id, comissao_membro_id, pessoa_id, created_at)
                 VALUES (:voto_id, :comissao_membro_id, :pessoa_id, NOW())',
                ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId, 'pessoa_id' => $personId]
            );
            $this->execute(
                "INSERT INTO com_votacao_pessoas
                    (comissao_cargo_id, pessoa_id, votos, papel, origem, created_at, updated_at)
                 VALUES (:comissao_cargo_id, :pessoa_id, 0, 'candidato', 'indicacao', NOW(), NOW())
                 ON DUPLICATE KEY UPDATE updated_at = com_votacao_pessoas.updated_at",
                ['comissao_cargo_id' => $context['comissao_cargo_id'], 'pessoa_id' => $personId]
            );
            $database->commit();
            return $inserted;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function removeMemberIndication(int $voteId, int $commissionMemberId, int $indicationId): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $this->lockedIndicationContext($voteId, $commissionMemberId);
            if ($this->hasMemberFinishedIndications($voteId, $commissionMemberId)) {
                throw new \InvalidArgumentException('Sua indicação já foi encerrada.');
            }
            $indication = $this->fetch(
                'SELECT pessoa_id FROM com_indicacoes
                 WHERE id = :id AND voto_id = :voto_id AND comissao_membro_id = :comissao_membro_id
                 LIMIT 1',
                ['id' => $indicationId, 'voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
            );
            $removed = $this->execute(
                'DELETE FROM com_indicacoes
                 WHERE id = :id AND voto_id = :voto_id AND comissao_membro_id = :comissao_membro_id
                 LIMIT 1',
                ['id' => $indicationId, 'voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
            );
            if ($indication !== null && $this->fetch(
                'SELECT 1 FROM com_indicacoes WHERE voto_id = :voto_id AND pessoa_id = :pessoa_id LIMIT 1',
                ['voto_id' => $voteId, 'pessoa_id' => $indication['pessoa_id']]
            ) === null) {
                $this->execute(
                    "DELETE vp FROM com_votacao_pessoas vp
                     INNER JOIN com_voto v ON v.comissao_cargo_id = vp.comissao_cargo_id
                     WHERE v.id = :voto_id AND vp.pessoa_id = :pessoa_id
                       AND vp.origem = 'indicacao' AND vp.votos = 0 AND vp.papel = 'candidato'",
                    ['voto_id' => $voteId, 'pessoa_id' => $indication['pessoa_id']]
                );
            }
            $database->commit();
            return $removed;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function finishMemberIndications(int $voteId, int $commissionMemberId): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $context = $this->lockedIndicationContext($voteId, $commissionMemberId);
            $total = (int) ($this->fetch(
                'SELECT COUNT(*) total FROM com_indicacoes
                 WHERE voto_id = :voto_id AND comissao_membro_id = :comissao_membro_id',
                ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
            )['total'] ?? 0);
            if ($total > (int) $context['limite_indicacoes']) {
                throw new \InvalidArgumentException('Retire indicações até respeitar o limite configurado.');
            }

            $finished = $this->execute(
                'INSERT IGNORE INTO com_indicacao_conclusoes
                    (voto_id, comissao_membro_id, encerrada_at, created_at)
                 VALUES (:voto_id, :comissao_membro_id, NOW(), NOW())',
                ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
            );
            $database->commit();
            return $finished;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function indicationOverview(int $commissionOfficeId): array
    {
        $empty = [
            'names' => [],
            'missing_indications' => [],
            'missing_votes' => [],
            'vote_open' => false,
        ];
        $office = $this->fetch(
            'SELECT comissao_id FROM com_comissao_cargos WHERE id = :id LIMIT 1',
            ['id' => $commissionOfficeId]
        );
        $flow = $this->findVoteFlow($commissionOfficeId);
        if ($office === null) {
            return $empty;
        }

        if ($flow === null) {
            return $empty;
        }

        $names = $this->fetchAll(
            'SELECT DISTINCT p.id, p.nome
             FROM com_indicacoes i
             INNER JOIN com_pessoas p ON p.id = i.pessoa_id
             WHERE i.voto_id = :voto_id
             ORDER BY p.nome, p.id',
            ['voto_id' => $flow['id']]
        );
        $missingIndications = $this->fetchAll(
            "SELECT cm.id, p.nome, cm.papel
             FROM com_comissao_membros cm
             INNER JOIN com_pessoas p ON p.id = cm.pessoa_id
             WHERE cm.comissao_id = :comissao_id AND cm.papel <> 'presidente'
               AND NOT EXISTS (
                   SELECT 1 FROM com_indicacao_conclusoes ic
                   WHERE ic.voto_id = :voto_id AND ic.comissao_membro_id = cm.id
               )
             ORDER BY FIELD(cm.papel, 'secretario', 'membro'), p.nome, cm.id",
            ['voto_id' => $flow['id'], 'comissao_id' => $office['comissao_id']]
        );
        $voteOpen = $flow['status'] === 'votando';
        $missingVotes = $voteOpen ? $this->fetchAll(
            "SELECT cm.id, p.nome, cm.papel
             FROM com_comissao_membros cm
             INNER JOIN com_pessoas p ON p.id = cm.pessoa_id
             WHERE cm.comissao_id = :comissao_id
               AND NOT EXISTS (
                   SELECT 1 FROM com_votacao_membros vm
                   WHERE vm.voto_id = :voto_id AND vm.comissao_membro_id = cm.id
               )
             ORDER BY FIELD(cm.papel, 'presidente', 'secretario', 'membro'), p.nome, cm.id",
            ['voto_id' => $flow['id'], 'comissao_id' => $office['comissao_id']]
        ) : [];

        return [
            'names' => $names,
            'missing_indications' => $missingIndications,
            'missing_votes' => $missingVotes,
            'vote_open' => $voteOpen,
        ];
    }

    public function memberVotingCandidates(int $voteId): array
    {
        return $this->fetchAll(
            "SELECT vp.pessoa_id, p.nome
             FROM com_voto v
             INNER JOIN com_votacao_pessoas vp ON vp.comissao_cargo_id = v.comissao_cargo_id
             INNER JOIN com_pessoas p ON p.id = vp.pessoa_id
             WHERE v.id = :voto_id AND vp.papel = 'candidato'
             ORDER BY p.nome, p.id",
            ['voto_id' => $voteId]
        );
    }

    public function distinctIndications(int $voteId): array
    {
        return $this->fetchAll(
            'SELECT DISTINCT p.id, p.nome
             FROM com_voto v
             INNER JOIN com_votacao_pessoas vp ON vp.comissao_cargo_id = v.comissao_cargo_id
             INNER JOIN com_pessoas p ON p.id = vp.pessoa_id
             WHERE v.id = :voto_id
             ORDER BY p.nome, p.id',
            ['voto_id' => $voteId]
        );
    }

    public function currentCommissionPresentation(int $commissionId): ?array
    {
        $presentation = $this->fetch(
            "SELECT v.id voto_id, v.status etapa_status, v.limite_votos,
                    cc.id comissao_cargo_id, cc.ordem, cc.votacao_status,
                    cc.votacao_encerrada_at, c.nome cargo_nome
             FROM com_voto v
             INNER JOIN com_comissao_cargos cc ON cc.id = v.comissao_cargo_id
             INNER JOIN com_cargos c ON c.id = cc.cargo_id
             WHERE cc.comissao_id = :comissao_id
               AND (
                    (cc.votacao_status = 'aberta' AND v.status IN ('indicacao', 'votando', 'encerrado'))
                    OR (cc.votacao_status = 'encerrada' AND v.status = 'encerrado')
               )
             ORDER BY
                CASE
                    WHEN cc.votacao_status = 'encerrada' THEN cc.votacao_encerrada_at
                    ELSE v.updated_at
                END DESC,
                CASE
                    WHEN cc.votacao_status = 'aberta' AND v.status = 'votando' THEN 0
                    WHEN cc.votacao_status = 'aberta' AND v.status = 'indicacao' THEN 1
                    WHEN cc.votacao_status = 'aberta' AND v.status = 'encerrado' THEN 2
                    ELSE 3
                END,
                cc.ordem, cc.id
             LIMIT 1",
            ['comissao_id' => $commissionId]
        );

        if ($presentation === null) {
            return null;
        }

        if ($presentation['votacao_status'] === 'encerrada') {
            $presentation['display_mode'] = 'result';
        } elseif ($presentation['etapa_status'] === 'votando') {
            $presentation['display_mode'] = 'voting';
        } else {
            $presentation['display_mode'] = 'indications';
        }

        return $presentation;
    }

    public function memberVoteReceipt(int $voteId, int $commissionMemberId): ?array
    {
        $ballot = $this->fetch(
            'SELECT id, confirmado_at FROM com_votacao_membros
             WHERE voto_id = :voto_id AND comissao_membro_id = :comissao_membro_id
             LIMIT 1',
            ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
        );
        if ($ballot === null) {
            return null;
        }

        $ballot['choices'] = array_map(
            static fn (array $choice): int => (int) $choice['pessoa_id'],
            $this->fetchAll(
                'SELECT pessoa_id FROM com_votacao_escolhas
                 WHERE votacao_membro_id = :votacao_membro_id
                 ORDER BY id',
                ['votacao_membro_id' => $ballot['id']]
            )
        );

        return $ballot;
    }

    public function confirmMemberVote(int $voteId, int $commissionMemberId, array $personIds): void
    {
        $personIds = array_values(array_unique(array_map('intval', $personIds)));
        $personIds = array_values(array_filter($personIds, static fn (int $id): bool => $id > 0));
        $database = $this->db();
        $database->beginTransaction();

        try {
            $context = $this->fetch(
                'SELECT v.id voto_id, v.comissao_cargo_id, v.status, v.limite_votos,
                        cc.comissao_id, cc.votacao_status
                 FROM com_voto v
                 INNER JOIN com_comissao_cargos cc ON cc.id = v.comissao_cargo_id
                 WHERE v.id = :voto_id
                 LIMIT 1 FOR UPDATE',
                ['voto_id' => $voteId]
            );
            if ($context === null || $context['status'] !== 'votando' || $context['votacao_status'] !== 'aberta') {
                throw new \InvalidArgumentException('A votação não está aberta para este cargo.');
            }
            if ($this->fetch(
                'SELECT 1 FROM com_comissao_membros WHERE id = :id AND comissao_id = :comissao_id LIMIT 1',
                ['id' => $commissionMemberId, 'comissao_id' => $context['comissao_id']]
            ) === null) {
                throw new \InvalidArgumentException('Membro da comissão não encontrado.');
            }
            if ($this->memberVoteReceipt($voteId, $commissionMemberId) !== null) {
                throw new \InvalidArgumentException('Seu voto já foi confirmado.');
            }

            $limit = (int) $context['limite_votos'];
            if ($personIds === [] || count($personIds) > $limit) {
                throw new \InvalidArgumentException('Selecione de 1 até ' . $limit . ' candidato(s).');
            }

            $availableIds = array_map(
                static fn (array $candidate): int => (int) $candidate['pessoa_id'],
                $this->memberVotingCandidates($voteId)
            );
            if (array_diff($personIds, $availableIds) !== []) {
                throw new \InvalidArgumentException('Um dos candidatos selecionados não está disponível para votação.');
            }

            $this->execute(
                'INSERT INTO com_votacao_membros
                    (voto_id, comissao_membro_id, confirmado_at, created_at)
                 VALUES (:voto_id, :comissao_membro_id, NOW(), NOW())',
                ['voto_id' => $voteId, 'comissao_membro_id' => $commissionMemberId]
            );
            $ballotId = (int) $database->lastInsertId();
            $choiceStatement = $database->prepare(
                'INSERT INTO com_votacao_escolhas (votacao_membro_id, pessoa_id, created_at)
                 VALUES (:votacao_membro_id, :pessoa_id, NOW())'
            );
            $voteStatement = $database->prepare(
                "UPDATE com_votacao_pessoas
                 SET votos = votos + 1, updated_at = NOW()
                 WHERE comissao_cargo_id = :comissao_cargo_id
                   AND pessoa_id = :pessoa_id AND papel = 'candidato'"
            );
            foreach ($personIds as $personId) {
                $choiceStatement->execute(['votacao_membro_id' => $ballotId, 'pessoa_id' => $personId]);
                $voteStatement->execute([
                    'comissao_cargo_id' => $context['comissao_cargo_id'],
                    'pessoa_id' => $personId,
                ]);
            }

            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function openIndications(int $commissionOfficeId, int $indicationLimit, int $voteLimit): bool
    {
        if ($indicationLimit < 1 || $indicationLimit > 9) {
            throw new \InvalidArgumentException('O limite de indicações deve estar entre 1 e 9.');
        }
        if ($voteLimit < 1 || $voteLimit > 99) {
            throw new \InvalidArgumentException('A quantidade de votos deve estar entre 1 e 99.');
        }

        $database = $this->db();
        $database->beginTransaction();

        try {
            $current = $this->findVoteFlow($commissionOfficeId);
            $opened = $this->setVoteFlowStatus($commissionOfficeId, 'indicacao', $indicationLimit, $voteLimit);
            if ($current !== null && $current['status'] !== 'indicacao') {
                $this->execute(
                    'DELETE FROM com_indicacao_conclusoes WHERE voto_id = :voto_id',
                    ['voto_id' => $current['id']]
                );
            }
            $database->commit();
            return $opened;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function closeIndications(int $commissionOfficeId): bool
    {
        return $this->execute(
            "UPDATE com_voto
             SET status = 'encerrado', updated_at = NOW()
             WHERE comissao_cargo_id = :comissao_cargo_id AND status = 'indicacao'",
            ['comissao_cargo_id' => $commissionOfficeId]
        );
    }

    public function openVoting(int $commissionOfficeId): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $opened = $this->execute(
                "UPDATE com_voto
                 SET status = 'votando', updated_at = NOW()
                 WHERE comissao_cargo_id = :comissao_cargo_id AND status = 'encerrado'",
                ['comissao_cargo_id' => $commissionOfficeId]
            );
            $this->execute(
                "UPDATE com_votacao_pessoas
                 SET votos = 0, papel = 'candidato', updated_at = NOW()
                 WHERE comissao_cargo_id = :comissao_cargo_id",
                ['comissao_cargo_id' => $commissionOfficeId]
            );
            $this->clearMemberBallots($commissionOfficeId);
            $database->commit();
            return $opened;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function cancelVoting(int $commissionOfficeId): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $updated = $this->execute(
                "UPDATE com_voto
                 SET status = 'aguardando', indicacoes_abertas_at = NULL, updated_at = NOW()
                 WHERE comissao_cargo_id = :comissao_cargo_id AND status = 'votando'",
                ['comissao_cargo_id' => $commissionOfficeId]
            );
            $this->execute(
                "UPDATE com_votacao_pessoas
                 SET votos = 0, papel = 'candidato', updated_at = NOW()
                 WHERE comissao_cargo_id = :comissao_cargo_id",
                ['comissao_cargo_id' => $commissionOfficeId]
            );
            $this->clearMemberBallots($commissionOfficeId);

            $database->commit();
            return $updated;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function availablePeopleForVote(int $commissionOfficeId): array
    {
        return $this->fetchAll(
            'SELECT p.id, p.nome
             FROM com_pessoas p
             WHERE NOT EXISTS (
                 SELECT 1 FROM com_votacao_pessoas vp
                 WHERE vp.comissao_cargo_id = :comissao_cargo_id AND vp.pessoa_id = p.id
             )
             ORDER BY p.nome, p.id',
            ['comissao_cargo_id' => $commissionOfficeId]
        );
    }

    public function attachPersonToVote(int $commissionOfficeId, int $personId): bool
    {
        return $this->execute(
            "INSERT INTO com_votacao_pessoas
                (comissao_cargo_id, pessoa_id, votos, papel, origem, created_at, updated_at)
             SELECT :comissao_cargo_id, id, 0, 'candidato', 'admin', NOW(), NOW()
             FROM com_pessoas WHERE id = :pessoa_id
             ON DUPLICATE KEY UPDATE origem = 'admin', updated_at = NOW()",
            ['comissao_cargo_id' => $commissionOfficeId, 'pessoa_id' => $personId]
        );
    }

    public function createAndAttachPersonToVote(int $commissionOfficeId, string $name): int
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $person = $this->fetch('SELECT id FROM com_pessoas WHERE nome = :nome LIMIT 1', ['nome' => $name]);
            $personId = $person === null
                ? $this->createPerson(['nome' => $name, 'cpf' => ''])
                : (int) $person['id'];

            $this->attachPersonToVote($commissionOfficeId, $personId);
            $database->commit();
            return $personId;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function saveVoteParticipants(int $commissionOfficeId, array $entries): void
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $statement = $database->prepare(
                'UPDATE com_votacao_pessoas
                 SET votos = :votos, papel = :papel, updated_at = NOW()
                 WHERE id = :id AND comissao_cargo_id = :comissao_cargo_id'
            );
            foreach ($entries as $entry) {
                $statement->execute([
                    'id' => $entry['id'],
                    'comissao_cargo_id' => $commissionOfficeId,
                    'votos' => $entry['votos'],
                    'papel' => $entry['papel'],
                ]);
            }

            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function closeVote(int $commissionOfficeId): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $updated = $this->execute(
                "UPDATE com_comissao_cargos
                 SET votacao_status = 'encerrada', votacao_encerrada_at = NOW()
                 WHERE id = :id AND votacao_status = 'aberta'",
                ['id' => $commissionOfficeId]
            );
            $this->setVoteFlowStatus($commissionOfficeId, 'encerrado');
            $database->commit();
            return $updated;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function reopenVote(int $commissionOfficeId): bool
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $updated = $this->execute(
                "UPDATE com_comissao_cargos
                 SET votacao_status = 'aberta', votacao_encerrada_at = NULL
                 WHERE id = :id AND votacao_status = 'encerrada'",
                ['id' => $commissionOfficeId]
            );
            $this->setVoteFlowStatus($commissionOfficeId, 'votando');
            $this->execute(
                "UPDATE com_votacao_pessoas
                 SET votos = 0, papel = 'candidato', updated_at = NOW()
                 WHERE comissao_cargo_id = :comissao_cargo_id",
                ['comissao_cargo_id' => $commissionOfficeId]
            );
            $this->clearMemberBallots($commissionOfficeId);
            $database->commit();
            return $updated;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    private function lockedIndicationContext(int $voteId, int $commissionMemberId): array
    {
        $context = $this->fetch(
            'SELECT v.id voto_id, v.comissao_cargo_id, v.status, v.limite_indicacoes,
                    cc.comissao_id, cc.votacao_status
             FROM com_voto v
             INNER JOIN com_comissao_cargos cc ON cc.id = v.comissao_cargo_id
             WHERE v.id = :voto_id
             LIMIT 1 FOR UPDATE',
            ['voto_id' => $voteId]
        );
        if ($context === null || $context['status'] !== 'indicacao' || $context['votacao_status'] !== 'aberta') {
            throw new \InvalidArgumentException('As indicações não estão abertas para este cargo.');
        }

        $member = $this->fetch(
            'SELECT 1 FROM com_comissao_membros
             WHERE id = :id AND comissao_id = :comissao_id
             LIMIT 1',
            ['id' => $commissionMemberId, 'comissao_id' => $context['comissao_id']]
        );
        if ($member === null) {
            throw new \InvalidArgumentException('Membro da comissão não encontrado.');
        }

        return $context;
    }

    private function clearMemberBallots(int $commissionOfficeId): void
    {
        $this->execute(
            'DELETE vm FROM com_votacao_membros vm
             INNER JOIN com_voto v ON v.id = vm.voto_id
             WHERE v.comissao_cargo_id = :comissao_cargo_id',
            ['comissao_cargo_id' => $commissionOfficeId]
        );
    }

    private function setVoteFlowStatus(
        int $commissionOfficeId,
        string $status,
        ?int $indicationLimit = null,
        ?int $voteLimit = null
    ): bool
    {
        return $this->execute(
            'INSERT INTO com_voto
                (comissao_cargo_id, status, limite_indicacoes, limite_votos, indicacoes_abertas_at, created_at, updated_at)
             VALUES
                (:comissao_cargo_id, :insert_status, COALESCE(:insert_indication_limit, 1),
                 COALESCE(:insert_vote_limit, 1), IF(:insert_open = 1, NOW(), NULL), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                status = :update_status,
                limite_indicacoes = COALESCE(:update_indication_limit, limite_indicacoes),
                limite_votos = COALESCE(:update_vote_limit, limite_votos),
                indicacoes_abertas_at = IF(:update_open = 1, NOW(), indicacoes_abertas_at),
                updated_at = NOW()',
            [
                'comissao_cargo_id' => $commissionOfficeId,
                'insert_status' => $status,
                'insert_indication_limit' => $indicationLimit,
                'insert_vote_limit' => $voteLimit,
                'insert_open' => $status === 'indicacao' ? 1 : 0,
                'update_status' => $status,
                'update_indication_limit' => $indicationLimit,
                'update_vote_limit' => $voteLimit,
                'update_open' => $status === 'indicacao' ? 1 : 0,
            ]
        );
    }

    public function availableOffices(int $commissionId): array
    {
        return $this->fetchAll(
            "SELECT c.id, c.nome
             FROM com_cargos c
             WHERE c.status = 'ativo'
               AND NOT EXISTS (
                   SELECT 1 FROM com_comissao_cargos cc
                   WHERE cc.comissao_id = :comissao_id AND cc.cargo_id = c.id
               )
             ORDER BY c.nome, c.id",
            ['comissao_id' => $commissionId]
        );
    }

    public function attachOffice(int $commissionId, int $officeId): bool
    {
        return $this->execute(
            'INSERT IGNORE INTO com_comissao_cargos (comissao_id, cargo_id, ordem, created_at)
             SELECT :comissao_id, id,
                    (SELECT COALESCE(MAX(existing.ordem), 0) + 1
                     FROM com_comissao_cargos existing
                     WHERE existing.comissao_id = :ordem_comissao_id),
                    NOW()
             FROM com_cargos WHERE id = :cargo_id',
            ['comissao_id' => $commissionId, 'ordem_comissao_id' => $commissionId, 'cargo_id' => $officeId]
        );
    }

    public function createAndAttachOffice(int $commissionId, string $name): int
    {
        $database = $this->db();
        $database->beginTransaction();

        try {
            $office = $this->fetch('SELECT id, status FROM com_cargos WHERE nome = :nome LIMIT 1', ['nome' => $name]);
            if ($office === null) {
                $officeId = $this->createOffice(['nome' => $name, 'status' => 'ativo']);
            } else {
                $officeId = (int) $office['id'];
                if ($office['status'] !== 'ativo') {
                    $this->execute(
                        "UPDATE com_cargos SET status = 'ativo', updated_at = NOW() WHERE id = :id",
                        ['id' => $officeId]
                    );
                }
            }

            $this->attachOffice($commissionId, $officeId);
            $database->commit();
            return $officeId;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function detachOffice(int $commissionId, int $officeId): bool
    {
        return $this->execute(
            'DELETE FROM com_comissao_cargos WHERE comissao_id = :comissao_id AND cargo_id = :cargo_id LIMIT 1',
            ['comissao_id' => $commissionId, 'cargo_id' => $officeId]
        );
    }

    public function moveCommissionOffice(int $commissionId, int $officeId, string $direction): bool
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            return false;
        }

        $database = $this->db();
        $database->beginTransaction();

        try {
            $current = $this->fetch(
                'SELECT id, ordem FROM com_comissao_cargos
                 WHERE comissao_id = :comissao_id AND cargo_id = :cargo_id
                 LIMIT 1 FOR UPDATE',
                ['comissao_id' => $commissionId, 'cargo_id' => $officeId]
            );
            if ($current === null) {
                $database->rollBack();
                return false;
            }

            $operator = $direction === 'up' ? '<' : '>';
            $order = $direction === 'up' ? 'DESC' : 'ASC';
            $neighbor = $this->fetch(
                "SELECT id, ordem FROM com_comissao_cargos
                 WHERE comissao_id = :comissao_id AND ordem {$operator} :ordem
                 ORDER BY ordem {$order}, id {$order}
                 LIMIT 1 FOR UPDATE",
                ['comissao_id' => $commissionId, 'ordem' => $current['ordem']]
            );
            if ($neighbor === null) {
                $database->rollBack();
                return false;
            }

            $this->execute(
                'UPDATE com_comissao_cargos SET ordem = :ordem WHERE id = :id',
                ['id' => $current['id'], 'ordem' => $neighbor['ordem']]
            );
            $this->execute(
                'UPDATE com_comissao_cargos SET ordem = :ordem WHERE id = :id',
                ['id' => $neighbor['id'], 'ordem' => $current['ordem']]
            );
            $database->commit();
            return true;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
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
