<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F165_CAMPAIGN_LOCK_LAB')!=='1'
 ||getenv('PERF_F164_PG_SCOPE_LAB')!=='1'
 ||getenv('PGDATABASE')!=='perf_f162_ci'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'
 ||!extension_loaded('pdo_pgsql')) {
 throw new RuntimeException('F165 must run in disposable PG16 CI only');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=perf_f162_ci';
$connect=static fn(string $user,string $pw):PDO=>new PDO($dsn,$user,$pw,[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false,
]);
$admin=$connect('postgres','postgres');
$a=$connect('perf_f162_a','ci-only-perf-f162-a');
$b=$connect('perf_f162_b','ci-only-perf-f162-b');
$n=0;
$assert=static function(bool $pass,string $why)use(&$n):void {
 ++$n;
 if(!$pass)throw new RuntimeException('F165 FAILED: '.$why);
};
$denies=static function(callable $fn):bool{
 try{$fn();return false;}catch(PDOException $e){return true;}
};
$checkEvents=static function(PDO $conn,string $campaign):array {
 $q=$conn->prepare('SELECT event_type,managed FROM perf_f162.campaign_events
  WHERE campaign=? ORDER BY event_id');
 $q->execute([$campaign]);
 return $q->fetchAll(PDO::FETCH_ASSOC);
};

$assert((int)$a->query('SELECT count(*) FROM perf_f162.campaign_events')->fetchColumn()>=3,
 'A can inspect baseline demo scope events under its own PG role');
$assert((int)$b->query('SELECT count(*) FROM perf_f162.campaign_events')->fetchColumn()>=3,
 'B can inspect only its baseline scope events');
$assert($denies(static fn()=>$a->exec("INSERT INTO perf_f162.campaign_events
 (tenant,campaign,channel,event_type,managed) VALUES
 ('perf-demo-aurora','cam-f165-forged','google_ads','registered_demo',true)")),
 'A cannot forge an audit entry directly');
$assert($denies(static fn()=>$a->exec('DELETE FROM perf_f162.campaign_events')),
 'A cannot delete a trusted campaign event');
$assert($denies(static fn()=>$a->exec("UPDATE perf_f162.campaign_events
 SET event_type='reactivated_demo'")),
 'A cannot edit an audit event');
$assert($denies(static fn()=>$a->exec("UPDATE perf_f162.managed_campaigns
 SET managed=false WHERE campaign='cam-f164-aurora'")),
 'A cannot retire campaigns by direct SQL');

$admin->exec("INSERT INTO perf_f162.managed_campaigns(tenant,campaign,channel)
 VALUES('perf-demo-aurora','cam-f165-lock','google_ads'),
       ('perf-demo-boreal','cam-f165-boreal','meta')");
$timeline=$checkEvents($a,'cam-f165-lock');
$assert(count($timeline)===1&&$timeline[0]['event_type']==='registered_demo',
 'administrator registry insert automatically adds synthetic scope event');
$assert(count($checkEvents($b,'cam-f165-boreal'))===1,
 'B can inspect own new campaign event');
$assert(count($checkEvents($a,'cam-f165-boreal'))===0,
 'A cannot see event for B campaign via RLS');
$assert(count($checkEvents($b,'cam-f165-lock'))===0,
 'B cannot see event for A campaign via RLS');

$a->beginTransaction();
try {
 $a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f165-lock','cam-f165-lock',
 'google_ads','Primeiro review')");

 $admin->exec("SET lock_timeout='200ms'");
 $wasDenied=false;
 $sqlState='';
 try {
  $admin->exec("UPDATE perf_f162.managed_campaigns
   SET managed=false WHERE tenant='perf-demo-aurora'
   AND campaign='cam-f165-lock'");
 } catch(PDOException $e) {
  $wasDenied=true;$sqlState=(string)$e->getCode();
 }
 $admin->exec('SET lock_timeout=0');
 $assert($wasDenied&&$sqlState==='55P03',
  'concurrent campaign retirement blocks on row SHARE lock until writer commits');
 $assert($a->query("SELECT count(*) FROM perf_f162.events
  WHERE reference='rev-f165-lock'")->fetchColumn()==1,
  'writer pending transaction has exact one same-transaction review audit event');
 $a->commit();
} catch(Throwable $e) {
 if($a->inTransaction())$a->rollBack();
 throw $e;
}
$assert($a->query("SELECT status FROM perf_f162.reviews
 WHERE reference='rev-f165-lock'")->fetchColumn()==='pending_demo',
 'writer committed fictional review and kept pending status');

$assert($admin->exec("UPDATE perf_f162.managed_campaigns SET managed=false
 WHERE tenant='perf-demo-aurora' AND campaign='cam-f165-lock'")===1,
 'admin can retire synthetic campaign after writer transaction releases lock');
$timeline=$checkEvents($a,'cam-f165-lock');
$assert(count($timeline)===2&&$timeline[1]['event_type']==='retired_demo'
 &&(string)$timeline[1]['managed']==='',
 'retirement appends one automatic event');
$assert($denies(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f165-after-retirement',
 'cam-f165-lock','google_ads','Forbidden')")),
 'new fictional review refused once campaign retired');
$assert($denies(static fn()=>$a->exec("UPDATE perf_f162.reviews
 SET status='approved_mock_only',version=version+1
 WHERE tenant='perf-demo-aurora' AND reference='rev-f165-lock'")),
 'existing fictional review cannot transition after retirement');
$assert((int)$a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f165-lock'")->fetchColumn()===1,
 'failed post-retirement changes never append misleading review events');
$assert($a->query("SELECT version FROM perf_f162.reviews
 WHERE reference='rev-f165-lock'")->fetchColumn()==1,
 'failed SQL review mutation preserves version');
$assert(count($checkEvents($a,'cam-f165-lock'))===2,
 'failed review writes cannot invent campaign management audit events');

// Concurrency in the reverse order: uncommitted retirement must block new
// reviews rather than reading a stale active campaign record.
$admin->beginTransaction();
try {
 $admin->exec("UPDATE perf_f162.managed_campaigns SET managed=true
  WHERE tenant='perf-demo-aurora' AND campaign='cam-f165-lock'");
 $admin->commit();
} catch(Throwable $e) {
 if($admin->inTransaction())$admin->rollBack();
 throw $e;
}
$admin->beginTransaction();
try {
 $admin->exec("UPDATE perf_f162.managed_campaigns SET managed=false
  WHERE tenant='perf-demo-aurora' AND campaign='cam-f165-lock'");
 $a->exec("SET lock_timeout='200ms'");
 $deniedOnLock=false;$code='';
 try {
  $a->exec("INSERT INTO perf_f162.reviews
    (tenant,reference,campaign,channel,title)
    VALUES('perf-demo-aurora','rev-f165-uncommitted',
    'cam-f165-lock','google_ads','Blocked while admin retires')");
 } catch(PDOException $e) {
  $deniedOnLock=true;$code=(string)$e->getCode();
 }
 $a->exec('SET lock_timeout=0');
 $assert($deniedOnLock&&$code==='55P03',
  'review insert waits on uncommitted admin retirement, cannot bypass read');
 $admin->commit();
} catch(Throwable $e) {
 if($admin->inTransaction())$admin->rollBack();
 throw $e;
}
$assert($denies(static fn()=>$a->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-aurora','rev-f165-uncommitted',
 'cam-f165-lock','google_ads','Blocked after retirement')")),
 'after admin commits retirement, new review remains refused');
$assert((int)$a->query("SELECT count(*) FROM perf_f162.reviews
 WHERE reference='rev-f165-uncommitted'")->fetchColumn()===0,
 'blocked insert leaves no phantom review');

$timeline=$checkEvents($a,'cam-f165-lock');
$assert(count($timeline)===4&&$timeline[2]['event_type']==='reactivated_demo'
 &&$timeline[3]['event_type']==='retired_demo',
 'scope history records reactivate and second retirement in chronological order');
$admin->exec("UPDATE perf_f162.managed_campaigns SET managed=false
 WHERE tenant='perf-demo-aurora' AND campaign='cam-f165-lock'");
$assert(count($checkEvents($a,'cam-f165-lock'))===4,
 'idempotent admin reapply of same status does not generate fake event');

$assert($b->exec("INSERT INTO perf_f162.reviews
 (tenant,reference,campaign,channel,title)
 VALUES('perf-demo-boreal','rev-f165-b',
 'cam-f165-boreal','meta','B unaffected')")===1,
 'tenant B remains allowed to submit unrelated fictitious campaign review');
$assert((int)$b->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f165-b'")->fetchColumn()===1,
 'B audit remains separate and writes once');
$assert((int)$a->query("SELECT count(*) FROM perf_f162.events
 WHERE reference='rev-f165-b'")->fetchColumn()===0,
 'tenant A cannot access B review audit during own retirement incident');
$flags=$admin->query("SELECT relrowsecurity,relforcerowsecurity
 FROM pg_class WHERE oid='perf_f162.campaign_events'::regclass")->fetch();
$assert(in_array((string)$flags['relrowsecurity'],['1','t'],true)
 &&in_array((string)$flags['relforcerowsecurity'],['1','t'],true),
 'scope audit table has enabled and forced row-level security');
echo "F165 PG16 retirement lock and scope audit: {$n} PASS / 0 FAIL — NO-GO\n";
