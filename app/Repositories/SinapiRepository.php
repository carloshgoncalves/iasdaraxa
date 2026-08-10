<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class SinapiRepository extends BaseRepository
{
    private const TYPES = ['Material', 'Servico', 'Equipamento'];

    public function search(string $type, string $query, int $page = 1, int $perPage = 50): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'has_more' => false];
        }

        $query = trim($query);
        $page = max(1, $page);
        $perPage = max(10, min($perPage, 100));
        $offset = ($page - 1) * $perPage;
        $params = ['tipo' => $type];
        $where = '';
        if ($query !== '') {
            $where = ' AND (codigo LIKE :codigo OR descricao LIKE :descricao)';
            $params['codigo'] = $query . '%';
            $params['descricao'] = '%' . $query . '%';
        }

        $total = (int) ($this->fetch(
            "SELECT COUNT(*) total
             FROM sinapi_catalogo
             WHERE tipo = :tipo AND ativo = 1{$where}",
            $params
        )['total'] ?? 0);
        $items = $this->fetchAll(
            "SELECT id, tipo, codigo, descricao, unidade, preco_mg, competencia_preco, grupo, competencia, uf, regime
             FROM sinapi_catalogo
             WHERE tipo = :tipo AND ativo = 1{$where}
             ORDER BY CASE WHEN codigo = " . ($query !== '' ? ':codigo_exato' : "''") . " THEN 0 ELSE 1 END, descricao
             LIMIT {$perPage} OFFSET {$offset}",
            $query !== '' ? $params + ['codigo_exato' => $query] : $params
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'has_more' => $offset + count($items) < $total,
        ];
    }

    public function findForType(int $id, string $type): ?array
    {
        if ($id <= 0 || !in_array($type, self::TYPES, true)) {
            return null;
        }

        return $this->fetch(
            'SELECT * FROM sinapi_catalogo WHERE id = :id AND tipo = :tipo AND ativo = 1 LIMIT 1',
            ['id' => $id, 'tipo' => $type]
        );
    }

    public function findReference(int $id, string $type): ?array
    {
        if ($id <= 0 || !in_array($type, self::TYPES, true)) {
            return null;
        }
        return $this->fetch('SELECT * FROM sinapi_catalogo WHERE id = :id AND tipo = :tipo LIMIT 1', ['id' => $id, 'tipo' => $type]);
    }

    public function bestMatch(string $type, string $query): ?array
    {
        if (!in_array($type, self::TYPES, true) || trim($query) === '') return null;
        $words = array_values(array_filter(preg_split('/\s+/', trim($query)) ?: [], static fn (string $word): bool => mb_strlen($word) >= 4));
        if ($words === []) return null;
        $words = array_slice(array_values(array_unique($words)), 0, 8);
        for ($required = min(3, count($words)); $required >= 1; $required--) {
            $conditions = [];
            $params = ['tipo' => $type];
            foreach (array_slice($words, 0, $required) as $index => $word) {
                $conditions[] = "descricao LIKE :word{$index}";
                $params["word{$index}"] = '%' . $word . '%';
            }
            $score = [];
            foreach ($words as $index => $word) {
                $score[] = "(descricao LIKE :score{$index})";
                $params["score{$index}"] = '%' . $word . '%';
            }
            $match = $this->fetch('SELECT * FROM sinapi_catalogo WHERE ativo=1 AND tipo=:tipo AND ' . implode(' AND ', $conditions) . ' ORDER BY (' . implode('+', $score) . ') DESC, (preco_mg IS NULL), CHAR_LENGTH(descricao), codigo LIMIT 1', $params);
            if ($match) return $match;
        }
        return null;
    }

    public function referenceSummary(): ?array
    {
        return $this->fetch(
            "SELECT competencia, MAX(competencia_preco) competencia_preco, uf, regime, fonte_url, COUNT(*) total
             FROM sinapi_catalogo
             WHERE ativo = 1
             GROUP BY competencia, uf, regime, fonte_url
             ORDER BY competencia DESC
             LIMIT 1"
        );
    }

    public function importCsv(string $path, string $competence, string $sourceUrl): array
    {
        $streamPath = str_ends_with(strtolower($path), '.gz') ? 'compress.zlib://' . $path : $path;
        $handle = fopen($streamPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Nao foi possivel abrir o CSV do SINAPI.');
        }

        $header = fgetcsv($handle);
        $expected = ['tipo', 'codigo', 'descricao', 'unidade', 'preco_mg', 'grupo', 'origem'];
        if ($header !== $expected) {
            fclose($handle);
            throw new \RuntimeException('Cabecalho do CSV do SINAPI invalido.');
        }

        $pdo = $this->db();
        $statement = $pdo->prepare(
            "INSERT INTO sinapi_catalogo
                (tipo, codigo, descricao, unidade, preco_mg, competencia_preco, grupo, origem_preco, competencia, uf, regime, fonte_url, ativo, created_at, updated_at)
             VALUES
                (:tipo, :codigo, :descricao, :unidade, :preco_mg, :competencia_preco, :grupo, :origem_preco, :competencia, 'MG', 'Sem desoneracao', :fonte_url, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                descricao = VALUES(descricao), unidade = VALUES(unidade), preco_mg = VALUES(preco_mg), competencia_preco = VALUES(competencia_preco),
                grupo = VALUES(grupo), origem_preco = VALUES(origem_preco), fonte_url = VALUES(fonte_url),
                ativo = 1, updated_at = NOW()"
        );

        $counts = ['Material' => 0, 'Servico' => 0, 'Equipamento' => 0];
        $pdo->beginTransaction();
        try {
            $priceRows = $this->fetchAll(
                'SELECT tipo, codigo, preco_mg, COALESCE(competencia_preco, competencia) competencia_preco, origem_preco
                 FROM sinapi_catalogo
                 WHERE ativo = 1 AND preco_mg IS NOT NULL'
            );
            $prices = [];
            foreach ($priceRows as $priceRow) {
                $prices[$priceRow['tipo'] . '|' . $priceRow['codigo']] = $priceRow;
            }
            $pdo->exec('UPDATE sinapi_catalogo SET ativo = 0 WHERE ativo = 1');

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($expected) || !isset($counts[$row[0]])) {
                    continue;
                }
                $price = trim($row[4]) === '' ? null : $row[4];
                $priceCompetence = $price !== null ? $competence : null;
                $priceOrigin = trim($row[6]) === '' ? null : trim($row[6]);
                if ($priceOrigin === 'Composicao SINAPI') {
                    $priceOrigin = 'SINAPI';
                } elseif ($priceOrigin !== null) {
                    $priceOrigin = mb_substr($priceOrigin, 0, 10);
                }
                $previousPrice = $prices[$row[0] . '|' . trim($row[1])] ?? null;
                if ($price === null && $previousPrice !== null) {
                    $price = $previousPrice['preco_mg'];
                    $priceCompetence = $previousPrice['competencia_preco'];
                    $priceOrigin = $previousPrice['origem_preco'];
                }
                $statement->execute([
                    'tipo' => $row[0],
                    'codigo' => trim($row[1]),
                    'descricao' => trim($row[2]),
                    'unidade' => trim($row[3]),
                    'preco_mg' => $price,
                    'competencia_preco' => $priceCompetence,
                    'grupo' => trim($row[5]) === '' ? null : trim($row[5]),
                    'origem_preco' => $priceOrigin,
                    'competencia' => $competence,
                    'fonte_url' => $sourceUrl,
                ]);
                $counts[$row[0]]++;
            }
            if (array_sum($counts) < 10000 || $counts['Material'] < 1000 || $counts['Servico'] < 5000 || $counts['Equipamento'] < 100) {
                throw new \RuntimeException('O catalogo extraido esta incompleto; a versao atual foi mantida.');
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        } finally {
            fclose($handle);
        }

        return $counts;
    }
}
