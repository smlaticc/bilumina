<?php
declare(strict_types=1);

final class Cache
{
    public function __construct(
        private string $dir,
        private int $ttlSeconds
    ) {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    private function path(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return rtrim($this->dir, '/\\') . DIRECTORY_SEPARATOR . $safe . '.json';
    }

    public function get(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path)) return null;

        $raw = @file_get_contents($path);
        if ($raw === false) return null;

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['_meta'], $decoded['data'])) return null;

        $savedAt = (int)($decoded['_meta']['savedAt'] ?? 0);
        if ($savedAt <= 0) return null;
        if (time() - $savedAt > $this->ttlSeconds) {
            return null;
        }

        return is_array($decoded['data']) ? $decoded['data'] : null;
    }

    public function getStale(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path)) return null;

        $raw = @file_get_contents($path);
        if ($raw === false) return null;

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['data'])) return null;

        return is_array($decoded['data']) ? $decoded['data'] : null;
    }

    public function set(string $key, array $data): void
    {
        $path = $this->path($key);
        $payload = [
            '_meta' => ['savedAt' => time()],
            'data' => $data,
        ];
        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}