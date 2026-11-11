<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
require_once $root.'/app/Performance/Planning/OfflineBudgetScenarioPreview.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioBudgetRollup.php';
require_once $root.'/app/Performance/Planning/OfflinePortfolioBudgetCsvExporter.php';

use App\Performance\Planning\OfflineBudgetScenarioPreview as Preview;
use App\Performance\Planning\OfflinePortfolioBudgetRollup as Rollup;
use App\Performance\Planning\OfflinePortfolioBudgetCsvExporter as Csv;

$n=0;
$assert=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F203 FAIL: '.$why);
};
$scenario=static fn(string $name,int $total,int $google):array=>[
 'schema_version'=>Preview::SCHEMA,
 'scenario_ref'=>$name,
 'currency'=>'BRL',
 'start_date'=>'2026-11-12','end_date'=>'2026-11-16',
 'total_centavos'=>$total,
 'channel_split_bp'=>['google_ads'=>$google,'meta_ads'=>10000-$google],
];
$portfolio=['schema_version'=>Rollup::SCHEMA,'scenarios'=>[
 $scenario('scenario-demo-beta',99,3000),
 $scenario('scenario-demo-alpha',101,5000),
]];
$req=['schema_version'=>Csv::SCHEMA,'portfolio_request'=>$portfolio];
$one=Csv::render($req);
$assert($one['filename']==='performance-offline-portfolio-demo.csv'
 &&$one['content_type']==='text/csv; charset=utf-8'
 &&$one['row_count']===9,
 'CSV reports header, portfolio, two scenarios and five days');
$assert(hash('sha256',$one['csv'])===$one['sha256'],
 'CSV checksum matches reproducible bytes');
$lines=explode("\n",trim($one['csv']));
$assert(count($lines)===9
 &&$lines[0]==='kind,reference,date,google_ads_centavos,meta_ads_centavos,total_centavos'
 &&$lines[1]==='portfolio,synthetic-aggregate,,79,121,200',
 'CSV header and aggregate totals reconcile exactly');
$assert($lines[2]==='scenario,scenario-demo-alpha,,50,51,101'
 &&$lines[3]==='scenario,scenario-demo-beta,,29,70,99'
 &&$lines[4]==='daily,synthetic-aggregate,2026-11-12,',
 'CSV has only demonstrative canonical scenario refs, sorted');
$reverse=Csv::render(array_replace($req,[
 'portfolio_request'=>array_replace($portfolio,[
  'scenarios'=>array_reverse($portfolio['scenarios'])])
]));
$assert($reverse===$one,
 'export is byte-for-byte stable irrespective of scenario order');
$assert($one['simulation_only']===true
 &&$one['contains_real_customer_data']===false
 &&$one['provider_connected']===false
 &&$one['media_execution_allowed']===false
 &&$one['billing_allowed']===false
 &&$one['ready_for_production']===false,
 'export never certifies operational spend, provider or billing');
$attack=array_replace($portfolio,[
 'scenarios'=>[$scenario('=HYPERLINK("evil")',1,10000)]
]);
foreach([
 array_replace($req,['portfolio_request'=>$attack]),
 array_replace($req,['schema_version'=>'unknown']),
 array_replace($req,['is_approved'=>true]),
 array_replace($req,['portfolio_request'=>[]]),
 array_replace($req,['portfolio_request'=>array_replace($portfolio,[
  'scenarios'=>array_merge($portfolio['scenarios'],[$portfolio['scenarios'][0]])
 ])]),
] as $bad){
 try{Csv::render($bad);$assert(false,'bad CSV request must throw');}
 catch(DomainException){$assert(true,'invalid CSV request rejected');}
}
$assert(!str_contains($one['csv'],'HYPERLINK(')
 &&!str_contains($one['csv'],'http://')
 &&!str_contains($one['csv'],'https://'),
 'output cannot include formula injection or arbitrary external URLs');
echo "F203 in-memory portfolio CSV: {$n} PASS / 0 FAIL — NO REAL ADS\n";
