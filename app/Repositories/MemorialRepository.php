<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class MemorialRepository extends BaseRepository
{
    public function findByObra(int $obraId): ?array
    {
        return $this->fetch('SELECT * FROM obra_memoriais WHERE obra_id = :obra_id', ['obra_id' => $obraId]);
    }

    public function save(int $obraId, array $data): int
    {
        $columns = [
            'ativo','tipo_obra','padrao_acabamento','sistema_estrutural','area_terreno','area_construida','testada',
            'pavimentos','unidades','banheiros','cozinhas','pe_direito','area_externa','area_cobertura','meses_obra',
            'possui_subsolo','possui_elevador','possui_piscina','comprimento_baldrame','comprimento_muro_divisa',
            'quantidade_brocas','peso_aco_estimado','volume_concreto_estimado','area_revestimento_interno',
            'area_revestimento_externo','area_reboco','quantidade_portas_madeira','quantidade_esquadrias_aluminio',
            'guarda_corpo_m','quantidade_bancadas','soleiras_m','peitoris_m','area_forro_gesso','area_forro_madeira',
            'area_telhado_metalico','area_telhado_ceramico','calhas_m','rufos_m','area_pintura_interna',
            'area_pintura_externa','quantidade_bacias','quantidade_torneiras','quantidade_cubas','area_deck',
            'area_paisagismo','area_piscina','pontos_eletricos','pontos_hidraulicos','observacoes',
        ];
        $params = [];
        foreach ($columns as $column) $params[$column] = $data[$column] ?? 0;
        $params['obra_id'] = $obraId;
        $existing = $this->findByObra($obraId);
        if ($existing) {
            $params['id'] = $existing['id'];
            $assignments = implode(',', array_map(static fn(string $column): string => "{$column}=:{$column}", $columns));
            $this->execute("UPDATE obra_memoriais SET {$assignments},updated_at=NOW() WHERE id=:id AND obra_id=:obra_id", $params);
            return (int) $existing['id'];
        }
        $columnSql = implode(',', $columns);
        $valueSql = implode(',', array_map(static fn(string $column): string => ":{$column}", $columns));
        $this->execute("INSERT INTO obra_memoriais (obra_id,{$columnSql},created_at,updated_at) VALUES (:obra_id,{$valueSql},NOW(),NOW())", $params);
        return (int) $this->db()->lastInsertId();
    }

    public function linksByObra(int $obraId): array
    {
        return $this->fetchAll('SELECT link.*, item.nome item_nome, item.status item_status FROM obra_memorial_vinculos link INNER JOIN obra_memoriais memorial ON memorial.id=link.memorial_id INNER JOIN obra_itens item ON item.id=link.item_id WHERE memorial.obra_id=:obra_id ORDER BY item.ordem,item.id', ['obra_id' => $obraId]);
    }

    public function linkedItem(int $obraId, int $itemId): ?array
    {
        return $this->fetch('SELECT link.* FROM obra_memorial_vinculos link INNER JOIN obra_memoriais memorial ON memorial.id=link.memorial_id WHERE memorial.obra_id=:obra_id AND link.item_id=:item_id', ['obra_id' => $obraId, 'item_id' => $itemId]);
    }

    public function findRule(int $memorialId, string $ruleCode): ?array
    {
        return $this->fetch('SELECT * FROM obra_memorial_vinculos WHERE memorial_id=:memorial_id AND regra_codigo=:regra_codigo', ['memorial_id' => $memorialId, 'regra_codigo' => $ruleCode]);
    }

    public function link(int $memorialId, int $itemId, string $ruleCode, string $origin, float $quantity, string $unit, string $formula): void
    {
        $this->execute('INSERT INTO obra_memorial_vinculos (memorial_id,item_id,regra_codigo,origem,quantidade_calculada,unidade,formula,created_at,updated_at) VALUES (:memorial_id,:item_id,:regra_codigo,:origem,:quantidade,:unidade,:formula,NOW(),NOW()) ON DUPLICATE KEY UPDATE item_id=VALUES(item_id),origem=VALUES(origem),quantidade_calculada=VALUES(quantidade_calculada),unidade=VALUES(unidade),formula=VALUES(formula),updated_at=NOW()', ['memorial_id'=>$memorialId,'item_id'=>$itemId,'regra_codigo'=>$ruleCode,'origem'=>$origin,'quantidade'=>$quantity,'unidade'=>$unit,'formula'=>$formula]);
    }

    public function exclusions(int $memorialId): array
    {
        return array_column($this->fetchAll('SELECT regra_codigo FROM obra_memorial_exclusoes WHERE memorial_id=:id', ['id'=>$memorialId]), 'regra_codigo');
    }

    public function suppressRule(int $memorialId, string $ruleCode): void
    {
        $this->execute('INSERT IGNORE INTO obra_memorial_exclusoes (memorial_id,regra_codigo,created_at) VALUES (:id,:code,NOW())', ['id'=>$memorialId,'code'=>$ruleCode]);
    }

    public function detachItem(int $itemId): void
    {
        $this->execute('DELETE FROM obra_memorial_vinculos WHERE item_id=:item_id', ['item_id'=>$itemId]);
    }

    public function markGenerated(int $memorialId): void
    {
        $this->execute('UPDATE obra_memoriais SET generated_at=NOW(),updated_at=NOW() WHERE id=:id', ['id'=>$memorialId]);
    }

    public function compositionAnalysis(int $obraId): array
    {
        return $this->fetchAll('SELECT analysis.*, item.nome item_nome FROM obra_item_composicao_analises analysis INNER JOIN obra_itens item ON item.id=analysis.item_id WHERE item.obra_id=:obra_id ORDER BY analysis.status,item.nome', ['obra_id'=>$obraId]);
    }

    public function saveCompositionAnalysis(int $itemId, string $status, int $confidence, string $reason): void
    {
        $this->execute('INSERT INTO obra_item_composicao_analises (item_id,status,confianca,justificativa,analyzed_at) VALUES (:item_id,:status,:confianca,:justificativa,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),confianca=VALUES(confianca),justificativa=VALUES(justificativa),analyzed_at=NOW()', ['item_id'=>$itemId,'status'=>$status,'confianca'=>max(0,min(100,$confidence)),'justificativa'=>mb_substr($reason,0,500)]);
    }
}
