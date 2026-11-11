<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

foreach([
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB',
 'PERF_F92_CREDENTIAL_READER_LAB',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB',
 'PERF_F98_CREDENTIAL_REVIEW_LAB',
 'PERF_F177_F100_READ_COHERENCE_LAB',
] as $flag){
 if(getenv($flag)!=='1')throw new RuntimeException('F177 missing CI flag: '.$flag);
}
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F177 only runs in disposable F100 PG16 CI');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $user,string $pass):PDO=>new PDO($dsn,$user,$pass,$opts);
if(($argv[1]??'')==='--worker'){
 // The subprocess does not take a caller-supplied tenant.
 $a=$open('perf_f91_reader_a','ci-f91-only-a');
 $report=(new CredentialBoundDemoWorkbenchReader())->read($a);
 echo json_encode([
   'status'=>$report['status'],
   'campaigns'=>$report['campaigns'],
   'authorized'=>$report['dispatch_enabled'],
   'billing'=>$report['billing_enabled'],
 ],JSON_THROW_ON_ERROR)."\n";
 exit(0);
}
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$read=new CredentialBoundDemoWorkbenchReader();
$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F177 FAILED: '.$why);
};
$beforeA=$read->read($a);
$beforeB=$read->read($b);
$check($beforeA['status']==='credential_scoped_demo_workbench_only'
 &&$beforeB['status']==='credential_scoped_demo_workbench_only',
 'both users read their own real SQL-backed F100 workbench with new second pass');
$check($beforeA['counts']['linked_campaigns']===1
 &&$beforeA['campaigns'][0]['campaign_ref']==='cam-demo-f89-a',
 'A starts with an account-linked mock campaign');
$check($beforeB['campaigns'][0]['campaign_ref']==='cam-demo-f89-b',
 'B remains separate from A throughout the test');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'new double-pass workbench commits its READ ONLY SQL transaction');

$record=$admin->query("SELECT lifecycle_state FROM performance_managed_campaigns
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn();
$check(is_string($record)&&in_array($record,['draft','paused','archived'],true),
 'campaign state from actual PostgreSQL fixture is recognized');
$next=$record==='draft'?'paused':'draft';
$originalView=$admin->query(
 "SELECT pg_get_viewdef('public.perf_f97_demo_review_read'::regclass,true)"
)->fetchColumn();
$check(is_string($originalView)&&str_contains($originalView,'performance_campaign_intents'),
 'capture exact existing F97 review-view query without changing the schema');
$originalView=rtrim(trim($originalView),"; \t\r\n");
// Instrument original F97 view in disposable CI with a 2s delay. Running
// F100 unmodified earlier established the original expected data set.
$slowView='CREATE OR REPLACE VIEW public.perf_f97_demo_review_read AS
 SELECT src.* FROM ('.$originalView.') AS src
 CROSS JOIN (SELECT 0 AS sleep_probe FROM pg_sleep(2.0)) AS f177_delay';
$admin->exec($slowView);

$slowBaseline=$read->read($a);
$check($slowBaseline['status']==='credential_scoped_demo_workbench_only'
 &&$slowBaseline['campaigns']===$beforeA['campaigns'],
 'instrumented 2-second view still permits coherent read without concurrent mutation');

$worker=proc_open([PHP_BINARY,__FILE__,'--worker'],
 [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
 $pipes,dirname(__DIR__,2),array_merge(getenv()?:[],[
 'APP_ENV'=>'testing',
 'PERF_F100_CREDENTIAL_WORKBENCH_LAB'=>'1',
 'PERF_F92_CREDENTIAL_READER_LAB'=>'1',
 'PERF_F94_CREDENTIAL_CAMPAIGN_LAB'=>'1',
 'PERF_F98_CREDENTIAL_REVIEW_LAB'=>'1',
 'PERF_F177_F100_READ_COHERENCE_LAB'=>'1',
 'PGDATABASE'=>'perf_test_campaign_registry',
 'PGHOST'=>'127.0.0.1','PGPORT'=>'5432',
]));
if(!is_resource($worker))throw new RuntimeException('F177 SQL worker unavailable');
fclose($pipes[0]);
$insideReview=false;
for($i=0;$i<95;$i++){
 $running=$admin->query("SELECT count(*) FROM pg_stat_activity
 WHERE datname='perf_test_campaign_registry'
 AND usename='perf_f91_reader_a'
 AND state='active' AND query LIKE '%perf_f97_demo_review_read%'")->fetchColumn();
 if((int)$running>0){$insideReview=true;break;}
 usleep(50000);
}
$check($insideReview,
 'administrator observed F100 worker inside deliberately slow review view');
$admin->prepare("UPDATE performance_managed_campaigns
 SET lifecycle_state=?
 WHERE campaign_ref='cam-demo-f89-a'")->execute([$next]);
$check($admin->query("SELECT lifecycle_state FROM performance_managed_campaigns
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===$next,
 'mock campaign changed after initial account/campaign reads and before final pass');

$out=stream_get_contents($pipes[1]);fclose($pipes[1]);
$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
$exit=proc_close($worker);
$check($exit===0,'concurrent F100 PHP process exits cleanly: '.substr((string)$err,0,100));
$mid=json_decode((string)$out,true,8,JSON_THROW_ON_ERROR);
$check($mid['status']==='components_changed_during_composition'
 &&$mid['campaigns']===[]
 &&$mid['authorized']===false
 &&$mid['billing']===false,
 'F100 returns no mixed old/new campaign data or grant when state changed during read');

// Restore the original view (same columns, ACLs) and fixture lifecycle.
// The only race-related mutation is synthetically scoped.
$admin->exec('CREATE OR REPLACE VIEW public.perf_f97_demo_review_read AS '.$originalView);
$after=$read->read($a);
$check($after['status']==='credential_scoped_demo_workbench_only'
 &&$after['campaigns'][0]['campaign_observation_state']!==
   $beforeA['campaigns'][0]['campaign_observation_state'],
 'new lifecycle state appears only in a fresh coherent snapshot');
$check($read->read($b)['campaigns']===$beforeB['campaigns'],
 'unrelated B workbench unchanged throughout A concurrent scope update');

$admin->prepare("UPDATE performance_managed_campaigns
 SET lifecycle_state=?
 WHERE campaign_ref='cam-demo-f89-a'")->execute([$record]);
$check($read->read($a)['campaigns']===$beforeA['campaigns'],
 'original A fixture state restores deterministically with original view');
$check($read->read($a)['dispatch_enabled']===false
 &&$read->read($a)['billing_enabled']===false,
 'all successful F100 reads continue to deny billable/provider actions');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'F100 leaves no leaked open transactions after success or conflict');

echo "F177 F100 multi-view concurrent lifecycle fence: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
