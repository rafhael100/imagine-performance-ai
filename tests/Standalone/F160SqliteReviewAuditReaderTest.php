<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/F157SyntheticSqliteReviewStore.php';
require dirname(__DIR__,2).'/src/Domain/Performance/F160SyntheticSqliteAuditReader.php';

use Imagine\Performance\Domain\SyntheticManagedScopeLedger;
use Imagine\Performance\Domain\F157SyntheticSqliteReviewStore;
use Imagine\Performance\Domain\F160SyntheticSqliteAuditReader;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F52_SCOPE_LAB')!=='1'
 ||getenv('PERF_F157_SQLITE_LAB')!=='1'
 ||getenv('PERF_F160_AUDIT_LAB')!=='1') {
 throw new RuntimeException('F160 lab-only audit checks');
}
$dir=(string)getenv('RUNNER_TEMP').'/f160-audit';
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('F160 temp directory unavailable');
$path=$dir.'/reviews.sqlite';
if(file_exists($path))throw new RuntimeException('F160 database must start clean');
$tenant='perf-demo-aurora';$other='perf-demo-boreal';
$scope=new SyntheticManagedScopeLedger();
$scope->setDemoCapacity($tenant,'google_ads',2,3);
$scope->setDemoCapacity($other,'google_ads',1,2);
$scope->includeCampaign($tenant,'google_ads','cam-f160-search');
$scope->includeCampaign($other,'google_ads','cam-f160-other');
$store=new F157SyntheticSqliteReviewStore($path,$scope);
$n=0;
$check=static function(bool $pass,string $why)use(&$n):void{
 ++$n;if(!$pass)throw new RuntimeException('F160 FAILED: '.$why);
};
$check($store->submit($tenant,'rev-f160-first','cam-f160-search','google_ads',
 'Criativo versão inicial')==='submitted_demo','A first synthetic review submitted');
$check($store->decide($tenant,'rev-f160-first',1,'request_changes','reviewer-demo-ana')
 ==='changes_requested_demo','A requested changes transition');
$check($store->revise($tenant,'rev-f160-first',2,'Criativo revisado')
 ==='resubmitted_demo','A review resubmitted');
$check($store->decide($tenant,'rev-f160-first',3,'approve_mock','reviewer-demo-beto')
 ==='approved_mock_only','A only mock-approved');
$check($store->submit($tenant,'rev-f160-second','cam-f160-search','google_ads',
 'Outra versão')==='submitted_demo','A second mock pending review submitted');
$check($store->submit($other,'rev-f160-first','cam-f160-other','google_ads',
 'Mesma referência de B')==='submitted_demo','B independent review with same ref');

$reader=new F160SyntheticSqliteAuditReader($path);
$report=$reader->inspect($tenant);
$check($report['status']==='consistent_mock_only'
 &&$report['event_count']===5 &&count($report['events'])===5,
 'five-event A state machine consistent in read-only SQLite verifier');
$check($report['can_export']===true
 &&$report['audit_verified_for_production']===false,
 'mock consistency report exportable but never production certified');
$check($report['dispatch_enabled']===false
 &&$report['billing_enabled']===false
 &&$report['iam_verified']===false
 &&$report['human_approval_verified']===false,
 'audit never authorizes real provider operations');
$check(preg_match('/^[a-f0-9]{64}$/D',(string)$report['digest'])===1,
 'deterministic SHA-256 digest for synthetic report');
$check($reader->inspect($tenant)===$report,
 'unchanged DB returns same audit digest and event ordering');
$check($reader->inspect($other)['event_count']===1,
 'B sees only own event even with same review reference');
$check($reader->inspect('perf-demo-unknown')['event_count']===0,
 'valid tenant without reviews has empty consistent audit');
$check($reader->inspect('perf-demo-bad OR 1=1')===null,
 'injected tenant string refused before SQL');
