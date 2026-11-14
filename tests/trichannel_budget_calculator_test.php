<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/Commercial/ChannelMinimumBudgetProposal.php';
require_once __DIR__ . '/../src/Domain/Commercial/TrichannelBudgetCalculator.php';

use Imagine\Performance\Commercial\TrichannelBudgetCalculator as Calculator;

function sameCalculator(mixed $want, mixed $got, string $test): void
{
    if ($want !== $got) throw new RuntimeException('Calculator failed: ' . $test .
        ' (want ' . var_export($want, true) . ' got ' . var_export($got, true) . ')');
}
function deniedCalculator(callable $call, string $label): void
{
    try { $call(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Calculator accepted invalid input: ' . $label);
}

$result = Calculator::estimate('2026-11', 30, [
    'meta_ads'=>12500, 'google_ads'=>25000, 'chatgpt_ads'=>10000,
]);
sameCalculator(['chatgpt_ads','google_ads','meta_ads'], array_keys($result['channels']), 'stable order');
sameCalculator(10,$result['channels']['chatgpt_ads']['desired_leads_allocated'],'equal allocation ChatGPT');
sameCalculator(10,$result['channels']['google_ads']['desired_leads_allocated'],'equal allocation Google');
sameCalculator(10,$result['channels']['meta_ads']['desired_leads_allocated'],'equal allocation Meta');
sameCalculator(290000,$result['proposed_monthly_floor_total_cents'],'proposed floors BRL 2900');
sameCalculator(555000,$result['suggested_monthly_media_total_cents'],'recommended media BRL 5550');
sameCalculator(111000,$result['illustrative_fee_on_suggested_media_cents'],'20 percent estimated fee BRL 1110');
sameCalculator(666000,$result['illustrative_total_separate_outlay_cents'],'media + separate fee BRL 6660');
sameCalculator(38,$result['illustrative_capacity_leads_at_assumed_cpl'],'illustrative leads sum');
sameCalculator(false,$result['leads_or_results_guaranteed'],'no leads guaranteed');
sameCalculator(false,$result['portal_catalog_approved'],'not Portal canonical offer');
sameCalculator(false,$result['crm_lead_saved'],'no fake CRM capture');
sameCalculator(false,$result['personal_data_collected'],'no PII');
sameCalculator(false,$result['asaas_write_enabled'],'no Asaas');
sameCalculator(false,$result['ads_write_enabled'],'no Ads');

$reversed=Calculator::estimate('2026-11',30,[
    'chatgpt_ads'=>10000,'google_ads'=>25000,'meta_ads'=>12500
]);
sameCalculator($result,$reversed,'input order invariant');

$odd=Calculator::estimate('2026-11',5,['google_ads'=>12500,'meta_ads'=>12500]);
sameCalculator(3,$odd['channels']['google_ads']['desired_leads_allocated'],'remainder allocated alphabetically');
sameCalculator(2,$odd['channels']['meta_ads']['desired_leads_allocated'],'other channel');
sameCalculator(150000,$odd['channels']['google_ads']['suggested_monthly_media_cents'],'recommended reference dominates');
sameCalculator(100000,$odd['channels']['meta_ads']['suggested_monthly_media_cents'],'Meta reference dominates');

$single=Calculator::estimate('2026-11',1,['chatgpt_ads'=>1]);
sameCalculator(180000,$single['suggested_monthly_media_total_cents'],'recommended reference floor');
sameCalculator(36000,$single['illustrative_fee_on_suggested_media_cents'],'20 percent fee');

deniedCalculator(fn()=>Calculator::estimate('2026-13',30,['google_ads'=>10000]),'invalid month');
deniedCalculator(fn()=>Calculator::estimate('1999-01',30,['google_ads'=>10000]),'year below range');
deniedCalculator(fn()=>Calculator::estimate('2026-11',0,['google_ads'=>10000]),'zero leads');
deniedCalculator(fn()=>Calculator::estimate('2026-11',3001,['google_ads'=>10000]),'excessive leads');
deniedCalculator(fn()=>Calculator::estimate('2026-11',30,[]),'no channel');
deniedCalculator(fn()=>Calculator::estimate('2026-11',30,['linkedin_ads'=>10000]),'unsupported channel');
deniedCalculator(fn()=>Calculator::estimate('2026-11',30,['google_ads'=>0]),'CPL zero');
deniedCalculator(fn()=>Calculator::estimate('2026-11',30,['google_ads'=>-1]),'CPL negative');
deniedCalculator(fn()=>Calculator::estimate('2026-11',30,['meta_ads'=>10.25]),'float CPL');
deniedCalculator(fn()=>Calculator::estimate('2026-11',30,['meta_ads'=>'1025']),'string CPL');
deniedCalculator(fn()=>Calculator::estimate('2026-11',30,['google_ads'=>10000001]),'CPL too large');
deniedCalculator(fn()=>Calculator::estimate('2026-11',3000,['google_ads'=>10000000]),'target exceeds monthly cap');

echo "OK: 3-channel customer-supplied CPL, budget floors/recommendations, fair lead allocation, 20% separate fee, no PII/payments/Ads\n";
