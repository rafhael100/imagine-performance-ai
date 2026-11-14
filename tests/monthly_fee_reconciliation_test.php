<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliation.php';

use Imagine\Performance\Commercial\MonthlyFeeReconciliation as Fee;

function same($expected, $actual, string $case): void {
    if ($expected !== $actual) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
function rejected(callable $callback, string $case): void {
    try { $callback(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected rejection: ' . $case);
}
$one = Fee::calculate('2026-11', ['google_ads' => ['planned_cents' => 70000, 'actual_cents' => 70000]]);
same(14000, $one['advance_fee_cents'], 'one channel advance');
same(0, $one['adjustment_cents'], 'no adjustment');
same(true, $one['simulated'], 'simulation flag');
same('requires_finance_review_no_auto_charge_or_credit', $one['settlement_status'], 'no automatic charge');
$two = Fee::calculate('2026-11', [
    'google_ads' => ['planned_cents' => 70000, 'actual_cents' => 65000],
    'meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 90000],
]);
same(28000, $two['advance_fee_cents'], 'two channels advance');
same(31000, $two['earned_fee_cents'], 'two channels earned');
same(3000, $two['adjustment_cents'], 'positive reconciliation');
$credit = Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 0]]);
same(-14000, $credit['adjustment_cents'], 'negative adjustment does not issue credit');
$round = Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70001, 'actual_cents' => 70003]]);
same(14001, $round['earned_fee_cents'], 'half-up rounding');
rejected(fn() => Fee::calculate('2026-11', ['google_ads' => ['planned_cents' => 69999, 'actual_cents' => 90000]]), 'minimum per channel');
rejected(fn() => Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => -1]]), 'negative actual');
rejected(fn() => Fee::calculate('2026-13', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 0]]), 'month validation');
rejected(fn() => Fee::calculate('2026-11', ['other' => ['planned_cents' => 70000, 'actual_cents' => 0]]), 'unknown channel');
rejected(fn() => Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000.0, 'actual_cents' => 0]]), 'float input');
rejected(fn() => Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 0, 'extra' => 1]]), 'unknown field');
echo "OK: simulated monthly fee reconciliation tests passed\\n";
