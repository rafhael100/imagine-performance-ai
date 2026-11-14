<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/ChatgptAdsOfflineAdapter.php';

use Imagine\Performance\Commercial\ChatgptAdsOfflineAdapter as Adapter;

function eqChatgpt(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
}

function rejectChatgpt(callable $call, string $label): void
{
    try { $call(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected fail-closed rejection: ' . $label);
}

$tenant = 'tenant_chatgpt_lab123';
$account = 'adacct_123';
$zone = 'America/Sao_Paulo';
$month = '2026-11';

$plan = Adapter::insightsRequestPlan($account, $month, $zone);
eqChatgpt('GET', $plan['method'], 'Insights method');
eqChatgpt('/v1/ad_account/insights', $plan['path'], 'only Insights path');
eqChatgpt('chatgpt_ads', $plan['provider'], 'provider identity');
eqChatgpt(false, $plan['transport_enabled'], 'network must be off');
eqChatgpt(false, $plan['ads_write_enabled'], 'ads write disabled');
if (!str_contains($plan['query']['time_ranges[]'], '2026-11-30')) {
    throw new RuntimeException('Month boundary absent');
}
$leap = Adapter::insightsRequestPlan($account, '2028-02', $zone);
if (!str_contains($leap['query']['time_ranges[]'], '2028-02-29')) {
    throw new RuntimeException('Leap February boundary absent');
}
rejectChatgpt(fn() => Adapter::insightsRequestPlan('adacct_123/../x', $month, $zone), 'path injection');
rejectChatgpt(fn() => Adapter::insightsRequestPlan($account, '2026-13', $zone), 'invalid month');
rejectChatgpt(fn() => Adapter::insightsRequestPlan($account, $month, 'Bad/Zone'), 'unknown timezone');

$fixture = [
    'account_id' => $account,
    'currency_code' => 'BRL',
    'time_zone' => $zone,
    'complete' => true,
    'has_more' => false,
    'data' => [
        ['readable_time' => '2026-11-01', 'spend' => '40.50', 'impressions' => 1000, 'clicks' => 30],
        ['readable_time' => '2026-11-02', 'spend' => '0.05', 'impressions' => 300, 'clicks' => 5],
    ],
];
$r = Adapter::monthlyFixture($tenant, $month, $account, $zone, $fixture);
eqChatgpt(4055, $r['synthetic_account_report']['spend_cents'], 'exact BRL cents');
eqChatgpt(1300, $r['impressions'], 'summed impressions');
eqChatgpt(35, $r['clicks'], 'summed clicks');
eqChatgpt(2, $r['reported_day_count'], 'daily rows');
eqChatgpt('chatgpt_ads', $r['synthetic_account_report']['channel'], 'provider channel');
eqChatgpt(false, $r['provider_source_authenticated'], 'no false attestation');
eqChatgpt(false, $r['provider_spend_verified'], 'no false verification');
eqChatgpt(false, $r['finance_approved'], 'no automatic fee approval');
eqChatgpt(false, $r['asaas_write_enabled'], 'no charges');
eqChatgpt(false, $r['ads_write_enabled'], 'no ad mutations');

$bad = $fixture; $bad['complete'] = false;
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'partial results');
$bad = $fixture; $bad['has_more'] = true;
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'incomplete cursor');
$bad = $fixture; $bad['currency_code'] = 'USD';
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'currency mismatch');
$bad = $fixture; $bad['account_id'] = 'adacct_other';
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'account cross-contamination');
$bad = $fixture; $bad['time_zone'] = 'UTC';
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'timezone mismatch');
$bad = $fixture; $bad['data'][1]['readable_time'] = '2026-11-01';
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'duplicate day');
$bad = $fixture; $bad['data'][1]['readable_time'] = '2026-12-01';
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'outside month');
$bad = $fixture; $bad['data'][0]['spend'] = 40.5;
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'binary float');
$bad = $fixture; $bad['data'][0]['spend'] = '1.234';
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'fractional cent');
$bad = $fixture; $bad['data'][0]['spend'] = '-1.00';
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'negative spend');
$bad = $fixture; $bad['data'][0]['provider_authenticated'] = true;
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'spoofed trust flag');
$bad = $fixture; $bad['data'][0]['clicks'] = 1001;
rejectChatgpt(fn() => Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad), 'impossible clicks');
$bad = $fixture; $bad['data'] = [];
$zero = Adapter::monthlyFixture($tenant, $month, $account, $zone, $bad);
eqChatgpt(0, $zero['synthetic_account_report']['spend_cents'], 'explicit zero fixture');
eqChatgpt(false, $zero['provider_spend_verified'], 'synthetic zero not verified');

echo "OK: ChatGPT Ads Insights offline contract, exact BRL, tenant/account scope, adversarial rejects, no HTTP/charges/ads writes\n";
