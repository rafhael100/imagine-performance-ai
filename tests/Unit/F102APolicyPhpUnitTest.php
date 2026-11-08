<?php
declare(strict_types=1);

namespace Tests\Unit;

use Imagine\Performance\Domain\F102AAccessPolicy;
use Imagine\Performance\Domain\OperationalMembership;
use Imagine\Performance\Domain\OperationalResource;
use Imagine\Performance\Domain\PerformanceTenant;
use Imagine\Performance\Domain\ServiceEligibilitySnapshot;
use Imagine\Performance\Domain\SyntheticApprovalProposal;
use Imagine\Performance\Domain\SyntheticAuthorizationContext;
use PHPUnit\Framework\TestCase;

/**
 * Synthetic policy tests only. No identity attestation, DB, HTTP or ad-provider checks.
 */
final class F102APolicyPhpUnitTest extends TestCase
{
    private const NOW = 1800000000;

    /** @return array{PerformanceTenant,OperationalMembership,ServiceEligibilitySnapshot,SyntheticAuthorizationContext,OperationalResource} */
    private function fixture(): array
    {
        return [
            new PerformanceTenant('tenant-demo-a', 'active'),
            new OperationalMembership('tenant-demo-a', 'actor-demo-a', 'analyst', 'active', ['campaigns:read']),
            new ServiceEligibilitySnapshot('tenant-demo-a', 'svc-demo-a', 'eligible_mock', 'portal_demo_fixture'),
            SyntheticAuthorizationContext::forTest('tenant-demo-a', 'actor-demo-a', 'svc-demo-a', ['campaigns:read'], self::NOW + 3600),
            new OperationalResource('tenant-demo-a', 'svc-demo-a', 'campaign-demo-a'),
        ];
    }

    public function test_authorized_tenant_reads_own_synthetic_resource(): void
    {
        [$tenant, $member, $eligible, $context, $resource] = $this->fixture();
        self::assertTrue((new F102AAccessPolicy())->canRead($tenant, $member, $eligible, $context, $resource, 'tenant-demo-a', self::NOW));
    }

    public function test_cross_tenant_resource_is_denied(): void
    {
        [$tenant, $member, $eligible, $context] = $this->fixture();
        $foreign = new OperationalResource('tenant-demo-b', 'svc-demo-a', 'campaign-demo-b');
        self::assertFalse((new F102AAccessPolicy())->canRead($tenant, $member, $eligible, $context, $foreign, 'tenant-demo-a', self::NOW));
    }

    public function test_tenant_id_injection_is_denied(): void
    {
        [$tenant, $member, $eligible, $context, $resource] = $this->fixture();
        self::assertFalse((new F102AAccessPolicy())->canRead($tenant, $member, $eligible, $context, $resource, 'tenant-demo-b', self::NOW));
    }

    public function test_suspended_actor_and_expired_context_are_denied(): void
    {
        [$tenant, $member, $eligible, $context, $resource] = $this->fixture();
        $revoked = new OperationalMembership($member->tenantId, $member->actorId, $member->role, 'revoked', $member->scopes);
        $expired = SyntheticAuthorizationContext::forTest($context->tenantId, $context->actorId, $context->serviceId, $context->scopes, self::NOW);
        $policy = new F102AAccessPolicy();
        self::assertFalse($policy->canRead($tenant, $revoked, $eligible, $context, $resource, 'tenant-demo-a', self::NOW));
        self::assertFalse($policy->canRead($tenant, $member, $eligible, $expired, $resource, 'tenant-demo-a', self::NOW));
    }

    public function test_pending_payment_never_enables_reads(): void
    {
        [$tenant, $member, $eligible, $context, $resource] = $this->fixture();
        $pending = new ServiceEligibilitySnapshot($eligible->tenantId, $eligible->serviceId, 'pending_payment', 'portal_demo_fixture');
        self::assertFalse((new F102AAccessPolicy())->canRead($tenant, $member, $pending, $context, $resource, 'tenant-demo-a', self::NOW));
    }

    public function test_proposal_requires_separate_approver_matching_manifest(): void
    {
        [$tenant, , $eligible, , $resource] = $this->fixture();
        $approver = new OperationalMembership('tenant-demo-a', 'approver-demo-a', 'approver', 'active', ['proposal:approve']);
        $grant = SyntheticAuthorizationContext::forTest('tenant-demo-a', 'approver-demo-a', 'svc-demo-a', ['proposal:approve'], self::NOW + 60);
        $policy = new F102AAccessPolicy();
        $proposal = new SyntheticApprovalProposal($resource, 'actor-demo-a', 'manifest-v1');
        self::assertTrue($policy->canApproveProposal($tenant, $approver, $eligible, $grant, $proposal, 'manifest-v1', self::NOW));
        self::assertFalse($policy->canApproveProposal($tenant, $approver, $eligible, $grant, $proposal, 'manifest-v2', self::NOW));
        self::assertFalse($policy->canApproveProposal($tenant, $approver, $eligible, $grant, new SyntheticApprovalProposal($resource, 'approver-demo-a', 'manifest-v1'), 'manifest-v1', self::NOW));
    }
}