$check($report['events'][0]['transition']==='submitted_demo'
 &&$report['events'][3]['transition']==='approved_mock_only',
 'event chronology includes initial submit and final mock approval');

$admin=new PDO('sqlite:'.$path);
$admin->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$admin->exec("UPDATE f157_reviews SET status='pending_demo'
 WHERE tenant='$tenant' AND reference='rev-f160-first'");
$broken=$reader->inspect($tenant);
$check($broken['status']==='audit_inconsistent_fail_closed'
 &&$broken['can_export']===false
 &&$broken['events']===[],
 'status modified without audit is detected and review data withheld');
$admin->exec("UPDATE f157_reviews SET status='approved_mock_only'
 WHERE tenant='$tenant' AND reference='rev-f160-first'");
$check($reader->inspect($tenant)===$report,
 'restoring legitimate status restores audit consistency');

$admin->exec("UPDATE f157_events SET transition='approved_mock_only'
 WHERE tenant='$tenant' AND reference='rev-f160-first' AND version=2");
$check($reader->inspect($tenant)['status']==='audit_inconsistent_fail_closed',
 'illegal state transition in event history rejected');
$admin->exec("UPDATE f157_events SET transition='changes_requested_demo'
 WHERE tenant='$tenant' AND reference='rev-f160-first' AND version=2");
$check($reader->inspect($tenant)===$report,
 'restoring event transition restores same digest');

$eventRow=$admin->query("SELECT event_id,transition,reviewer_fixture
 FROM f157_events WHERE tenant='$tenant' AND reference='rev-f160-first'
 AND version=2")->fetch(PDO::FETCH_ASSOC);
$check(is_array($eventRow),'capture synthetic event for fault injection');
$admin->exec("DELETE FROM f157_events WHERE tenant='$tenant'
 AND reference='rev-f160-first' AND version=2");
$check($reader->inspect($tenant)['status']==='audit_inconsistent_fail_closed',
 'deleted middle audit event detected via missing version');
$restore=$admin->prepare('INSERT INTO f157_events
(event_id,tenant,reference,transition,version,reviewer_fixture)
VALUES(?,?,?,?,?,?)');
$restore->execute([
 (int)$eventRow['event_id'],$tenant,'rev-f160-first',
 $eventRow['transition'],2,$eventRow['reviewer_fixture']
]);
$check($reader->inspect($tenant)===$report,
 'reinstating event yields original audit digest');

$admin->exec("UPDATE f157_events SET reviewer_fixture='injected-reviewer'
 WHERE tenant='$tenant' AND reference='rev-f160-first' AND version=3");
$check($reader->inspect($tenant)['status']==='audit_inconsistent_fail_closed',
 'untrusted reviewer fixture format rejected');
$admin->exec("UPDATE f157_events SET reviewer_fixture='reviewer-demo-system'
 WHERE tenant='$tenant' AND reference='rev-f160-first' AND version=3");
$check($reader->inspect($tenant)===$report,
 'restoring reviewer fixture returns consistent audit');

// Expected-negative: an administrator can delete BOTH history and rows.
// A new audit over the empty database cannot prove it ever contained data.
$admin->exec("DELETE FROM f157_events WHERE tenant='$tenant'");
$admin->exec("DELETE FROM f157_reviews WHERE tenant='$tenant'");
$afterDualDeletion=$reader->inspect($tenant);
$check($afterDualDeletion['status']==='consistent_mock_only'
 &&$afterDualDeletion['event_count']===0,
 'EXPECTED ANTIROLLBACK GAP: deleting audit AND state makes empty mock audit look consistent');
$check($reader->inspect($other)['event_count']===1,
 'A deletion does not make B audit visible or alter B entries');
$check($afterDualDeletion['audit_verified_for_production']===false,
 'even the expected-negative dual deletion never grants trust in production');

echo "F160 SQLite review audit consistency: {$n} PASS / 0 FAIL — NO-GO\n";
