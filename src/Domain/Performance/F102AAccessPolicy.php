<?php

declare(strict_types=1);

namespace Imagine\Performance\Domain;

/**
 * Fail-closed, pure domain policy for synthetic fixtures, NOT production auth.
 * No database, session, external API, HTTP, network, or I/O side effects.
 */
final class F102AAccessPolicy
{
    public function canRead(
        PerformanceTenant $tenant,
        OperationalMembership $member,
        ServiceEligibilitySnapshot $eligibility,
        SyntheticAuthorizationContext $context,
        OperationalResource $resource,
        string $requestedTenantId,
        int $nowEpoch,
    ): bool {
        return $requestedTenantId === $tenant->id
            && $this->allows($tenant, $member, $eligibility, $context, $resource, 'campaigns:read', $nowEpoch);
    }

    public function canApproveProposal(
        PerformanceTenant $tenant,
        OperationalMembership $member,
        ServiceEligibilitySnapshot $eligibility,
        SyntheticAuthorizationContext $context,
        SyntheticApprovalProposal $proposal,
        string $presentedManifestVersion,
        int $nowEpoch,
    ): bool {
        return $member->actorId !== $proposal->proposerActorId
            && in_array($member->role, ['approver', 'tenant_admin'], true)
            && $proposal->manifestVersion === $presentedManifestVersion
            && $this->allows($tenant, $member, $eligibility, $context, $proposal->resource, 'proposal:approve', $nowEpoch);
    }

    private function allows(
        PerformanceTenant $tenant,
        OperationalMembership $member,
        ServiceEligibilitySnapshot $eligibility,
        SyntheticAuthorizationContext $context,
        OperationalResource $resource,
        string $requiredScope,
        int $nowEpoch,
    ): bool {
        return $context->source === 'synthetic_test_only'
            && $context->expiresAtEpoch > $nowEpoch
            && $tenant->status === 'active'
            && $member->status === 'active'
            && $member->tenantId === $tenant->id
            && $member->actorId === $context->actorId
            && $context->tenantId === $tenant->id
            && $resource->tenantId === $tenant->id
            && $eligibility->tenantId === $tenant->id
            && $eligibility->serviceId === $context->serviceId
            && $resource->serviceId === $context->serviceId
            && $eligibility->state === 'eligible_mock'
            && $eligibility->source === 'portal_demo_fixture'
            && in_array($requiredScope, $member->scopes, true)
            && in_array($requiredScope, $context->scopes, true);
    }
}
