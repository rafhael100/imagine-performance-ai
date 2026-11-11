<?php
declare(strict_types=1);

/**
 * F190: concurrent revocation/revision drift while an ORIGINAL F100
 * PostgreSQL read is already underway. The F189 wrapper must DENY the
 * whole response, never leak earlier eligible rows.
 */
require dirname(__DIR__,2).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoServiceStatusFence;

foreach([
 'PERF_F190_SERVICE_RACE_LAB',
 'PERF_F189_SERVICE_STATUS_LAB','PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB','PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB'
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F190 missing CI lab flag '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F190 requires disposable PG16');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $user,string $pass):PDO=>new PDO($dsn,$user,$pass,$opt);
if(($argv[1]??'')==='--worker'){
 $db=$open('perf_f91_reader_b','ci-f91-only-b');
 $data=(new CredentialBoundDemoServiceStatusFence())->read($db);
 echo json_encode([
  'status'=>$data['status'],
  'accounts'=>$data['accounts'],
  'campaigns'=>$data['campaigns'],
  'reviews'=>$data['reviews'],
  'dispatch'=>$data['dispatch_enabled'],
  'billing'=>$data['billing_enabled']
 ],JSON_THROW_ON_ERROR)."\n";
 exit(0);
}

$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$read=static fn(PDO $db):array=>(new CredentialBoundDemoServiceStatusFence())->read($db);
$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F190 FAILED: '.$why);
};
$tenant='99999999-9999-4999-8999-999999999999';
$admin->exec("UPDATE public.portal_service_links
 SET source_revision='demo-revision-f189-revoked',link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$admin->exec("UPDATE public.service_eligibility_snapshots
 SET state='eligible',observed_at=clock_timestamp()
 WHERE id='ac890000-0000-4000-8000-000000000002'::uuid");
$baseline=$read($b);
$check($baseline['status']==='credential_scoped_demo_workbench_only'
 &&$baseline['counts']['linked_campaigns']===1
 &&$baseline['counts']['visible_reviews']===1,
 'newer synthetic revision is now eligible for original B PostgreSQL preview');
$check($read($a)['status']!=='credential_scoped_demo_workbench_only',
 'A inactive SQL principal is unaffected by B demo activation');

$originalView=$admin->query(
 "SELECT pg_get_viewdef('public.perf_f97_demo_review_read'::regclass,true)"
)->fetchColumn();
$check(is_string($originalView)
 &&str_contains($originalView,'performance_campaign_intents'),
 'the original constrained review view exists and can be instrumented in CI');
$originalView=rtrim(trim($originalView),"; \t\r\n");
$slow='CREATE OR REPLACE VIEW public.perf_f97_demo_review_read AS
 SELECT src.* FROM ('.$originalView.') AS src
 CROSS JOIN (SELECT 0 AS ci_only_probe FROM pg_sleep(2.0)) AS f190_delayed';
$admin->exec($slow);
$check($read($b)===$baseline,
 'a two-second delayed SQL view by itself preserves a valid B report');

$race=static function(callable $mutate,string $scenario)use(
 $admin,$read,$b,$check,$baseline
):void{
 $proc=proc_open(
  [PHP_BINARY,__FILE__,'--worker'],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
  $pipes,dirname(__DIR__,2),array_merge(getenv()?:[],[
   'APP_ENV'=>'testing','PERF_F190_SERVICE_RACE_LAB'=>'1',
   'PERF_F189_SERVICE_STATUS_LAB'=>'1',
   'PERF_F100_CREDENTIAL_WORKBENCH_LAB'=>'1',
   'PERF_F92_CREDENTIAL_READER_LAB'=>'1',
   'PERF_F94_CREDENTIAL_CAMPAIGN_LAB'=>'1',
   'PERF_F98_CREDENTIAL_REVIEW_LAB'=>'1',
   'PGHOST'=>'127.0.0.1','PGPORT'=>'5432',
   'PGDATABASE'=>'perf_test_campaign_registry'
  ])
 );
 if(!is_resource($proc))throw new RuntimeException('F190 isolated worker start failed');
 fclose($pipes[0]);
 $inside=false;
 for($i=0;$i<95;$i++){
  $query=$admin->query(
   "SELECT count(*) FROM pg_stat_activity
    WHERE datname='perf_test_campaign_registry'
      AND usename='perf_f91_reader_b' AND state='active'
      AND query LIKE '%perf_f97_demo_review_read%'"
  )->fetchColumn();
  if((int)$query>0){$inside=true;break;}
  usleep(50000);
 }
 $check($inside,'observed B SQL reader mid-query during '.$scenario);
 $mutate();
 $out=(string)stream_get_contents($pipes[1]);fclose($pipes[1]);
 $err=(string)stream_get_contents($pipes[2]);fclose($pipes[2]);
 $exit=proc_close($proc);
 $check($exit===0,'B worker exited without PHP error: '.substr($err,0,120));
 $data=json_decode($out,true,8,JSON_THROW_ON_ERROR);
 $check($data['status']==='service_changed_during_demo_read'
   &&$data['accounts']===[]&&$data['campaigns']===[]&&$data['reviews']===[],
   $scenario.' denied ALL already-read SQL data at second service probe');
 $check($data['dispatch']===false&&$data['billing']===false,
   'concurrent service state never produces advertising or billing authority');
};
$race(static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.portal_service_links SET link_status='pending'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
},'Portal service cancellation');
$check($read($b)['status']!=='credential_scoped_demo_workbench_only',
 'cancellation remains denied after the concurrent read finishes');
$admin->exec("UPDATE public.portal_service_links SET link_status='active'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
$check($read($b)===$baseline,
 'manual synthetic reactivation permits a NEW B demo read, not a client entitlement');

$race(static function()use($admin,$tenant):void{
 $admin->exec("UPDATE public.portal_service_links
 SET source_revision='demo-revision-f190-changed'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");
},'Portal service revision drift');
$check($read($b)['status']!=='credential_scoped_demo_workbench_only',
 'mismatched latest revision remains denied after concurrent SQL read');
$admin->exec("UPDATE public.portal_service_links
 SET source_revision='demo-revision-f189-revoked'
 WHERE tenant_id='{$tenant}'::uuid AND portal_service_ref='svc-demo-f88'");

$admin->exec('CREATE OR REPLACE VIEW public.perf_f97_demo_review_read AS '.$originalView);
$check($read($b)===$baseline,
 'normal constrained original SQL review view restores valid synthetic B read');
$check($read($a)['status']!=='credential_scoped_demo_workbench_only',
 'A remains inactive when B service changes during read');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'B/A SQL readers leave no dangling transactions');
echo "F190 concurrent service cancellation/revision during F100: {$n} PASS / 0 FAIL — NO-GO\n";
