<?php

declare(strict_types=1);

/**
 * FAST-PERF: NO public filesystem disk, symlinks, or local temporary URLs.
 * Laravel 13 normally enables GET/PUT /storage/{path} when "serve" is true.
 * Keep storage inaccessible over HTTP until a separate audited gate is met.
 */
return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],
    ],
    'links' => [],
];
