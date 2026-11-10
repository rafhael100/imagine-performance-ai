<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/src/Domain/Performance/SyntheticPortalEventContract.php';
require_once dirname(__DIR__, 2).'/src/Domain/Performance/SyntheticCampaignRegistry.php';
require_once dirname(__DIR__, 2).'/src/Domain/Performance/SyntheticApprovalsAndReports.php';
require_once dirname(__DIR__, 2).'/src/Domain/Performance/SyntheticPortalDashboardFactory.php';

use Imagine\Performance\Domain\SyntheticPortalDashboardFactory;

$expected = json_decode(
    (string) file_get_contents(dirname(__DIR__, 2).'/contracts/demo/performance-dashboard-v1.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$actual = SyntheticPortalDashboardFactory::build();
$n = 0;
$check = static function (string $label, mixed $got, mixed $wanted) use (&$n): void {
    if ($got !== $wanted) {
        throw new RuntimeException("FAIL {$label}: ".var_export($got, true)." !== ".var_export($wanted, true));
    }
    ++$n;
    echo "PASS {$label}\n";
};
$check('01 source fixture matches actual factory', $actual, $expected);
$check('02 source identifies mock', $actual['producer'], 'imagine-performance-ai-mock');
$check('03 schema is a demo-only version', $actual['schema_version'], 'perf.portal.snapshot.demo.v1');
$check('04 offline-only provenance', $actual['source'], 'synthetic_fixture_only');
$check('05 commercial is not contracted', $actual['commercial']['contracted'], false);
$check('06 numeric franchise unpublished', $actual['commercial']['capacity_state'], 'unknown_not_for_sale_demo');
$check('07 prices not homologated', $actual['commercial']['price_state'], 'not_homologated');
$check('08 service remains onboarding demo', $actual['service_state'], 'onboarding_pending_demo');
$check('09 two managed campaigns', $actual['metrics']['campaigns_managed'], 2);
$check('10 first campaign fake active', $actual['campaigns'][0]['state'], 'active_demo');
$check('11 paused still counts', $actual['campaigns'][1]['state'], 'paused_demo');
$check('12 clicks are fake', $actual['metrics']['clicks'], 264);
$check('13 metrics are fake', $actual['metrics']['status'], 'not_real_metrics');
$check('14 media spend absent', $actual['metrics']['media_spend'], null);
$check('15 one pending approval', $actual['metrics']['approvals_pending'], 1);
$check('16 no silent approval', $actual['approvals'][0]['status'], 'pending_human_demo');
$check('17 campaign of approval belongs to same snapshot', $actual['approvals'][0]['campaign_id'], $actual['campaigns'][0]['id']);
$check('18 campaign 1 tenant same', $actual['campaigns'][0]['tenant_ref'], $actual['performance_tenant_ref']);
$check('19 campaign 2 tenant same', $actual['campaigns'][1]['tenant_ref'], $actual['performance_tenant_ref']);
$check('20 cross-tenant data absent', str_contains(json_encode($actual, JSON_THROW_ON_ERROR), 'tenant-demo-b'), false);
echo "PASS {$n} demo snapshot cases\n";
