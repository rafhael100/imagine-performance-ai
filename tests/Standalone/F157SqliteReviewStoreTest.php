<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F157SyntheticSqliteReviewStore.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F157SyntheticSqliteReviewStore;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F157_SQLITE_LAB')!=='1'
 ||!extension_loaded('pdo_sqlite'))throw new RuntimeException('F157 test lab only');
$runner=(string)getenv('RUNNER_TEMP');
$dir=$runner.'/f157-persist';
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('F157 temp folder unavailable');
$db=$dir.'/reviews.sqlite';
if(file_exists($db))throw new RuntimeException('F157 test database must start new');
$scope=new SyntheticManagedScopeLedger();
$a='perf-demo-aurora';$b='perf-demo-boreal';
$scope->setDemoCapacity($a,'google_ads',2,3);
$scope->setDemoCapacity($a,'meta',2,3);
$scope->setDemoCapacity($b,'google_ads',2,3);
$scope->includeCampaign($a,'google_ads','cam-f157-search');
$scope->includeCampaign($a,'meta','cam-f157-social');
$scope->includeCampaign($b,'google_ads','cam-f157-boreal');
$store=new F157SyntheticSqliteReviewStore($db,$scope);
$n=0;
$check=static function(bool $ok,string $reason)use(&$n):void {
 ++$n;if(!$ok)throw new RuntimeException('F157 FAIL: '.$reason);
};
$throws=static function(callable $fn):bool{
 try{$fn();return false;}catch(InvalidArgumentException|RuntimeException $e){return true;}
};
$check($store->submit($a,'rev-f157-search','cam-f157-search','google_ads','Criativo pesquisa')
 ==='submitted_demo','persist fictional A review');
$check($store->submit($a,'rev-f157-search','cam-f157-search','google_ads','Novo')
 ==='duplicate_review_denied','same-tenant duplicate denied');
$check($store->submit($b,'rev-f157-search','cam-f157-boreal','google_ads','B separada')
 ==='submitted_demo','same review reference allowed for separate tenant B');
$check($store->submit($b,'rev-f157-cross','cam-f157-search','google_ads','Cross')
 ==='out_of_scope_or_wrong_channel_denied','cross-tenant campaign reference denied');
$check($store->submit($a,'rev-f157-wrong','cam-f157-social','google_ads','Wrong')
 ==='out_of_scope_or_wrong_channel_denied','channel spoofing denied');
$check($throws(static fn()=>$store->submit($a,'rev-f157-script','cam-f157-search',
 'google_ads','<script>x</script>')),'HTML injection title refused');
$check($throws(static fn()=>$store->submit($a,'rev-%invalid','cam-f157-search',
 'google_ads','X')),'invalid review identifier refused');
$check($throws(static fn()=>$store->submit($a,'rev-f157-wrong','cam-f157-search',
 'email','X')),'unknown channel refused');

$check($store->decide($a,'rev-f157-search',0,'approve_mock','reviewer-demo-ana')
 ==='stale_version_denied','invalid expected version refused');
$check($store->decide($a,'rev-f157-search',1,'unknown','reviewer-demo-ana')
 ==='unknown_decision_denied','unknown decision refused');
$check($store->decide($a,'rev-f157-search',1,'request_changes','reviewer-demo-ana')
 ==='changes_requested_demo','request changes committed in SQLite');
$check($store->decide($a,'rev-f157-search',1,'reject_mock','reviewer-demo-ana')
 ==='stale_version_denied','stale competing decision refused');
$check($store->revise($a,'rev-f157-search',2,'Criativo de pesquisa atualizado')
 ==='resubmitted_demo','new title persisted after request changes');
$check($store->decide($a,'rev-f157-search',3,'approve_mock','reviewer-demo-beto')
 ==='approved_mock_only','mock-only final decision persisted');
