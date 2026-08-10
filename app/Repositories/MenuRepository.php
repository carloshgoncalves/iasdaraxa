<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class MenuRepository extends BaseRepository
{
    private ?array $items = null;

    public function activeForUser(int $userId): array
    {
        $items = array_values(array_filter($this->items(), static fn (array $item): bool => $item['status'] === 'Ativo'));
        $allowedIds = $this->allowedMenuIds($userId);

        if ($allowedIds === ['*']) {
            return $this->sort($items);
        }

        return $this->sort(array_values(array_filter($items, static fn (array $item): bool => in_array((int) $item['id'], $allowedIds, true))));
    }

    public function activeTreeForUser(int $userId): array
    {
        return $this->tree($this->activeForUser($userId));
    }

    public function tree(array $items): array
    {
        $byParent = [];
        foreach ($items as $item) {
            $parent = $item['id_menu_pai'] === null ? 0 : (int) $item['id_menu_pai'];
            $byParent[$parent][] = $item;
        }

        $build = function (int $parentId) use (&$build, &$byParent): array {
            $children = $byParent[$parentId] ?? [];
            $children = $this->sort($children);

            return array_map(function (array $child) use ($build): array {
                $child['children'] = $build((int) $child['id']);
                return $child;
            }, $children);
        };

        return $build(0);
    }

    public function items(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $fileItems = $this->itemsFromMenuBin();
        if ($fileItems !== null) {
            $this->syncMenuRows($fileItems);
            return $this->items = $fileItems;
        }

        return $this->items = $this->fetchAll(
            'SELECT id, id_menu_pai, nome, descricao, rota, icone, ordem, status, tipo, nivel, chave_permissao
             FROM menus
             ORDER BY ordem, id'
        );
    }

    private function itemsFromMenuBin(): ?array
    {
        $path = APP_BASE_PATH . '/menu.bin';
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Nao foi possivel ler menu.bin.');
        }

        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (($document['schema'] ?? null) !== 'gm-menu/v1' || !is_array($document['items'] ?? null)) {
            throw new \RuntimeException('menu.bin incompativel com gm-menu/v1.');
        }

        $expected = (string) ($document['checksum'] ?? '');
        $actual = 'sha256:' . hash('sha256', json_encode(
            $document['items'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));

        if ($expected === '' || !hash_equals($expected, $actual)) {
            throw new \RuntimeException('Checksum do menu.bin nao confere. Publique novamente pelo appgm.');
        }

        return array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'id_menu_pai' => $item['id_menu_pai'] === null ? null : (int) $item['id_menu_pai'],
            'nome' => (string) $item['nome'],
            'descricao' => (string) ($item['descricao'] ?? ''),
            'rota' => (string) ($item['rota'] ?? ''),
            'icone' => (string) ($item['icone'] ?? 'circle'),
            'ordem' => (int) ($item['ordem'] ?? 0),
            'status' => (string) ($item['status'] ?? 'Inativo'),
            'tipo' => (string) ($item['tipo'] ?? 'Separador'),
            'nivel' => (string) ($item['nivel'] ?? ''),
            'chave_permissao' => (string) ($item['chave_permissao'] ?? ''),
        ], $document['items']);
    }

    private function syncMenuRows(array $items): void
    {
        $sql = "
            INSERT INTO menus (id, id_menu_pai, nome, descricao, rota, icone, ordem, status, tipo, nivel, chave_permissao)
            VALUES (:id, :id_menu_pai, :nome, :descricao, :rota, :icone, :ordem, :status, :tipo, :nivel, :chave_permissao)
            ON DUPLICATE KEY UPDATE
                id_menu_pai = VALUES(id_menu_pai),
                nome = VALUES(nome),
                descricao = VALUES(descricao),
                rota = VALUES(rota),
                icone = VALUES(icone),
                ordem = VALUES(ordem),
                status = VALUES(status),
                tipo = VALUES(tipo),
                nivel = VALUES(nivel),
                chave_permissao = VALUES(chave_permissao)
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($this->sortByDepth($items) as $item) {
            $stmt->execute($item);
        }
    }

    private function allowedMenuIds(int $userId): array
    {
        $isAdmin = $this->fetch(
            "SELECT 1
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.slug = 'admin' AND r.status = 'Ativo'
             LIMIT 1",
            ['user_id' => $userId]
        );

        if ($isAdmin) {
            return ['*'];
        }

        $rows = $this->fetchAll(
            "SELECT DISTINCT a.menu_id
             FROM access_rules a
             INNER JOIN user_roles ur ON ur.role_id = a.role_id
             WHERE ur.user_id = :user_id
               AND a.type = 'visualizar'
               AND a.status = 'Ativo'",
            ['user_id' => $userId]
        );

        return array_map('intval', array_column($rows, 'menu_id'));
    }

    private function sort(array $items): array
    {
        usort($items, static fn (array $a, array $b): int => ((int) $a['ordem'] <=> (int) $b['ordem']) ?: ((int) $a['id'] <=> (int) $b['id']));
        return $items;
    }

    private function sortByDepth(array $items): array
    {
        usort($items, function (array $a, array $b) use ($items): int {
            return $this->depth($a, $items) <=> $this->depth($b, $items);
        });

        return $items;
    }

    private function depth(array $item, array $items): int
    {
        $map = [];
        foreach ($items as $candidate) {
            $map[(int) $candidate['id']] = $candidate;
        }

        $depth = 1;
        $parent = $item['id_menu_pai'];
        $visited = [];
        while ($parent !== null && isset($map[(int) $parent]) && !isset($visited[(int) $parent])) {
            $visited[(int) $parent] = true;
            $depth++;
            $parent = $map[(int) $parent]['id_menu_pai'];
        }

        return $depth;
    }
}
