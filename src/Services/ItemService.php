<?php
declare(strict_types=1);

final class ItemService
{
    public function __construct(private ApiClient $api) {}

    public function load(): array
    {
        $raw = $this->api->getJson('/items/get', 'api_items');

        if (!isset($raw['success']) || $raw['success'] !== true) {
            throw new RuntimeException("Items API napačen odgovor");
        }

        $cdn = $raw['cdnUrl'] ?? [];
        $cdnGrid = is_array($cdn) ? (string)($cdn['grid'] ?? '') : '';

        $root = $raw['rootGroup'] ?? null;
        if (!is_array($root)) {
            throw new RuntimeException("Items API manjka 'rootGroup'");
        }

        $groups = $root['groups'] ?? null;
        if (!is_array($groups)) {
            throw new RuntimeException("Items API rootGroup nima 'groups'");
        }

        $out = [];

        foreach ($groups as $g) {
            if (!is_array($g)) continue;

            $fallbackGroupId = (int)($g['id'] ?? 0);
            $fallbackGroupName = (string)($g['nameSmall'] ?? '');

            $itemsDict = $g['items'] ?? null;
            if (!is_array($itemsDict)) continue; 

            foreach ($itemsDict as $key => $it) {
                if (!is_array($it)) continue;

                $id = (int)($it['id'] ?? 0);
                if ($id <= 0) continue;

                $gallery = $it['gallery'] ?? [];
                $imageRel = null;
                if (is_array($gallery) && isset($gallery[0]['imageUrl'])) {
                    $imageRel = (string)$gallery[0]['imageUrl']; 
                }
                $imageAbs = null;
                if ($imageRel) {
                    if (str_starts_with($imageRel, 'http://') || str_starts_with($imageRel, 'https://')) {
                        $imageAbs = $imageRel;
                    } elseif ($cdnGrid !== '') {
                        $imageAbs = rtrim($cdnGrid, '/') . $imageRel;
                    } else {
                        $imageAbs = $imageRel;
                    }
                }

                $price = isset($it['price']) ? (float)$it['price'] : null;
                $priceOld = isset($it['priceOld']) ? (float)$it['priceOld'] : null;
                if ($priceOld !== null && $priceOld <= 0) $priceOld = null;

                $out[] = [
                    'id' => $id,
                    'sku' => (string)($it['sku'] ?? ''),
                    'groupId' => (int)($it['groupId'] ?? $fallbackGroupId),
                    'groupName' => (string)($it['groupName'] ?? $fallbackGroupName),
                    'name' => (string)($it['name'] ?? ''),
                    'description' => (string)($it['description'] ?? ''),
                    'brand' => (string)($it['brand'] ?? ''),
                    'color' => (string)($it['color'] ?? ''),
                    'price' => $price,
                    'priceOld' => $priceOld,
                    'discountPercent' => isset($it['discountPercent']) ? (int)$it['discountPercent'] : null,
                    'stock' => isset($it['stock']) ? (int)$it['stock'] : null,
                    'sort'  => isset($it['sort']) ? (int)$it['sort'] : 0,
                    'imageUrl' => $imageAbs,
                ];
            }
        }
        usort($out, function ($a, $b) {
            $sa = (int)($a['sort'] ?? 0);
            $sb = (int)($b['sort'] ?? 0);
            if ($sa !== $sb) return $sa <=> $sb;
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return $out;
    }

    public function findById(array $items, int $id): ?array
    {
        foreach ($items as $it) {
            if ((int)$it['id'] === $id) return $it;
        }
        return null;
    }

    public function filterByPrice(array $items, ?float $min, ?float $max): array
    {
        return array_values(array_filter($items, function ($it) use ($min, $max) {
            $p = $it['price'] ?? null;
            if ($p === null) return false;
            if ($min !== null && $p < $min) return false;
            if ($max !== null && $p > $max) return false;
            return true;
        }));
    }

    public function inGroups(array $items, array $groupIds): array
    {
        $set = array_flip(array_map('intval', $groupIds));
        return array_values(array_filter($items, function ($it) use ($set) {
            $gid = (int)($it['groupId'] ?? 0);
            return $gid > 0 && isset($set[$gid]);
        }));
    }
}