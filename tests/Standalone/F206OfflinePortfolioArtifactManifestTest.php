<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
foreach([
 'OfflineBudgetScenarioPreview.php',
 'OfflinePortfolioBudgetRollup.php',
 'OfflinePortfolioBudgetCsvExporter.php',
 'OfflinePortfolioHtmlPreview.php',
 'OfflinePortfolioArtifactManifest.php',
] as $path)require_once $root.'/app/Performance/Planning/'.$path;

use App\Performance\Planning\OfflineBudgetScenarioPreview as Preview;
use App\Performance\Planning\OfflinePortfolioBudgetRollup as Rollup;
use App\Performance\Planning\OfflinePortfolioArtifactManifest as Manifest;

$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F206 FAIL: '.$why);
};
$scenario=static fn(string $id,int $cents,int $google):array=>[
 'schema_version'=>Preview::SCHEMA,'scenario_ref'=>$id,'currency'=>'BRL',
 'start_date'=>'2026-11-12','end_date'=>'2026-11-16',
 'total_centavos'=>$cents,
 'channel_split_bp'=>['google_ads'=>$google,'meta_ads'=>10000-$google],
];
$one=$scenario('scenario-demo-one',101,5000);
$two=$scenario('scenario-demo-two',99,3000);
$req=['schema_version'=>Manifest::SCHEMA,'portfolio_request'=>[
 'schema_version'=>Rollup::SCHEMA,'scenarios'=>[$one,$two],
]];
$manifest=Manifest::build($req);
$check($manifest['schema_version']===Manifest::SCHEMA
 &&preg_match('/^perf-demo-[a-f0-9]{24}$/D',$manifest['manifest_ref'])===1
 &&$manifest['scenario_count']===2
 &&$manifest['total_centavos']===200,
 'deterministic bundle manifest contains version, refs and exact totals');
foreach(['source_sha256','csv_sha256','html_sha256','bundle_sha256'] as $key){
 $check(preg_match('/^[a-f0-9]{64}$/D',$manifest[$key])===1,
  'source/CSV/HTML evidence is represented as SHA256 digest');
}
$check(Manifest::matches($req,$manifest)===true,
 'valid byte-identical input reproduces matching digest manifest');
$swapped=array_replace($req,['portfolio_request'=>array_replace(
 $req['portfolio_request'],['scenarios'=>[$two,$one]]
)]);
$check(Manifest::build($swapped)===$manifest
 &&Manifest::matches($swapped,$manifest)===true,
 'scenario ordering does not alter canonical exported artifacts');
$changed=$scenario('scenario-demo-two',100,3000);
$changedRequest=array_replace($req,['portfolio_request'=>array_replace(
 $req['portfolio_request'],['scenarios'=>[$one,$changed]]
)]);
$check(Manifest::matches($changedRequest,$manifest)===false,
 'even one changed centavo invalidates all saved evidence');
$altered=$manifest;
$altered['csv_sha256']=str_repeat('0',64);
$check(Manifest::matches($req,$altered)===false,
 'forged CSV hash cannot validate against recomputed artifact');
$altered=$manifest;
$altered['advertiser_approval_verified']=true;
$check(Manifest::matches($req,$altered)===false,
 'manifest cannot be misrepresented as advertiser approval');
$altered=$manifest;
$altered['extra_field']='approved';
$check(Manifest::matches($req,$altered)===false,
 'unknown manifest fields invalidate verification');
$check($manifest['simulation_only']===true
 &&$manifest['artifact_signature_verified']===false
 &&$manifest['independent_authority_verified']===false
 &&$manifest['advertiser_approval_verified']===false
 &&$manifest['media_execution_allowed']===false
 &&$manifest['billing_allowed']===false
 &&$manifest['ready_for_production']===false,
 'local digests are not a cryptographic external approval or spend authority');
$check(Manifest::matches(['schema_version'=>'fake'],[])===false,
 'invalid candidate returns false without disclosure or exception');
echo "F206 offline bundle digest: {$n} PASS / 0 FAIL — UNTRUSTED MANIFEST\n";
