<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';

use Imagine\Performance\Commercial\MonthlySpendEvidencePreview as Evidence;

function evidenceSame(mixed $expected, mixed $actual, string $case): void {
    if ($actual !== $expected) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
function evidenceReject(callable $f, string $case): void {
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected rejection: ' . $case);
}
$quote = [
    'catalog_version' => str_repeat('b', 64),
    'product' => 'performance',
    'currency' => 'BRL',
    'gateway' => 'asaas',
    'real_charge_enabled' => false,
    'media_by_channel_cents' => ['google_ads' => 70000, 'meta_ads' => 70000],
    'media_paid_directly_to_platforms' => true,
    'management_fee_basis_points' => 2000,
    'estimated_management_fee_cents' => 28000,
    'reference_period' => 'monthly',
    'amount_now_cents' => 28000,
    'settlement_policy' => 'estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment',
];
function evidenceRow(string $channel, string $id, int $cents, string $period = '2026-11'): array {
    return ['channel' => $channel, 'period' => $period, 'report_id' => $id, 'source' => 'synthetic_fixture', 'spend_cents' => $cents];
}
$rows = [
    evidenceRow('google_ads', 'google-campaign-1', 30000),
    evidenceRow('google_ads', 'google-campaign-2', 45000),
    evidenceRow('meta_ads', 'meta-campaign-1', 60000),
];
$result = Evidence::build($quote, '2026-11', $rows);
evidenceSame(3, $result['record_count'], 'three synthetic reports');
evidenceSame(['google_ads' => 75000, 'meta_ads' => 60000], $result['unverified_spend_by_channel_cents'], 'correct aggregation');
evidenceSame(['google_ads' => 2, 'meta_ads' => 1], $result['record_counts_by_channel'], 'counts');
evidenceSame(28000, $result['preview']['estimated_advance_fee_cents'], 'advance');
evidenceSame(27000, $result['preview']['unverified_earned_fee_cents'], 'earned');
evidenceSame(-1000, $result['preview']['review_only_adjustment_cents'], 'adjustment');
evidenceSame(false, $result['provider_spend_verified'], 'never certify provider spend');
evidenceSame(false, $result['source_authenticated'], 'no authenticated source');
evidenceSame(false, $result['durable_idempotency_guaranteed'], 'no durable database');
evidenceSame(false, $result['automatic_charge_or_credit_enabled'], 'no automatic settlement');
evidenceSame(false, $result['ads_mutation_enabled'], 'no campaign writes');
evidenceSame($result['records_fingerprint_sha256_not_proof'],
    Evidence::build($quote, '2026-11', array_reverse($rows))['records_fingerprint_sha256_not_proof'],
    'fingerprint insensitive to record ordering');
evidenceSame($result['preview']['review_only_adjustment_cents'],
    Evidence::build($quote, '2026-11', array_reverse($rows))['preview']['review_only_adjustment_cents'],
    'reordered spend unchanged');
$zero = Evidence::build($quote, '2026-11', [
    evidenceRow('google_ads', 'zero-g', 0), evidenceRow('meta_ads', 'zero-m', 0),
]);
evidenceSame(-28000, $zero['preview']['review_only_adjustment_cents'], 'explicit zero not guessed');
evidenceReject(fn() => Evidence::build($quote, '2026-11', [evidenceRow('google_ads', 'only-g', 0)]), 'absent Meta not zero');
evidenceReject(fn() => Evidence::build($quote, '2026-11', []), 'missing all');
evidenceReject(fn() => Evidence::build($quote, '2026-11', array_fill(0, 201, $rows[0])), 'record cap');
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], $rows[0], $rows[2]]), 'duplicate report');
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], evidenceRow('google_ads', 'g3', 80000000), $rows[2]]), 'cumulative cap');
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], evidenceRow('other', 'fake', 0), $rows[2]]), 'unknown channel');
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], evidenceRow('meta_ads', 'wrong-month', 0, '2026-10')]), 'month mismatch');
$bad = $rows[2]; $bad['source'] = 'provider_verified';
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], $bad]), 'spoofed verification');
$bad = $rows[2]; $bad['spend_cents'] = 100.50;
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], $bad]), 'float amount');
$bad = $rows[2]; $bad['report_id'] = '../escape';
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], $bad]), 'unsafe id');
$bad = $rows[2]; $bad['authenticated'] = true;
evidenceReject(fn() => Evidence::build($quote, '2026-11', [$rows[0], $bad]), 'unknown attestation field');
$badQuote = $quote; $badQuote['amount_now_cents'] += 1;
evidenceReject(fn() => Evidence::build($badQuote, '2026-11', $rows), 'quote mutation rejected');
echo "OK: synthetic spend evidence intake is scoped, deterministic and fail closed\n";
