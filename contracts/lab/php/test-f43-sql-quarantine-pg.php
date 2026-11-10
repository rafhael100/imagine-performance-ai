<?php
declare(strict_types=1);

require_once __DIR__.'/SyntheticIdempotentIntentGateway.php';
require_once __DIR__.'/SyntheticSqlQuarantineGateway.php';
require_once __DIR__.'/SyntheticCentralAdmissionGateway.php';

use Imagine\Protocol\Lab\SyntheticIdempotentIntentGateway;
use Imagine\Protocol\Lab\SyntheticSqlQuarantineGateway;
use Imagine\Protocol\Lab\SyntheticCentralAdmissionGateway;

$phase=getenv('PERF_F43_PHASE');
$tokenFile=getenv('F43_LEASE_FILE');
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
 ||getenv('PERF_F35_INTENT_LAB')!=='1'
 ||getenv('PERF_F43_ATOMIC_SQL_GUARD_LAB')!=='1'
 ||getenv('PERF_F42_CENTRAL_CUTOVER_LAB')!=='1'
 ||getenv('PERF_F41_QUARANTINE_LAB')!=='1'
 ||getenv('PGHOST')!=='127.0.0.1'
 ||getenv('PGPORT')!=='5432'||getenv('PGDATABASE')!=='portal_perf_auth_lab'
 ||!is_string($tokenFile)||$tokenFile===''
 ||!in_array($phase,['locked','released','locked_again','re_released'],true)){
    throw new RuntimeException('F43 CI only, four synthetic SQL phases.');
}
$pdo=static fn(string $db,int $port,string $role,string $pass):PDO=>
 new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname='.$db.';connect_timeout=3',
 $role,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false]);
