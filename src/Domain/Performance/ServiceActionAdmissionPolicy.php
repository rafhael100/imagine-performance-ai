<?php

declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * F1-18: a pure, typed admission contract for future service mutations.
 *
 * CRITICAL: these snapshots are assertions supplied by a caller, NOT
 * authenticated identity / verified remote evidence. The operational gate
 * is deliberately CLOSED and cannot be enabled through a Laravel provider.
 * Laboratory ALLOW only tests domain semantics, not production security.
 */
final readonly class ServiceActionRequest
{
    public function __construct(
        public string $organizationRef,
        public string $tenantRef,
        public string $serviceRef,
        public string $actorRef,
        public string $resourceRef,
        public string $operation,
        public string $manifestVersion,
    ) {
    }
}

final readonly class ServiceAdmissionEvidence
{
    /** @param list<string> $actorScopes */
    public function __construct(
        public string $organizationRef,
        public string $tenantRef,
        public string $serviceRef,
        public string $resourceTenantRef,
        public string $resourceServiceRef,
        public string $actorRef,
        public string $actorTenantRef,
        public string $actorStatus,
        public array $actorScopes,
        public string $sourceState,
        public string $localState,
        public string $contractState,
        public int $sourceRevision,
        public int $localRevision,
        public int $sourceObservedAt,
        public int $localObservedAt,
        public int $externalRecoveryEpoch,
        public int $localRecoveryEpoch,
        public string $recoveryState,
        public bool $sourceAvailable,
        public bool $localAvailable,
        public bool $externalFenceAvailable,
        public bool $workloadAttestedInLab,
    ) {
    }
}

final readonly class ServiceActionApproval
{
    public function __construct(
        public string $approvalId,
        public string $proposerActorRef,
        public string $approverActorRef,
        public string $approverStatus,
        public string $organizationRef,
        public string $tenantRef,
        public string $serviceRef,
        public string $resourceRef,
        public string $operation,
        public string $manifestVersion,
        public int $approvedRevision,
        public int $approvedAt,
        public string $state,
    ) {
    }
}

final readonly class ServiceActionDecision
{
    private function __construct(
        public bool $allowed,
        public string $code,
    ) {
    }

    public static function deny(string $code): self
    {
        return new self(false, $code);
    }

    /** An ALLOW value is strictly a domain-level *laboratory* result. */
    public static function syntheticAllow(): self
    {
        return new self(true, 'admitted_synthetic_only');
    }
}

final class ServiceActionAdmissionPolicy
{
    private const MAX_FRESHNESS_SECONDS = 30;
    private const MAX_FUTURE_SKEW_SECONDS = 3;

    /** @var array<string, array{scope:string,secondApprover:bool}> */
    private const OPERATIONS = [
        'campaign.create' => ['scope' => 'campaigns:write', 'secondApprover' => true],
        'campaign.pause' => ['scope' => 'campaigns:write', 'secondApprover' => false],
        'campaign.replace' => ['scope' => 'campaigns:write', 'secondApprover' => true],
        'campaign.release' => ['scope' => 'campaigns:write', 'secondApprover' => true],
        'approval.decide' => ['scope' => 'proposals:approve', 'secondApprover' => true],
    ];

    /**
     * This constructor is intentionally CLOSED for any operational use.
     * There is NO method to register a production ALLOW policy.
     */
    public function __construct(private readonly bool $syntheticTestMode = false)
    {
    }

    /**
     * Simulation-only factory; not an authentication adapter.
     * The PHP CLI and explicit test flag are both required.
     */
    public static function forSyntheticLabOnly(): self
    {
        return new self(
            PHP_SAPI === 'cli'
            && getenv('PERF_F18_ADMISSION_LAB') === '1'
            && getenv('APP_ENV') === 'testing'
        );
    }

