<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/MonthlyFeeReconciliationV6.php';
require_once __DIR__ . '/../src/Domain/Commercial/ChannelMinimumBudgetProposal.php';

use Imagine\Performance\Commercial\MonthlyFeeReconciliationV6 as Fee;
use Imagine\Performance\Commercial\ChannelMinimumBudgetProposal as Floors;

function same($expected, $actual, string $case): void {
    if ($expected !== $actual) {
        throw new RuntimeException($case . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
function rejected(callable $callback, string $case): void {
    try { $callback(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected rejection: ' . $case);
}
$one = Fee::calculate('2026-11', ['google_ads' => ['planned_cents' => 100000, 'actual_cents' => 100000]]);
same(20000, $one['advance_fee_cents'], 'one channel advance');
same(0, $one['adjustment_cents'], 'no adjustment');
same(true, $one['simulated'], 'simulation flag');
same('requires_finance_review_no_auto_charge_or_credit', $one['settlement_status'], 'no automatic charge');
$two = Fee::calculate('2026-11', [
    'google_ads' => ['planned_cents' => 100000, 'actual_cents' => 65000],
    'meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 90000],
]);
same(34000, $two['advance_fee_cents'], 'two channels advance');
same(31000, $two['earned_fee_cents'], 'two channels earned');
same(-3000, $two['adjustment_cents'], 'positive reconciliation');
$credit = Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 0]]);
same(-14000, $credit['adjustment_cents'], 'negative adjustment does not issue credit');
$round = Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70001, 'actual_cents' => 70003]]);
same(14001, $round['earned_fee_cents'], 'half-up rounding');
rejected(fn() => Fee::calculate('2026-11', ['google_ads' => ['planned_cents' => 99999, 'actual_cents' => 90000]]), 'minimum per channel');
rejected(fn() => Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => -1]]), 'negative actual');
rejected(fn() => Fee::calculate('2026-13', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 0]]), 'month validation');
rejected(fn() => Fee::calculate('2026-11', ['other' => ['planned_cents' => 70000, 'actual_cents' => 0]]), 'unknown channel');
rejected(fn() => Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000.0, 'actual_cents' => 0]]), 'float input');
rejected(fn() => Fee::calculate('2026-11', ['meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 0, 'extra' => 1]]), 'unknown field');
// Parity with the owner-approved V6 commercial preview, including all three channels.
$quoted = Floors::preview([
    'chatgpt_ads' => 120000, 'google_ads' => 100000, 'meta_ads' => 70000,
]);
$three = Fee::calculate('2026-11', [
    'meta_ads' => ['planned_cents' => 70000, 'actual_cents' => 0],
    'google_ads' => ['planned_cents' => 100000, 'actual_cents' => 125000],
    'chatgpt_ads' => ['planned_cents' => 120000, 'actual_cents' => 100000],
]);
same($quoted['illustrative_management_fee_cents'], $three['advance_fee_cents'], 'V6 quote/fee parity');
same(290000, $three['planned_media_cents'], 'V6 trichannel floor total');
same(225000, $three['actual_media_cents'], 'V6 hypothetical actual total');
same(45000, $three['earned_fee_cents'], '20% of actual three-channel media');
same(-13000, $three['adjustment_cents'], 'manual negative adjustment no automatic credit');
same(['chatgpt_ads','google_ads','meta_ads'], array_keys($three['channels']), 'stable order');
foreach (['provider_spend_verified','finance_approved','billable','gateway_write_enabled','ads_write_enabled'] as $flag) {
    same(false, $three[$flag], 'cannot auto activate '.$flag);
}
$zero = Fee::calculate('2026-11', [
    'chatgpt_ads' => ['planned_cents' => 120000, 'actual_cents' => 0],
]);
same(0, $zero['earned_fee_cents'], 'zero is legal actual spend, below minimum');
same(-24000, $zero['adjustment_cents'], 'zero spend is not automatic refund');
rejected(fn() => Fee::calculate('2026-11', ['chatgpt_ads' => ['planned_cents' => 119999, 'actual_cents' => 0]]), 'ChatGPT lower than floor');
rejected(fn() => Fee::calculate('2026-11', ['google_ads' => ['planned_cents' => 70000, 'actual_cents' => 0]]), 'old uniform floor no longer allowed');
rejected(fn() => Fee::calculate('2026-11', []), 'no channels');
rejected(fn() => Fee::calculate('2026-11', ['chatgpt_ads' => ['planned_cents' => 120000, 'actual_cents' => 100000001]]), 'actual cap');
rejected(fn() => Fee::calculate('2026-11', ['chatgpt_ads' => ['planned_cents' => '120000', 'actual_cents' => 0]]), 'money strings forbidden');

echo "OK: V6 Meta/Google/ChatGPT monthly fee parity, no billing or ad mutations\n";
