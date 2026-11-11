<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
require_once $root.'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once $root.'/app/Performance/Planning/OfflineBudgetPacingMonitor.php';
require_once $root.'/app/Performance/Planning/OfflineBudgetPacingAlerts.php';

use App\Performance\Planning\OfflineBudgetScenarioPreview as Preview;
use App\Performance\Planning\OfflineBudgetPacingAlerts as Alerts;

$n=0;
$expect = static function(bool $ok, string $why) use (&$n): void {
    ++$n;
    if(!$ok) throw new RuntimeException("F201 FAIL: ".$why);
};
$scenario=[
 'schema_version'=>Preview::SCHEMA,'scenario_ref'=>'scenario-demo-alerts',
 'currency'=>'BRL','start_date'=>'2026-11-12','end_date'=>'2026-11-16',
 'total_centavos'=>101,
 'channel_split_bp'=>['google_ads'=>6000,'meta_ads'=>4000],
];
$obs=[
 ['date'=>'2026-11-12','google_ads_centavos'=>15,'meta_ads_centavos'=>10],
 ['date'=>'2026-11-13','google_ads_centavos'=>3,'meta_ads_centavos'=>5],
 ['date'=>'2026-11-14','google_ads_centavos'=>13,'meta_ads_centavos'=>12],
];
$input=[
 'schema_version'=>Alerts::SCHEMA,
 'pacing_input'=>[
  'schema_version'=>'perf.planning.offline-pacing.synthetic.v1',
  'scenario'=>$scenario,'source'=>'synthetic_fixture_only',
  'observations'=>$obs
 ],
 'over_tolerance_bp'=>500,'under_tolerance_bp'=>100,
];
$under=Alerts::evaluate($input);
$expect($under['status']==='synthetic_below_tolerance'
 &&$under['delta_centavos']===-3
 &&$under['last_observed_date']==='2026-11-14'
 &&$under['alerts']===['synthetic_cumulative_under_tolerance'],
 'under delivery is signaled on exact planned cumulative amount');
$expect($under['requires_human_review_demo']===true
 &&$under['media_execution_allowed']===false
 &&$under['notification_delivery_allowed']===false
 &&$under['automatic_pause_allowed']===false
 &&$under['billing_allowed']===false
 &&$under['ready_for_production']===false,
 'synthetic threshold never executes a provider, alert or billing');
$quiet=Alerts::evaluate(array_replace($input,['under_tolerance_bp'=>500]));
$expect($quiet['status']==='synthetic_within_tolerance'
 &&$quiet['alerts']===[] &&$quiet['requires_human_review_demo']===false,
 'tolerance suppresses a synthetic under-run');
$aboveObs=[
 ['date'=>'2026-11-12','google_ads_centavos'=>24,'meta_ads_centavos'=>18],
];
$over=Alerts::evaluate(array_replace($input,[
 'pacing_input'=>array_replace($input['pacing_input'],['observations'=>$aboveObs]),
 'over_tolerance_bp'=>500,
]));
$expect($over['status']==='synthetic_above_tolerance'
 &&$over['delta_centavos']===21
 &&$over['alerts']===['synthetic_cumulative_over_tolerance'],
 'synthetic over-pacing is flagged above allowed bp');
$exact=Alerts::evaluate(array_replace($input,[
 'pacing_input'=>array_replace($input['pacing_input'],[
  'observations'=>[['date'=>'2026-11-12',
   'google_ads_centavos'=>12,'meta_ads_centavos'=>10]],
 ]),
 'over_tolerance_bp'=>0,
]));
$expect($exact['status']==='synthetic_above_tolerance'
 &&$exact['delta_centavos']===1,
 '0bp tolerance catches one-centavo synthetic excess');
$overBudget=Alerts::evaluate(array_replace($input,[
 'pacing_input'=>array_replace($input['pacing_input'],[
  'observations'=>[['date'=>'2026-11-12',
   'google_ads_centavos'=>102,'meta_ads_centavos'=>0]]
 ])
]));
$expect($overBudget['status']==='synthetic_total_exceeded'
 &&$overBudget['alerts']===['synthetic_total_above_planned_budget',
  'synthetic_cumulative_over_tolerance'],
 'total hypothetical budget breach takes priority without execution');
$none=Alerts::evaluate(array_replace($input,[
 'pacing_input'=>array_replace($input['pacing_input'],['observations'=>[]]),
]));
$expect($none['status']==='insufficient_synthetic_data'
 &&$none['last_observed_date']===null &&$none['alerts']===[],
 'empty observations never generate an invented notification');
$invalid=[
 array_replace($input,['over_tolerance_bp'=>10001]),
 array_replace($input,['under_tolerance_bp'=>-1]),
 array_replace($input,['over_tolerance_bp'=>1.2]),
 array_replace($input,['over_tolerance_bp'=>'500']),
 array_replace($input,['approved_by'=>'operator']),
 array_replace($input,['schema_version'=>'other']),
 array_replace($input,['pacing_input'=>[]]),
 array_replace($input,['pacing_input'=>array_replace($input['pacing_input'],['source'=>'google_live'])]),
];
foreach($invalid as $bad){
 try{ Alerts::evaluate($bad);$expect(false,'unsafe input must fail');}
 catch(DomainException){$expect(true,'unsafe or invalid inputs rejected');}
}
echo "F201 synthetic pacing alerts: {$n} PASS / 0 FAIL; NEVER SEND OR PAUSE\n";
