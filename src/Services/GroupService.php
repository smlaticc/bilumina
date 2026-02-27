<?php
declare(strict_types=1);

final class GroupService
{
    private array $byId = [];
    private array $tree = [];
    public function __construct(private ApiClient $api) {}

    public function load(): void
    {
        $raw = $this->api->getJson('/groups/get', 'api_groups');

        if (!isset($raw['success']) || $raw['success'] !== true) {
            throw new RuntimeException("Groups API napačen odgovor");
        }

        if (!isset($raw['groups']) || !is_array($raw['groups'])) {
            throw new RuntimeException("Groups API manjka groups field");
        }
        $refs = [];
        foreach ($raw['groups'] as $key => $g) {
            if (!is_array($g)) continue;

            $id = (int)($g['id'] ?? 0);
            if ($id <= 0) continue;

            $refs[$id] = [
                'id' => $id,
                'parentId' => (int)($g['parentId'] ?? 0), 
                'code' => (string)($g['code'] ?? ''),
                'name' => (string)($g['name'] ?? ''),
                'sort' => (int)($g['sort'] ?? 0),
                'children' => [],
            ];
        }
        foreach ($refs as $id => &$node) {
            $pid = (int)$node['parentId'];
            if ($pid !== 0 && isset($refs[$pid])) {
                $refs[$pid]['children'][] = &$node;
            }
        }
        unset($node);
        $tree = [];
        foreach ($refs as $id => $node) {
            $pid = (int)$node['parentId'];
            if ($pid === 0 || !isset($refs[$pid])) {
                $tree[] = $node;
            }
        }
        $sortFn = function (&$nodes) use (&$sortFn) {
            usort($nodes, function ($a, $b) {
                $sa = (int)$a['sort'];
                $sb = (int)$b['sort'];
                if ($sa !== $sb) return $sa <=> $sb;
                return strcmp((string)$a['name'], (string)$b['name']);
            });

            foreach ($nodes as &$n) {
                if (!empty($n['children'])) $sortFn($n['children']);
            }
        };
        $sortFn($tree);

        $this->byId = $refs;
        $this->tree = $tree;
    }

    public function getTree(): array
    {
        return $this->tree;
    }

    public function exists(int $groupId): bool
    {
        return isset($this->byId[$groupId]);
    }

    public function get(int $groupId): ?array
    {
        return $this->byId[$groupId] ?? null;
    }

    public function getDescendantIds(int $groupId): array
    {
        if (!isset($this->byId[$groupId])) return [];

        $ids = [];
        $stack = [$this->byId[$groupId]];

        while ($stack) {
            $n = array_pop($stack);
            $ids[] = (int)$n['id'];

            foreach (($n['children'] ?? []) as $child) {
                $stack[] = $child;
            }
        }

        return array_values(array_unique($ids));
    }

    public function getBreadcrumb(int $groupId): array
    {
        $crumbs = [];
        $curr = $this->byId[$groupId] ?? null;

        while ($curr) {
            $crumbs[] = $curr;
            $pid = (int)$curr['parentId'];
            $curr = ($pid !== 0 && isset($this->byId[$pid])) ? $this->byId[$pid] : null;
        }

        return array_reverse($crumbs);
    }
}