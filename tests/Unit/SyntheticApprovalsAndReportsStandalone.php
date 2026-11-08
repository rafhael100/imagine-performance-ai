<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2).'/src/Domain/Performance/SyntheticApprovalsAndReports.php';

use Imagine\Performance\Domain\SyntheticApprovalDesk;
use Imagine\Performance\Domain\SyntheticPerformanceReports;

$desk = new SyntheticApprovalDesk();
$reports = new SyntheticPerformanceReports();
$checks = 0;
$check = static function (string $label, mixed $actual, mixed $expected) use (&$checks): void {
    if ($actual !== $expected) {
        throw new RuntimeException("FAIL {$label}: got ".var_export($actual, true).' expected '.var_export($expected, true));
    }
    ++$checks;
    echo "PASS {$label}\n";
};
$now = 1800000000;
$check('01 proposal A created', $desk->propose('tenant-a', 'proposal-a', 'author-a', 'campaign-a', 'v1', $now + 300), 'submitted_demo');
$check('02 pending review', $desk->statusFor('tenant-a', 'proposal-a'), 'pending_human_demo');
$check('03 B cannot inspect A', $desk->statusFor('tenant-b', 'proposal-a'), 'not_found');
$check('04 B cannot approve A', $desk->decide('tenant-b', 'proposal-a', 'reviewer-b', 'v1', true, true, $now), 'not_found');
$check('05 self-approval denied', $desk->decide('tenant-a', 'proposal-a', 'author-a', 'v1', true, true, $now), 'authorization_denied');
$check('06 unauthorized reviewer denied', $desk->decide('tenant-a', 'proposal-a', 'reviewer-a', 'v1', false, true, $now), 'authorization_denied');
$check('07 manifest version mismatch', $desk->decide('tenant-a', 'proposal-a', 'reviewer-a', 'v2', true, true, $now), 'manifest_conflict');
$check('08 still pending after denials', $desk->statusFor('tenant-a', 'proposal-a'), 'pending_human_demo');
$check('09 approved demo only', $desk->decide('tenant-a', 'proposal-a', 'reviewer-a', 'v1', true, true, $now), 'approved_demo_no_execution');
$check('10 approved but never executed', $desk->statusFor('tenant-a', 'proposal-a'), 'approved_demo_no_execution');
$check('11 second decision denied', $desk->decide('tenant-a', 'proposal-a', 'reviewer-a', 'v1', true, false, $now), 'already_decided');
$check('12 duplicate proposal blocked', $desk->propose('tenant-b', 'proposal-a', 'author-b', 'campaign-b', 'v1', $now + 10), 'already_exists_or_out_of_scope');
$check('13 separate tenant proposal', $desk->propose('tenant-b', 'proposal-b', 'author-b', 'campaign-b', 'v1', $now + 10), 'submitted_demo');
$check('14 expired proposal denied', $desk->decide('tenant-b', 'proposal-b', 'reviewer-b', 'v1', true, true, $now + 10), 'expired_denied');
$check('15 expired stays pending - no silent approval', $desk->statusFor('tenant-b', 'proposal-b'), 'pending_human_demo');
$check('16 rejection authorized', $desk->decide('tenant-b', 'proposal-b', 'reviewer-b', 'v1', true, false, $now), 'rejected_demo');
$check('17 rejection state', $desk->statusFor('tenant-b', 'proposal-b'), 'rejected_demo');
$check('18 tenant A audit only A', count(array_filter($desk->historyFor('tenant-a'), static fn (array $a): bool => $a['tenant'] !== 'tenant-a')), 0);
$check('19 tenant B audit only B', count(array_filter($desk->historyFor('tenant-b'), static fn (array $a): bool => $a['tenant'] !== 'tenant-b')), 0);
$reports->setDemo('tenant-a', 'report-a', 'google_ads', 200, 14);
$check('20 own report', $reports->forTenant('tenant-a', 'report-a')['clicks'], 14);
$check('21 cross tenant report hidden', $reports->forTenant('tenant-b', 'report-a'), null);
$check('22 source marked as fake', $reports->forTenant('tenant-a', 'report-a')['source'], 'synthetic_demo');
$check('23 metrics marked non-real', $reports->forTenant('tenant-a', 'report-a')['status'], 'not_real_metrics');
$reports->setDemo('tenant-b', 'report-b', 'meta', 500, 50);
$check('24 Meta report segregated', $reports->forTenant('tenant-b', 'report-b')['channel'], 'meta');
$check('25 no A access to B', $reports->forTenant('tenant-a', 'report-b'), null);
try {
    $reports->setDemo('tenant-c', 'bad', 'meta', 10, 11);
    throw new RuntimeException('Accepted clicks greater than impressions');
} catch (InvalidArgumentException $e) {
    $check('26 invalid metrics rejected', $e->getMessage(), 'Invalid demo report');
}
try {
    $reports->setDemo('tenant-a', 'report-a', 'google_ads', 1, 0);
    throw new RuntimeException('Accepted duplicate report ID');
} catch (InvalidArgumentException $e) {
    $check('27 duplicate report rejected', $e->getMessage(), 'Duplicate report ID');
}
$check('28 unrecognized request hidden', $desk->statusFor('tenant-a', 'missing'), 'not_found');
$check('29 report absent', $reports->forTenant('tenant-a', 'missing'), null);
echo "PASS {$checks} F1-05 synthetic approval/report checks\n";
