<?php
declare(strict_types=1);

/** F184 BEFORE/AFTER real PostgreSQL16 campaign retirement visibility. */
require dirname(__DIR__,2).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F184_MANAGED_VISIBILITY_LAB')!=='1'
 ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||!in_array($argv[1]??'',['before','after'],true)){
 throw new RuntimeException('F184 test is restricted to before/after disposable CI');
}
$after=($argv[1]==='after');
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $role,string $pass):PDO=>new PDO($dsn,$role,$pass,$opt);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$reader=new CredentialBoundDemoWorkbenchReader();
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F184 FAILED: '.$why);
};
$baseline=$reader->read($a);
$other=$reader->read($b);
$check($baseline['status']==='credential_scoped_demo_workbench_only'
 &&$baseline['counts']['linked_campaigns']===1
 &&$baseline['counts']['visible_reviews']===1,
 'original A F100 workbench starts with one active linked campaign and review');
$check($other['status']==='credential_scoped_demo_workbench_only'
 &&$other['counts']['linked_campaigns']===1,
 'SQL login B has independent campaign scope');
$check($admin->query("SELECT managed FROM performance_managed_campaigns
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===true
 ||$admin->query("SELECT managed::text FROM performance_managed_campaigns
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()==='true',
 'A fixture is genuinely marked managed before retirement');
$check((int)$a->query("SELECT count(*) FROM public.perf_f93_demo_campaign_account_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===1
 &&(int)$a->query("SELECT count(*) FROM public.perf_f97_demo_review_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===1,
 'both F93 and F97 initially contain A fixture row');

$admin->exec("UPDATE performance_managed_campaigns
 SET managed=false WHERE campaign_ref='cam-demo-f89-a'");
$check($admin->query("SELECT managed::text FROM performance_managed_campaigns
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()==='false',
 'retirement is committed in real PostgreSQL');
$f93=(int)$a->query("SELECT count(*) FROM public.perf_f93_demo_campaign_account_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn();
$f97=(int)$a->query("SELECT count(*) FROM public.perf_f97_demo_review_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn();
$report=$reader->read($a);
$check($report['status']==='credential_scoped_demo_workbench_only'
 &&$report['portal_identity_authenticated']===false
 &&$report['dispatch_enabled']===false
 &&$report['billing_enabled']===false,
 'A report retains explicit safety flags during a campaign withdrawal');
if(!$after){
 $check($f93===1 &&$f97===1,
  'EXPECTED BUG: original credential views reveal unmanaged campaign and review');
 $check($report['counts']['linked_campaigns']===1
  &&$report['counts']['visible_reviews']===1,
  'EXPECTED BUG: original F100 still projects retired A rows');
}else{
 $check($f93===0 &&$f97===0,
  'F184 migration hides unmanaged campaign from both SQL views');
 $check($report['campaigns']===[] &&$report['reviews']===[]
  &&$report['counts']['linked_campaigns']===0
  &&$report['counts']['visible_reviews']===0,
  'F100 no longer emits withdrawn campaign or linked approval journal');
 $check($report['counts']['account_candidates']===2,
  'candidate accounts can remain visible as unverified demo without any managed campaign');
}
$check($reader->read($b)['campaigns']===$other['campaigns']
 &&$reader->read($b)['reviews']===$other['reviews'],
 'withdrawal of A campaign never changes B SQL principal view');
$check($a->query("SELECT session_user")->fetchColumn()==='perf_f91_reader_a',
 'tenant binding cannot be forged by direct SQL report parameter');

$admin->exec("UPDATE performance_managed_campaigns
 SET managed=true WHERE campaign_ref='cam-demo-f89-a'");
$restored=$reader->read($a);
$check($restored['status']==='credential_scoped_demo_workbench_only'
 &&$restored['counts']['linked_campaigns']===1,
 're-enabling fixture campaign restores its currently managed row');
if($after){
 $check($restored['counts']['visible_reviews']===1,
  'EXPECTED REMAINING GAP: old review reappears on unmanaged-to-managed toggle, needs separate epoch fence');
}
$check(!$a->inTransaction() &&!$b->inTransaction(),
 'A and B F100 report transactions are closed on all paths');
echo "F184 ".($after?'AFTER':'BEFORE')." unmanaged campaign visibility: {$n} PASS / 0 FAIL — ".
 ($after?'FIXED WITH REACTIVATION GAP':'EXPECTED BUG')." / NO-GO\n";
