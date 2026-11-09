<?php
declare(strict_types=1);

/**
 * F1-49 CI-only NO-GO assertion. This is not a production access control:
 * privileged GitHub users can change this code and manifest together.
 *
 * Its purpose is to fail a lab CI regression if deployment, provider wiring,
 * real billing or active outbox is accidentally introduced while P0 is open.
 */
if(PHP_SAPI!=='cli'
 ||getenv('APP_ENV')!=='testing'
 ||getenv('GITHUB_ACTIONS')!=='true'
 ||getenv('PERF_F49_RELEASE_NO_GO_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='portal_perf_auth_lab'){
 throw new RuntimeException('F49 no-go guard only executes in disposable GitHub Actions.');
}
$root=dirname(__DIR__,3);
$manifestPath=$root.'/contracts/lab/ci/performance-release-no-go.json';
if(!is_file($manifestPath)){
 throw new RuntimeException('Missing F49 release-blocked manifest');
}
$raw=file_get_contents($manifestPath);
if(!is_string($raw)||strlen($raw)>15000){
 throw new RuntimeException('Invalid F49 policy manifest size');
}
$policy=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
if(!is_array($policy))throw new RuntimeException('Manifest must be a JSON object');
$required=[
 'schema_version'=>'perf.release.no_go.lab.v1',
 'mode'=>'synthetic_ci_only',
 'release_state'=>'blocked',
 'operational_activation_allowed'=>false,
 'external_provider_dispatch_allowed'=>false,
 'billing_allowed'=>false,
 'automatic_merge_allowed'=>false,
 'real_portal_or_crm_authority_connected'=>false,
];
$gates=[
 'P0'=>'open',
 'HT-R01'=>'open',
 'SEC-R03/T3'=>'open',
 'AppSec-DBA'=>'not_approved',
 'restore_epoch_antirollback'=>'unresolved',
 'provider_idempotency'=>'unproven',
 'human_approval'=>'mock_only',
];
$validate=static function(mixed $doc)use($required,$gates):bool{
 if(!is_array($doc))return false;
 foreach($required as $key=>$value){
  if(!array_key_exists($key,$doc)||$doc[$key]!==$value)return false;
 }
 if(!isset($doc['blocking_gates'])||!is_array($doc['blocking_gates'])){
  return false;
 }
 foreach($gates as $key=>$value){
  if(!array_key_exists($key,$doc['blocking_gates'])
      ||$doc['blocking_gates'][$key]!==$value)return false;
 }
 return true;
};
$n=0;
$check=static function(string $label,mixed $actual,mixed $wanted)use(&$n):void{
 if($actual!==$wanted){
  throw new RuntimeException('FAIL F49 '.$label.' got='
    .var_export($actual,true).' expected='.var_export($wanted,true));
 }
 echo 'PASS F49 '.++$n.' '.$label.PHP_EOL;
};
$check('explicit CI manifest remains NO-GO',$validate($policy),true);
foreach([
  'operational_activation_allowed',
  'external_provider_dispatch_allowed',
  'billing_allowed',
  'automatic_merge_allowed',
  'real_portal_or_crm_authority_connected'
] as $field){
 $modified=$policy;
 $modified[$field]=true;
 $check('counterfactual '.$field.'=true fails policy',
   $validate($modified),false);
}
foreach($gates as $key=>$value){
 $modified=$policy;
 $modified['blocking_gates'][$key]='approved';
 $check('counterfactual gate '.$key.'=approved fails policy',
   $validate($modified),false);
}
$modified=$policy;
unset($modified['release_state']);
$check('manifest missing release-state fails policy',
 $validate($modified),false);
$modified=$policy;
$modified['release_state']='ready';
$check('manifest ready claim fails while P0 unresolved',
 $validate($modified),false);
$modified=$policy;
$modified['mode']='operational';
$check('operational mode cannot silently replace CI-only',
 $validate($modified),false);
$modified=$policy;
$modified['schema_version']='perf.release.no_go.lab.v2';
$check('unknown policy schema fails until reviewed',
 $validate($modified),false);
$check('real manifest is unchanged after mutation tests',$validate($policy),true);

// No production wiring. Scan only executable app paths, not historical
// adversarial test fixtures, documentation or harmless fixture JSON.
$paths=['app','routes','bootstrap','database/migrations'];
$scanned=0;
$forbidden=[
 '/\bHttp::(post|put|patch|delete|withToken)\s*\(/i',
 '/\b(?:curl_init|curl_exec)\s*\(/i',
 '/\bnew\s+(?:GoogleAdsClient|FacebookAdsApi|MetaAdsClient)\b/i',
 '/\b(?:Route|Schedule)::(?:post|put|patch|delete)\s*\(/i',
 '/\bdispatch_enabled\s*[:=]\s*true\b/i',
 '/\b(?:SyntheticExternalAtomicPermitObserver|SyntheticServerClockPermitGateway)\b/',
 '/\bperf_f(?:45|46|47)_admission\b/i',
];
foreach($paths as $dir){
 $base=$root.'/'.$dir;
 if(!is_dir($base))throw new RuntimeException('Missing executable path '.$dir);
 $iter=new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS)
 );
 foreach($iter as $file){
  if(!$file instanceof SplFileInfo||!$file->isFile()
     ||strtolower($file->getExtension())!=='php')continue;
  ++$scanned;
  $source=file_get_contents($file->getPathname());
  if(!is_string($source))throw new RuntimeException('Unreadable executable source');
  foreach($forbidden as $pattern){
   if(preg_match($pattern,$source)===1){
    throw new RuntimeException(
      'FAIL F49 prohibited operational wiring pattern '.$pattern
      .' in '.str_replace($root.'/','',$file->getPathname())
    );
   }
  }
 }
}
$check('executable PHP modules scanned for obvious provider/dispatch wiring',
 $scanned>0,true);
