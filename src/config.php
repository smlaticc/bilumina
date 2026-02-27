<?php
declare(strict_types=1);

return [
    'api' => [
        'baseUrl' => 'https://egi.bilumina.com/mw/api/v1',
        'key'     => 'bf84d5ef-7fe2-4609-8b75-49279dd3271e',
        'timeout_seconds' => 8,
    ],
    'cache' => [
        'dir' => __DIR__ . '/../storage/cache',
        'ttl_seconds' => 600, 
    ],
];