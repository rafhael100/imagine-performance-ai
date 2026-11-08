<?php

declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/** Pure domain fixtures for F1-02-A. Never treat these as authenticated claims. */
final readonly class PerformanceTenant
{
    public function __construct(public string $id, public string $status)
    {
        self::required($id);
    }

    public static function required(string $value): void
    {
        if ($value === '' || trim($value) !== $value) {
            throw new InvalidArgumentException('A nonempty opaque identifier is required.');
        }
    }
}

final readonly class OperationalMembership
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $role,
        public string $status,
        public array $scopes,
    ) {
        PerformanceTenant::required($tenantId);
        PerformanceTenant::required($actorId);
        PerformanceTenant::required($role);
    }
}

final readonly class ServiceEligibilitySnapshot
{
    public function __construct(
        public string $tenantId,
        public string $serviceId,
        public string $state,
        public string $source,
    ) {
        PerformanceTenant::required($tenantId);
        PerformanceTenant::required($serviceId);
    }
}

/**
 * Fake test-only grant: deliberately not a token, user session, or S2S assertion.
 * NEVER populate from incoming HTTP, queue, or client-provided data.
 */
final readonly class SyntheticAuthorizationContext
{
    /** @param list<string> $scopes */
    private function __construct(
        public string $tenantId,
        public string $actorId,
        public string $serviceId,
        public array $scopes,
        public int $expiresAtEpoch,
        public string $source,
    ) {}

    /** @param list<string> $scopes */
    public static function forTest(
        string $tenantId,
        string $actorId,
        string $serviceId,
        array $scopes,
        int $expiresAtEpoch,
    ): self {
        PerformanceTenant::required($tenantId);
        PerformanceTenant::required($actorId);
        PerformanceTenant::required($serviceId);
        return new self($tenantId, $actorId, $serviceId, $scopes, $expiresAtEpoch, 'synthetic_test_only');
    }
}

final readonly class OperationalResource
{
    public function __construct(
        public string $tenantId,
        public string $serviceId,
        public string $resourceId,
    ) {
        PerformanceTenant::required($tenantId);
        PerformanceTenant::required($serviceId);
        PerformanceTenant::required($resourceId);
    }
}

/** Proposal metadata only: no mutation and no external write action. */
final readonly class SyntheticApprovalProposal
{
    public function __construct(
        public OperationalResource $resource,
        public string $proposerActorId,
        public string $manifestVersion,
    ) {
        PerformanceTenant::required($proposerActorId);
        PerformanceTenant::required($manifestVersion);
    }
}
