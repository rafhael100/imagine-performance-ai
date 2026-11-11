<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/src/Domain/Performance/F166Pg16ReadOnlyDashboard.php';

use Imagine\Performance\Domain\F166Pg16ReadOnlyDashboard;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F167_SCOPE_EPOCH_LAB')!=='1'
 ||getenv('PERF_F166_READ_DASHBOARD_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'||!extension_loaded('pdo_pgsql')) {
 throw new RuntimeException('F167 generation lab must use disposable PG16');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$open=static fn(string $role,string $pwd):PDO=>new PDO($dsn,$role,$pwd,[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
$admin=$open('postgres','postgres');
$a=$open('perf_f162_a','ci-only-perf-f162-a');
$b=$open('perf_f162_b','ci-only-perf-f162-b');
$readerA=new F166Pg16ReadOnlyDashboard($a);
$readerB=new F166Pg16ReadOnlyDashboard($b);
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F167 FAILED: '.$why);
};
$denies=static function(callable $call):bool{
 try{$call();return false;}catch(PDOException $e){return true;}
};
$getEpoch=static function(PDO $db,string $campaign):int{
 $q=$db->prepare('SELECT scope_epoch FROM perf_f162.managed_campaigns WHERE campaign=?');
 $q->execute([$campaign]);
 return (int)$q->fetchColumn();
};
$getReviewEpoch=static function(PDO $db,string $ref):int{
 $q=$db->prepare('SELECT scope_epoch FROM perf_f162.reviews WHERE reference=?');
 $q->execute([$ref]);
 return (int)$q->fetchColumn();
};

$legacy=$a->query("SELECT count(*) FROM perf_f162.reviews
 WHERE reference LIKE 'rev-f166-%' AND scope_epoch=0")->fetchColumn();
$check((int)$legacy>=2,
 'pre-migration review rows explicitly marked legacy unverified epoch 0');
$check($a->query("SELECT count(*) FROM perf_f162.active_review_dashboard
 WHERE reference LIKE 'rev-f166-%'")->fetchColumn()==0,
 'upgrade cannot resurrect or auto-authorize pre-existing review history');

$admin->exec("INSERT INTO perf_f162.managed_campaigns
 (tenant,campaign,channel,managed,scope_epoch)
 VALUES('perf-demo-aurora','cam-f167-aurora','google_ads',true,999),
       ('perf-demo-boreal','cam-f167-boreal','meta',true,999)");
$check($getEpoch($a,'cam-f167-aurora')===1&&$getEpoch($b,'cam-f167-boreal')===1,
 'campaign INSERT epoch is assigned by SQL guard, ignoring caller value');
$check($a->query("SELECT count(*) FROM perf_f162.campaign_events
 WHERE campaign='cam-f167-boreal'")->fetchColumn()==0,
 'cross-tenant SQL scope events remain RLS-restricted');

$check($a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title,scope_epoch)
 VALUES('perf-demo-aurora','rev-f167-original','cam-f167-aurora',
 'google_ads','Antes de retirada',999)")===1,
 'review INSERT with forged high epoch is accepted only after SQL canonicalization');
$check($getReviewEpoch($a,'rev-f167-original')===1,
 'review INSERT ignored forged generation and stored SQL-managed epoch 1');
$check($readerA->read('perf-demo-aurora')['active_review_count']===1,
 'fresh review visible in its currently managed epoch');
$check($denies(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET scope_epoch=999 WHERE reference='rev-f167-original'")),
 'restricted reviewer role cannot tamper with generation column');

$admin->exec("UPDATE perf_f162.managed_campaigns
 SET managed=false WHERE tenant='perf-demo-aurora'
 AND campaign='cam-f167-aurora'");
$check($getEpoch($a,'cam-f167-aurora')===2,
 'retirement increments managed campaign generation to 2');
$check($readerA->read('perf-demo-aurora')['active_review_count']===0,
 'retirement immediately excludes first-generation review');
$check($denies(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f167-original'")),
 'review cannot transition after management retirement');
$check($a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f167-original'")->fetchColumn()==1,
 'denied old-generation review update writes no audit event');

$admin->exec("UPDATE perf_f162.managed_campaigns
 SET managed=true WHERE tenant='perf-demo-aurora'
 AND campaign='cam-f167-aurora'");
$check($getEpoch($a,'cam-f167-aurora')===3,
 'independent fixture admin reactivation increments generation to 3');
$check($readerA->read('perf-demo-aurora')['active_review_count']===0,
 'old mock review remains hidden even after campaign reactivation');
$check($denies(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f167-original'")),
 'old generation cannot resume mock approval after reactivation');
$check($getReviewEpoch($a,'rev-f167-original')===1,
 'old review keeps generation 1 and cannot be rebound');

$check($a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f167-new','cam-f167-aurora',
 'google_ads','Criativo novo após reativação')")===1,
 'new review may be created after explicit fixture campaign reactivation');
$check($getReviewEpoch($a,'rev-f167-new')===3,
 'new review binds to active third generation');
$reportA=$readerA->read('perf-demo-aurora');
$check($reportA['active_review_count']===1
 &&$reportA['reviews'][0]['review_ref']==='rev-f167-new',
 'only newly created generation-3 review is visible in Portal mock dashboard');
$check($reportA['quarantined_review_counts_demo']['google_ads']>=1,
 'previous generations remain aggregated in quarantine');
$check($a->exec("UPDATE perf_f162.reviews SET status='approved_mock_only',
 version=version+1 WHERE tenant='perf-demo-aurora' AND reference='rev-f167-new'
 AND status='pending_demo' AND version=1")===1,
 'valid new-generation mock decision permitted');
$check($a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f167-new'")->fetchColumn()==2,
 'new-generation decision has matching SQL audit events');
$check($denies(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET scope_epoch=1,status='pending_demo',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f167-new'")),
 'restricted reviewer cannot spoof scope_epoch during UPDATE');

$admin->exec("UPDATE perf_f162.managed_campaigns
 SET scope_epoch=999 WHERE tenant='perf-demo-aurora'
 AND campaign='cam-f167-aurora'");
$check($getEpoch($a,'cam-f167-aurora')===3,
 'even administrator update cannot spoof epoch while managed status unchanged');
$admin->exec("UPDATE perf_f162.managed_campaigns
 SET managed=true WHERE tenant='perf-demo-aurora'
 AND campaign='cam-f167-aurora'");
$check($getEpoch($a,'cam-f167-aurora')===3,
 'idempotent reactivation cannot mint extra generations');
$check($readerA->read('perf-demo-aurora')['active_review_count']===1,
 'new generation remains visible after no-op administrator update');

$b->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f167-b','cam-f167-boreal',
 'meta','B nova campanha')");
$check($getReviewEpoch($b,'rev-f167-b')===1,
 'unrelated tenant B remains in its own first generation');
$check($readerB->read('perf-demo-boreal')['active_review_count']===1
 &&$readerB->read('perf-demo-aurora')===null,
 'B only sees B new reviews; caller spoofing A refused');
$check($a->query("SELECT count(*) FROM perf_f162.active_review_dashboard
 WHERE tenant='perf-demo-boreal'")->fetchColumn()==0,
 'RLS still protects B details even when A campaigns rotate generations');

$history=$a->query("SELECT event_type,scope_epoch
 FROM perf_f162.campaign_events WHERE campaign='cam-f167-aurora'
 ORDER BY event_id")->fetchAll(PDO::FETCH_ASSOC);
$check(count($history)===3
 &&$history[0]['event_type']==='registered_demo'
 &&(int)$history[0]['scope_epoch']===1
 &&$history[1]['event_type']==='retired_demo'
 &&(int)$history[1]['scope_epoch']===2
 &&$history[2]['event_type']==='reactivated_demo'
 &&(int)$history[2]['scope_epoch']===3,
 'SQL campaign audit contains exact monotonic epoch for each state transition');

$check($reportA['iam_verified']===false
 &&$reportA['s2s_verified']===false
 &&$reportA['dispatch_enabled']===false
 &&$reportA['billing_enabled']===false
 &&$reportA['ready_for_production']===false,
 'generation lab does not create production permissions');

echo "F167 PostgreSQL16 scope generations: {$n} PASS / 0 FAIL — CI-only NO-GO\n";
