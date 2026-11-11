<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once dirname(__DIR__,2).'/app/Performance/Planning/OfflineBudgetScenarioComparison.php';

use App\Performance\Planning\OfflineBudgetScenarioPreview as Preview;
use App\Performance\Planning\OfflineBudgetScenarioComparison as Comparison;

$n=0;
$assert=static function(bool $ok,string $why)use(&$n):void{
    ++$n;
    if(!$ok)throw new RuntimeException('F199 FAILED: '.$why);
};
$scenario=static fn(string $ref,int $cents,int $google,string $from='2026-11-12',string $to='2026-11-16'):array=>[
    'schema_version'=>Preview::SCHEMA,
    'scenario_ref'=>$ref,
    'currency'=>'BRL',
    'start_date'=>$from,
    'end_date'=>$to,
    'total_centavos'=>$cents,
    'channel_split_bp'=>['google_ads'=>$google,'meta_ads'=>10000-$google],
];
$base=$scenario('scenario-demo-baseline',10001,6000);
$alt=$scenario('scenario-demo-alternative',12003,3000);
$request=['schema_version'=>Comparison::SCHEMA,'baseline'=>$base,'alternative'=>$alt];
$result=Comparison::compare($request);
$assert($result['schema_version']===Comparison::SCHEMA
    &&$result['delta_centavos']===2002
    &&$result['period']['days']===5,
    'two F105 forecasts are compared in centavos over equal dates');
$assert($result['channel_delta_centavos']['google_ads']===-2400
    &&$result['channel_delta_centavos']['meta_ads']===4402,
    'cross-channel redistribution remains exact including negative delta');
$assert(count($result['daily'])===5
    &&array_sum(array_column($result['daily'],'delta_centavos'))===2002
    &&array_sum(array_column($result['daily'],'google_ads_delta_centavos'))===-2400
    &&array_sum(array_column($result['daily'],'meta_ads_delta_centavos'))===4402,
    'each day and total reconciles without floating point drift');
$assert($result['simulation_only']===true
    &&$result['advertiser_approval_verified']===false
    &&$result['provider_connected']===false
    &&$result['media_execution_allowed']===false
    &&$result['billing_allowed']===false
    &&$result['ready_for_production']===false,
    'comparison cannot grant advertiser, provider, billing or execution authority');
$reverse=Comparison::compare([
    'schema_version'=>Comparison::SCHEMA,
    'baseline'=>$alt,'alternative'=>$base,
]);
$assert($reverse['delta_centavos']===-2002
    &&$reverse['channel_delta_centavos']['google_ads']===2400,
    'reverse comparison is the additive inverse');
$identical=$scenario('scenario-demo-second',10001,6000);
$same=Comparison::compare([
    'schema_version'=>Comparison::SCHEMA,
    'baseline'=>$base,'alternative'=>$identical,
]);
$assert($same['delta_centavos']===0
    &&$same['channel_delta_centavos']['google_ads']===0
    &&$same['channel_delta_centavos']['meta_ads']===0,
    'identical budgets with distinct references have zero delta');
$badCases=[
    ['schema_version'=>Comparison::SCHEMA,'baseline'=>$base,'alternative'=>$base],
    ['schema_version'=>Comparison::SCHEMA,'baseline'=>$base,
     'alternative'=>$scenario('scenario-demo-diff-day',12003,4000,'2026-11-13','2026-11-16')],
    ['schema_version'=>'fake','baseline'=>$base,'alternative'=>$alt],
    ['schema_version'=>Comparison::SCHEMA,'baseline'=>$base,'alternative'=>$alt,'approved'=>true],
    ['schema_version'=>Comparison::SCHEMA,'baseline'=>$base,'alternative'=>[]],
    ['schema_version'=>Comparison::SCHEMA,'baseline'=>$base,'alternative'=>array_replace($alt,['total_centavos'=>1.50])],
    ['schema_version'=>Comparison::SCHEMA,'baseline'=>$base,'alternative'=>array_replace($alt,['currency'=>'USD'])],
];
foreach($badCases as $bad){
    try{
        Comparison::compare($bad);
        $assert(false,'unsafe, malformed or mismatched contract must throw');
    }catch(DomainException){
        $assert(true,'invalid comparison rejected');
    }
}
// Centavo remainder, no divide-by-zero, many days and exact bounds.
$min=Comparison::compare([
    'schema_version'=>Comparison::SCHEMA,
    'baseline'=>$scenario('scenario-demo-one',1,1),
    'alternative'=>$scenario('scenario-demo-two',3,9999),
]);
$assert($min['delta_centavos']===2
    &&array_sum(array_column($min['daily'],'delta_centavos'))===2,
    'sub-real centavo remainder never disappears');
echo "F199 offline budget comparison: {$n} PASS / 0 FAIL; NO ADS / NO BILLING\n";