$admin=$pdo('portal_perf_auth_lab',5432,'postgres','ci-f08-db-only');
$adminExternal=$pdo('perf_f31_nonce_ci',5433,'postgres','ci-f31-nonce-db-only');
$newRole=$pdo('portal_perf_auth_lab',5432,'perf_f43_sql_consumer','ci-f43-sql-only');
$oldRole=$pdo('portal_perf_auth_lab',5432,'perf_f42_admission','ci-f42-admission-only');
$writer=new SyntheticIdempotentIntentGateway(
    $pdo('portal_perf_auth_lab',5432,'perf_f35_writer','ci-f35-writer-only')
);
$gate=new SyntheticSqlQuarantineGateway($newRole);
$oldFacade=new SyntheticCentralAdmissionGateway(
 $pdo('perf_f31_nonce_ci',5433,'perf_f41_guard_reader','ci-f41-guard-only'),
 $oldRole
);
$t=1800000500;
$owner='worker-f38-a';
$digest=hash('sha256','f43-local-quarantine-transactional-ci');
$n=0;
$check=static function(string $title,mixed $actual,mixed $expected)use(&$n):void {
 if($actual!==$expected){
  throw new RuntimeException('FAIL F43 '.$title.' actual '
    .var_export($actual,true).' expected '.var_export($expected,true));
 }
 echo 'PASS F43 '.++$n.' '.$title.PHP_EOL;
};
$parse=static function(string $packet):array{
 if(!preg_match('/^claimed_sql_mock:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',$packet,$m)){
  throw new RuntimeException('F43 invalid guarded SQL lease '.$packet);
 }
 return [(int)$m[1],(int)$m[2],(int)$m[3]];
};
$save=static function(array $token)use($tokenFile):void{
 if(file_put_contents($tokenFile,json_encode($token,JSON_THROW_ON_ERROR))===false)
  throw new RuntimeException('Missing F43 CI lease token file');
};
$load=static function()use($tokenFile):array{
 $data=@file_get_contents($tokenFile);
 $a=is_string($data)?json_decode($data,true):null;
 if(!is_array($a)||count($a)!==3)throw new RuntimeException('F43 CI token missing');
 return $a;
};
$countLeases=static fn():int=>(int)$admin->query(
    'SELECT count(*) FROM perf_f38_fenced.leases'
)->fetchColumn();
$countDispatch=static fn():int=>(int)$admin->query(
    'SELECT count(*) FROM perf_f35_intents.outbox WHERE dispatch_enabled'
)->fetchColumn();
switch($phase){
case 'locked':
 $check('new local SQL guard defaults to quarantine epoch 4',
    $gate->state(),'quarantined:4');
 $before=$countLeases();
 $check('direct SQL guarded claim denied (not just PHP check)',
    $newRole->query("SELECT perf_f43_sql_guard.claim('worker-f38-a',1800000500)")->fetchColumn(),
    'sql_quarantine_denied');
 $check('direct SQL guarded ACK denied',
    $newRole->query("SELECT perf_f43_sql_guard.ack(1,'worker-f38-a',1,4,1800000500)")->fetchColumn(),
    'sql_quarantine_denied');
 $check('PHP facade claim also denied',$gate->claim($owner,$t),'sql_quarantine_denied');
 $check('SQL denial leaves local lease table unchanged',$countLeases(),$before);
 $denied=false;
 try{
  $newRole->query("SELECT perf_f38_fenced.claim('worker-f38-a',1800000500)");
 }catch(PDOException $e){$denied=$e->getCode()==='42501';}
 $check('new role cannot bypass SQL guard via F38 claim',$denied,true);
 $denied=false;
 try{
  $newRole->query("SELECT perf_f38_fenced.ack(1,'worker-f38-a',1,1800000500)");
 }catch(PDOException $e){$denied=$e->getCode()==='42501';}
 $check('new role cannot bypass SQL guard via F38 ACK',$denied,true);
 $denied=false;
 try{
  $oldRole->query("SELECT perf_f38_fenced.claim('worker-f38-a',1800000500)");
 }catch(PDOException $e){$denied=$e->getCode()==='42501';}
 $check('F42 previous role direct SQL also revoked',$denied,true);
 $check('old F42 wrapper refuses because old SQL role is no longer authorized',
    $oldFacade->claim($owner,$t),'central_local_unavailable');
 $denied=false;
 try{$newRole->query('SELECT * FROM perf_f43_sql_guard.state');}
 catch(PDOException $e){$denied=$e->getCode()==='42501';}
 $check('new SQL role cannot directly select the guard table',$denied,true);
 $denied=false;
 try{$newRole->exec("UPDATE perf_f43_sql_guard.state SET status='released_mock'");}
 catch(PDOException $e){$denied=$e->getCode()==='42501';}
 $check('new role cannot update quarantine table',$denied,true);
 $check('guarded SQL closure never enables outbox',$countDispatch(),0);
 $check('external F41 source is still released at epoch 4',
    $adminExternal->query('SELECT perf_f41_recovery.read_state()')->fetchColumn(),
    'released_mock:4');
 $invalidApproval=false;
 try{
  $admin->exec("UPDATE perf_f43_sql_guard.state SET status='released_mock',approval_ref=NULL WHERE singleton_id=true");
 }catch(PDOException $e){$invalidApproval=$e->getCode()==='23514';}
 $check('missing mock operator approval rejected by local constraint',
    $invalidApproval,true);
 $check('missing approval did not open local guard',$gate->state(),'quarantined:4');
 break;
case 'released':
 $check('CI operator mock release makes local guard available',
    $gate->state(),'released_mock:4');
 $check('new read-only fixture for local transaction',
    $writer->reserve('f43-released-read',$digest),'reserved_synthetic');
 $token=$parse($gate->claim($owner,$t));
 $check('SQL returned an epoch-4 guarded token',$token[2],4);
 $check('SQL returned positive lease ID',$token[0]>0,true);
 $check('SQL returned positive generation',$token[1]>0,true);
 $check('unexpected future epoch rejected by SQL',
    $gate->acknowledge($token[0],$owner,$token[1],5,$t),
    'stale_sql_recovery_epoch_denied');
 $check('wrong owner rejected by F38 nested SQL gate',
    $gate->acknowledge($token[0],'worker-f38-b',$token[1],4,$t),
    'fenced_lease_denied');
 $save($token);
 $check('token persisted for cross-process restore simulation',
    is_file($tokenFile),true);
 $check('old F42 wrapper still blocked by revoked SQL role',
    $oldFacade->claim($owner,$t),'central_local_unavailable');
 $check('outbox remains disabled',$countDispatch(),0);
 break;
case 'locked_again':
 $check('simulated restore enforces new local quarantine epoch 5',
    $gate->state(),'quarantined:5');
 $token=$load();
 $check('previous token binds epoch four',$token[2],4);
 $check('direct SQL call cannot claim in quarantine',
    $newRole->query("SELECT perf_f43_sql_guard.claim('worker-f38-a',1800000501)")->fetchColumn(),
    'sql_quarantine_denied');
 $check('old token ACK denied in local SQL quarantine',
    $gate->acknowledge($token[0],$owner,$token[1],4,$t+1),
    'sql_quarantine_denied');
 $check('new role direct F38 remains denied',
    $gate->claim($owner,$t+1),'sql_quarantine_denied');
 $check('outbox remains disabled',$countDispatch(),0);
 break;
case 're_released':
 $check('mock admin release of epoch 5',
    $gate->state(),'released_mock:5');
 $previous=$load();
 $check('previous epoch 4 token cannot ACK in epoch 5',
    $gate->acknowledge($previous[0],$owner,$previous[1],4,$t+1),
    'stale_sql_recovery_epoch_denied');
 $check('new synthetic intent in epoch 5',
    $writer->reserve('f43-epoch5-read',$digest),'reserved_synthetic');
 $now=$t+31;
 $new=$parse($gate->claim($owner,$now));
 $check('SQL lease now includes epoch 5',$new[2],5);
 $check('SQL lease has valid outbox id',$new[0]>0,true);
 $check('mock ACK through SQL guard succeeds',
    $gate->acknowledge($new[0],$owner,$new[1],5,$now),
    'acknowledged_sql_mock');
 $check('duplicate ACK cannot succeed',
    $gate->acknowledge($new[0],$owner,$new[1],5,$now),
    'fenced_lease_denied');
 $admin->exec(
   'REVOKE EXECUTE ON FUNCTION perf_f43_sql_guard.claim(text,bigint) FROM perf_f43_sql_consumer'
 );
 $check('revoking SQL guarded function fails closed',
    $gate->claim($owner,$now),'sql_guard_unavailable');
 $admin->exec(
   'GRANT EXECUTE ON FUNCTION perf_f43_sql_guard.claim(text,bigint) TO perf_f43_sql_consumer'
 );
 $check('SQL read state recovers after role grant',
    $gate->state(),'released_mock:5');
 $check('external authority independently remains at epoch 4',
    $adminExternal->query('SELECT perf_f41_recovery.read_state()')->fetchColumn(),
    'released_mock:4');
 $check('EXPECTED UNSOLVED RISK: local epoch differs from external epoch',
    $gate->state()!==$adminExternal->query(
      'SELECT perf_f41_recovery.read_state()'
    )->fetchColumn(),true);
 $check('new SQL role has never activated dispatch',$countDispatch(),0);
 $check('all intents remain synthetic pending only',
    (int)$admin->query(
      "SELECT count(*) FROM perf_f35_intents.requests WHERE state<>'pending_synthetic'"
    )->fetchColumn(),0);
 putenv('PERF_F43_ATOMIC_SQL_GUARD_LAB=0');
 $check('CI feature flag closes gateway',$gate->claim($owner,$now),
    'sql_guard_lab_disabled');
 putenv('PERF_F43_ATOMIC_SQL_GUARD_LAB=1');
 putenv('APP_ENV=production');
 $check('production environment always closes gateway',
    $gate->claim($owner,$now),'sql_guard_lab_disabled');
 putenv('APP_ENV=testing');
 break;
}
echo "PASS F43 phase={$phase} assertions={$n} SQL row-locked quarantine only".PHP_EOL;