$provider=$root.'/app/Providers/AppServiceProvider.php';
$service=file_get_contents($provider);
$check('service provider still deliberately contains no bindings',
 is_string($service)
 && str_contains($service,'Deliberately empty')
 && str_contains($service,'Never register routes'),
 true);
$check('route-only kernel has no registered HTTP controllers',
 !is_file($root.'/routes/api.php')&&!is_file($root.'/routes/web.php'),
 true);

try{
 $db=new PDO(
  'pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=3',
  'postgres','ci-f08-db-only',[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
   PDO::ATTR_EMULATE_PREPARES=>false
  ]
 );
}catch(PDOException $e){
 throw new RuntimeException('F49 PostgreSQL synthetic outbox unavailable',0,$e);
}
$constraints=$db->query(
 "SELECT pg_get_constraintdef(oid) FROM pg_constraint"
 ." WHERE conrelid='perf_f35_intents.outbox'::regclass AND contype='c'"
)->fetchAll(PDO::FETCH_COLUMN);
$hasCheck=false;
foreach($constraints as $def){
 if(is_string($def)&&preg_match('/dispatch_enabled\s*=\s*false/i',$def)===1){
  $hasCheck=true;
 }
}
$check('actual SQL schema enforces dispatch_enabled=false CHECK',$hasCheck,true);
$check('all fake outbox events still disabled',
 (int)$db->query(
  'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
 )->fetchColumn(),0);
$check('all fake requests still pending only',
 (int)$db->query(
  "SELECT count(*) FROM perf_f35_intents.requests"
  ." WHERE state<>'pending_synthetic'"
 )->fetchColumn(),0);
$check('all fake requests limited to campaigns:read',
 (int)$db->query(
  "SELECT count(*) FROM perf_f35_intents.requests"
  ." WHERE permission<>'campaigns:read'"
 )->fetchColumn(),0);
echo "PASS F49 {$n} NO-GO CI assertions; scanned {$scanned} executable PHP files. NOT a release approval.".PHP_EOL;
