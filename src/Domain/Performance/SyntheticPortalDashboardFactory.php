<?php

declare(strict_types=1);

namespace Imagine\Performance\Domain;

use RuntimeException;

/**
 * Offline-only, deterministic demonstrator. The envelope is NOT a verified
 * HTTP response, a service account assertion or a commercial entitlement.
 */
final class SyntheticPortalDashboardFactory
{
    /** @return array<string,mixed> */
    public static function build(): array
    {
        $tenant = 'perf-demo-aurora';
        $organization = 'org-demo-aurora';
        $service = 'svc-demo-aurora';

        $projector = new SyntheticPortalProjector();
        $event = new SyntheticPortalEvent(
            '0.1-draft',
            'portal-imagine-mock',
            'performance.service.eligibility_changed',
            'evt-dashboard-demo-01',
            'corr-dashboard-demo-01',
            $organization,
            $tenant,
            $service,
            1,
            'eligible_mock'
        );
        if ($projector->accept($event) !== 'accepted_demo') {
            throw new RuntimeException('Mock Portal eligibility was rejected');
        }

        $registry = new SyntheticCampaignRegistry();
        // Capacity numbers below are exclusively local fixtures, NEVER plan limits.
        $registry->setDemoCapacity($tenant, 'google_ads', 1);
        $registry->setDemoCapacity($tenant, 'meta', 1);
        if ($registry->add($tenant, 'google_ads', 'demo-search-01') !== 'accepted_demo'
            || $registry->add($tenant, 'meta', 'demo-meta-01') !== 'accepted_demo'
            || $registry->pause($tenant, 'demo-meta-01') !== 'paused_demo') {
            throw new RuntimeException('Could not assemble fictional campaigns');
        }

        $approvals = new SyntheticApprovalDesk();
        if ($approvals->propose(
            $tenant,
            'demo-approval-01',
            'demo-author',
            'demo-search-01',
            'demo-v1',
            1800000300
        ) !== 'submitted_demo') {
            throw new RuntimeException('Could not assemble fictional approval');
        }

        $reports = new SyntheticPerformanceReports();
        $reports->setDemo($tenant, 'demo-report-01', 'google_ads', 4200, 264);
        $report = $reports->forTenant($tenant, 'demo-report-01');
        if ($report === null || $report['source'] !== 'synthetic_demo') {
            throw new RuntimeException('Could not assemble fictional report');
        }

        return [
            'schema_version' => 'perf.portal.snapshot.demo.v1',
            'producer' => 'imagine-performance-ai-mock',
            'source' => 'synthetic_fixture_only',
            'portal_organization_ref' => $organization,
            'performance_tenant_ref' => $tenant,
            'service_state' => $projector->statusFor($tenant, $service),
            'commercial' => [
                'contracted' => false,
                'capacity_state' => 'unknown_not_for_sale_demo',
                'price_state' => 'not_homologated',
            ],
            'metrics' => [
                'source' => $report['source'],
                'status' => $report['status'],
                'campaigns_managed' => $registry->count($tenant, 'google_ads') + $registry->count($tenant, 'meta'),
                'clicks' => $report['clicks'],
                'approvals_pending' => (int) ($approvals->statusFor($tenant, 'demo-approval-01') === 'pending_human_demo'),
                'media_spend' => null,
            ],
            'campaigns' => [
                [
                    'id' => 'demo-search-01',
                    'tenant_ref' => $tenant,
                    'channel' => 'google_ads',
                    'title' => 'Pesquisa — novos contatos (exemplo)',
                    'state' => $registry->state($tenant, 'demo-search-01'),
                    'managed' => true,
                ],
                [
                    'id' => 'demo-meta-01',
                    'tenant_ref' => $tenant,
                    'channel' => 'meta',
                    'title' => 'Reconhecimento — marca (exemplo)',
                    'state' => $registry->state($tenant, 'demo-meta-01'),
                    'managed' => true,
                ],
            ],
            'approvals' => [
                [
                    'id' => 'demo-approval-01',
                    'tenant_ref' => $tenant,
                    'campaign_id' => 'demo-search-01',
                    'status' => $approvals->statusFor($tenant, 'demo-approval-01'),
                    'manifest_version' => 'demo-v1',
                ],
            ],
        ];
    }
}
