<?php

declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;

/**
 * Test-only Portal envelope. Never construct this from untrusted HTTP/queue input.
 * It does NOT attest payment, contracts or workload identity.
 */
final readonly class SyntheticPortalEvent
{
    public function __construct(
        public string $schemaVersion,
        public string $producer,
        public string $eventType,
        public string $eventId,
        public string $correlationId,
        public string $portalOrganizationId,
        public string $performanceTenantId,
        public string $serviceId,
        public int $revision,
        public string $eligibility,
    ) {
        foreach ([$eventId, $correlationId, $portalOrganizationId, $performanceTenantId, $serviceId] as $id) {
            if ($id === '' || trim($id) !== $id || strlen($id) > 128) {
                throw new InvalidArgumentException('Invalid opaque synthetic identifier.');
            }
        }
        if ($revision < 1) {
            throw new InvalidArgumentException('Revision must be positive.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->schemaVersion,
            $this->producer,
            $this->eventType,
            $this->eventId,
            $this->correlationId,
            $this->portalOrganizationId,
            $this->performanceTenantId,
            $this->serviceId,
            $this->revision,
            $this->eligibility,
        ], JSON_THROW_ON_ERROR));
    }
}

/** In-memory idempotency/eligibility simulator; not a production message bus. */
final class SyntheticPortalProjector
{
    /** @var array<string, string> */
    private array $eventFingerprints = [];
    /** @var array<string, array{tenant:string,organization:string}> */
    private array $serviceOwners = [];
    /** @var array<string, array{revision:int,eligibility:string,state:string}> */
    private array $serviceStates = [];
    /** @var list<array{event_id:string,tenant_id:string,correlation_id:string,result:string}> */
    private array $audit = [];

    public function accept(SyntheticPortalEvent $event): string
    {
        if ($event->producer !== 'portal-imagine-mock' || $event->schemaVersion !== '0.1-draft') {
            return $this->audit($event, 'invalid_mock_origin');
        }
        if ($event->eventType !== 'performance.service.eligibility_changed') {
            return $this->audit($event, 'unsupported_event_type');
        }
        if (!in_array($event->eligibility, ['pending_payment', 'eligible_mock', 'suspended', 'cancelled'], true)) {
            return $this->audit($event, 'invalid_eligibility');
        }

        $eventKey = json_encode([$event->producer, $event->eventId], JSON_THROW_ON_ERROR);
        $fingerprint = $event->fingerprint();
        if (isset($this->eventFingerprints[$eventKey])) {
            return $this->audit($event, $this->eventFingerprints[$eventKey] === $fingerprint
                ? 'duplicate_event' : 'event_payload_conflict');
        }

        $owner = $this->serviceOwners[$event->serviceId] ?? null;
        if ($owner !== null && ($owner['tenant'] !== $event->performanceTenantId
            || $owner['organization'] !== $event->portalOrganizationId)) {
            return $this->audit($event, 'rejected_link');
        }

        $state = $this->serviceStates[$event->serviceId] ?? null;
        if ($state !== null && $event->revision <= $state['revision']) {
            return $this->audit($event, $event->revision < $state['revision']
                ? 'stale_revision' : 'revision_conflict');
        }
        if ($state !== null && $state['state'] === 'cancelled_demo'
            && $event->eligibility === 'eligible_mock') {
            return $this->audit($event, 'reactivation_requires_review');
        }

        $next = match ($event->eligibility) {
            'pending_payment' => 'pending_payment_demo',
            'eligible_mock' => 'onboarding_pending_demo',
            'suspended' => 'suspended_demo',
            'cancelled' => 'cancelled_demo',
        };

        $this->serviceOwners[$event->serviceId] = [
            'tenant' => $event->performanceTenantId,
            'organization' => $event->portalOrganizationId,
        ];
        $this->eventFingerprints[$eventKey] = $fingerprint;
        $this->serviceStates[$event->serviceId] = [
            'revision' => $event->revision,
            'eligibility' => $event->eligibility,
            'state' => $next,
        ];
        return $this->audit($event, 'accepted_demo');
    }

    public function statusFor(string $tenantId, string $serviceId): string
    {
        $owner = $this->serviceOwners[$serviceId] ?? null;
        if ($owner === null || $owner['tenant'] !== $tenantId) {
            return 'not_found'; // no information about another tenant's service
        }
        return $this->serviceStates[$serviceId]['state'];
    }

    /** @return list<array{event_id:string,tenant_id:string,correlation_id:string,result:string}> */
    public function auditFor(string $tenantId): array
    {
        return array_values(array_filter($this->audit, static fn (array $row): bool => $row['tenant_id'] === $tenantId));
    }

    private function audit(SyntheticPortalEvent $event, string $reason): string
    {
        $this->audit[] = [
            'event_id' => $event->eventId,
            'tenant_id' => $event->performanceTenantId,
            'correlation_id' => $event->correlationId,
            'result' => $reason,
        ];
        return $reason;
    }
}
