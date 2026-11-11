<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
require_once $root.'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioBudgetRollup.php';

use App\Performance\Planning\OfflineBudgetScenarioPreview as Preview;
use App\Performance\Planning\OfflinePortfolioBudgetRollup as Rollup;

$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
    ++$n;if(!$pass)throw new RuntimeException("F202 FAIL: ".$why);
};
$scenario=static fn(string $id,int $amount,int $google,string $start='2026-11-12',string $end='2026-11-16'):array=>[
    'schema_version'=>Preview::SCHEMA,
    'scenario_ref'=>$id,
    'currency'=>'BRL',
    'start_date'=>$start,'end_date'=>$end,
    'total_centavos'=>$amount,
    'channel_split_bp'=>['google_ads'=>$google,'meta_ads'=>10000-$google],
];
$a=$scenario('scenario-demo-alpha',101,5000);
$b=$scenario('scenario-demo-beta',99,3000);
$req=['schema_version'=>Rollup::SCHEMA,'scenarios'=>[$b,$a]];
$res=Rollup::aggregate($req);
$check($res['scenario_count']===2
    &&$res['total_centavos']===200
    &&$res['channel_totals_centavos']['google_ads']===79
    &&$res['channel_totals_centavos']['meta_ads']===121,
    'aggregate reconciles two BRL campaign budget previews exactly');
$check(array_column($res['daily'],'total_centavos')===[41,40,40,40,39]
    &&array_sum(array_column($res['daily'],'google_ads_centavos'))===79
    &&array_sum(array_column($res['daily'],'meta_ads_centavos'))===121,
    'daily centavo allocation and channel totals agree exactly');
$check(array_column($res['scenarios'],'scenario_ref')===
    ['scenario-demo-alpha','scenario-demo-beta'],
    'scenario summaries remain sorted regardless of input ordering');
$rev=Rollup::aggregate(array_replace($req,['scenarios'=>[$a,$b]]));
$check($rev===$res,'input order cannot alter a portfolio aggregation');
$check($res['simulation_only']===true
    &&$res['advertiser_approval_verified']===false
    &&$res['provider_connected']===false
    &&$res['media_execution_allowed']===false
    &&$res['automatic_pause_allowed']===false
    &&$res['billing_allowed']===false
    &&$res['ready_for_production']===false,
    'multi campaign aggregation cannot create authority to spend');
$single=Rollup::aggregate(array_replace($req,['scenarios'=>[$a]]));
$check($single['scenario_count']===1 &&$single['total_centavos']===101
    &&$single['daily'][0]['total_centavos']===21,
    'single scenario remains canonical F105 projection');
$large=Rollup::aggregate(array_replace($req,[
    'scenarios'=>array_map(static fn(int $i):array=>$scenario(
        'scenario-demo-scale-'.$i,2_000_000_000,5000),range(1,20))
]));
$check($large['total_centavos']===40_000_000_000
    &&$large['channel_totals_centavos']['google_ads']===20_000_000_000,
    'max 20 scenarios aggregate safely as signed 64-bit integers');
$invalid=[
 array_replace($req,['scenarios'=>[]]),
 array_replace($req,['scenarios'=>[$a,$a]]),
 array_replace($req,['scenarios'=>[$a,$scenario('scenario-demo-other',20,5000,'2026-11-13','2026-11-16')]]),
 array_replace($req,['scenarios'=>array_fill(0,21,$a)]),
 array_replace($req,['scenarios'=>['x']]),
 array_replace($req,['scenarios'=>[$a,array_replace($b,['currency'=>'USD'])]]),
 array_replace($req,['scenarios'=>[$a,array_replace($b,['total_centavos'=>12.5])]]),
 array_replace($req,['is_approved'=>true]),
 array_replace($req,['schema_version'=>'unsupported']),
];
foreach($invalid as $bad){
    try{Rollup::aggregate($bad);$check(false,'invalid input must be denied');}
    catch(DomainException){$check(true,'invalid input was rejected');}
}
echo "F202 offline multi campaign portfolio: {$n} PASS / 0 FAIL — NO ADS\n";
