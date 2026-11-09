<?php

declare(strict_types=1);

namespace App\Performance\Security;

/**
 * Caller-constructed laboratory snapshot. An object of this type is NOT a
 * verifiable Portal DB record or authenticated principal. Do not wire this
 * class to an operational authorization adapter.
 */
final readonly class SyntheticCurrentAuthority
{
    public function __construct(
        public string $organizationRef,
        public string $tenantRef,
        public string $serviceRef,
        public string $resourceRef,
        public string $actorRef,
        public string $sessionRef,
        public string $permission,
        public bool $sessionActive,
        public bool $mfaVerified,
        public bool $membershipActive,
        public bool $serviceActive,
        public int $membershipRevision,
        public int $serviceRevision,
        public int $sessionRevision,
        public int $recoveryEpoch,
        public bool $sourceAvailable,
        public int $observedAt,
    ){
    }

    public static function forSyntheticLab(int $now): self
    {
        return new self(
            organizationRef:'org-demo-a',
            tenantRef:'perf-demo-a',
            serviceRef:'svc-demo-a',
            resourceRef:'demo-cam-a1',
            actorRef:'actor-demo-a',
            sessionRef:'session-demo-a',
            permission:'campaigns:read',
            sessionActive:true,
            mfaVerified:true,
            membershipActive:true,
            serviceActive:true,
            membershipRevision:2,
            serviceRevision:3,
            sessionRevision:4,
            recoveryEpoch:5,
            sourceAvailable:true,
            observedAt:$now,
        );
    }
}
