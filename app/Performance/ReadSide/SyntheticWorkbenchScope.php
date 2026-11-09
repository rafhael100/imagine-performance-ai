<?php

declare(strict_types=1);

namespace App\Performance\ReadSide;

final readonly class SyntheticWorkbenchScope
{
    private function __construct(
        public string $tenantId,
        public string $tenantRef,
        public string $organizationRef,
        public string $serviceRef,
    ) {
    }

    public static function forSyntheticLabOnly(): ?self
    {
        if (PHP_SAPI!=='cli'
            || getenv('APP_ENV')!=='testing'
            || getenv('RUN_PERF_F22_PG_LAB')!=='1'
            || getenv('PERF_F28_APP_READ_LAB')!=='1') {
            return null;
        }
        return new self(
            '11111111-1111-4111-8111-111111111111',
            'perf-demo-a',
            'org-demo-a',
            'svc-demo-a',
        );
    }
}
