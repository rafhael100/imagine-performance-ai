<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
require_once __DIR__ . '/../src/Domain/Commercial/PortalQuoteReconciliationPreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySpendEvidencePreview.php';
require_once __DIR__ . '/../src/Domain/Commercial/MonthlySettlementCandidate.php';

use Imagine\Performance\Commercial\MonthlySettlementCandidate as Candidate;

function sameCandidate(mixed $expected, mixed $actual, string $case): void {
    if ($expected !== $actual) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
function denyCandidate(callable $f, string $case): void {
    try { $f(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Should reject: ' . $case);
}
function syntheticQuote(): array {
    return [
        'catalog_version' => str_repeat('a',64),
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
}
function syntheticRows(): array {
    return [
        ['channel' => 'google_ads', 'period' => '2026-11', 'report_id' => 'google-1', 'source' => 'synthetic_fixture', 'spend_cents' => 70000],
        ['channel' => 'meta_ads', 'period' => '2026-11', 'report_id' => 'meta-1', 'source' => 'synthetic_fixture', 'spend_cents' => 50000],
    ];
}
$tenant = '33333333-3333-4333-8333-333333333333';
$candidate = Candidate::build($tenant, syntheticQuote(), '2026-11', syntheticRows());
sameCandidate(2, count($candidate['spend_rows']), 'two rows');
sameCandidate(28000, $candidate['review_row']['advance_fee_cents'], 'advance 20 percent');
sameCandidate(24000, $candidate['review_row']['earned_fee_cents'], 'earned 20 percent');
sameCandidate(-4000, $candidate['review_row']['adjustment_cents'], 'negative adjustment');
sameCandidate(false, $candidate['tenant_authenticated'], 'tenant not authenticated');
sameCandidate(false, $candidate['portal_quote_authenticated'], 'quote not authenticated');
sameCandidate(false, $candidate['gateway_write_enabled'], 'no gateway writes');
sameCandidate('2026-11-01', $candidate['review_row']['reference_month'], 'month first');
$reversed = Candidate::build($tenant, syntheticQuote(), '2026-11', array_reverse(syntheticRows()));
sameCandidate($candidate['spend_rows'], $reversed['spend_rows'], 'deterministic row order');
sameCandidate($candidate['review_row'], $reversed['review_row'], 'deterministic review');
denyCandidate(fn() => Candidate::build('bad-tenant', syntheticQuote(), '2026-11', syntheticRows()), 'bad tenant');
denyCandidate(fn() => Candidate::build($tenant, syntheticQuote(), '2026-12', syntheticRows()), 'mismatched month');
$bad = syntheticQuote(); $bad['real_charge_enabled'] = true;
denyCandidate(fn() => Candidate::build($tenant, $bad, '2026-11', syntheticRows()), 'real charge flag');
$badRows = syntheticRows(); $badRows[1]['source'] = 'official_google';
denyCandidate(fn() => Candidate::build($tenant, syntheticQuote(), '2026-11', $badRows), 'claimed official source');
echo "OK: synthetic settlement candidate parameters are deterministic and untrusted\n";
