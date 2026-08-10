<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class ObraRepository extends BaseRepository
{
    public function dashboardSummary(): array
    {
        return [
            'ativas' => (int) ($this->fetch("SELECT COUNT(*) total FROM obras WHERE status IN ('Planejada','Em andamento')")['total'] ?? 0),
            'pendencias' => (int) ($this->fetch("SELECT COUNT(*) total FROM obra_pendencias WHERE status = 'Aberta'")['total'] ?? 0),
            'contratos' => (int) ($this->fetch("SELECT COUNT(*) total FROM obra_contratos WHERE status = 'Ativo'")['total'] ?? 0),
        ];
    }

    public function all(): array
    {
        return $this->fetchAll(
            "SELECT o.*,
                    COALESCE((SELECT AVG(i.progresso) FROM obra_itens i WHERE i.obra_id = o.id AND NOT EXISTS (SELECT 1 FROM obra_itens child WHERE child.parent_id = i.id)), 0) progresso_itens,
                    COALESCE((SELECT SUM(custo_previsto) FROM obra_itens i WHERE i.obra_id = o.id), 0) custo_previsto,
                    COALESCE((SELECT SUM(CASE WHEN mf.tipo = 'Saida' AND mf.status IN ('Pago','Comprometido') THEN mf.valor ELSE 0 END) FROM obra_movimentos_financeiros mf WHERE mf.obra_id = o.id), 0) custo_real
             FROM obras o
             ORDER BY FIELD(o.status, 'Em andamento', 'Planejada', 'Pausada', 'Concluida', 'Cancelada'), o.updated_at DESC, o.id DESC"
        );
    }

    public function recent(): array
    {
        return $this->fetchAll(
            "SELECT id, nome, local, status, data_inicio_prevista, data_fim_prevista
             FROM obras
             ORDER BY updated_at DESC, id DESC
             LIMIT 8"
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch('SELECT * FROM obras WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function createObra(array $data, int $userId): int
    {
        $this->execute(
            "INSERT INTO obras (nome, local, descricao, status, prioridade, data_inicio_prevista, data_fim_prevista, created_by, created_at, updated_at)
             VALUES (:nome, :local, :descricao, :status, :prioridade, :data_inicio_prevista, :data_fim_prevista, :created_by, NOW(), NOW())",
            [
                'nome' => $data['nome'],
                'local' => $data['local'] ?: null,
                'descricao' => $data['descricao'] ?: null,
                'status' => $data['status'],
                'prioridade' => $data['prioridade'],
                'data_inicio_prevista' => $data['data_inicio_prevista'] ?: null,
                'data_fim_prevista' => $data['data_fim_prevista'] ?: null,
                'created_by' => $userId,
            ]
        );

        return (int) $this->db()->lastInsertId();
    }

    public function overview(int $obraId): array
    {
        $finance = $this->financeSummary($obraId);
        $items = $this->fetch(
            "SELECT COUNT(*) total,
                    COALESCE(AVG(CASE WHEN NOT EXISTS (SELECT 1 FROM obra_itens child WHERE child.parent_id = i.id) THEN i.progresso END), 0) progresso
             FROM obra_itens i
             WHERE i.obra_id = :obra_id",
            ['obra_id' => $obraId]
        ) ?: ['total' => 0, 'progresso' => 0];

        return [
            'itens' => (int) ($items['total'] ?? 0),
            'progresso' => round((float) ($items['progresso'] ?? 0), 1),
            'orcado' => $finance['orcado'],
            'realizado' => $finance['realizado'],
            'saldo' => $finance['saldo'],
        ];
    }

    public function planningItems(int $obraId): array
    {
        $items = $this->fetchAll(
            "SELECT i.*, (SELECT COUNT(*) FROM obra_itens child WHERE child.parent_id = i.id) children_count
             FROM obra_itens i
             WHERE i.obra_id = :obra_id
             ORDER BY i.ordem, i.id",
            ['obra_id' => $obraId]
        );
        $flat = $this->flattenItems($items);
        $byId = [];
        foreach ($flat as $index => $item) {
            $flat[$index]['predecessor_ids'] = [];
            $flat[$index]['predecessors'] = [];
            $flat[$index]['resources'] = [];
            $byId[(int) $item['id']] = $index;
        }

        $relations = $this->fetchAll(
            "SELECT relation.item_id, relation.predecessor_id, relation.tipo, relation.defasagem_dias,
                    predecessor.nome predecessor_nome
             FROM obra_item_predecessores relation
             INNER JOIN obra_itens item ON item.id = relation.item_id
             INNER JOIN obra_itens predecessor ON predecessor.id = relation.predecessor_id
             WHERE item.obra_id = :obra_id
             ORDER BY relation.item_id, predecessor.ordem, predecessor.id",
            ['obra_id' => $obraId]
        );
        foreach ($relations as $relation) {
            $itemId = (int) $relation['item_id'];
            $predecessorId = (int) $relation['predecessor_id'];
            if (!isset($byId[$itemId])) {
                continue;
            }
            $predecessorIndex = $byId[$predecessorId] ?? null;
            $label = $predecessorIndex !== null
                ? $flat[$predecessorIndex]['wbs'] . ' ' . $flat[$predecessorIndex]['nome']
                : $relation['predecessor_nome'];
            $flat[$byId[$itemId]]['predecessor_ids'][] = $predecessorId;
            $flat[$byId[$itemId]]['predecessors'][] = $label;
        }

        foreach ($this->planningResources($obraId) as $resource) {
            $itemId = (int) $resource['item_id'];
            if (isset($byId[$itemId])) {
                $flat[$byId[$itemId]]['resources'][] = $resource;
            }
        }

        return $flat;
    }

    private function planningResources(int $obraId): array
    {
        return $this->fetchAll(
            "SELECT resources.*
             FROM (
                SELECT m.id resource_id, m.item_id, m.sinapi_id, 'Material' tipo, c.codigo sinapi_codigo, m.nome, m.unidade,
                       m.quantidade_prevista quantidade, m.quantidade_prevista * m.valor_unitario_previsto valor_previsto,
                       m.valor_unitario_previsto valor_unitario, m.status, f.nome fornecedor_nome, m.observacoes
                FROM obra_materiais m
                LEFT JOIN sinapi_catalogo c ON c.id = m.sinapi_id
                LEFT JOIN obra_fornecedores f ON f.id = m.fornecedor_id
                WHERE m.obra_id = :material_obra_id AND m.item_id IS NOT NULL
                UNION ALL
                SELECT s.id resource_id, s.item_id, s.sinapi_id, 'Servico' tipo, c.codigo sinapi_codigo, s.nome, s.unidade,
                       s.quantidade quantidade, s.valor_previsto valor_previsto,
                       CASE WHEN s.quantidade > 0 THEN s.valor_previsto / s.quantidade ELSE 0 END valor_unitario,
                       s.status, f.nome fornecedor_nome, s.observacoes
                FROM obra_servicos s
                LEFT JOIN sinapi_catalogo c ON c.id = s.sinapi_id
                LEFT JOIN obra_fornecedores f ON f.id = s.fornecedor_id
                WHERE s.obra_id = :service_obra_id AND s.item_id IS NOT NULL
                UNION ALL
                SELECT e.id resource_id, e.item_id, e.sinapi_id, 'Equipamento' tipo, c.codigo sinapi_codigo, e.nome, e.unidade,
                       e.quantidade_prevista quantidade, e.quantidade_prevista * e.valor_unitario_previsto valor_previsto,
                       e.valor_unitario_previsto valor_unitario, e.status, f.nome fornecedor_nome, e.observacoes
                FROM obra_equipamentos e
                LEFT JOIN sinapi_catalogo c ON c.id = e.sinapi_id
                LEFT JOIN obra_fornecedores f ON f.id = e.fornecedor_id
                WHERE e.obra_id = :equipment_obra_id AND e.item_id IS NOT NULL
             ) resources
             ORDER BY resources.item_id, FIELD(resources.tipo, 'Material', 'Servico', 'Equipamento'), resources.nome",
            [
                'material_obra_id' => $obraId,
                'service_obra_id' => $obraId,
                'equipment_obra_id' => $obraId,
            ]
        );
    }

    public function itemResourceCount(int $obraId, int $itemId): int
    {
        $row = $this->fetch(
            'SELECT (SELECT COUNT(*) FROM obra_materiais WHERE obra_id=:obra_m AND item_id=:item_m) + (SELECT COUNT(*) FROM obra_servicos WHERE obra_id=:obra_s AND item_id=:item_s) + (SELECT COUNT(*) FROM obra_equipamentos WHERE obra_id=:obra_e AND item_id=:item_e) total',
            ['obra_m'=>$obraId,'item_m'=>$itemId,'obra_s'=>$obraId,'item_s'=>$itemId,'obra_e'=>$obraId,'item_e'=>$itemId]
        );
        return (int)($row['total'] ?? 0);
    }

    public function compositionResourceExists(int $obraId, int $itemId, int $sinapiId, string $type): bool
    {
        $tables = ['Material'=>'obra_materiais','Servico'=>'obra_servicos','Equipamento'=>'obra_equipamentos'];
        if (!isset($tables[$type])) return false;
        return $this->fetch("SELECT id FROM {$tables[$type]} WHERE obra_id=:obra_id AND item_id=:item_id AND sinapi_id=:sinapi_id LIMIT 1", ['obra_id'=>$obraId,'item_id'=>$itemId,'sinapi_id'=>$sinapiId]) !== null;
    }

    public function findPlanningItem(int $obraId, int $itemId): ?array
    {
        foreach ($this->planningItems($obraId) as $item) {
            if ((int) $item['id'] === $itemId) {
                return $item;
            }
        }

        return null;
    }

    public function itemOptions(int $obraId, ?int $excludedBranchId = null): array
    {
        $items = $this->planningItems($obraId);
        if ($excludedBranchId === null) {
            return $items;
        }

        $excluded = array_flip($this->descendantIds($items, $excludedBranchId));
        $excluded[$excludedBranchId] = true;
        return array_values(array_filter($items, static fn (array $item): bool => !isset($excluded[(int) $item['id']])));
    }

    public function fornecedores(): array
    {
        return $this->fetchAll("SELECT id, nome FROM obra_fornecedores WHERE status = 'Ativo' ORDER BY nome");
    }

    public function createFornecedorIfNeeded(?string $nome): ?int
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return null;
        }

        $found = $this->fetch('SELECT id FROM obra_fornecedores WHERE nome = :nome LIMIT 1', ['nome' => $nome]);
        if ($found) {
            return (int) $found['id'];
        }

        $this->execute('INSERT INTO obra_fornecedores (nome, status, created_at, updated_at) VALUES (:nome, \'Ativo\', NOW(), NOW())', ['nome' => $nome]);
        return (int) $this->db()->lastInsertId();
    }

    public function createPlanningItem(int $obraId, array $data): int
    {
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $this->validateParent($obraId, null, $parentId);
        $predecessorIds = $this->normalizeIds($data['predecessor_ids'] ?? []);
        $this->validatePredecessors($obraId, null, $predecessorIds);

        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $this->insertPlanningItem($obraId, $parentId, $data);
            $itemId = (int) $pdo->lastInsertId();
            $this->syncPredecessors($itemId, $predecessorIds);
            $pdo->commit();
            return $itemId;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function updatePlanningItem(int $obraId, int $itemId, array $data): bool
    {
        if (!$this->findPlanningItem($obraId, $itemId)) {
            return false;
        }

        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $predecessorIds = $this->normalizeIds($data['predecessor_ids'] ?? []);
        $this->validateParent($obraId, $itemId, $parentId);
        $this->validatePredecessors($obraId, $itemId, $predecessorIds);

        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $updated = $this->execute(
                "UPDATE obra_itens
                 SET parent_id = :parent_id, nome = :nome, descricao = :descricao, responsavel = :responsavel,
                     ordem = :ordem, status = :status, progresso = :progresso,
                     duracao_prevista_dias = :duracao_prevista_dias,
                     data_inicio_prevista = :data_inicio_prevista, data_fim_prevista = :data_fim_prevista,
                     custo_previsto = :custo_previsto, marco = :marco, updated_at = NOW()
                 WHERE id = :id AND obra_id = :obra_id",
                $this->planningItemParams($data, $parentId) + ['id' => $itemId, 'obra_id' => $obraId]
            );
            $this->syncPredecessors($itemId, $predecessorIds);
            $pdo->commit();
            return $updated;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function deletePlanningItem(int $obraId, int $itemId): bool
    {
        return $this->execute('DELETE FROM obra_itens WHERE id = :id AND obra_id = :obra_id', ['id' => $itemId, 'obra_id' => $obraId]);
    }

    public function movePlanningItem(int $obraId, int $itemId, ?int $parentId): bool
    {
        if (!$this->findPlanningItem($obraId, $itemId)) {
            return false;
        }
        $this->validateParent($obraId, $itemId, $parentId);
        $order = (int) ($this->fetch(
            $parentId === null
                ? 'SELECT COALESCE(MAX(ordem), 0) + 10 next_order FROM obra_itens WHERE obra_id = :obra_id AND parent_id IS NULL'
                : 'SELECT COALESCE(MAX(ordem), 0) + 10 next_order FROM obra_itens WHERE obra_id = :obra_id AND parent_id = :parent_id',
            $parentId === null ? ['obra_id' => $obraId] : ['obra_id' => $obraId, 'parent_id' => $parentId]
        )['next_order'] ?? 10);

        return $this->execute(
            'UPDATE obra_itens SET parent_id = :parent_id, ordem = :ordem, updated_at = NOW() WHERE id = :id AND obra_id = :obra_id',
            ['parent_id' => $parentId, 'ordem' => $order, 'id' => $itemId, 'obra_id' => $obraId]
        );
    }

    public function findCompositionResource(int $obraId, string $type, int $resourceId): ?array
    {
        $definitions = [
            'Material' => ['table' => 'obra_materiais', 'quantity' => 'quantidade_prevista', 'unit_price' => 'valor_unitario_previsto'],
            'Servico' => ['table' => 'obra_servicos', 'quantity' => 'quantidade', 'unit_price' => 'CASE WHEN resource.quantidade > 0 THEN resource.valor_previsto / resource.quantidade ELSE 0 END'],
            'Equipamento' => ['table' => 'obra_equipamentos', 'quantity' => 'quantidade_prevista', 'unit_price' => 'valor_unitario_previsto'],
        ];
        if (!isset($definitions[$type])) {
            return null;
        }
        $definition = $definitions[$type];
        return $this->fetch(
            "SELECT resource.id resource_id, resource.obra_id, resource.item_id, resource.fornecedor_id, resource.sinapi_id,
                    resource.nome, resource.unidade, resource.{$definition['quantity']} quantidade,
                    {$definition['unit_price']} valor_unitario, resource.status, resource.observacoes,
                    catalog.codigo sinapi_codigo, supplier.nome fornecedor_nome
             FROM {$definition['table']} resource
             LEFT JOIN sinapi_catalogo catalog ON catalog.id = resource.sinapi_id
             LEFT JOIN obra_fornecedores supplier ON supplier.id = resource.fornecedor_id
             WHERE resource.id = :id AND resource.obra_id = :obra_id LIMIT 1",
            ['id' => $resourceId, 'obra_id' => $obraId]
        );
    }

    public function updateCompositionResource(int $obraId, string $type, int $resourceId, array $data): bool
    {
        $common = [
            'item_id' => $this->itemReference($obraId, $data['item_id']),
            'fornecedor_id' => $data['fornecedor_id'],
            'sinapi_id' => $data['sinapi_id'],
            'nome' => $data['nome'],
            'unidade' => $data['unidade'],
            'quantidade' => (float) $data['quantidade'],
            'valor_unitario' => (float) $data['valor_unitario'],
            'status' => $data['status'],
            'observacoes' => $data['observacoes'] ?: null,
            'id' => $resourceId,
            'obra_id' => $obraId,
        ];
        if ($type === 'Material') {
            return $this->execute(
                'UPDATE obra_materiais SET item_id=:item_id, fornecedor_id=:fornecedor_id, sinapi_id=:sinapi_id, nome=:nome, unidade=:unidade, quantidade_prevista=:quantidade, valor_unitario_previsto=:valor_unitario, status=:status, observacoes=:observacoes, updated_at=NOW() WHERE id=:id AND obra_id=:obra_id',
                $common
            );
        }
        if ($type === 'Servico') {
            $service = $common;
            $service['valor_previsto'] = $service['quantidade'] * $service['valor_unitario'];
            unset($service['valor_unitario']);
            return $this->execute(
                'UPDATE obra_servicos SET item_id=:item_id, fornecedor_id=:fornecedor_id, sinapi_id=:sinapi_id, nome=:nome, unidade=:unidade, quantidade=:quantidade, valor_previsto=:valor_previsto, status=:status, observacoes=:observacoes, updated_at=NOW() WHERE id=:id AND obra_id=:obra_id',
                $service
            );
        }
        if ($type === 'Equipamento') {
            return $this->execute(
                'UPDATE obra_equipamentos SET item_id=:item_id, fornecedor_id=:fornecedor_id, sinapi_id=:sinapi_id, nome=:nome, unidade=:unidade, quantidade_prevista=:quantidade, valor_unitario_previsto=:valor_unitario, status=:status, observacoes=:observacoes, updated_at=NOW() WHERE id=:id AND obra_id=:obra_id',
                $common
            );
        }
        return false;
    }

    public function deleteCompositionResource(int $obraId, string $type, int $resourceId): bool
    {
        $tables = [
            'Material' => 'obra_materiais',
            'Servico' => 'obra_servicos',
            'Equipamento' => 'obra_equipamentos',
        ];
        if (!isset($tables[$type])) {
            return false;
        }

        $statement = $this->db()->prepare("DELETE FROM {$tables[$type]} WHERE id = :id AND obra_id = :obra_id");
        $statement->execute(['id' => $resourceId, 'obra_id' => $obraId]);
        return $statement->rowCount() > 0;
    }

    public function createPlanningModel(int $obraId, string $name, string $description, int $userId): int
    {
        $items = $this->planningItems($obraId);
        if ($items === []) {
            throw new \InvalidArgumentException('O planejamento precisa ter itens para ser salvo como modelo.');
        }
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO obra_modelos (nome, descricao, created_by, created_at, updated_at) VALUES (:nome, :descricao, :created_by, NOW(), NOW())',
                ['nome' => $name, 'descricao' => $description ?: null, 'created_by' => $userId]
            );
            $modelId = (int) $pdo->lastInsertId();
            $modelIds = [];
            foreach ($items as $item) {
                $parentModelId = !empty($item['parent_id']) ? ($modelIds[(int) $item['parent_id']] ?? null) : null;
                $this->execute(
                    'INSERT INTO obra_modelo_itens (modelo_id, parent_item_id, nome, descricao, responsavel, ordem, duracao_prevista_dias, custo_previsto, marco, created_at) VALUES (:modelo_id, :parent_item_id, :nome, :descricao, :responsavel, :ordem, :duracao, :custo, :marco, NOW())',
                    ['modelo_id' => $modelId, 'parent_item_id' => $parentModelId, 'nome' => $item['nome'], 'descricao' => $item['descricao'], 'responsavel' => $item['responsavel'], 'ordem' => $item['ordem'], 'duracao' => $item['duracao_prevista_dias'], 'custo' => $item['custo_previsto'], 'marco' => $item['marco']]
                );
                $modelIds[(int) $item['id']] = (int) $pdo->lastInsertId();
            }
            $pdo->commit();
            return $modelId;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function planningModels(): array
    {
        return $this->fetchAll('SELECT model.*, COUNT(item.id) item_count FROM obra_modelos model LEFT JOIN obra_modelo_itens item ON item.modelo_id = model.id GROUP BY model.id ORDER BY model.nome');
    }

    public function applyPlanningModel(int $obraId, int $modelId): int
    {
        $items = $this->fetchAll('SELECT * FROM obra_modelo_itens WHERE modelo_id = :modelo_id ORDER BY id', ['modelo_id' => $modelId]);
        $createdIds = [];
        foreach ($items as $item) {
            $createdIds[(int) $item['id']] = $this->createPlanningItem($obraId, [
                'parent_id' => !empty($item['parent_item_id']) ? ($createdIds[(int) $item['parent_item_id']] ?? null) : null,
                'predecessor_ids' => [], 'nome' => $item['nome'], 'descricao' => $item['descricao'] ?? '',
                'responsavel' => $item['responsavel'] ?? '', 'ordem' => $item['ordem'], 'status' => 'Nao iniciada',
                'progresso' => 0, 'duracao_prevista_dias' => $item['duracao_prevista_dias'] ?? '',
                'data_inicio_prevista' => '', 'data_fim_prevista' => '', 'custo_previsto' => $item['custo_previsto'], 'marco' => $item['marco'],
            ]);
        }
        return count($createdIds);
    }

    public function importAnalyticalStructure(int $obraId, array $items): array
    {
        $created = 0;
        $idsByCode = [];
        $occurrences = [];
        foreach ($items as $item) {
            $parentId = isset($item['parent_code']) ? ($idsByCode[$item['parent_code']] ?? null) : null;
            $occurrenceKey = ($parentId ?? 0) . '|' . $item['name'];
            $occurrence = $occurrences[$occurrenceKey] ?? 0;
            $occurrences[$occurrenceKey] = $occurrence + 1;
            $matching = $parentId === null
                ? $this->fetchAll('SELECT id FROM obra_itens WHERE obra_id = :obra_id AND parent_id IS NULL AND nome = :nome ORDER BY ordem, id', ['obra_id' => $obraId, 'nome' => $item['name']])
                : $this->fetchAll('SELECT id FROM obra_itens WHERE obra_id = :obra_id AND parent_id = :parent_id AND nome = :nome ORDER BY ordem, id', ['obra_id' => $obraId, 'parent_id' => $parentId, 'nome' => $item['name']]);
            $existing = $matching[$occurrence] ?? null;
            if ($existing) {
                $idsByCode[$item['code']] = (int) $existing['id'];
                continue;
            }
            $idsByCode[$item['code']] = $this->createPlanningItem($obraId, [
                'parent_id' => $parentId,
                'predecessor_ids' => [],
                'nome' => $item['name'],
                'descricao' => $item['description'] ?? '',
                'responsavel' => $item['responsible'] ?? '',
                'ordem' => $item['order'],
                'status' => $item['status'] ?? 'Nao iniciada',
                'progresso' => $item['progress'] ?? 0,
                'duracao_prevista_dias' => $item['duration'] ?? '',
                'data_inicio_prevista' => $item['start'] ?? '',
                'data_fim_prevista' => $item['finish'] ?? '',
                'custo_previsto' => $item['cost'] ?? 0,
                'marco' => $item['milestone'] ?? 0,
            ]);
            $created++;
        }

        foreach ($items as $item) {
            $itemId = $idsByCode[$item['code']] ?? null;
            if (!$itemId || empty($item['predecessor_codes'])) continue;
            $predecessorIds = [];
            foreach ($item['predecessor_codes'] as $code) {
                if (isset($idsByCode[$code])) $predecessorIds[] = $idsByCode[$code];
            }
            $this->validatePredecessors($obraId, $itemId, $predecessorIds);
            $this->syncPredecessors($itemId, $predecessorIds);
        }

        return ['items' => $created];
    }

    public function materials(int $obraId): array
    {
        return $this->fetchAll(
            "SELECT m.*, i.nome item_nome, f.nome fornecedor_nome, c.codigo sinapi_codigo
             FROM obra_materiais m
             LEFT JOIN obra_itens i ON i.id = m.item_id
             LEFT JOIN obra_fornecedores f ON f.id = m.fornecedor_id
             LEFT JOIN sinapi_catalogo c ON c.id = m.sinapi_id
             WHERE m.obra_id = :obra_id
             ORDER BY FIELD(m.status, 'Solicitado', 'Previsto', 'Comprado', 'Entregue', 'Cancelado'), m.data_necessidade, m.id DESC",
            ['obra_id' => $obraId]
        );
    }

    public function createMaterial(int $obraId, array $data): bool
    {
        return $this->execute(
            "INSERT INTO obra_materiais (obra_id, item_id, fornecedor_id, sinapi_id, nome, especificacao, unidade, quantidade_prevista, quantidade_comprada, valor_unitario_previsto, valor_unitario_real, status, data_necessidade, observacoes, created_at, updated_at)
             VALUES (:obra_id, :item_id, :fornecedor_id, :sinapi_id, :nome, :especificacao, :unidade, :quantidade_prevista, :quantidade_comprada, :valor_unitario_previsto, :valor_unitario_real, :status, :data_necessidade, :observacoes, NOW(), NOW())",
            [
                'obra_id' => $obraId,
                'item_id' => $this->itemReference($obraId, $data['item_id'] ?? null),
                'fornecedor_id' => $data['fornecedor_id'],
                'sinapi_id' => $data['sinapi_id'],
                'nome' => $data['nome'],
                'especificacao' => $data['especificacao'] ?: null,
                'unidade' => $data['unidade'] ?: 'un',
                'quantidade_prevista' => (float) ($data['quantidade_prevista'] ?: 0),
                'quantidade_comprada' => (float) ($data['quantidade_comprada'] ?: 0),
                'valor_unitario_previsto' => (float) ($data['valor_unitario_previsto'] ?: 0),
                'valor_unitario_real' => (float) ($data['valor_unitario_real'] ?: 0),
                'status' => $data['status'],
                'data_necessidade' => $data['data_necessidade'] ?: null,
                'observacoes' => $data['observacoes'] ?: null,
            ]
        );
    }

    public function services(int $obraId): array
    {
        return $this->fetchAll(
            "SELECT s.*, i.nome item_nome, f.nome fornecedor_nome, c.codigo sinapi_codigo
             FROM obra_servicos s
             LEFT JOIN obra_itens i ON i.id = s.item_id
             LEFT JOIN obra_fornecedores f ON f.id = s.fornecedor_id
             LEFT JOIN sinapi_catalogo c ON c.id = s.sinapi_id
             WHERE s.obra_id = :obra_id
             ORDER BY FIELD(s.status, 'Em execucao', 'Contratado', 'Cotando', 'Previsto', 'Medido', 'Concluido', 'Cancelado'), s.id DESC",
            ['obra_id' => $obraId]
        );
    }

    public function createService(int $obraId, array $data): bool
    {
        return $this->execute(
            "INSERT INTO obra_servicos (obra_id, item_id, fornecedor_id, sinapi_id, nome, escopo, unidade, quantidade, valor_previsto, valor_contratado, status, data_inicio, data_fim, observacoes, created_at, updated_at)
             VALUES (:obra_id, :item_id, :fornecedor_id, :sinapi_id, :nome, :escopo, :unidade, :quantidade, :valor_previsto, :valor_contratado, :status, :data_inicio, :data_fim, :observacoes, NOW(), NOW())",
            [
                'obra_id' => $obraId,
                'item_id' => $this->itemReference($obraId, $data['item_id'] ?? null),
                'fornecedor_id' => $data['fornecedor_id'],
                'sinapi_id' => $data['sinapi_id'],
                'nome' => $data['nome'],
                'escopo' => $data['escopo'] ?: null,
                'unidade' => $data['unidade'] ?: 'servico',
                'quantidade' => (float) ($data['quantidade'] ?: 1),
                'valor_previsto' => (float) ($data['valor_previsto'] ?: 0),
                'valor_contratado' => (float) ($data['valor_contratado'] ?: 0),
                'status' => $data['status'],
                'data_inicio' => $data['data_inicio'] ?: null,
                'data_fim' => $data['data_fim'] ?: null,
                'observacoes' => $data['observacoes'] ?: null,
            ]
        );
    }

    public function equipment(int $obraId): array
    {
        return $this->fetchAll(
            "SELECT e.*, i.nome item_nome, f.nome fornecedor_nome, c.codigo sinapi_codigo
             FROM obra_equipamentos e
             LEFT JOIN obra_itens i ON i.id = e.item_id
             LEFT JOIN obra_fornecedores f ON f.id = e.fornecedor_id
             LEFT JOIN sinapi_catalogo c ON c.id = e.sinapi_id
             WHERE e.obra_id = :obra_id
             ORDER BY FIELD(e.status, 'Em uso', 'Contratado', 'Cotando', 'Previsto', 'Devolvido', 'Cancelado'), e.id DESC",
            ['obra_id' => $obraId]
        );
    }

    public function createEquipment(int $obraId, array $data): bool
    {
        return $this->execute(
            "INSERT INTO obra_equipamentos
                (obra_id, item_id, fornecedor_id, sinapi_id, nome, unidade, quantidade_prevista, quantidade_real, valor_unitario_previsto, valor_unitario_real, status, data_inicio, data_fim, observacoes, created_at, updated_at)
             VALUES
                (:obra_id, :item_id, :fornecedor_id, :sinapi_id, :nome, :unidade, :quantidade_prevista, :quantidade_real, :valor_unitario_previsto, :valor_unitario_real, :status, :data_inicio, :data_fim, :observacoes, NOW(), NOW())",
            [
                'obra_id' => $obraId,
                'item_id' => $this->itemReference($obraId, $data['item_id'] ?? null),
                'fornecedor_id' => $data['fornecedor_id'],
                'sinapi_id' => $data['sinapi_id'],
                'nome' => $data['nome'],
                'unidade' => $data['unidade'],
                'quantidade_prevista' => (float) $data['quantidade_prevista'],
                'quantidade_real' => (float) $data['quantidade_real'],
                'valor_unitario_previsto' => (float) $data['valor_unitario_previsto'],
                'valor_unitario_real' => (float) $data['valor_unitario_real'],
                'status' => $data['status'],
                'data_inicio' => $data['data_inicio'] ?: null,
                'data_fim' => $data['data_fim'] ?: null,
                'observacoes' => $data['observacoes'] ?: null,
            ]
        );
    }

    public function financeSummary(int $obraId): array
    {
        $orcado = (float) ($this->fetch(
            "SELECT
                COALESCE((SELECT SUM(custo_previsto) FROM obra_itens WHERE obra_id = :obra_id_a), 0) itens,
                COALESCE((SELECT SUM(quantidade_prevista * valor_unitario_previsto) FROM obra_materiais WHERE obra_id = :obra_id_b), 0) materiais,
                COALESCE((SELECT SUM(valor_previsto) FROM obra_servicos WHERE obra_id = :obra_id_c), 0) servicos,
                COALESCE((SELECT SUM(quantidade_prevista * valor_unitario_previsto) FROM obra_equipamentos WHERE obra_id = :obra_id_d), 0) equipamentos",
            ['obra_id_a' => $obraId, 'obra_id_b' => $obraId, 'obra_id_c' => $obraId, 'obra_id_d' => $obraId]
        )['itens'] ?? 0);
        $extra = $this->fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN tipo = 'Entrada' AND status IN ('Recebido','Comprometido') THEN valor ELSE 0 END), 0) entradas,
                COALESCE(SUM(CASE WHEN tipo = 'Saida' AND status IN ('Pago','Comprometido') THEN valor ELSE 0 END), 0) saidas
             FROM obra_movimentos_financeiros
             WHERE obra_id = :obra_id",
            ['obra_id' => $obraId]
        ) ?: ['entradas' => 0, 'saidas' => 0];
        $orcados = $this->fetch(
            "SELECT
                COALESCE((SELECT SUM(custo_previsto) FROM obra_itens WHERE obra_id = :obra_id_a), 0) itens,
                COALESCE((SELECT SUM(quantidade_prevista * valor_unitario_previsto) FROM obra_materiais WHERE obra_id = :obra_id_b), 0) materiais,
                COALESCE((SELECT SUM(valor_previsto) FROM obra_servicos WHERE obra_id = :obra_id_c), 0) servicos,
                COALESCE((SELECT SUM(quantidade_prevista * valor_unitario_previsto) FROM obra_equipamentos WHERE obra_id = :obra_id_d), 0) equipamentos",
            ['obra_id_a' => $obraId, 'obra_id_b' => $obraId, 'obra_id_c' => $obraId, 'obra_id_d' => $obraId]
        ) ?: ['itens' => 0, 'materiais' => 0, 'servicos' => 0, 'equipamentos' => 0];
        $orcado = (float) $orcados['itens'] + (float) $orcados['materiais'] + (float) $orcados['servicos'] + (float) $orcados['equipamentos'];
        $realizado = (float) $extra['saidas'];

        return ['orcado' => $orcado, 'realizado' => $realizado, 'entradas' => (float) $extra['entradas'], 'saldo' => (float) $extra['entradas'] - $realizado];
    }

    public function movements(int $obraId): array
    {
        return $this->fetchAll(
            "SELECT mf.*, i.nome item_nome, f.nome fornecedor_nome
             FROM obra_movimentos_financeiros mf
             LEFT JOIN obra_itens i ON i.id = mf.item_id
             LEFT JOIN obra_fornecedores f ON f.id = mf.fornecedor_id
             WHERE mf.obra_id = :obra_id
             ORDER BY mf.data_movimento DESC, mf.id DESC",
            ['obra_id' => $obraId]
        );
    }

    public function createMovement(int $obraId, array $data, int $userId): bool
    {
        return $this->execute(
            "INSERT INTO obra_movimentos_financeiros (obra_id, item_id, fornecedor_id, tipo, categoria, descricao, valor, data_movimento, vencimento, status, forma_pagamento, documento, observacoes, created_by, created_at, updated_at)
             VALUES (:obra_id, :item_id, :fornecedor_id, :tipo, :categoria, :descricao, :valor, :data_movimento, :vencimento, :status, :forma_pagamento, :documento, :observacoes, :created_by, NOW(), NOW())",
            [
                'obra_id' => $obraId,
                'item_id' => $this->itemReference($obraId, $data['item_id'] ?? null),
                'fornecedor_id' => $data['fornecedor_id'],
                'tipo' => $data['tipo'],
                'categoria' => $data['categoria'],
                'descricao' => $data['descricao'],
                'valor' => (float) ($data['valor'] ?: 0),
                'data_movimento' => $data['data_movimento'],
                'vencimento' => $data['vencimento'] ?: null,
                'status' => $data['status'],
                'forma_pagamento' => $data['forma_pagamento'] ?: null,
                'documento' => $data['documento'] ?: null,
                'observacoes' => $data['observacoes'] ?: null,
                'created_by' => $userId,
            ]
        );
    }

    public function diary(int $obraId): array
    {
        return $this->fetchAll('SELECT * FROM obra_diarios WHERE obra_id = :obra_id ORDER BY data_registro DESC, id DESC LIMIT 10', ['obra_id' => $obraId]);
    }

    public function createDiary(int $obraId, array $data, int $userId): bool
    {
        return $this->execute(
            "INSERT INTO obra_diarios (obra_id, data_registro, titulo, relato, clima, equipe_presente, impedimentos, created_by, created_at, updated_at)
             VALUES (:obra_id, :data_registro, :titulo, :relato, :clima, :equipe_presente, :impedimentos, :created_by, NOW(), NOW())",
            [
                'obra_id' => $obraId,
                'data_registro' => $data['data_registro'],
                'titulo' => $data['titulo'],
                'relato' => $data['relato'],
                'clima' => $data['clima'] ?: null,
                'equipe_presente' => $data['equipe_presente'] ?: null,
                'impedimentos' => $data['impedimentos'] ?: null,
                'created_by' => $userId,
            ]
        );
    }

    private function insertPlanningItem(int $obraId, ?int $parentId, array $data): void
    {
        $this->execute(
            "INSERT INTO obra_itens (obra_id, parent_id, nome, descricao, responsavel, ordem, status, progresso, duracao_prevista_dias, data_inicio_prevista, data_fim_prevista, custo_previsto, marco, created_at, updated_at)
             VALUES (:obra_id, :parent_id, :nome, :descricao, :responsavel, :ordem, :status, :progresso, :duracao_prevista_dias, :data_inicio_prevista, :data_fim_prevista, :custo_previsto, :marco, NOW(), NOW())",
            ['obra_id' => $obraId] + $this->planningItemParams($data, $parentId)
        );
    }

    private function planningItemParams(array $data, ?int $parentId): array
    {
        return [
            'parent_id' => $parentId,
            'nome' => $data['nome'],
            'descricao' => $data['descricao'] ?: null,
            'responsavel' => $data['responsavel'] ?: null,
            'ordem' => (int) ($data['ordem'] ?: 0),
            'status' => $data['status'],
            'progresso' => min(100, max(0, (int) $data['progresso'])),
            'duracao_prevista_dias' => $data['duracao_prevista_dias'] !== '' ? (int) $data['duracao_prevista_dias'] : null,
            'data_inicio_prevista' => $data['data_inicio_prevista'] ?: null,
            'data_fim_prevista' => $data['data_fim_prevista'] ?: null,
            'custo_previsto' => (float) ($data['custo_previsto'] ?: 0),
            'marco' => !empty($data['marco']) ? 1 : 0,
        ];
    }

    private function flattenItems(array $items): array
    {
        $children = [];
        foreach ($items as $item) {
            $children[(int) ($item['parent_id'] ?? 0)][] = $item;
        }

        $flat = [];
        $visited = [];
        $walk = function (int $parentId, int $depth, string $prefix) use (&$walk, &$flat, &$visited, $children): void {
            foreach ($children[$parentId] ?? [] as $position => $item) {
                $id = (int) $item['id'];
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                $item['depth'] = $depth;
                $item['wbs'] = $prefix . ($position + 1);
                $flat[] = $item;
                $walk($id, $depth + 1, $item['wbs'] . '.');
            }
        };
        $walk(0, 0, '');

        foreach ($items as $item) {
            if (!isset($visited[(int) $item['id']])) {
                $item['depth'] = 0;
                $item['wbs'] = (string) (count($flat) + 1);
                $flat[] = $item;
            }
        }

        return $flat;
    }

    private function descendantIds(array $items, int $itemId): array
    {
        $descendants = [];
        $pending = [$itemId];
        while ($pending !== []) {
            $parentId = array_pop($pending);
            foreach ($items as $item) {
                if ((int) ($item['parent_id'] ?? 0) === $parentId) {
                    $id = (int) $item['id'];
                    $descendants[] = $id;
                    $pending[] = $id;
                }
            }
        }
        return $descendants;
    }

    private function validateParent(int $obraId, ?int $itemId, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        $parent = $this->fetch('SELECT id FROM obra_itens WHERE id = :id AND obra_id = :obra_id', ['id' => $parentId, 'obra_id' => $obraId]);
        if (!$parent) {
            throw new \InvalidArgumentException('O item superior informado nao pertence a esta obra.');
        }
        if ($itemId !== null) {
            $items = $this->planningItems($obraId);
            if ($parentId === $itemId || in_array($parentId, $this->descendantIds($items, $itemId), true)) {
                throw new \InvalidArgumentException('Um item nao pode ser movido para dentro de si mesmo.');
            }
        }
    }

    private function validatePredecessors(int $obraId, ?int $itemId, array $predecessorIds): void
    {
        if ($predecessorIds === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($predecessorIds), '?'));
        $stmt = $this->db()->prepare("SELECT id FROM obra_itens WHERE obra_id = ? AND id IN ({$placeholders})");
        $stmt->execute([$obraId, ...$predecessorIds]);
        if (count($stmt->fetchAll()) !== count($predecessorIds) || ($itemId !== null && in_array($itemId, $predecessorIds, true))) {
            throw new \InvalidArgumentException('As predecessoras devem ser itens diferentes da mesma obra.');
        }
        if ($itemId !== null && $this->createsDependencyCycle($obraId, $itemId, $predecessorIds)) {
            throw new \InvalidArgumentException('As predecessoras informadas criam uma dependencia circular.');
        }
    }

    private function createsDependencyCycle(int $obraId, int $itemId, array $predecessorIds): bool
    {
        $relations = $this->fetchAll(
            'SELECT relation.item_id, relation.predecessor_id FROM obra_item_predecessores relation INNER JOIN obra_itens item ON item.id = relation.item_id WHERE item.obra_id = :obra_id AND relation.item_id <> :item_id',
            ['obra_id' => $obraId, 'item_id' => $itemId]
        );
        $graph = [];
        foreach ($relations as $relation) {
            $graph[(int) $relation['item_id']][] = (int) $relation['predecessor_id'];
        }
        $graph[$itemId] = $predecessorIds;

        $visiting = [];
        $visited = [];
        $visit = function (int $node) use (&$visit, &$visiting, &$visited, $graph): bool {
            if (isset($visiting[$node])) {
                return true;
            }
            if (isset($visited[$node])) {
                return false;
            }
            $visiting[$node] = true;
            foreach ($graph[$node] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            unset($visiting[$node]);
            $visited[$node] = true;
            return false;
        };

        return $visit($itemId);
    }

    private function syncPredecessors(int $itemId, array $predecessorIds): void
    {
        $this->execute('DELETE FROM obra_item_predecessores WHERE item_id = :item_id', ['item_id' => $itemId]);
        $stmt = $this->db()->prepare("INSERT INTO obra_item_predecessores (item_id, predecessor_id, tipo, defasagem_dias, created_at) VALUES (:item_id, :predecessor_id, 'FS', 0, NOW())");
        foreach ($predecessorIds as $predecessorId) {
            $stmt->execute(['item_id' => $itemId, 'predecessor_id' => $predecessorId]);
        }
    }

    private function normalizeIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    private function itemReference(int $obraId, mixed $itemId): ?int
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return null;
        }
        $item = $this->fetch('SELECT id FROM obra_itens WHERE id = :id AND obra_id = :obra_id', ['id' => $itemId, 'obra_id' => $obraId]);
        return $item ? $itemId : null;
    }
}
