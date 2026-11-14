<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';
use Imagine\Performance\Commercial\MonthlyFeeReconciliation;

function assertSameValue(mixed $expected, mixed $actual, string $description): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($description . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}
function mustReject(callable $call, string $description): void
{
    try { $call(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected invalid input rejection: ' . $description);
}
$fixture = json_decode(file_get_contents(__DIR__ . '/../contracts/portal/performance-offer-v4.pinned.json'), true, 512, JSON_THROW_ON_ERROR);

// Snapshot audit: these are decision-backed values, not independently authorized charges.
assertSameValue('pinned_offline_snapshot_not_runtime_source_of_truth', $fixture['status'], 'fixture status');
assertSameValue(4, $fixture['catalog_schema_version'], 'catalog revision');
assertSameValue('performance', $fixture['offer_id'], 'product');
assertSameValue('BRL', $fixture['currency'], 'currency');
assertSameValue(2000, $fixture['management_fee_basis_points'], 'approved 20 percent');
assertSameValue(70000, $fixture['minimum_media_investment_per_channel_cents'], 'R700 minimum');
assertSameValue(100000000, $fixture['quote_maximum_per_channel_cents'], 'Portal sandbox cap');
assertSameValue(['google_ads', 'meta_ads'], $fixture['channels'], 'ad channels');
assertSameValue('monthly', $fixture['reference_period'], 'monthly contract');
assertSameValue('estimated_management_fee_upfront_with_monthly_actual_media_spend_adjustment', $fixture['operational_settlement_policy'], 'advance and true-up');
assertSameValue(true, $fixture['media_spend_paid_directly_to_platforms'], 'separate media');
assertSameValue(false, $fixture['direct_checkout_enabled'], 'no direct checkout');
assertSameValue(false, $fixture['automatic_settlement_charge_enabled'], 'no automatic charge');

// Reference quote function mirrors the pinned Portal ApprovedOfferCatalog::quote,
// not a production API. Check fee at total (never sum independently rounded channel fees).
function portalQuoteReference(array $fixture, array $planned): int
{
    if ($planned === [] || array_diff(array_keys($planned), $fixture['channels']) !== []) {
        throw new InvalidArgumentException('Unsupported scope');
    }
    $total = 0;
    foreach ($planned as $cents) {
        if (!is_int($cents) || $cents < $fixture['minimum_media_investment_per_channel_cents'] || $cents > $fixture['quote_maximum_per_channel_cents']) {
            throw new InvalidArgumentException('Amount outside quote limits');
        }
        $total += $cents;
    }
    return intdiv($total * $fixture['management_fee_basis_points'] + 5000, 10000);
}
$plans = [
    ['google_ads' => 70000],
    ['meta_ads' => 70001],
    ['google_ads' => 70003],
    ['meta_ads' => 99999999],
    ['google_ads' => 100000000],
    ['google_ads' => 70003, 'meta_ads' => 70003],
    ['meta_ads' => 100000000, 'google_ads' => 70001],
    ['google_ads' => 100000000, 'meta_ads' => 100000000]
];
foreach ($plans as $planned) {
    $channels = [];
    foreach ($planned as $channel => $cents) {
        $channels[$channel] = ['planned_cents' => $cents, 'actual_cents' => $cents];
    }
    $result = MonthlyFeeReconciliation::calculate('2026-11', $channels);
    $reference = portalQuoteReference($fixture, $planned);
    assertSameValue($reference, $result['advance_fee_cents'], 'advance mirrors Portal');
    assertSameValue($reference, $result['earned_fee_cents'], 'earned mirrors Portal');
    assertSameValue(0, $result['adjustment_cents'], 'equal actual no adjustment');
    assertSameValue('requires_finance_review_no_auto_charge_or_credit', $result['settlement_status'], 'review required');
}
$lower = MonthlyFeeReconciliation::calculate('2026-11', [
    'google_ads' => ['planned_cents' => 70000, 'actual_cents' => 69997],
]);
assertSameValue(-1, $lower['adjustment_cents'], 'below planned is not subject to minimum top-up');
$unused = MonthlyFeeReconciliation::calculate('2026-11', [
    'google_ads' => ['planned_cents' => 70000, 'actual_cents' => 0],
]);
assertSameValue(-14000, $unused['adjustment_cents'], 'no forced floor on actual spend');
mustReject(
    fn() => MonthlyFeeReconciliation::calculate('2026-11', [
        'google_ads' => ['planned_cents' => 100000001, 'actual_cents' => 70000],
    ]),
    'planned above Portal upper bound'
);
mustReject(
    fn() => MonthlyFeeReconciliation::calculate('2026-11', [
        'meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 100000001],
    ]),
    'actual above Portal upper bound'
);
mustReject(
    fn() => MonthlyFeeReconciliation::calculate('2026-11', [
        'meta_ads' => ['planned_cents' => 69999, 'actual_cents' => 70000],
    ]),
    'minimum applied per channel, not portfolio'
);
echo "OK: pinned Portal catalog v4 parity cases passed\n";
