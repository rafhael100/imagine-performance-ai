<?php
declare(strict_types=1);
/**
 * Strictly a CLI-only fixture interpreter. Called by GitHub CI's local Node
 * parity checker. No network, provider keys, DB, billing or personal data.
 */
require_once __DIR__.'/../../src/Domain/Commercial/PerformanceBudgetRecommendation.php';
use Imagine\Performance\Commercial\PerformanceBudgetRecommendation as Budget;
if (PHP_SAPI !== 'cli' || $argc !== 1) {
    fwrite(STDERR,"CLI-only calculator parity\n");exit(2);
}
$source=stream_get_contents(STDIN,65537);
if(!is_string($source) || strlen($source)>65536 || strlen($source)<2) {
    fwrite(STDERR,"Invalid fixture bytes\n");exit(2);
}
$cases=json_decode($source,true,12,JSON_THROW_ON_ERROR);
if(!is_array($cases)||!array_is_list($cases)||count($cases)<1||count($cases)>32) {
    fwrite(STDERR,"Invalid fixture cases\n");exit(2);
}
$out=[];
foreach($cases as $case){
    if(!is_array($case)||array_keys($case)!==[
      'channels','objective','targetLeads','historicalCplCents','monthlyLimitCents'
    ]||!is_array($case['channels'])||!is_string($case['objective'])||
    !is_int($case['targetLeads'])||!is_int($case['historicalCplCents'])||
    !is_int($case['monthlyLimitCents'])){
        fwrite(STDERR,"Invalid fixture shape\n");exit(2);
    }
    $r=Budget::plan(
        $case['channels'],$case['objective'],
        $case['targetLeads'],$case['historicalCplCents'],$case['monthlyLimitCents']
    );
    $out[]=[
      'minimum'=>$r['minimum_media_cents'],
      'baseline'=>$r['recommended_baseline_cents'],
      'goal'=>$r['visitor_goal_scenario_cents'],
      'suggested'=>$r['suggested_media_cents'],
      'fee'=>$r['illustrative_management_cents'],
      'total'=>$r['informational_total_cents'],
      'warning'=>$r['exceeds_monthly_limit'],
      'usesVisitorCpl'=>$r['uses_visitor_supplied_cpl'],
      'channels'=>array_map(static fn(array $item)=>[
        'id'=>$item['channel'],
        'floor'=>$item['minimum_cents'],
        'recommended'=>$item['recommended_start_cents'],
        'suggested'=>$item['suggested_cents'],
      ],$r['channel_rows']),
      'noCheckout'=>$r['direct_checkout_enabled']===false,
      'noAdsWrites'=>$r['provider_ads_write_enabled']===false,
      'noCharge'=>$r['asaas_charge_enabled']===false,
    ];
}
echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
