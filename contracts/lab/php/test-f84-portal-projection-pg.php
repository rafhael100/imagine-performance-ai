<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('RUN_PERF_F22_PG_LAB')!=='1'
   ||getenv('PERF_F28_APP_READ_LAB')!=='1'
   ||getenv('PERF_F84_WORKBENCH_LAB')!=='1'
   ||getenv('PGHOST')!=='127.0.0.1'
   ||getenv('PGPORT')!=='5432'
   ||getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F84 only runs on disposable PostgreSQL CI');
}
require_once dirname(__DIR__,3).'/vendor/autoload.php';
use App\Performance\ReadSide\SyntheticCampaignWorkbenchReader;
use App\Performance\ReadSide\SyntheticWorkbenchScope;
use App\Performance\Contracts\PortalWorkbenchOfflineProjection;

$pdo=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    'perf_f28_app_reader','ci-f28-app-read-only',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
$scope=SyntheticWorkbenchScope::forSyntheticLabOnly();
$original=(new SyntheticCampaignWorkbenchReader($pdo))->read($scope);
if(!is_array($original))throw new RuntimeException('F84 F28 PostgreSQL projection unavailable');
$projector=new PortalWorkbenchOfflineProjection();
$count=0;
$assert=static function(bool $ok,string $reason)use(&$count):void{
    if(!$ok)throw new RuntimeException('F84 '.$reason);
    ++$count;
};
$view=$projector->project($original);
$assert(is_array($view),'real PostgreSQL workbench projection');
$assert($view['schema_version']==='perf.portal.workbench.offline.synthetic.v1','version');
$assert($view['organization_ref']==='org-demo-a','source organization');
$assert($view['service_ref']==='svc-demo-a','service');
$assert($view['portal_stage_mapping_ready']===false,'stage mapping not faked');
$assert($view['portal_stage_mapping_status']==='pending_explicit_enum_alignment','pending Portal UI statuses');
$assert($view['counts'] === $original['counts'],'count parity with PostgreSQL RLS');
$assert(count($view['campaigns'])===2,'actual F28 campaign count');
$assert(count($view['requests'])===6,'actual F28 review count');
$assert($view['campaigns'][0]['portal_channel']==='google_ads','first channel maps');
$assert($view['campaigns'][0]['performance_state']==='paused_demo','paused retained');
$assert($view['campaigns'][0]['portal_stage']===null,'no invented Portal stage');
foreach($view['requests'] as $req){
    $assert($req['portal_stage']===null,'no forged Portal review status');
}
foreach(['synthetic_only','read_only'] as $flag) $assert($view[$flag]===true,"$flag true");
foreach(['contracted','portal_identity_authenticated','ready_for_production',
         'dispatch_enabled','billing_enabled'] as $flag) {
    $assert($view[$flag]===false,"$flag blocked");
}
$json=json_encode($view,JSON_THROW_ON_ERROR);
$target=getenv('PERF_F84_OUTPUT_FILE');
if(is_string($target) && $target!=='') {
    if (getenv('APP_ENV')!=='testing' || !is_dir(dirname($target))
        || is_link($target) || file_exists($target)
        || file_put_contents($target,$json."\n",LOCK_EX)===false) {
        throw new RuntimeException('F84 refused unsafe CI fixture export');
    }
}
foreach(['11111111-1111-4111-8111-111111111111','actor-demo-',
         'reviewer-demo-','access_token','media_spend','provider_account_id'] as $secret) {
    $assert(!str_contains($json,$secret),"no leaked $secret");
}
$negative=[
    ['contract elevated',['contracted'=>true]],
    ['dispatch elevated',['dispatch_enabled'=>true]],
    ['wrong schema',['schema_version'=>'untrusted']],
    ['wrong source',['source'=>'live_ads']],
    ['forged org',['organization_ref'=>'../../../unsafe']],
    ['out-of-sync counts',['counts'=>array_replace($original['counts'],['managed_campaigns'=>1])]],
    ['unverified final review',['requests'=>array_map(static function($r){
        $r['journaled']=false;return $r;
    },$original['requests'])]],
    ['unknown channel',['campaigns'=>array_map(static function($c){
        $c['channel']='meta_ads';return $c;
    },$original['campaigns'])]],
    ['extra field',['injected'=>true]],
];
foreach($negative as [$label,$mutation]){
    $copy=array_replace($original,$mutation);
    $assert($projector->project($copy)===null,"reject $label");
}
$copy=$original;
$copy['campaigns'][]=$copy['campaigns'][0];
$copy['counts']['managed_campaigns']=3;
$assert($projector->project($copy)===null,'duplicate campaign denied');
$copy=$original;
$copy['requests'][]=$copy['requests'][0];
$copy['counts']['approvals_approved_internal']++;
$assert($projector->project($copy)===null,'duplicate request denied');
putenv('PERF_F84_WORKBENCH_LAB=0');
$assert($projector->project($original)===null,'flag removal denies');
putenv('PERF_F84_WORKBENCH_LAB=1');
putenv('APP_ENV=production');
$assert($projector->project($original)===null,'production denies');
echo "PASS F84 $count assertions: genuine PostgreSQL F28 read model -> safe offline Portal candidate\n";
