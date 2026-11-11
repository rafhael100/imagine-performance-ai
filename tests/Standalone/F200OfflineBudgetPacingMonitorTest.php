<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once dirname(__DIR__,2).'/app/Performance/Planning/OfflineBudgetPacingMonitor.php';

use App\Performance\Planning\OfflineBudgetScenarioPreview as Preview;
use App\Performance\Planning\OfflineBudgetPacingMonitor as Monitor;
$n=0;
$check=static function(bool $value,string $why)use(&$n):void{
    ++$n;
    if(!$value)throw new RuntimeException('F200 FAILED '.$why);
};
$scenario=[
 'schema_version'=>Preview::SCHEMA,
 'scenario_ref'=>'scenario-demo-pacing',
 'currency'=>'BRL',
 'start_date'=>'2026-11-12',
 'end_date'=>'2026-11-16',
 'total_centavos'=>101,
 'channel_split_bp'=>['google_ads'=>5000,'meta_ads'=>5000],
];
$ob=[
 ['date'=>'2026-11-12','google_ads_centavos'=>15,'meta_ads_centavos'=>10],
 ['date'=>'2026-11-13','google_ads_centavos'=>3,'meta_ads_centavos'=>5],
 ['date'=>'2026-11-14','google_ads_centavos'=>13,'meta_ads_centavos'=>12],
];
$req=['schema_version'=>Monitor::SCHEMA,
 'scenario'=>$scenario,'source'=>'synthetic_fixture_only',
 'observations'=>$ob];
$res=Monitor::analyze($req);
$check($res['observed_days']===3
 &&$res['observed_total_centavos']===58
 &&$res['cumulative_planned_centavos']===61
 &&$res['cumulative_delta_centavos']===-3,
 'prefix days reconcile hypothetical observed and projected centavos');
$check($res['daily'][0]['daily_delta_centavos']===4
 &&$res['daily'][0]['cumulative_above_plan']===true
 &&$res['daily'][1]['cumulative_above_plan']===false
 &&$res['daily'][2]['cumulative_delta_centavos']===-3,
 'pacing crosses above/below planned threshold without floating point');
$check($res['observed_channel_totals_centavos']['google_ads']===31
 &&$res['observed_channel_totals_centavos']['meta_ads']===27
 &&$res['total_budget_exceeded']===false,
 'channel observed totals reconcile and do not imply real budget execution');
$check($res['simulation_only']===true
 &&$res['provider_connected']===false
 &&$res['advertiser_approval_verified']===false
 &&$res['media_execution_allowed']===false
 &&$res['automatic_pause_allowed']===false
 &&$res['billing_allowed']===false
 &&$res['ready_for_production']===false,
 'offline monitoring never grants real provider, approval or billing actions');
$empty=Monitor::analyze(array_replace($req,['observations'=>[]]));
$check($empty['observed_days']===0
 &&$empty['observed_total_centavos']===0
 &&$empty['cumulative_above_plan']===false,
 'zero observations produce an explicit zero-state');
$big=Monitor::analyze(array_replace($req,[
 'observations'=>[
  ['date'=>'2026-11-12','google_ads_centavos'=>102,'meta_ads_centavos'=>0]
 ]
]));
$check($big['total_budget_exceeded']===true
 &&$big['cumulative_above_plan']===true
 &&$big['automatic_pause_allowed']===false,
 'synthetic overspend signals do not automatically pause ads');
$invalid=[
 array_replace($req,['source'=>'google_ads_live']),
 array_replace($req,['observations'=>[$ob[1]]]),
 array_replace($req,['observations'=>[$ob[0],$ob[0]]]),
 array_replace($req,['observations'=>[array_replace($ob[0],['google_ads_centavos'=>-1])]]),
 array_replace($req,['observations'=>[array_replace($ob[0],['google_ads_centavos'=>1.25])]]),
 array_replace($req,['observations'=>[array_replace($ob[0],['unexpected'=>true])]]),
 array_replace($req,['observations'=>array_fill(0,6,$ob[0])]),
 array_replace($req,['schema_version'=>'unsupported']),
 array_replace($req,['scenario'=>array_replace($scenario,['currency'=>'USD'])]),
 array_replace($req,['is_live'=>true]),
];
foreach($invalid as $bad){
    try{
        Monitor::analyze($bad);
        $check(false,'invalid or potentially live usage must be denied');
    }catch(DomainException){
        $check(true,'invalid pacing rejected');
    }
}
echo "F200 offline pacing monitor: {$n} PASS / 0 FAIL; NO PROVIDER / NO PAUSE\n";
