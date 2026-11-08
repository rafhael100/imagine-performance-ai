<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Domain/Performance/F102ADomain.php';
require_once __DIR__ . '/../../src/Domain/Performance/F102AAccessPolicy.php';

use Imagine\Performance\Domain\F102AAccessPolicy;
use Imagine\Performance\Domain\OperationalMembership;
use Imagine\Performance\Domain\OperationalResource;
use Imagine\Performance\Domain\PerformanceTenant;
use Imagine\Performance\Domain\ServiceEligibilitySnapshot;
use Imagine\Performance\Domain\SyntheticApprovalProposal;
use Imagine\Performance\Domain\SyntheticAuthorizationContext;

$now = 1_800_000_000;
$tenant = new PerformanceTenant('tenant-demo-a', 'active');
$member = new OperationalMembership('tenant-demo-a', 'actor-demo-a', 'analyst', 'active', ['campaigns:read']);
$eligible = new ServiceEligibilitySnapshot('tenant-demo-a', 'svc-demo-a', 'eligible_mock', 'portal_demo_fixture');
$context = SyntheticAuthorizationContext::forTest('tenant-demo-a', 'actor-demo-a', 'svc-demo-a', ['campaigns:read'], $now + 3600);
$resource = new OperationalResource('tenant-demo-a', 'svc-demo-a', 'campaign-demo-a');
$policy = new F102AAccessPolicy();
$count = 0;

function check(string $name, bool $observed, bool $expected): void
{
    global $count;
    if ($observed !== $expected) {
        throw new RuntimeException("FAIL {$name}: expected " . ($expected ? 'ALLOW' : 'DENY'));
    }
    ++$count;
    echo "PASS {$name}\n";
}

check('01 own tenant authorized mock read', $policy->canRead($tenant, $member, $eligible, $context, $resource, 'tenant-demo-a', $now), true);
check('02 B tenant IDOR resource denied', $policy->canRead($tenant, $member, $eligible, $context, new OperationalResource('tenant-demo-b', 'svc-demo-a', 'campaign-b'), 'tenant-demo-a', $now), false);
check('03 requested tenant injection denied', $policy->canRead($tenant, $member, $eligible, $context, $resource, 'tenant-demo-b', $now), false);
check('04 member belongs B denied', $policy->canRead($tenant, new OperationalMembership('tenant-demo-b', 'actor-demo-a', 'analyst', 'active', ['campaigns:read']), $eligible, $context, $resource, 'tenant-demo-a', $now), false);
check('05 actor mismatch denied', $policy->canRead($tenant, new OperationalMembership('tenant-demo-a', 'other', 'analyst', 'active', ['campaigns:read']), $eligible, $context, $resource, 'tenant-demo-a', $now), false);
check('06 revoked member denied', $policy->canRead($tenant, new OperationalMembership('tenant-demo-a', 'actor-demo-a', 'analyst', 'revoked', ['campaigns:read']), $eligible, $context, $resource, 'tenant-demo-a', $now), false);
check('07 suspended tenant denied', $policy->canRead(new PerformanceTenant('tenant-demo-a', 'suspended'), $member, $eligible, $context, $resource, 'tenant-demo-a', $now), false);
check('08 expired synthetic grant denied', $policy->canRead($tenant, $member, $eligible, SyntheticAuthorizationContext::forTest('tenant-demo-a', 'actor-demo-a', 'svc-demo-a', ['campaigns:read'], $now), $resource, 'tenant-demo-a', $now), false);
check('09 no membership scope denied', $policy->canRead($tenant, new OperationalMembership('tenant-demo-a', 'actor-demo-a', 'analyst', 'active', []), $eligible, $context, $resource, 'tenant-demo-a', $now), false);
check('10 no grant scope denied', $policy->canRead($tenant, $member, $eligible, SyntheticAuthorizationContext::forTest('tenant-demo-a', 'actor-demo-a', 'svc-demo-a', [], $now + 60), $resource, 'tenant-demo-a', $now), false);
check('11 pending payment denied', $policy->canRead($tenant, $member, new ServiceEligibilitySnapshot('tenant-demo-a', 'svc-demo-a', 'pending_payment', 'portal_demo_fixture'), $context, $resource, 'tenant-demo-a', $now), false);
check('12 unknown payment denied', $policy->canRead($tenant, $member, new ServiceEligibilitySnapshot('tenant-demo-a', 'svc-demo-a', 'unknown', 'portal_demo_fixture'), $context, $resource, 'tenant-demo-a', $now), false);
check('13 non-demo source denied', $policy->canRead($tenant, $member, new ServiceEligibilitySnapshot('tenant-demo-a', 'svc-demo-a', 'eligible_mock', 'external_unknown'), $context, $resource, 'tenant-demo-a', $now), false);
check('14 service mismatch denied', $policy->canRead($tenant, $member, $eligible, $context, new OperationalResource('tenant-demo-a', 'svc-demo-b', 'campaign-a'), 'tenant-demo-a', $now), false);
check('15 context cross tenant denied', $policy->canRead($tenant, $member, $eligible, SyntheticAuthorizationContext::forTest('tenant-demo-b', 'actor-demo-a', 'svc-demo-a', ['campaigns:read'], $now + 60), $resource, 'tenant-demo-a', $now), false);
$approver = new OperationalMembership('tenant-demo-a', 'approver-a', 'approver', 'active', ['proposal:approve']);
$approvalContext = SyntheticAuthorizationContext::forTest('tenant-demo-a', 'approver-a', 'svc-demo-a', ['proposal:approve'], $now + 3600);
$proposal = new SyntheticApprovalProposal($resource, 'actor-demo-a', 'manifest-v1');
check('16 separate approver may approve synthetic proposal', $policy->canApproveProposal($tenant, $approver, $eligible, $approvalContext, $proposal, 'manifest-v1', $now), true);
check('17 altered manifest denied', $policy->canApproveProposal($tenant, $approver, $eligible, $approvalContext, $proposal, 'manifest-v2', $now), false);
check('18 self approval denied', $policy->canApproveProposal($tenant, $approver, $eligible, $approvalContext, new SyntheticApprovalProposal($resource, 'approver-a', 'manifest-v1'), 'manifest-v1', $now), false);
check('19 analyst role cannot approve', $policy->canApproveProposal($tenant, new OperationalMembership('tenant-demo-a', 'approver-a', 'analyst', 'active', ['proposal:approve']), $eligible, $approvalContext, $proposal, 'manifest-v1', $now), false);
check('20 missing approval permission denied', $policy->canApproveProposal($tenant, new OperationalMembership('tenant-demo-a', 'approver-a', 'approver', 'active', []), $eligible, $approvalContext, $proposal, 'manifest-v1', $now), false);
check('21 B proposal cannot be approved in A', $policy->canApproveProposal($tenant, $approver, $eligible, $approvalContext, new SyntheticApprovalProposal(new OperationalResource('tenant-demo-b', 'svc-demo-a', 'campaign-b'), 'actor-demo-b', 'manifest-v1'), 'manifest-v1', $now), false);
check('22 tenant admin without scope cannot approve', $policy->canApproveProposal($tenant, new OperationalMembership('tenant-demo-a', 'approver-a', 'tenant_admin', 'active', ['campaigns:read']), $eligible, $approvalContext, $proposal, 'manifest-v1', $now), false);

echo "PASS {$count} synthetic policy scenarios total\n";