    public function decide(
        ServiceActionRequest $request,
        ?ServiceAdmissionEvidence $evidence,
        ?ServiceActionApproval $approval,
        int $nowEpoch,
    ): ServiceActionDecision {
        if (!$this->syntheticTestMode || PHP_SAPI !== 'cli'
            || getenv('PERF_F18_ADMISSION_LAB') !== '1'
            || getenv('APP_ENV') !== 'testing') {
            return ServiceActionDecision::deny('deployment_gate_closed');
        }
        if ($nowEpoch <= 0) {
            return ServiceActionDecision::deny('invalid_clock');
        }
        $required = self::OPERATIONS[$request->operation] ?? null;
        if ($required === null || !self::validIdentifier($request->resourceRef)
            || !self::validIdentifier($request->actorRef)
            || !self::validIdentifier($request->tenantRef)
            || !self::validIdentifier($request->serviceRef)
            || !self::validIdentifier($request->organizationRef)
            || !self::validIdentifier($request->manifestVersion)) {
            return ServiceActionDecision::deny('unknown_or_invalid_action');
        }
        if ($evidence === null) {
            return ServiceActionDecision::deny('missing_evidence');
        }
        if (!$evidence->sourceAvailable
            || !$evidence->localAvailable
            || !$evidence->externalFenceAvailable
            || !$evidence->workloadAttestedInLab) {
            return ServiceActionDecision::deny('identity_or_authority_unavailable');
        }
        if ($request->organizationRef !== $evidence->organizationRef
            || $request->tenantRef !== $evidence->tenantRef
            || $request->serviceRef !== $evidence->serviceRef
            || $request->tenantRef !== $evidence->resourceTenantRef
            || $request->serviceRef !== $evidence->resourceServiceRef
            || $request->actorRef !== $evidence->actorRef
            || $request->tenantRef !== $evidence->actorTenantRef) {
            return ServiceActionDecision::deny('tenant_or_actor_binding_denied');
        }
        if ($evidence->actorStatus !== 'active_mock'
            || !in_array($required['scope'], $evidence->actorScopes, true)) {
            return ServiceActionDecision::deny('actor_scope_denied');
        }
        if ($evidence->sourceRevision < 1 || $evidence->localRevision < 1
            || $evidence->sourceRevision !== $evidence->localRevision) {
            return ServiceActionDecision::deny('service_revision_mismatch');
        }
        if ($evidence->externalRecoveryEpoch < 1
            || $evidence->localRecoveryEpoch < 1
            || $evidence->externalRecoveryEpoch !== $evidence->localRecoveryEpoch
            || $evidence->recoveryState !== 'ready_mock') {
            return ServiceActionDecision::deny('recovery_quarantine');
        }
        if (!self::fresh($evidence->sourceObservedAt, $nowEpoch)
            || !self::fresh($evidence->localObservedAt, $nowEpoch)) {
            return ServiceActionDecision::deny('stale_or_future_evidence');
        }
        if ($evidence->sourceState !== 'active_mock'
            || $evidence->localState !== 'active_mock'
            || $evidence->contractState !== 'eligible_mock') {
            return ServiceActionDecision::deny('service_not_eligible');
        }
        if (!$required['secondApprover']) {
            return ServiceActionDecision::syntheticAllow();
        }
        if ($approval === null || $approval->state !== 'approved_mock'
            || !self::validIdentifier($approval->approvalId)
            || $approval->approverStatus !== 'active_mock'
            || $approval->approverActorRef === $approval->proposerActorRef
            || $approval->proposerActorRef !== $request->actorRef
            || !self::validIdentifier($approval->approverActorRef)
            || $approval->organizationRef !== $request->organizationRef
            || $approval->tenantRef !== $request->tenantRef
            || $approval->serviceRef !== $request->serviceRef
            || $approval->resourceRef !== $request->resourceRef
            || $approval->operation !== $request->operation
            || $approval->manifestVersion !== $request->manifestVersion
            || $approval->approvedRevision !== $evidence->sourceRevision
            || !self::fresh($approval->approvedAt, $nowEpoch)) {
            return ServiceActionDecision::deny('independent_approval_required');
        }
        return ServiceActionDecision::syntheticAllow();
    }

    private static function fresh(int $observedAt, int $nowEpoch): bool
    {
        return $observedAt > 0
            && $observedAt >= $nowEpoch - self::MAX_FRESHNESS_SECONDS
            && $observedAt <= $nowEpoch + self::MAX_FUTURE_SKEW_SECONDS;
    }

    private static function validIdentifier(string $id): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,95}$/D', $id);
    }
}
