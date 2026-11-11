<?php
declare(strict_types=1);

/**
 * F191: material service change followed by immediate restoration (ABA).
 * The final F189 SQL status fields remain byte-equal, but locally
 * monotonic F191 tenant/link versions MUST cause a fail-closed response.
 */
require dirname(__DIR__,2).'/vendor/autoload.php';
use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;

foreach([
 'PERF_F191_SERVICE_EPOCH_LAB','PERF_F189_SERVICE_STATUS_LAB',
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB','PERF_F92_CREDENTIAL_READER_LAB',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB','PERF_F98_CREDENTIAL_REVIEW_LAB'
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F191 requires CI lab '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F191 requires disposable PG16 testing');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $u,string $pass):PDO=>new PDO($dsn,$u,$pass,$opt);
if(($argv[1]??'')==='--worker'){
 $db=$open('perf_f91_reader_b','ci-f91-only-b');
 $data=(new CredentialBoundDemoServiceStatusFence())->read($db);
 echo json_encode([
  'status'=>$data['status'],
  'accounts'=>$data['accounts'],'campaigns'=>$data['campaigns'],
  'reviews'=>$data['reviews'],'dispatch'=>$data['dispatch_enabled'],
  'billing'=>$data['billing_enabled']
 ],JSON_THROW_ON_ERROR)."\n";
 exit(0);
}
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$read=static fn(PDO $db):array=>(new CredentialBoundDemoServiceStatusFence())->read($db);
$n=0;
$check=static function(bool $ok,string $msg)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F191 FAILED: '.$msg);
};
$blocked=static function(callable $cb):bool{
 try{$cb();return false;}
 catch(PDOException $e){return $e->getCode()==='42501';}
};
$tenant='99999999-9999-4999-8999-999999999999';
$base=$read($b);
$check($base['status']==='credential_scoped_demo_workbench_only'
 &&$base['counts']['linked_campaigns']===1
 &&$base['reviews'][0]['review_observation_state']==='pending_human_demo',
 'F190 left B synthetic service active with the NEW pending campaign only');
$check($read($a)['status']!=='credential_scoped_demo_workbench_only',
 'A remains inactive and isolated when B activates');
$version=static function()use($b):array{
 return $b->query('SELECT * FROM public.perf_f191_service_epoch_for_login()')
  ->fetch(PDO::FETCH_ASSOC);
};
$first=$version();
$check((int)$first['tenant_epoch']===1&&(int)$first['service_epoch']===1,
 'new epoch table starts after F190 fixture setup at version 1');
$check($blocked(static fn()=>$admin->exec(
 "UPDATE public.portal_service_links SET link_epoch=5
  WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'"
)), 'manual service epoch jump refused by PostgreSQL trigger');
$check($blocked(static fn()=>$admin->exec(
 "UPDATE public.performance_tenants SET status_epoch=5
  WHERE id='{$tenant}'::uuid"
)), 'manual tenant epoch jump refused by PostgreSQL trigger');
$check($version()===$first,
 'failed direct epoch rewrites leave original SQL versions unchanged');
$check($admin->query(
 "SELECT has_table_privilege('perf_f91_reader_b',
 'portal_service_links','SELECT')"
)->fetchColumn()===false,
 'reader B cannot inspect/forge base service-link state directly');
$check($admin->query(
 "SELECT has_function_privilege('perf_f91_reader_b',
 'public.perf_f191_service_epoch_for_login()','EXECUTE')"
)->fetchColumn()===true,
 'reader B gets only fixed SQL login-bound epoch function');

$original=$admin->query(
 "SELECT pg_get_viewdef('public.perf_f97_demo_review_read'::regclass,true)"
)->fetchColumn();
$check(is_string($original)&&str_contains($original,'performance_campaign_intents'),
 'F97 view original SQL exists before disposable slow-reader instrumentation');