$check($store->decide($a,'rev-f157-search',4,'approve_mock','reviewer-demo-beto')
 ==='review_not_pending_denied','cannot re-approve final review');

$check($store->submit($a,'rev-f157-social','cam-f157-social','meta','Peça social')
 ==='submitted_demo','independent Meta review persisted');
$check($store->decide($a,'rev-f157-social',1,'reject_mock','reviewer-demo-ana')
 ==='rejected_mock_only','mock reject persists with audit');
$check($store->submit($a,'rev-f157-crash','cam-f157-social','meta','Crash test')
 ==='submitted_demo','prepare review for transaction rollback fault injection');

$other=new F157SyntheticSqliteReviewStore($db,$scope);
$check($other->project($a)['review_count']===3,
 'separate connection sees committed A review rows');
$check($other->project($b)['review_count']===1,
 'separate connection reads only B review rows');
$check($other->decide($a,'rev-f157-social',1,'approve_mock','reviewer-demo-beto')
 ==='stale_version_denied','new connection denies stale review version');
$check($other->decide($b,'rev-f157-search',1,'approve_mock','reviewer-demo-beto')
 ==='approved_mock_only','B can decide its own same-reference review');

$project=$other->project($a);
$check($project['summary']['approved_mock_only']===1
 &&$project['summary']['rejected_mock_only']===1
 &&$project['summary']['pending_demo']===1,
 'persisted status counters accurate across connections');
$check($project['audit_entries_demo']===7 && count($other->events($a))===7,
 'A audit has exactly one entry per successful transition');
$check($other->project($b)['audit_entries_demo']===2,
 'B audit entries stay separate from A');
$check($project['dispatch_enabled']===false
 &&$project['billing_enabled']===false
 &&$project['human_approval_verified']===false
 &&$project['authorization_eligible']===false
 &&$project['ready_for_production']===false,
 'SQLite persistence never enables paid media or real approval');
$check($project['source']==='sqlite_runner_fixture_only',
 'SQLite output cannot be confused with live Portal or in-memory DTO');

// Force an exception AFTER attempted row UPDATE but BEFORE event INSERT.
// The transaction must be rolled back, leaving both item and audit unchanged.
$pdo=new PDO('sqlite:'.$db);
$pdo->exec("CREATE TRIGGER f157_ci_abort BEFORE INSERT ON f157_events
 WHEN NEW.reference='rev-f157-crash' AND NEW.transition='approved_mock_only'
 BEGIN SELECT RAISE(ABORT,'synthetic_event_store_offline'); END;");
$check($throws(static fn()=>$store->decide($a,'rev-f157-crash',1,
 'approve_mock','reviewer-demo-ana')),
 'simulated audit storage outage fails closed');
$after=$other->project($a);
$crash=array_values(array_filter($after['reviews'],
 static fn(array $row):bool=>$row['review_ref']==='rev-f157-crash'));
$check(count($crash)===1
 &&$crash[0]['status']==='pending_demo'
 &&$crash[0]['version']===1,
 'transaction rolled back status and version after audit insertion failure');
$check($after['audit_entries_demo']===7,
 'transaction did not leave an orphan event');
$pdo->exec('DROP TRIGGER f157_ci_abort');
$check($other->decide($a,'rev-f157-crash',1,'approve_mock','reviewer-demo-ana')
 ==='approved_mock_only','retry safe after known full rollback');

$check($other->revise($a,'rev-f157-search',4,'Novo')
 ==='revision_not_requested_denied','terminal mock decision cannot be reopened');
$check($other->project($a)['review_count']===3,
 'connection restart does not duplicate synthetic review records');

$forbidden=$runner.'/outside.sqlite';
$check($throws(static fn()=>new F157SyntheticSqliteReviewStore($forbidden,$scope)),
 'SQLite store refuses DB path outside dedicated runner child directory');
echo "F157 SQLite durable fictional reviews: {$n} PASS / 0 FAIL — NO-GO\n";
