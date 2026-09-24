<?php

declare(strict_types=1);

return [
    'tenant_pubkeys' => [
        'YOUR_NPUB_OR_HEX_PUBKEY_HERE',
    ],

    'host' => '127.0.0.1',
    'port' => 8081,
    'base_url' => 'https://blossom.example.com',

    'storage_path' => dirname(__DIR__).'/data/blobs',
    'database_path' => dirname(__DIR__).'/data/hubstr-blossom.sqlite',
    'max_upload_bytes' => 104857600,
    'allowed_types' => ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'video/mp4', 'audio/mpeg'],

    'allow_private_mirror_hosts' => false,

    'trusted_proxies' => ['127.0.0.1'],

    'worker_pool_limit' => 0,

    'max_image_pixels' => 50000000,

    'log_level' => 'info',
];
