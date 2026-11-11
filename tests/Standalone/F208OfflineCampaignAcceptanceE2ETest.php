<?php
declare(strict_types=1);

/** F208: full offline demonstration parity with the original CLI programs. */
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F208_OFFLINE_E2E_LAB')!=='1'||$argc!==3){
 throw new RuntimeException('F208 only on isolated CLI with two output files');
}
$root=dirname(__DIR__,2);
foreach([
 'OfflineBudgetScenarioPreview.php',
 'OfflineBudgetPacingMonitor.php',
 'OfflineBudgetPacingAlerts.php',
 'OfflinePortfolioBudgetRollup.php',
 'OfflinePortfolioBudgetCsvExporter.php',
 'OfflinePortfolioHtmlPreview.php',
 'OfflinePortfolioArtifactManifest.php',
] as $name)require_once $root.'/app/Performance/Planning/'.$name;

use App\Performance\Planning\OfflineBudgetPacingAlerts as Alerts;
use App\Performance\Planning\OfflineBudgetPacingMonitor as Pacing;
use App\Performance\Planning\OfflinePortfolioBudgetRollup as Rollup;
use App\Performance\Planning\OfflinePortfolioBudgetCsvExporter as Csv;
use App\Performance\Planning\OfflinePortfolioHtmlPreview as Html;
use App\Performance\Planning\OfflinePortfolioArtifactManifest as Manifest;

$n=0;
$assert=static function(bool $ok,string $why)use(&$n):void{
 ++$n;
 if(!$ok)throw new RuntimeException('F208 FAIL: '.$why);
};
$runner=realpath((string)getenv('RUNNER_TEMP'));
$assert(is_string($runner),'runner temp exists');
$read=static function(string $file,string $extension)use($runner):string{
 if(dirname($file)!==$runner
   ||basename($file)!=='f208-cli.'.$extension
   ||is_link($file)||!is_file($file)
   ||realpath($file)!==$file
   ||filesize($file)<100||filesize($file)>150_000){
   throw new RuntimeException('Unsafe CLI artifact reference');
 }
 return (string)file_get_contents($file);
};
$input=static function(string $path):array {
 $bytes=file_get_contents($path);
 if(!is_string($bytes)||strlen($bytes)>20_000){
  throw new RuntimeException('Missing or oversized fixture');
 }
 $value=json_decode($bytes,true,20,JSON_THROW_ON_ERROR);
 if(!is_array($value))throw new RuntimeException('Invalid fixture');
 return $value;
};
$csvReq=$input($root.'/contracts/demo/f204-offline-portfolio-request.json');
$htmlReq=$input($root.'/contracts/demo/f207-offline-portfolio-html-request.json');
$assert($csvReq['portfolio_request']===$htmlReq['portfolio_request'],
 'CLI fixtures agree on exact synthetic campaigns, dates and budgets');
$portfolio=Rollup::aggregate($csvReq['portfolio_request']);
$assert($portfolio['scenario_count']===2
 &&$portfolio['total_centavos']===200
 &&$portfolio['channel_totals_centavos']['google_ads']===79
 &&$portfolio['channel_totals_centavos']['meta_ads']===121,
 'Google and Meta budgets reconcile across the same two scenarios');
$csv=Csv::render($csvReq);
$html=Html::render($htmlReq);
$manifestReq=['schema_version'=>Manifest::SCHEMA,
 'portfolio_request'=>$csvReq['portfolio_request']];
$manifest=Manifest::build($manifestReq);
$assert(hash_equals($manifest['csv_sha256'],$csv['sha256'])
 &&hash_equals($manifest['html_sha256'],$html['html_sha256'])
 &&Manifest::matches($manifestReq,$manifest),
 'F206 manifest is consistent with F202/F203/F205 renderers');
$assert(hash_equals(hash('sha256',$read($argv[1],'csv')),$csv['sha256'])
 &&$read($argv[1],'csv')===$csv['csv'],
 'F204 executable CLI CSV is byte-for-byte identical to F203 in memory');
$assert(hash_equals(hash('sha256',$read($argv[2],'html')),$html['html_sha256'])
 &&$read($argv[2],'html')===$html['html'],
 'F207 executable CLI HTML is byte-for-byte identical to F205 in memory');
$assert($csv['media_execution_allowed']===false
 &&$html['media_execution_allowed']===false
 &&$manifest['media_execution_allowed']===false
 &&$portfolio['media_execution_allowed']===false,
 'no exported artifact can be interpreted as live media authorization');
$scenario=$csvReq['portfolio_request']['scenarios'][0];
$alerts=Alerts::evaluate([
 'schema_version'=>Alerts::SCHEMA,
 'pacing_input'=>[
  'schema_version'=>Pacing::SCHEMA,
  'scenario'=>$scenario,
  'source'=>'synthetic_fixture_only',
  'observations'=>[[
   'date'=>'2026-11-12','google_ads_centavos'=>19,'meta_ads_centavos'=>11
  ]],
 ],
 'over_tolerance_bp'=>200,'under_tolerance_bp'=>200
]);
$assert($alerts['status']==='synthetic_above_tolerance'
 &&$alerts['media_execution_allowed']===false
 &&$alerts['automatic_pause_allowed']===false
 &&$alerts['notification_delivery_allowed']===false,
 'F201 notices simulate overrun but cannot pause or send alerts');
$changed=$csvReq['portfolio_request'];
$changed['scenarios'][0]['total_centavos']+=1;
$assert(!Manifest::matches([
 'schema_version'=>Manifest::SCHEMA,'portfolio_request'=>$changed
],$manifest),'revised source centavo invalidates original artifact evidence');
$assert(!preg_match('/<(?:script|iframe|form|a|img)\b/i',$html['html'])
 &&str_contains($html['html'],'DEMONSTRAÇÃO NÃO HOMOLOGADA')
 &&!str_contains($html['html'],'https://'),
 'static HTML contains visible warning and no executable or remote controls');
echo "F208 end-to-end offline campaign demonstration: {$n} PASS / 0 FAIL — NO-GO\n";
