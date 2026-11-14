<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Domain/Commercial/PerformanceBudgetRecommendation.php';
use Imagine\Performance\Commercial\PerformanceBudgetRecommendation as Budget;

function assertBudget(bool $condition,string $message):void{
    if(!$condition) throw new RuntimeException('Performance calculator parity: '.$message);
}
function denyBudget(callable $fn,string $message):void{
    try{$fn();}catch(InvalidArgumentException){return;}
    throw new RuntimeException('Expected rejection: '.$message);
}
$default=Budget::plan(['meta_ads','chatgpt_ads','google_ads']);
assertBudget($default['minimum_media_cents']===290000,'all channel minima');
assertBudget($default['recommended_baseline_cents']===430000,'all recommended baselines');
assertBudget($default['suggested_media_cents']===430000,'recommendation baseline');
assertBudget($default['illustrative_management_cents']===86000,'20 percent');
assertBudget($default['informational_total_cents']===516000,'total');
assertBudget(array_column($default['channel_rows'],'channel')===
    ['chatgpt_ads','google_ads','meta_ads'],'canonical order');

$only=Budget::plan(['chatgpt_ads']);
assertBudget($only['minimum_media_cents']===120000,'ChatGPT approved minimum');
assertBudget($only['suggested_media_cents']===180000,'ChatGPT recommended');
$google=Budget::plan(['google_ads']);
assertBudget($google['minimum_media_cents']===100000,'Google floor');
assertBudget($google['suggested_media_cents']===150000,'Google recommendation');
$meta=Budget::plan(['meta_ads']);
assertBudget($meta['minimum_media_cents']===70000,'Meta floor');
assertBudget($meta['suggested_media_cents']===100000,'Meta recommendation');
$custom=Budget::plan(['meta_ads'],'leads',30,5000);
assertBudget($custom['visitor_goal_scenario_cents']===150000,'30 x declared R$50');
assertBudget($custom['suggested_media_cents']===150000,'scenario overrides base');
assertBudget($custom['illustrative_management_cents']===30000,'20 percent customized');
assertBudget($custom['uses_visitor_supplied_cpl']===true,'declared CPL, not predicted');
$warning=Budget::plan(['chatgpt_ads'],'awareness',0,0,120000);
assertBudget($warning['exceeds_monthly_limit']===true,'warning only');
assertBudget($warning['suggested_media_cents']===180000,'warning cannot silently reduce');

$scenario=Budget::plan(['meta_ads','google_ads'],'leads',100,2500);
assertBudget($scenario['minimum_media_cents']===170000,'two-channel floor');
assertBudget($scenario['recommended_baseline_cents']===250000,'two-channel baseline');
assertBudget($scenario['suggested_media_cents']===250000,'250k scenario on baseline');
// Scenario R$3600 over 3 providers: baseline R$4300 wins.
$three=Budget::plan(['meta_ads','google_ads','chatgpt_ads'],'sales',30,12000);
assertBudget($three['visitor_goal_scenario_cents']===360000,'visitor scenario');
assertBudget($three['suggested_media_cents']===430000,'recommended baseline wins');
// Scenario R$5000 over 3: proportional R$10 rounding.
$weighted=Budget::plan(['meta_ads','google_ads','chatgpt_ads'],'leads',100,5000);
$rows=array_column($weighted['channel_rows'],'suggested_cents','channel');
assertBudget($rows['chatgpt_ads']===210000,'ChatGPT weighted 1800/4300');
assertBudget($rows['google_ads']===175000,'Google weighted 1500/4300');
assertBudget($rows['meta_ads']===117000,'Meta weighted 1000/4300');
assertBudget($weighted['suggested_media_cents']===502000,'round individual channels upwards');
foreach(['provider_spend_verified','results_guaranteed','direct_checkout_enabled',
    'crm_intake_enabled','provider_ads_write_enabled','asaas_charge_enabled'] as $flag)
    assertBudget($weighted[$flag]===false,'no side effect: '.$flag);
foreach([
  [[], 'leads', 0,0,0],
  [['bad_ads'],'leads',0,0,0],
  [['meta_ads','meta_ads'],'leads',0,0,0],
  [['meta_ads'],'unknown',0,0,0],
  [['meta_ads'],'leads',20,0,0],
  [['meta_ads'],'leads',0,2000,0],
  [['meta_ads'],'leads',100001,400,0],
  [['meta_ads'],'leads',100000,100000000,0],
  [['meta_ads'],'leads',1,-5,0],
  [['meta_ads'],'leads',0,0,-3],
  [['meta_ads'],'leads',0,0,100000001]
] as $args)denyBudget(fn()=>Budget::plan(...$args),'out of bound/cross-origin parameters');
echo "PASS: Performance budget mirrors Comercial Meta/Google/ChatGPT minima, recommended, user CPL, weights and no writes\n";
