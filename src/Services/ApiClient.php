<?php
declare(strict_types=1);

final class ApiClient
{
    public function __construct(
        private string $baseUrl,
        private string $key,
        private int $timeoutSeconds,
        private Cache $cache
    ) {}

    /**
     * @throws RuntimeException
     */
    public function getJson(string $endpoint, string $cacheKey): array
    {
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) return $cached;

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $url .= (str_contains($url, '?') ? '&' : '?') . 'key=' . urlencode($this->key);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false || $code < 200 || $code >= 300) {
            throw new RuntimeException("API napaka ($code): " . ($err ?: 'Request failed'));
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $stale = $this->cache->getStale($cacheKey);
            if ($stale !== null) return $stale;

            throw new RuntimeException("Napačen JSON odgovor.");
        }

        $this->cache->set($cacheKey, $decoded);
        return $decoded;
    }
}