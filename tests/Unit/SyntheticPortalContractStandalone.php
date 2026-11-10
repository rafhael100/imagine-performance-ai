<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/src/Domain/Performance/SyntheticPortalEventContract.php';

use Imagine\Performance\Domain\SyntheticPortalEvent;
use Imagine\Performance\Domain\SyntheticPortalProjector;

$p = new SyntheticPortalProjector();
$base = static fn (string $event, int $revision, string $eligibility = 'eligible_mock', string $tenant = 'perf-demo-a', string $org = 'org-demo-a', string $service = 'service-demo-a', string $producer = 'portal-imagine-mock', string $schema = '0.1-draft', string $type = 'performance.service.eligibility_changed'): SyntheticPortalEvent
    => new SyntheticPortalEvent($schema, $producer, $type, $event, 'correlation-demo-a', $org, $tenant, $service, $revision, $eligibility);
$count = 0;
$check = static function (string $name, mixed $actual, mixed $expected) use (&$count): void {
    if ($actual !== $expected) {
        throw new RuntimeException("FAIL $name: got ".var_export($actual, true)." expected ".var_export($expected, true));
    }
    echo "PASS $name\n";
    ++$count;
};

$pending = $base('evt-a-1', 1, 'pending_payment');
$check('01 pending accepted as demo only', $p->accept($pending), 'accepted_demo');
$check('02 pending not activated', $p->statusFor('perf-demo-a', 'service-demo-a'), 'pending_payment_demo');
$eligible = $base('evt-a-2', 2);
$check('03 eligible mock accepted', $p->accept($eligible), 'accepted_demo');
$check('04 eligibility is not a real active service', $p->statusFor('perf-demo-a', 'service-demo-a'), 'onboarding_pending_demo');
$check('05 duplicate event', $p->accept($eligible), 'duplicate_event');
$check('06 replay same event changed payload', $p->accept($base('evt-a-2', 3)), 'event_payload_conflict');
$check('07 different tenant cannot take over service', $p->accept($base('evt-b-bad', 3, 'eligible_mock', 'perf-demo-b', 'org-demo-b')), 'rejected_link');
$check('08 tenant B cannot view A', $p->statusFor('perf-demo-b', 'service-demo-a'), 'not_found');
$check('09 stale event denied', $p->accept($base('evt-a-stale', 1)), 'stale_revision');
$check('10 equal revision different event rejected', $p->accept($base('evt-a-same-rev', 2)), 'revision_conflict');
$check('11 suspension recorded as demo', $p->accept($base('evt-a-3', 3, 'suspended')), 'accepted_demo');
$check('12 suspended state', $p->statusFor('perf-demo-a', 'service-demo-a'), 'suspended_demo');
$check('13 cancellation recorded', $p->accept($base('evt-a-4', 4, 'cancelled')), 'accepted_demo');
$check('14 canceled state', $p->statusFor('perf-demo-a', 'service-demo-a'), 'cancelled_demo');
$check('15 reactivation without gate blocked', $p->accept($base('evt-a-5', 5)), 'reactivation_requires_review');
$check('16 source mismatch', $p->accept($base('evt-a-invalid-source', 7, 'eligible_mock', producer: 'untrusted')), 'invalid_mock_origin');
$check('17 schema mismatch', $p->accept($base('evt-a-invalid-schema', 7, 'eligible_mock', schema: '999')), 'invalid_mock_origin');
$check('18 event type mismatch', $p->accept($base('evt-a-invalid-type', 7, 'eligible_mock', type: 'payment.confirmed')), 'unsupported_event_type');
$check('19 unknown eligibility', $p->accept($base('evt-a-invalid-state', 7, 'paid')), 'invalid_eligibility');
$check('20 separate tenant B accepted', $p->accept($base('evt-b-1', 1, 'eligible_mock', 'perf-demo-b', 'org-demo-b', 'service-demo-b')), 'accepted_demo');
$check('21 separate tenant B isolated', $p->statusFor('perf-demo-b', 'service-demo-b'), 'onboarding_pending_demo');
$check('22 A cannot read B', $p->statusFor('perf-demo-a', 'service-demo-b'), 'not_found');
$check('23 audit tenant scoped', count($p->auditFor('perf-demo-a')) > 0 && count($p->auditFor('perf-demo-b')) >= 2 && count(array_filter($p->auditFor('perf-demo-b'), fn(array $e) => $e['tenant_id'] !== 'perf-demo-b')) === 0, true);
$check('24 audit contains no payment or PII', str_contains(json_encode($p->auditFor('perf-demo-a')), 'eligible_mock'), false);
try {
    $base('', 1);
    throw new RuntimeException('Empty event ID was accepted');
} catch (InvalidArgumentException $e) {
    $check('25 empty event id refused', $e->getMessage(), 'Invalid opaque synthetic identifier.');
}
try {
    $base('evt-rev-zero', 0);
    throw new RuntimeException('Zero revision was accepted');
} catch (InvalidArgumentException $e) {
    $check('26 zero revision refused', $e->getMessage(), 'Revision must be positive.');
}
echo "PASS $count isolated synthetic Portal contract checks\n";