$original=rtrim(trim($original),"; \t\r\n");
$admin->exec('CREATE OR REPLACE VIEW public.perf_f97_demo_review_read AS
 SELECT src.* FROM ('.$original.') AS src
 CROSS JOIN (SELECT 0 AS probe FROM pg_sleep(2.0)) AS f191_slow_read');
$check($read($b)===$base,
 'extra latency alone does not invalidate a stable linked B demo');

$race=static function(callable $change,string $scenario,string $versionKey)
 use($admin,$b,$check,$read,$version):void{
 $before=$version();
 $statusBefore=$b->query('SELECT * FROM public.perf_f189_login_service_status()')
  ->fetch(PDO::FETCH_ASSOC);
 $worker=proc_open([PHP_BINARY,__FILE__,'--worker'],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
  $pipes,dirname(__DIR__,2),array_merge(getenv()?:[],[
   'APP_ENV'=>'testing','PERF_F191_SERVICE_EPOCH_LAB'=>'1',
   'PERF_F189_SERVICE_STATUS_LAB'=>'1',
   'PERF_F100_CREDENTIAL_WORKBENCH_LAB'=>'1',
   'PERF_F92_CREDENTIAL_READER_LAB'=>'1',
   'PERF_F94_CREDENTIAL_CAMPAIGN_LAB'=>'1',
   'PERF_F98_CREDENTIAL_REVIEW_LAB'=>'1',
   'PGHOST'=>'127.0.0.1','PGPORT'=>'5432',
   'PGDATABASE'=>'perf_test_campaign_registry'
  ])
 );
 if(!is_resource($worker))throw new RuntimeException('F191 worker could not start');
 fclose($pipes[0]);
 $inRead=false;
 for($i=0;$i<95;$i++){
  $q=$admin->query(
   "SELECT count(*) FROM pg_stat_activity WHERE datname='perf_test_campaign_registry'
    AND usename='perf_f91_reader_b' AND state='active'
    AND query LIKE '%perf_f97_demo_review_read%'"
  )->fetchColumn();
  if((int)$q>0){$inRead=true;break;}
  usleep(50000);
 }
 $check($inRead,$scenario.': observed B reader inside delayed SQL view');
 $change();
 $after=$version();
 $statusAfter=$b->query('SELECT * FROM public.perf_f189_login_service_status()')
  ->fetch(PDO::FETCH_ASSOC);
 $check((int)$after[$versionKey]>=(int)$before[$versionKey]+2,
  $scenario.': two state transitions advanced monotonic SQL version');
 $check($statusBefore===$statusAfter,
  $scenario.': F189 visible status/evidence fields are unchanged (ABA)');
 $out=(string)stream_get_contents($pipes[1]);fclose($pipes[1]);
 $err=(string)stream_get_contents($pipes[2]);fclose($pipes[2]);
 $exit=proc_close($worker);
 $check($exit===0,$scenario.': worker exits without PHP error: '.substr($err,0,120));
 $report=json_decode($out,true,8,JSON_THROW_ON_ERROR);
 $check($report['status']==='service_changed_during_demo_read'
  &&$report['accounts']===[]&&$report['campaigns']===[]&&$report['reviews']===[],
  $scenario.': F191 epoch detects ABA invisible to simple F189 status check');
 $check($report['dispatch']===false&&$report['billing']===false,
  'ABA revocation never grants operational actions');
};

$race(static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.portal_service_links SET link_status='pending'
  WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
 $admin->exec("UPDATE public.portal_service_links SET link_status='active'
  WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
},'service pending then active','service_epoch');

$race(static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.performance_tenants SET status='inactive'
  WHERE id='{$tenant}'::uuid");
 $admin->exec("UPDATE public.performance_tenants SET status='active'
  WHERE id='{$tenant}'::uuid");
},'tenant inactive then active','tenant_epoch');

$race(static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.portal_service_links
  SET source_revision='demo-revision-f191-temporary'
  WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
 $admin->exec("UPDATE public.portal_service_links
  SET source_revision='demo-revision-f189-revoked'
  WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
},'revision changed then reverted','service_epoch');

$admin->exec('CREATE OR REPLACE VIEW public.perf_f97_demo_review_read AS '.$original);
$check($read($b)===$base,
 'a NEW stable read after ABA completes can still render synthetic B demo');
$check($read($a)['status']!=='credential_scoped_demo_workbench_only',
 'A remains inactive despite all transient B transitions');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'no SQL transaction leaks after repeated adversarial ABA tests');
echo "F191 tenant/link ABA service epoch: {$n} PASS / 0 FAIL — LOCAL-ONLY / NO-GO\n";
