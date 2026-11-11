<?php
declare(strict_types=1);

/** F185 executable proof: old SQL rows must never resurrect on reactivation. */
require dirname(__DIR__,2).'/vendor/autoload.php';

use App\Performance\ReadSide\CredentialBoundDemoWorkbenchReader;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F185_MANAGEMENT_EPOCH_LAB')!=='1'
 ||getenv('PERF_F184_MANAGED_VISIBILITY_LAB')!=='1'
 ||getenv('PERF_F100_CREDENTIAL_WORKBENCH_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||!extension_loaded('pdo_pgsql')){
 throw new RuntimeException('F185 requires disposable local PostgreSQL16');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false];
$open=static fn(string $role,string $password):PDO=>new PDO($dsn,$role,$password,$options);
$admin=$open('postgres','ci-f22-db-only');
$a=$open('perf_f91_reader_a','ci-f91-only-a');
$b=$open('perf_f91_reader_b','ci-f91-only-b');
$reader=new CredentialBoundDemoWorkbenchReader();
$n=0;
$check=static function(bool $okay,string $why)use(&$n):void{
 ++$n;if(!$okay)throw new RuntimeException('F185 FAILED: '.$why);
};
$expectDenied=static function(callable $call):bool{
 try{$call();return false;}catch(PDOException $e){return $e->getCode()==='42501';}
};
$originalA=$reader->read($a);
$originalB=$reader->read($b);
$check($originalA['status']==='credential_scoped_demo_workbench_only'
 &&$originalA['counts']['linked_campaigns']===1
 &&$originalA['counts']['visible_reviews']===1,
 'A begins with original managed account link and review');
$check($originalB['status']==='credential_scoped_demo_workbench_only'
 &&$originalB['counts']['linked_campaigns']===1,
 'B begins with its own managed view');
$campaign='cam-demo-f89-a';
$scope=(int)$admin->query("SELECT management_epoch
 FROM public.performance_managed_campaigns WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn();
$check($scope===1,'A initial managed epoch is 1');
$check((int)$admin->query("SELECT l.management_epoch
 FROM public.performance_campaign_account_links l
 JOIN public.performance_managed_campaigns c ON c.id=l.campaign_id
 WHERE c.campaign_ref='cam-demo-f89-a'")->fetchColumn()===1,
 'existing account link bound to historical epoch 1');
$check((int)$admin->query("SELECT i.management_epoch
 FROM public.performance_campaign_intents i
 JOIN public.performance_managed_campaigns c ON c.id=i.campaign_id
 WHERE c.campaign_ref='cam-demo-f89-a'")->fetchColumn()===1,
 'existing review intent bound to historical epoch 1');
$check($expectDenied(static fn()=>$admin->exec(
 "UPDATE public.performance_managed_campaigns
  SET management_epoch=2 WHERE campaign_ref='cam-demo-f89-a'")),
 'direct SQL cannot bump campaign epoch without a retirement event');
$admin->exec("UPDATE public.performance_managed_campaigns
 SET managed=false WHERE campaign_ref='cam-demo-f89-a'");
$check((int)$admin->query("SELECT management_epoch
 FROM public.performance_managed_campaigns WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===2,
 'real retirement increments campaign epoch before old data is hidden');
$check((int)$a->query("SELECT count(*) FROM public.perf_f93_demo_campaign_account_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===0
 &&(int)$a->query("SELECT count(*) FROM public.perf_f97_demo_review_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===0,
 'F93/F97 hide retired campaign and review');

$admin->exec("UPDATE public.performance_managed_campaigns
 SET managed=true WHERE campaign_ref='cam-demo-f89-a'");
$check((int)$admin->query("SELECT management_epoch
 FROM public.performance_managed_campaigns WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===2,
 'reactivation preserves newer campaign epoch; it does not reset it');
$check((int)$a->query("SELECT count(*) FROM public.perf_f93_demo_campaign_account_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===0
 &&(int)$a->query("SELECT count(*) FROM public.perf_f97_demo_review_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===0,
 'old link and review stay invisible AFTER managed=false->true');
$stale=$reader->read($a);
$check($stale['status']==='credential_scoped_demo_workbench_only'
 &&$stale['campaigns']===[]&&$stale['reviews']===[],
 'F100 refuses resurrected campaign and old signed-off review');
$check($stale['counts']['linked_campaigns']===0
 &&$stale['counts']['visible_reviews']===0
 &&$stale['counts']['account_candidates']===2,
 'only candidate accounts remain visible for A, with no implicit approval');
$check($reader->read($b)===$originalB,
 'B SQL account/campaign/review projection unaffected by A retirement');

$check($expectDenied(static fn()=>$admin->exec(
 "UPDATE public.performance_campaign_account_links
  SET management_epoch=2
  WHERE campaign_id=(SELECT id FROM public.performance_managed_campaigns
    WHERE campaign_ref='cam-demo-f89-a')")),
 'existing link epoch cannot be rewritten to restore authorization');
$check($expectDenied(static fn()=>$admin->exec(
 "UPDATE public.performance_campaign_intents
  SET management_epoch=2
  WHERE campaign_id=(SELECT id FROM public.performance_managed_campaigns
    WHERE campaign_ref='cam-demo-f89-a')")),
 'existing review epoch cannot be rewritten to restore old approvals');
$check($expectDenied(static fn()=>$admin->exec(
 "UPDATE public.performance_managed_campaigns
  SET management_epoch=1 WHERE campaign_ref='cam-demo-f89-a'")),
 'direct SQL cannot roll campaign epoch back to original value');

$admin->exec("UPDATE public.performance_managed_campaigns
 SET managed=false WHERE campaign_ref='cam-demo-f89-a'");
$check((int)$admin->query("SELECT management_epoch
 FROM public.performance_managed_campaigns WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===3,
 'second retirement advances epoch again without reusing old identifiers');
$admin->exec("UPDATE public.performance_managed_campaigns
 SET managed=true WHERE campaign_ref='cam-demo-f89-a'");
$check((int)$a->query("SELECT count(*) FROM public.perf_f97_demo_review_read
 WHERE campaign_ref='cam-demo-f89-a'")->fetchColumn()===0,
 'second reactivation still cannot revive original approval');

$check((int)$a->query("SELECT count(*) FROM public.perf_f91_demo_account_read")->fetchColumn()===2,
 'account candidate view remains a separate read-only SQL scope');
$check($reader->read($a)['dispatch_enabled']===false
 &&$reader->read($a)['billing_enabled']===false
 &&$reader->read($a)['ready_for_production']===false,
 'SQL epoch gate never grants publisher or billing capabilities');
$check(!$a->inTransaction()&&!$b->inTransaction(),
 'all F100 read-only transactions close cleanly');
echo "F185 management-epoch tombstone on reactivation: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
